<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\User\ToggleUserStatusAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    /**
     * Fetch Users List (Team Roster)
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $requester = $request->user();

        // 🔒 Tenantable trait handles the company isolation automatically.
        $users = User::query()
            ->with('roles') // 🔥 FAANG Fix: Eager load roles to prevent N+1 Query
            ->where('id', '!=', $requester->id) // Aapka logic: Khud ko list mein mat dikhao
            ->latest()
            ->paginate(15); // 🔥 FAANG Fix: get() ki jagah paginate use karein taaki kal ko 1000 users hon toh app crash na ho

        return \App\Http\Resources\UserResource::collection($users);
    }

    /**
     * The Individual Kill Switch
     */
    public function toggleStatus(Request $request, User $user, ToggleUserStatusAction $action): JsonResponse
    {
        $requester = $request->user();

        // 🛡️ Rule 1: Permission Check
        if (!$requester->can('manage company users') && !$requester->can('manage all users')) {
            return response()->json(['message' => 'Unauthorized action. You do not have permission to modify account status.'], 403);
        }

        // 🛡️ Rule 2: Boundary Check (Admins can only touch their own company unless they have 'manage all users')
        if (!$requester->can('manage all users') && $requester->company_id !== $user->company_id) {
            return response()->json(['message' => 'User not found in your company.'], 404);
        }

        // 🛡️ Rule 3: Prevent self-lockout
        if ($requester->id === $user->id) {
            return response()->json(['message' => 'You cannot deactivate your own account.'], 403);
        }

        // 🛡️ Rule 4: Prevent revoking Superadmin access unless authorized
        if ($user->hasRole('superadmin') && !$requester->hasRole('superadmin')) {
            return response()->json(['message' => 'Action denied. Cannot modify a Superadmin.'], 403);
        }

        // 🚀 Execute Business Logic with full Context for Emails & Audits
        $isActive = $action->execute(
            $user,
            $request->user(),        // Admin User
            $request->ip(),          // IP Address
            $request->userAgent()    // 🔥 The Browser/Device Info
        );

        return response()->json([
            'message' => $isActive
                ? "Account for {$user->name} activated successfully."
                : "Account for {$user->name} deactivated. They have been logged out and notified via email.",
            'is_active' => $isActive
        ]);
    }
}
