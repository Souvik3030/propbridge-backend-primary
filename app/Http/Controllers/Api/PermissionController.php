<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    /**
     * List all system roles and permissions.
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->can('manage roles and permissions')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $roles = Role::with('permissions')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ];
        });

        return response()->json([
            'roles' => $roles,
            'all_permissions' => Permission::all()->pluck('name'),
        ]);
    }

    /**
     * Create a new custom role.
     */
    public function storeRole(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string|unique:roles,name']);

        $role = Role::create(['name' => $request->name, 'guard_name' => 'web']);

        return response()->json(['message' => "Role '{$role->name}' created.", 'role' => $role], 201);
    }

    /**
     * Sync permissions for a specific role.
     * This is the "Checkbox" logic.
     */
    public function updateRolePermissions(Request $request, Role $role): JsonResponse
    {
        // 🔒 Safety: Never allow stripping permissions from Superadmin
        if ($role->name === 'superadmin') {
            return response()->json(['message' => 'The superadmin role cannot be modified.'], 422);
        }

        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,name'
        ]);

        $role->syncPermissions($request->permissions);

        return response()->json([
            'message' => "Permissions for role '{$role->name}' updated successfully.",
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions()->pluck('name'),
            ]
        ]);
    }

    /**
     * Delete a custom role.
     */
    public function deleteRole(Role $role): JsonResponse
    {
        if (in_array($role->name, ['superadmin', 'admin', 'agent', 'owner'])) {
            return response()->json(['message' => 'Default system roles cannot be deleted.'], 422);
        }

        $role->delete();

        return response()->json(['message' => "Role '{$role->name}' deleted successfully."]);
    }
}
