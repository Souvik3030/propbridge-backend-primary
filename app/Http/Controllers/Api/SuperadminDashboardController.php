<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Spatie\Permission\Models\Role;

class SuperadminDashboardController extends Controller
{
    use AuthorizesRequests; 

    public function stats(Request $request): JsonResponse
    {
        if (!$request->user()->can('view stats')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // 🔥 FAANG FIX: Increased TTL to 1 hour (3600s) because we now actively manage invalidation
        $stats = Cache::remember('superadmin_dashboard_stats', 3600, function () {
            return [
                'total_companies' => Company::count(),
                'active_users' => User::withoutGlobalScopes()->where('is_active', 1)->count(),
                'users_by_role' => Role::withCount('users')->pluck('users_count', 'name'),
            ];
        });

        return response()->json(['data' => $stats]);
    }
}