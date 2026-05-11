<?php

declare(strict_types=1);

namespace App\Actions\PropertyFinder\Listing;

use App\Exceptions\PropertyFinder\PropertyFinderException;
use App\Models\PropertyFinderListing;
use App\Services\PropertyFinderApiClient;
use Illuminate\Support\Facades\Log;

/**
 * Unpublish a PropertyFinder listing (active/under_review → inactive).
 *
 * CORRECT ENDPOINT: POST /listings/{listing_id}/unpublish (NOT DELETE)
 *
 * Accepted reason codes (per PF API docs):
 *   sold | rented | duplicate | incorrect_info | temporary_hold | other
 */
class UnpublishListingAction
{
    public function __construct(
        private PropertyFinderApiClient $client
    ) {}

    /**
     * Unpublish a listing from PropertyFinder.
     *
     * @param  string|null  $reason  PF accepted reason code
     * @throws PropertyFinderException
     */
    public function execute(PropertyFinderListing $listing, ?string $reason = null): PropertyFinderListing
    {
        // Guard: listing must be on PF
        if (!$listing->pf_id) {
            throw new PropertyFinderException(
                'Cannot unpublish: listing has not been submitted to PropertyFinder (no pf_id).',
                400
            );
        }

        // Guard: validate reason code if provided
        if ($reason !== null) {
            $validReasons = config('propertyfinder.unpublish_reasons', []);
            if (!in_array($reason, $validReasons, true)) {
                throw new PropertyFinderException(
                    "Invalid unpublish reason: '{$reason}'. Accepted values: " . implode(', ', $validReasons),
                    400
                );
        }
        }

        try {
            // POST /listings/{listing_id}/unpublish
            // Optional body: { "reason": "sold" }
            $body = $reason ? ['reason' => $reason] : [];

            $this->client->post(
                $listing->company,
                "listings/{$listing->pf_id}/unpublish",
                $body
            );

            // PF API docs: status returns 'inactive' after unpublishing
            $listing->update([
                'status'           => PropertyFinderListing::STATUS_INACTIVE,
                'published_at'     => null,
                'unpublish_reason' => $reason,
            ]);

            Log::info('PropertyFinder listing unpublished', [
                'listing_id' => $listing->id,
                'pf_id'      => $listing->pf_id,
                'reason'     => $reason,
            ]);

            return $listing->fresh();

        } catch (PropertyFinderException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('PropertyFinder unpublish exception', [
                'listing_id' => $listing->id,
                'pf_id'      => $listing->pf_id,
                'error'      => $e->getMessage(),
            ]);

            throw new PropertyFinderException(
                'Listing unpublication error: ' . $e->getMessage(),
                500,
                $e
            );
        }
    }
}