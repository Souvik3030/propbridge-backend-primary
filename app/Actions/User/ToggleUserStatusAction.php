<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Jobs\SendAccountSuspendedEmailJob;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ToggleUserStatusAction
{
    /**
     * Toggles the user's active status and instantly revokes ALL access if suspended.
     */
    public function execute(User $targetUser, User $adminUser, string $ipAddress, string $userAgent): bool
    {
        // 1. Flip the status
        $isActive = !$targetUser->is_active;
        $targetUser->update(['is_active' => $isActive]);

        // 2. 🔥 THE TRUE KILL SWITCH
        if (!$isActive) {
            
            // A. Delete API Tokens (Logs out mobile apps / external integrations)
            $targetUser->tokens()->delete();

            // B. Delete Web Sessions (Instantly kicks them out of the React SPA)
            try {
                DB::table('sessions')->where('user_id', $targetUser->id)->delete();
            } catch (\Exception $e) {
                // Fails silently if the database session driver is not being used
            }

            // C. 📧 Dispatch the contextual email
            SendAccountSuspendedEmailJob::dispatch($targetUser, $adminUser);

            // D. 📝 Log the Suspension in the Audit Trail
            $this->logAudit($targetUser, $adminUser, $ipAddress, $userAgent, 'user.suspended');

        } else {
            // E. 📧 Dispatch the activation email
            \App\Jobs\SendAccountActivatedEmailJob::dispatch($targetUser, $adminUser);

            // 📝 Log the Activation in the Audit Trail
            $this->logAudit($targetUser, $adminUser, $ipAddress, $userAgent, 'user.activated');
        }

        return $isActive;
    }

    /**
     * Helper method to map the audit log to your actual database schema
     */
    private function logAudit(User $targetUser, User $adminUser, string $ipAddress, string $userAgent, string $action): void
    {
        AuditLog::create([
            'user_id' => $adminUser->id,                     // The Admin (Actor)
            'company_id' => $targetUser->company_id,         // Tenant Boundary
            'action' => $action,                             // e.g., 'user.suspended'
            'resource_type' => get_class($targetUser),       // 'App\Models\User'
            'resource_id' => $targetUser->id,                // The Victim (Target)
            'changes' => [
                'status_changed_to' => $action === 'user.suspended' ? 'inactive' : 'active'
            ],
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }
}