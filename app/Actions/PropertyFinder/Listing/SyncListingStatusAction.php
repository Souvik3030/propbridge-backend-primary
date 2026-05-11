<?php

declare(strict_types=1);

namespace App\Actions\PropertyFinder\Listing;

use App\Models\PropertyFinderListing;
use App\Services\PropertyFinderApiClient;
use Illuminate\Support\Facades\Log;

/**
 * Sync listing status from PropertyFinder.
 *
 * Calls GET /listings/{pf_id} to fetch the current status from PF
 * and updates the local record accordingly.
 */
class SyncListingStatusAction
{
    public function __construct(
        private PropertyFinderApiClient $client
    ) {}

    /**
     * Sync a listing's status from PF API.
     */
    public function execute(PropertyFinderListing $listing): PropertyFinderListing
    {
        // FIX: was using $listing->pf_listing_id (doesn't exist) — correct field is pf_id
        if (!$listing->pf_id) {
            return $listing; // not submitted to PF yet, nothing to sync
        }

        try {
            $data = $this->client->get(
                $listing->company,
                "listings/{$listing->pf_id}"
            );

            $pfStatus      = $data['status'] ?? null;
            $internalStatus = $this->mapPfStatus($pfStatus);

            // FIX: was trying to update 'pf_response' column (doesn't exist)
            $listing->update([
                'status'         => $internalStatus,
                'pf_listing_url' => $data['listing_url'] ?? $listing->pf_listing_url,
            ]);

            Log::info('PropertyFinder listing status synced', [
                'listing_id'  => $listing->id,
                'pf_id'       => $listing->pf_id,
                'pf_status'   => $pfStatus,
                'local_status' => $internalStatus,
            ]);

            return $listing->fresh();

        } catch (\Throwable $e) {
            Log::warning('PropertyFinder status sync failed', [
                'listing_id' => $listing->id,
                'pf_id'      => $listing->pf_id,
                'error'      => $e->getMessage(),
            ]);

            return $listing;
        }
    }

    /**
     * Map PF API v2 status strings to our internal status constants.
     * PF API v2 uses: draft, active, under_review, inactive
     */
    private function mapPfStatus(?string $pfStatus): string
    {
        return match (strtolower($pfStatus ?? '')) {
            'active', 'live', 'published' => PropertyFinderListing::STATUS_ACTIVE,
            'under_review', 'pending'     => PropertyFinderListing::STATUS_UNDER_REVIEW,
            'inactive', 'unpublished'     => PropertyFinderListing::STATUS_INACTIVE,
            'draft'                       => PropertyFinderListing::STATUS_DRAFT,
            'rejected'                    => PropertyFinderListing::STATUS_COMPLIANCE_FAILED,
            default                       => PropertyFinderListing::STATUS_DRAFT,
        };
    }
}