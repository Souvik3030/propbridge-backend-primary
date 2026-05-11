<?php
declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\VerifyEmailAction;
use App\Jobs\SendVerificationEmailJob;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function verify(Request $request, string $id, string $hash, VerifyEmailAction $action): RedirectResponse
    {
        // Action marks user is_active = 1 and sets email_verified_at
        $action->execute($id, $hash); 
        
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

        // Redirect naye tab mein khulega
        return redirect()->to($frontendUrl . '/login?verified=true');
    }

    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        // Agar user already verified hai, toh email mat bhejo
        if ($user->is_active === 1 || $user->email_verified_at !== null) {
            return response()->json(['message' => 'Your email is already verified.'], 400);
        }

        SendVerificationEmailJob::dispatch($user);

        return response()->json(['message' => 'A new verification link has been sent.']);
    }
}