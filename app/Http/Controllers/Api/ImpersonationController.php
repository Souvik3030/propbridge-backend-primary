<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    /**
     * Start Impersonating a User (SPA Session Swap Approach)
     */
    public function impersonate(Request $request, User $user): JsonResponse
    {
        $impersonator = $request->user();

        // 🔒 Security Checks
        if (!$impersonator->can('impersonate users')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        if ($user->hasRole('superadmin')) {
            return response()->json(['message' => 'Cannot impersonate another Superadmin.'], 403);
        }

        // 1. Store the original Superadmin's ID in the current session
        $request->session()->put('impersonated_by', $impersonator->id);

        // 2. 🔥 The Magic: Seamlessly swap the user in the CURRENT session
        // (Do not logout or invalidate, just overwrite the user ID)
        Auth::guard('web')->login($user);

        // 3. Clear Spatie permissions cache to prevent ghosting
        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $user->load('roles'); // Eager load for frontend context
        return response()->json([
            'message' => "You are now impersonating {$user->name}.",
            'user' => new UserResource($user)
        ]);
    }

    /**
     * Stop Impersonating and Return to Superadmin
     */
    public function leave(Request $request): JsonResponse
    {
        if (!$request->session()->has('impersonated_by')) {
            return response()->json(['message' => 'Not currently impersonating.'], 400);
        }

        $superadminId = $request->session()->get('impersonated_by');

        // 🔥 Manually fetch Superadmin WITHOUT the Tenantable Global Scope
        $superadmin = \App\Models\User::withoutGlobalScope('company')->find($superadminId);

        if (!$superadmin) {
            $request->session()->forget('impersonated_by');
            return response()->json(['message' => 'Could not restore identity. User not found.'], 401);
        }

        // 1. Remove the impersonation flag FIRST
        $request->session()->forget('impersonated_by');

        // 2. 🔥 Seamlessly swap BACK to the Superadmin
        Auth::guard('web')->login($superadmin);

        // 3. Clear Spatie cache again
        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $superadmin->load('roles'); // Eager load for frontend context
        return response()->json([
            'message' => 'Welcome back, Superadmin. Identity restored.',
            'user' => new UserResource($superadmin)
        ]);
    }
}