<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PropertyFinderListing;
use App\Models\User;

class PropertyFinderListingPolicy
{
    /**
     * Determine if user can view any listings
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view listings');
    }

    /**
     * Determine if user can view the listing
     */
    public function view(User $user, PropertyFinderListing $listing): bool
    {
        if (!$user->can('view listings')) return false;

        return $user->hasRole('superadmin') || $user->company_id === $listing->company_id;
    }

    /**
     * Determine if user can create listings
     */
    public function create(User $user): bool
    {
        return $user->can('create listings') && 
               ($user->hasRole('superadmin') || $user->company->hasPropertyFinderEnabled());
    }

    /**
     * Determine if user can update the listing
     */
    public function update(User $user, PropertyFinderListing $listing): bool
    {
        if ($user->company_id !== $listing->company_id && !$user->hasRole('superadmin')) {
            return false;
        }

        // 1. Superadmin/Power User check
        if ($user->can('edit any listing')) return true;

        // 2. Company Admin check
        if ($user->can('edit company listings') && $user->company_id === $listing->company_id) {
            return true;
        }

        // 3. Owner check
        return $user->can('edit own listings') && $user->id === $listing->agent_id;
    }

    /**
     * Determine if user can delete the listing
     */
    public function delete(User $user, PropertyFinderListing $listing): bool
    {
        if ($user->company_id !== $listing->company_id && !$user->hasRole('superadmin')) {
            return false;
        }

        if ($user->can('delete any listing')) return true;

        if ($user->can('delete company listings') && $user->company_id === $listing->company_id) {
            return true;
        }

        return $user->can('delete own listings') && $user->id === $listing->agent_id;
    }
}