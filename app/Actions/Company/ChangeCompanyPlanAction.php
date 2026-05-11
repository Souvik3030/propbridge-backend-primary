<?php

namespace App\Actions\Company;

use App\Models\Company;
use App\Models\User;
use App\Models\Invitation;
use Illuminate\Validation\ValidationException;

class ChangeCompanyPlanAction
{
    public function execute(Company $company, string $plan): Company
    {
        $planValue = strtolower(trim($plan));

        if (!array_key_exists($planValue, config('saas.plans'))) {
            throw ValidationException::withMessages([
                'plan' => ['The selected plan is not available.']
            ]);
        }

        // 🔥 PREVENT DOWNGRADE CHEATS
        $newPlanLimit = config("saas.plans.{$planValue}.max_users", 5);
        $currentUsersCount = User::where('company_id', $company->id)->count();
        $pendingInvitesCount = Invitation::where('company_id', $company->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->count();

        $totalConsumed = $currentUsersCount + $pendingInvitesCount;

        if ($newPlanLimit !== 999999 && $totalConsumed > $newPlanLimit) {
            throw ValidationException::withMessages([
                'plan' => ["Cannot downgrade. Current usage ({$totalConsumed} seats) exceeds the " . ucfirst($planValue) . " plan limit of {$newPlanLimit}. Please remove users or pending invites."]
            ]);
        }

        $company->update(['plan' => $planValue]);

        return $company;
    }
}