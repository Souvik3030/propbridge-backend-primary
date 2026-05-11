<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Company\CreateCompanyAction;
use App\Actions\Company\UpdateCompanyAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Actions\Company\ToggleCompanyStatusAction;
use App\Actions\Company\ChangeCompanyPlanAction;
use App\Http\Requests\Company\ChangePlanRequest;

class CompanyController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Company::class);

        $companies = Company::query()
            ->withCount('users')
            // 🔥 N+1 Fix: Eager load pending invites efficiently
            ->withCount(['invitations as pending_invites_count' => function ($query) {
                $query->whereNull('used_at')->where('expires_at', '>', now());
            }])
            ->filter($request->only(['search', 'plan', 'slug', 'status']))
            ->latest()
            ->paginate(15);
        return CompanyResource::collection($companies);
    }

    public function store(StoreCompanyRequest $request, CreateCompanyAction $action): JsonResponse
    {
        // 🔥 FIX: Check Policy explicitly before doing anything
        $this->authorize('create', Company::class);

        $company = $action->execute($request->validated());

        return response()->json([
            'message' => 'Company created successfully.',
            'company' => new CompanyResource($company)
        ], 201);
    }

    public function show(Company $company): CompanyResource
    {
        $this->authorize('view', $company);

       return new CompanyResource($company);
    }

    public function update(UpdateCompanyRequest $request, Company $company, UpdateCompanyAction $action): JsonResponse
    {
        $this->authorize('update', $company);

        $updatedCompany = $action->execute($company, $request->validated());

        return response()->json([
            'message' => 'Company updated successfully.',
            'company' => new CompanyResource($updatedCompany)
        ]);
    }

   public function toggleStatus(Request $request, Company $company, ToggleCompanyStatusAction $action): JsonResponse
    {
        $this->authorize('update', $company);

        $isActive = $action->execute(
            $company, 
            $request->user(), 
            $request->ip(), 
            $request->userAgent()
        );

        return response()->json([
            'message' => $isActive ? 'Company activated.' : 'Company suspended and all users logged out.',
            'is_active' => $isActive
        ]);
    }

    public function users(Request $request, Company $company): AnonymousResourceCollection
    {
        // 1. Policy Check: Ensure only authorized users (Superadmin or the company's Admin) can view this list
        $this->authorize('view', $company);

        // 2. Fetch Users belonging to this specific company
        // Superadmin bypasses the Tenantable Global Scope automatically based on our previous setup
        $users = \App\Models\User::where('company_id', $company->id)
            ->latest()
            ->paginate(15);

        return \App\Http\Resources\UserResource::collection($users);
    }

    public function changePlan(ChangePlanRequest $request, Company $company, ChangeCompanyPlanAction $action): JsonResponse
    {
        $updatedCompany = $action->execute($company, $request->validated('plan'));

        return response()->json([
            'message' => "Brokerage upgraded to " . ucfirst($updatedCompany->plan) . " plan successfully.",
            'company' => new CompanyResource($updatedCompany)
        ]);
    }
}
