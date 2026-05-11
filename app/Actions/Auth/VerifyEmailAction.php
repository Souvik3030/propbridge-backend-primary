<?php
declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class VerifyEmailAction
{
    // 🔥 FIX: Changed 'int $id' to 'string $id' and added the hash parameter
    public function execute(string $id, string $hash): void
    {
        $user = User::findOrFail($id);

        // 🔒 FAANG SECURITY: Verify the secure hash matches the user's email perfectly
        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            abort(403, 'Invalid or expired verification link.');
        }

        if ($user->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['email' => 'Email already verified.']);
        }

        $user->markEmailAsVerified();
        $user->update(['is_active' => 1]);
    }
}