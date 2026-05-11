<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CheckLoginAttempts
{
    public function handle(Request $request, Closure $next): Response
    {
        // We only care about the login route
        if ($request->is('api/auth/login') && $request->isMethod('POST')) {
            
            $throttleKey = Str::transliterate(Str::lower($request->input('email')).'|'.$request->ip());

            // 🔥 FAANG FIX: Just check, don't hit. 
            // The actual "hit" logic is now safely inside LoginRequest.
            if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
                $seconds = RateLimiter::availableIn($throttleKey);
                
                return response()->json([
                    'message' => 'Too many login attempts.',
                    'retry_after_seconds' => $seconds,
                    'retry_after_minutes' => ceil($seconds / 60),
                ], 429);
            }
        }

        return $next($request);
    }
}
