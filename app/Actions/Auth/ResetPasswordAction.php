<?php
declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Exception;

class ResetPasswordAction
{
    public function execute(string $token, string $email, string $password): void
    {
        DB::transaction(function () use ($token, $email, $password) {
            
            // 🔒 FAANG FIX: Pessimistic Locking to prevent Race Conditions (Double-spend attack)
            $record = DB::table('password_reset_tokens')
                ->where('email', $email)
                ->lockForUpdate() // Puts a row-level lock until the transaction commits
                ->first();
            
            if (!$record || !Hash::check($token, $record->token)) {
                throw new Exception('Invalid or expired reset link.', 422);
            }
            
            // 🕒 Check Expiry (60 minutes)
            if (now()->diffInMinutes($record->created_at) > 60) {
                throw new Exception('This reset link has expired.', 422);
            }
            
            // 🛡️ Double-click / Race condition check
            if (!is_null($record->used_at)) {
                throw new Exception('This reset link has already been used.', 422);
            }
            
            // ✅ Stamp as used securely inside the locked transaction
            DB::table('password_reset_tokens')
                ->where('email', $email)
                ->update(['used_at' => now()]);
                
            // ✅ Update User Password
            $user = User::where('email', $email)->firstOrFail();
            $user->update(['password' => Hash::make($password)]);
            
            // 🛑 THE KILL SWITCH: Revoke ALL active Sanctum tokens — force re-login on all devices
            $user->tokens()->delete();
        });
    }
}