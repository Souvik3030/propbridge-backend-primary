<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    /**
     * Superadmins can bypass all other checks in this policy.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('superadmin')) {
            return true;
        }
        return null; // Fall through to the specific checks below
    }

    /**
     * Determine whether the user can view ANY companies (The List).
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view all companies'); 
    }

    /**
     * Determine whether the user can create a NEW company.
     */
    public function create(User $user): bool
    {
        return $user->can('manage companies'); 
    }

    /**
     * Determine whether the user can view a SPECIFIC company.
     */
    public function view(User $user, Company $company): bool
    {
        return $user->can('manage company profile') && $user->company_id === $company->id;
    }

    /**
     * Determine whether the user can update the company (e.g., change name/logo).
     */
    public function update(User $user, Company $company): bool
    {
        return $user->can('manage company profile') && $user->company_id === $company->id;
    }
}