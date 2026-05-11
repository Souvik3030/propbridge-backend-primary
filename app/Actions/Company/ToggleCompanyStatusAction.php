<?php

declare(strict_types=1);

namespace App\Actions\Company;

use App\Jobs\SendCompanySuspendedEmailJob;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ToggleCompanyStatusAction
{
    /**
     * Executes the company suspension, wipes all sessions, notifies admins, and logs the audit.
     */
    public function execute(Company $company, User $superAdmin, string $ipAddress, string $userAgent): bool
    {
        $isActive = !$company->is_active;
        $company->update(['is_active' => $isActive]);

        if (!$isActive) {
            // 1. 🛑 FAANG Fix: Chunked & Relationship-based Wiping
            User::where('company_id', $company->id)->chunkById(100, function ($users) {
                foreach ($users as $user) {
                    $user->tokens()->delete();
                    try {
                        DB::table('sessions')->where('user_id', $user->id)->delete();
                    } catch (\Exception $e) {
                        // Fails silently if a non-database session driver is used
                    }
                }
            });

            // 2. 📧 Dispatch Email to Company Admins ONLY
            SendCompanySuspendedEmailJob::dispatch($company, $superAdmin);

            // 3. 📝 Log the Company Suspension
            $this->logAudit($company, $superAdmin, $ipAddress, $userAgent, 'company.suspended');
            
        } else {
            // 4. 📧 Dispatch the activation email
            \App\Jobs\SendCompanyActivatedEmailJob::dispatch($company, $superAdmin);

            // 📝 Log the Company Activation
            $this->logAudit($company, $superAdmin, $ipAddress, $userAgent, 'company.activated');
        }

        return $isActive;
    }

    /**
     * Helper method to map the audit log to your polymorphic database schema
     */
    private function logAudit(Company $company, User $superAdmin, string $ipAddress, string $userAgent, string $action): void
    {
        AuditLog::create([
            'user_id' => $superAdmin->id,                    // The SuperAdmin (Actor)
            'company_id' => $company->id,                    // The Tenant Boundary
            'action' => $action,                             // e.g., 'company.suspended'
            'resource_type' => get_class($company),          // 'App\Models\Company'
            'resource_id' => $company->id,                   // The Target Company
            'changes' => [
                'status_changed_to' => $action === 'company.suspended' ? 'inactive' : 'active'
            ],
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }
}