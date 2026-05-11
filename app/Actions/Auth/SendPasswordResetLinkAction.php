<?php
declare(strict_types=1);

namespace App\Actions\Auth;

use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendPasswordResetLinkAction
{
    public function execute(string $email): void
    {
        $user = User::where('email', $email)->first();
        
        // Silently return if no user — prevents email enumeration attacks
        if (!$user) return;
        
        // Delete any existing token, insert new one (single active token rule)
        DB::table('password_reset_tokens')->where('email', $email)->delete();
        
        $plainToken = Str::random(64);
        
        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => Hash::make($plainToken), // Hashed — not plaintext
            'created_at' => now(),
        ]);
        
        // In a true FAANG setup, you would use Mail::to()->queue() here!
        Mail::to($email)->queue(new PasswordResetMail($user, $plainToken));
    }
}