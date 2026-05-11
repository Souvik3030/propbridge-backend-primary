<?php

declare(strict_types=1);

namespace App\Actions\PropertyFinder\Auth;

use App\Exceptions\PropertyFinder\PropertyFinderException;
use App\Models\Company;
use App\Services\PropertyFinderApiClient;
use Illuminate\Support\Facades\Log;

/**
 * Test PropertyFinder credentials by making a real API call.
 * Uses GET /users?per_page=1 as a lightweight connectivity test.
 */
class TestPropertyFinderCredentialsAction
{
    public function __construct(
        private PropertyFinderApiClient $client
    ) {}

    /**
     * Test company PF credentials.
     */
    public function execute(Company $company): array
    {
        try {
            // Lightweight test call — fetch first agent to verify token works
            $response = $this->client->get($company, 'users', ['per_page' => 1]);

            Log::info('PropertyFinder credentials validated successfully', [
                'company_id' => $company->id,
            ]);

            return [
                'success' => true,
                'message' => 'PropertyFinder API credentials are valid.',
                'user_count' => count($response['data'] ?? $response),
            ];
        } catch (PropertyFinderException $e) {
            Log::warning('PropertyFinder credentials test failed', [
                'company_id' => $company->id,
                'error'      => $e->getMessage(),
                'status'     => $e->getStatusCode(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'status'  => $e->getStatusCode(),
                'context' => $e->getContext(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Unexpected error testing credentials: ' . $e->getMessage(),
            ];
        }
    }
}
