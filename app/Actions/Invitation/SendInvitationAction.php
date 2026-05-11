<?php

declare(strict_types=1);

namespace App\Actions\Invitation;

use App\Models\Invitation;
use App\Jobs\ProcessInvitationEmail;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SendInvitationAction
{
    public function execute(array $data, string $companyId): Invitation
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($data, $companyId) {
            // DEFENSE: Pessimistic Lock on the Company to serialize concurrent requests
            $company = Company::where('id', $companyId)->lockForUpdate()->firstOrFail();
            $plan = strtolower($company->plan ?? 'basic');
    
            // 1. Fetch the limit from config
            $maxAllowedUsers = config("saas.plans.{$plan}.max_users", 5);
    
            // 2. Count existing active users in this company
            $currentUsersCount = User::where('company_id', $companyId)->count();
    
            // 3. Count pending active invitations (not expired, not used)
            $pendingInvitesCount = Invitation::where('company_id', $companyId)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->count();
    
            $totalConsumedSeats = $currentUsersCount + $pendingInvitesCount;
    
            // 🔥 THE QUOTA FIREWALL
            if ($totalConsumedSeats >= $maxAllowedUsers) {
                throw ValidationException::withMessages([
                    'email' => "Your {$plan} plan only allows {$maxAllowedUsers} team members. You have reached your limit. Please upgrade your plan."
                ]);
            }
    
            // 🔒 FAANG: Check the flag from the frontend. Default to false if missing.
            $isSendNow = $data['is_send_now'] ?? false;
    
            $token = Str::random(60);
    
            $invitation = Invitation::create([
                'email' => $data['email'],
                'role' => $data['role'],
                'company_id' => $companyId,
                'token' => $token,
                'expires_at' => now()->addHours(24),
                'sent_at' => $isSendNow ? now() : null,
            ]);
    
            // 🔥 FAANG SPEED & LOGIC: Sirf tabhi email queue mein daalo jab Admin chahe
            if ($isSendNow) {
                // FIXED N+1: Using $company instead of $invitation->company
                ProcessInvitationEmail::dispatch($invitation->email, $token, $company->name ?? 'Our Platform');
            }
    
            return $invitation;
        });
    }
}
