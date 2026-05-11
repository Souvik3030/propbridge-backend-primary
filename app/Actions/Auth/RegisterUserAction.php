<?php
declare(strict_types=1);

namespace App\Actions\Auth;

use App\Actions\Audit\LogUserAction;
use App\Jobs\SendVerificationEmailJob;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException; // 🔥 Import added

class RegisterUserAction
{
    public function __construct(protected LogUserAction $logger) {}

    public function execute(array $data, string $ip): User
    {
        return DB::transaction(function () use ($data, $ip) {
            
            // 🔒 FAANG: Pessimistic Locking to prevent Race Conditions
            $invitation = Invitation::where('token', $data['token'])
                ->lockForUpdate()
                ->first();

            // 🔥 FIX: Prevent Null Pointer Exception (500 Error)
            if (!$invitation) {
                throw new UnprocessableEntityHttpException('Invalid or expired invitation token.');
            }

            // 🛡️ Double-click/Race condition check
            if ($invitation->used_at !== null) {
                throw new ConflictHttpException('Race condition detected: Token already used.');
            }

            // Create Inactive User
            $user = User::create([
                'company_id' => $invitation->company_id,
                'name' => $data['name'],
                'email' => $invitation->email,
                'password' => Hash::make($data['password']),
                'is_active' => 0, // Strictly Inactive until verified
            ]);

            $user->assignRole($invitation->role);
            $invitation->update(['used_at' => now()]);

            // Dispatch Verification Email
            SendVerificationEmailJob::dispatch($user);

            // Save Audit Log
            $this->logger->execute($user->id, $user->company_id, $ip, 'auth.registered');

            return $user;
        });
    }
}