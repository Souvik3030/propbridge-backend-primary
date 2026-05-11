<?php

namespace App\Actions\Invitation;

use App\Models\Invitation;

class RevokeInvitationAction
{
    public function execute(Invitation $invitation): void
    {
        // Delete the invitation (uses SoftDeletes) so it doesn't appear in the UI anymore
        // and cannot be accidentally resent, permanently freeing the quota.
        $invitation->delete();
    }
}