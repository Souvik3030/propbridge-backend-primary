<?php
declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ResetPasswordAction;
use App\Actions\Auth\SendPasswordResetLinkAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Http\JsonResponse;

class PasswordResetController extends Controller
{
    public function forgot(ForgotPasswordRequest $request, SendPasswordResetLinkAction $action): JsonResponse
    {
        // 🔥 FIX: Extract just the 'email' string from the validated array
        $action->execute($request->validated('email'));
        
        // SECURITY: Always return success to prevent attackers from guessing registered emails
        return response()->json([
            'message' => 'If this email exists in our system, a reset link has been sent.'
        ]);
    }

    public function reset(ResetPasswordRequest $request, ResetPasswordAction $action): JsonResponse
    {
        // 🔥 FAANG FIX: Array bhejne ki jagah, individual strings nikal kar bhejein.
        // Assuming aapka action kuch aese defined hai: execute(string $token, string $email, string $password)
        $action->execute(
            $request->validated('token'), 
            $request->validated('email'), 
            $request->validated('password')
        );

        return response()->json([
            'message' => 'Password reset successfully. Please sign in with your new password.'
        ]);
    }
}