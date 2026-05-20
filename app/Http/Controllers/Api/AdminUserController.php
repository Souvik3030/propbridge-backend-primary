<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminUserController extends Controller
{
    /**
     * GET /api/v1/admin/users
     *
     * Returns a paginated list of users with search, role filters, and company mapping.
     */
    public function index(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        if (!$currentUser) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Authorization: Only Super Admin and Admin can access user management
        if (!$currentUser->hasRole('superadmin') && !$currentUser->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized. Only admins can manage users.'], 403);
        }

        $perPage = (int) $request->query('per_page', 20);
        $roleFilter = $request->query('role');
        $search = $request->query('search');

        // Query definition (Super Admins see all users, regular Admins are scoped by Tenantable)
        $query = User::with(['roles', 'company']);

        if ($currentUser->hasRole('superadmin')) {
            $query->withoutGlobalScope('company');
        }

        // Search by Name, Email, or Company
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                  ->orWhere('email', 'like', $searchTerm)
                  ->orWhereHas('company', function ($cq) use ($searchTerm) {
                      $cq->where('name', 'like', $searchTerm);
                  });
            });
        }

        // Filter by Role Name
        if (!empty($roleFilter)) {
            $query->whereHas('roles', function ($rq) use ($roleFilter) {
                $dbRole = match ($roleFilter) {
                    'Super Admin' => 'superadmin',
                    'Admin' => 'admin',
                    'Listing Agent' => 'agent',
                    'Listing Owner' => 'owner',
                    default => strtolower(str_replace(' ', '', $roleFilter))
                };
                $rq->where('name', $dbRole);
            });
        }

        $paginated = $query->latest()->paginate($perPage);

        // Role mapping lookup dictionary
        $roleMap = [
            'superadmin' => 'Super Admin',
            'admin' => 'Admin',
            'agent' => 'Listing Agent',
            'owner' => 'Listing Owner',
        ];

        // Format user list per especified Developer Contract
        $formattedData = collect($paginated->items())->map(function (User $user) use ($currentUser, $roleMap) {
            $dbRole = $user->roles->first()?->name ?? 'agent';
            $roleLabel = $roleMap[$dbRole] ?? ucwords(str_replace('_', ' ', $dbRole));

            // Logic for hasActions: Do not allow impersonation/deletion of yourself, or any Super Admin
            $hasActions = true;
            if ($user->id === $currentUser->id || $dbRole === 'superadmin') {
                $hasActions = false;
            }

            return [
                'id' => $user->id,
                'name' => $user->name ?: 'No Name',
                'email' => $user->email,
                'role' => $roleLabel,
                'company' => $user->company->name ?? 'N/A',
                'hasActions' => $hasActions,
            ];
        });

        return response()->json([
            'data' => $formattedData,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ]
        ]);
    }

    /**
     * POST /api/v1/admin/users/{id}/impersonate
     *
     * Safer Stateless Impersonation: generates access token to seamless switch session context.
     */
    public function impersonate(Request $request, string $id): JsonResponse
    {
        $currentUser = $request->user();
        if (!$currentUser) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Authorization: Only Super Admin and Admin can impersonate users
        if (!$currentUser->hasRole('superadmin') && !$currentUser->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized. Only admins can impersonate.'], 403);
        }

        // Fetch target user (Super Admins can look everywhere, Admins scoped)
        $targetQuery = User::with('company');
        if ($currentUser->hasRole('superadmin')) {
            $targetQuery->withoutGlobalScope('company');
        }
        $targetUser = $targetQuery->find($id);

        if (!$targetUser) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // Security constraints
        if ($targetUser->id === $currentUser->id) {
            return response()->json(['message' => 'Cannot impersonate yourself.'], 400);
        }

        if ($targetUser->hasRole('superadmin')) {
            return response()->json(['message' => 'Cannot impersonate another Super Admin.'], 403);
        }

        // Create Sanctum impersonation access token
        $token = $targetUser->createToken('impersonation-token')->plainTextToken;

        // Role labels dictionary
        $roleMap = [
            'superadmin' => 'Super Admin',
            'admin' => 'Admin',
            'agent' => 'Listing Agent',
            'owner' => 'Listing Owner',
        ];
        $dbRole = $targetUser->roles->first()?->name ?? 'agent';
        $roleLabel = $roleMap[$dbRole] ?? ucwords(str_replace('_', ' ', $dbRole));

        return response()->json([
            'message' => 'Impersonating user successfully',
            'token' => $token,
            'user' => [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'email' => $targetUser->email,
                'role' => $roleLabel,
                'company' => $targetUser->company->name ?? 'N/A'
            ]
        ]);
    }

    /**
     * DELETE /api/v1/admin/users/{id}
     *
     * Deletes user profile safely.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $currentUser = $request->user();
        if (!$currentUser) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Authorization check
        if (!$currentUser->hasRole('superadmin') && !$currentUser->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized. Only admins can delete profiles.'], 403);
        }

        $targetQuery = User::query();
        if ($currentUser->hasRole('superadmin')) {
            $targetQuery->withoutGlobalScope('company');
        }
        $targetUser = $targetQuery->find($id);

        if (!$targetUser) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // Security constraints
        if ($targetUser->id === $currentUser->id) {
            return response()->json(['message' => 'Cannot delete your own account.'], 400);
        }

        if ($targetUser->hasRole('superadmin')) {
            return response()->json(['message' => 'Super Admin profiles cannot be deleted.'], 403);
        }

        // Revoke any active Sanctum tokens
        $targetUser->tokens()->delete();

        // Perform hard delete
        $targetUser->delete();

        return response()->json([
            'success' => true,
            'message' => 'User has been safely deleted.'
        ]);
    }
}
