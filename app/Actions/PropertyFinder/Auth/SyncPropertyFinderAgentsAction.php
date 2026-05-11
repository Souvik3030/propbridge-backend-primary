<?php

declare(strict_types=1);

namespace App\Actions\PropertyFinder\Auth;

use App\Models\Company;
use App\Models\User;
use App\Services\PropertyFinderApiClient;
use Illuminate\Support\Facades\Log;

/**
 * Synchronise PropertyFinder agents for a company.
 *
 * Fetches all PF Expert users from GET /users (with pagination) and
 * updates the pf_agent_id on matching local users by email.
 */
class SyncPropertyFinderAgentsAction
{
    public function __construct(
        private PropertyFinderApiClient $client
    ) {}

    /**
     * Sync all PF agents for a company, handling pagination.
     */
    public function execute(Company $company): void
    {
        $page    = 1;
        $synced  = 0;
        $skipped = 0;

        do {
            try {
                $response = $this->client->get($company, 'users', [
                    'page'     => $page,
                    'per_page' => 100, // max per page
                ]);
            } catch (\Throwable $e) {
                Log::error('PropertyFinder agent sync failed', [
                    'company_id' => $company->id,
                    'page'       => $page,
                    'error'      => $e->getMessage(),
                ]);
                return;
            }

            // PF API may return agents in 'data' key or directly as array
            $agents = $response['data'] ?? $response ?? [];

            if (empty($agents)) {
                break;
            }

            foreach ($agents as $agentData) {
                $email     = $agentData['email'] ?? null;
                $pfAgentId = isset($agentData['id']) ? (string) $agentData['id'] : null;
                $isActive  = $agentData['is_active'] ?? true;

                if (!$email || !$pfAgentId) {
                    $skipped++;
                    continue;
                }

                $user = User::where('company_id', $company->id)
                    ->where('email', $email)
                    ->first();

                if ($user) {
                    $user->update(['pf_agent_id' => $pfAgentId]);
                    $synced++;

                    Log::info('PropertyFinder agent ID synced', [
                        'user_id'    => $user->id,
                        'email'      => $email,
                        'pf_agent_id' => $pfAgentId,
                        'is_active'  => $isActive,
                    ]);
                } else {
                    $skipped++;
                }
            }

            $page++;

            // Check if there are more pages
            $hasMorePages = isset($response['meta']['last_page'])
                ? $page <= $response['meta']['last_page']
                : count($agents) >= 100;

        } while ($hasMorePages);

        Log::info('PropertyFinder agent sync completed', [
            'company_id' => $company->id,
            'synced'     => $synced,
            'skipped'    => $skipped,
        ]);
    }
}
