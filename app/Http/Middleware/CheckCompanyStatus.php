<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckCompanyStatus
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = $request->user();

            // 🔥 Bypass suspension checks if a Superadmin is currently impersonating this user
            if ($request->hasSession() && $request->session()->has('impersonated_by')) {
                return $next($request);
            }

            // 1. INDIVIDUAL CHECK: Is the specific user suspended?
            if (!$user->is_active) {
                $this->revokeAccess($request, $user);
                return response()->json([
                    'message' => 'Your account has been suspended. You have been logged out.'
                ], 401);
            }

            // 2. COMPANY CHECK: Is the user's company suspended? (Superadmins bypass this)
            if (!$user->hasRole('superadmin') && (!$user->company || !$user->company->is_active)) {
                $this->revokeAccess($request, $user);
                return response()->json([
                    'message' => 'Your company account is suspended. Access revoked.'
                ], 403);
            }
        }

        return $next($request);
    }

    /**
     * Helper method to instantly kill all sessions and tokens
     */
    private function revokeAccess(Request $request, $user): void
    {
        $user->tokens()->delete(); // Clear Sanctum API tokens
        Auth::guard('web')->logout(); // Logout of Web Guard
        
        $request->session()->invalidate(); // Destroy Redis/File session
        $request->session()->regenerateToken(); // Destroy CSRF
    }
}
