<?php

declare(strict_types=1);

namespace App\Actions\PropertyFinder\Auth;

use App\Exceptions\PropertyFinder\PropertyFinderException;
use App\Models\Company;
use Illuminate\Support\Facades\Log;

/**
 * Get the PF API key for a company.
 *
 * The Atlas API client exchanges the API key and secret for a JWT.
 */
class GetPropertyFinderTokenAction
{
    /**
     * Get the PF API key for a company.
     *
     * @throws PropertyFinderException If the company does not have PF enabled or credentials are missing.
     */
    public function execute(Company $company): string
    {
        if (!$company->hasPropertyFinderEnabled()) {
            throw new PropertyFinderException(
                'PropertyFinder is not enabled or the API credentials are not configured for this company.',
                401,
                null,
                ['company_id' => $company->id]
            );
        }

        $credentials = $company->getPropertyFinderCredentials();

        $token = $credentials['api_key'] ?? $credentials['client_id'] ?? null;

        if (!$token) {
            Log::warning('PropertyFinder company has pf_enabled but no API key', [
                'company_id' => $company->id,
            ]);

            throw new PropertyFinderException(
                'PropertyFinder API key is not set for this company. Please configure it in company settings.',
                401,
                null,
                ['company_id' => $company->id]
            );
        }

        return $token;
    }
}
