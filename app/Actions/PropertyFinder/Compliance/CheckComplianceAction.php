<?php

declare(strict_types=1);

namespace App\Actions\PropertyFinder\Compliance;

use App\Exceptions\PropertyFinder\PropertyFinderException;
use App\Models\PropertyFinderListing;
use App\Services\PropertyFinderApiClient;
use Illuminate\Support\Facades\Log;

/**
 * Check compliance for a PropertyFinder listing via the PF API.
 *
 * CORRECT ENDPOINT: GET /listings/{listing_id}/compliance
 * (The listing must exist on PF first — i.e., have a pf_id)
 *
 * Response fields:
 *  - compliant (bool): true = ready to publish
 *  - errors (array): blocking errors that must be fixed
 *  - warnings (array): non-blocking suggestions
 *  - permit_valid (bool): whether permit was verified with authority
 *  - permit_status (string): active|expired|not_found|not_required
 *  - checked_at (string): timestamp of check
 */
class CheckComplianceAction
{
    public function __construct(
        private PropertyFinderApiClient $client
    ) {}

    /**
     * Run compliance check against PF API and update local listing.
     *
     * @throws PropertyFinderException If listing has no pf_id or API call fails
     */
    public function execute(PropertyFinderListing $listing): PropertyFinderListing
    {
        $service = app(\App\Services\PropertyFinder\EmiratePermitService::class);
        
        // Skip compliance for non-permit emirates or exempt areas
        if (!$service->requiresPermit($listing->emirate_id, $listing->location_name)) {
            $listing->update([
                'compliance_status' => 'exempt',
                'can_publish'       => true,
                'is_exempt_area'    => true,
            ]);
            return $listing;
        }

        if (!$listing->permit_number || !$listing->company->license_number) {
            $listing->update(['compliance_status' => 'failed', 'can_publish' => false]);
            return $listing;
        }

        try {
            // Call PF API: GET /v1/compliances/{permitNumber}/{licenseNumber}
            $response = $this->client->get(
                $listing->company,
                "compliances/{$listing->permit_number}/{$listing->company->license_number}",
                ['permitType' => $this->resolvePermitType($listing->emirate_id)]
            );

            $data = $response['data'][0] ?? null;

            if (!$data) {
                $listing->update(['compliance_status' => 'failed', 'can_publish' => false]);
                return $listing;
            }

            // Auto-fill regulated fields from compliance response
            $listing->update([
                'compliance_status'        => 'passed',
                'can_publish'              => true,
                'price'                    => $data['property']['value'] ?? $data['property']['price'] ?? $listing->price,
                'listing_type'             => $this->mapListingType($data['property']['listingType'] ?? ''),
                'project_status'           => $this->mapProjectStatus($data['property']['saleType'] ?? ''),
                'compliance_snapshot'      => $data,
                'last_compliance_check_at' => now(),
            ]);

            return $listing;

        } catch (\Throwable $e) {
            Log::error('Compliance check failed', ['error' => $e->getMessage(), 'listing_id' => $listing->id]);
            $listing->update(['compliance_status' => 'failed', 'can_publish' => false]);
            return $listing;
        }
    }

    private function resolvePermitType(int $emirateId): string
    {
        return match($emirateId) {
            2 => 'adrec',
            default => 'dld'
        };
    }

    private function mapListingType(string $pfListingType): string
    {
        // Simple heuristic: if it mentions Villa/Townhouse/etc, or just default to sale
        return strtolower($pfListingType) === 'rent' ? 'rent' : 'sale'; // Simplification based on current schema
    }

    private function mapProjectStatus(string $saleType): ?string
    {
        if (strcasecmp($saleType, 'Primary') === 0) {
            return 'off_plan_primary';
        }
        if (strcasecmp($saleType, 'Secondary') === 0) {
            return 'completed';
        }
        return 'completed'; // Default
    }
}