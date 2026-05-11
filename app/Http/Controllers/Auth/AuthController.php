<?php
declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterUserAction $action): JsonResponse
    {
       $user = $action->execute($request->validated(), $request->ip());
       
       // 🔥 FAANG FIX 3: Prevent "Flicker/Boot-out" Loop. 
       // User is created with is_active=0. Do NOT log them in automatically.
       // They must verify their email and get activated first.
       
       return response()->json([
           'message' => 'Account created successfully. Please check your email to verify and activate your account.', 
           'user' => new UserResource($user)
       ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $request->authenticate();
        $request->session()->regenerate();
        
        // 🔥 Clear impersonation flag on explicit login
        $request->session()->forget('impersonated_by');
        
        Auth::user()->update(['last_login_at' => now()]);
        
        return response()->json(['message' => 'Login successful.', 'user' => new UserResource(Auth::user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(Request $request): UserResource
    {
        // 🔥 FAANG FIX 1: Prevent Redundant Queries
        // loadMissing 'roles' ensures UserResource can get permissions/role efficiently
        $request->user()->loadMissing(['company', 'roles']); 
        
        return new UserResource($request->user());
    }
}