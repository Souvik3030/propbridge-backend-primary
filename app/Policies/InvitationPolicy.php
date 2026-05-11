<?php
declare(strict_types=1);

namespace App\Policies;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class InvitationPolicy
{
    /**
     * Can the user view the list of invitations?
     */
    public function viewAny(User $user): bool
    {
        return $user->can('manage invitations');
    }

    /**
     * Can the user view a specific invitation?
     */
    public function view(User $user, Invitation $invitation): bool
    {
        if ($user->can('manage invitations')) {
            // Superadmins can see all, Admins see their company
            return $user->hasRole('superadmin') || $user->company_id === $invitation->company_id;
        }
        
        return false;
    }

    /**
     * Can the user create invitations?
     */
    public function create(User $user): bool
    {
        return $user->can('manage invitations');
    }

    /**
     * Can the user update/send a pending invitation?
     */
    public function update(User $user, Invitation $invitation): bool
    {
        return $user->can('manage invitations') && 
               ($user->hasRole('superadmin') || $user->company_id === $invitation->company_id);
    }

    /**
     * Can the user delete an invitation?
     */
    public function delete(User $user, Invitation $invitation): bool
    {
        return $user->can('manage invitations') && 
               ($user->hasRole('superadmin') || $user->company_id === $invitation->company_id);
    }
}