<?php

declare(strict_types=1);

namespace App\Actions\Invitation;

use App\Models\Invitation;
use App\Jobs\ProcessInvitationEmail;
use Illuminate\Support\Str;
use Exception;

class DispatchPendingInvitationAction
{
    public function execute(Invitation $invitation): void
    {
        // 🛡️ Security Check: Agar user already register ho chuka hai
        if ($invitation->used_at !== null) {
            throw new Exception('User has already registered.');
        }

        // 🛡️ Quota Check: If the invitation is expired, it's currently NOT taking up a seat.
        // We must check if there is an available seat before reviving it.
        if ($invitation->expires_at->isPast()) {
            $companyId = $invitation->company_id;
            // Lock company to prevent race conditions just like SendInvitationAction
            $company = \App\Models\Company::where('id', $companyId)->lockForUpdate()->first();
            if ($company) {
                $plan = strtolower($company->plan ?? 'basic');
                $maxAllowedUsers = config("saas.plans.{$plan}.max_users", 5);

                $currentUsersCount = \App\Models\User::where('company_id', $companyId)->count();
                $pendingInvitesCount = \App\Models\Invitation::where('company_id', $companyId)
                    ->whereNull('used_at')
                    ->where('expires_at', '>', now())
                    ->count();

                if (($currentUsersCount + $pendingInvitesCount) >= $maxAllowedUsers) {
                    throw new Exception("Your {$plan} plan only allows {$maxAllowedUsers} team members. You have reached your limit and cannot resend an expired invitation. Please upgrade your plan.");
                }
            }
        }

        // 🔄 Hamesha naya token generate karo (Security against expired tokens)
        $newToken = Str::random(60);

        $invitation->update([
            'token' => $newToken,
            'expires_at' => now()->addHours(24),
            'sent_at' => now(),
        ]);

        // 🚀 Dispatch Email Job
        // Controller ne pehle hi company load kar li thi, isliye N+1 query nahi hogi
        ProcessInvitationEmail::dispatch(
            $invitation->email,
            $newToken,
            $invitation->company->name ?? 'PropBridge'
        );
    }
}