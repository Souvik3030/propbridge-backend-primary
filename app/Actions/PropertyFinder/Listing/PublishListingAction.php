<?php

declare(strict_types=1);

namespace App\Actions\PropertyFinder\Listing;

use App\Exceptions\PropertyFinder\PropertyFinderException;
use App\Models\PropertyFinderListing;
use App\Services\PropertyFinderApiClient;
use Illuminate\Support\Facades\Log;

/**
 * Publish a PropertyFinder listing (draft → active).
 *
 * CORRECT ENDPOINT: POST /listings/{listing_id}/publish (NO body required)
 *
 * Flow:
 *  1. Guard: listing must exist on PF (has pf_id)
 *  2. Guard: compliance must pass (compliant = true from GET /listings/{id}/compliance)
 *  3. Guard: don't re-publish if under_review (resets the review queue)
 *  4. POST /listings/{pf_id}/publish
 *  5. Update local status: active or under_review
 */
class PublishListingAction
{
    public function __construct(
        private PropertyFinderApiClient $client
    ) {}

    /**
     * Publish a listing to PropertyFinder.
     *
     * @throws PropertyFinderException
     */
    public function execute(PropertyFinderListing $listing): PropertyFinderListing
    {
        // Guard: listing must have been submitted to PF first
        if (!$listing->pf_id) {
            throw new PropertyFinderException(
                'Cannot publish: listing has not been submitted to PropertyFinder yet (no pf_id). Create the listing first.',
                400
            );
        }

        // Guard: do NOT re-publish while under_review (resets review queue per PF docs)
        if ($listing->isUnderReview()) {
            throw new PropertyFinderException(
                'Cannot publish: listing is already under review by PropertyFinder. Wait for review to complete.',
                409
            );
        }

        // Guard: listing must pass compliance before publishing
        if (!$listing->isCompliant()) {
            $errors = $listing->validation_diffs ?? [];
            if (isset($listing->compliance_snapshot['errors'])) {
                $errors = array_merge($errors, $listing->compliance_snapshot['errors']);
            }
            throw new PropertyFinderException(
                'Cannot publish: listing has compliance issues that must be resolved first. Errors: '
                . implode(', ', $errors),
                422,
                null,
                ['errors' => $errors]
            );
        }

        try {
            // POST /listings/{listing_id}/publish — NO body required per PF API docs
            $pfData = $this->client->post(
                $listing->company,
                "listings/{$listing->pf_id}/publish"
            );

            $pfStatus = $pfData['status'] ?? 'active';

            // Map PF API status to our internal status
            $internalStatus = match ($pfStatus) {
                'active'        => PropertyFinderListing::STATUS_ACTIVE,
                'under_review'  => PropertyFinderListing::STATUS_UNDER_REVIEW,
                default         => PropertyFinderListing::STATUS_ACTIVE,
            };

            $listing->update([
                'status'         => $internalStatus,
                'published_at'   => now(),
                'pf_listing_url' => $pfData['listing_url'] ?? $listing->pf_listing_url,
            ]);

            Log::info('PropertyFinder listing published', [
                'listing_id' => $listing->id,
                'pf_id'      => $listing->pf_id,
                'status'     => $internalStatus,
                'listing_url' => $listing->pf_listing_url,
            ]);

            return $listing->fresh();

        } catch (PropertyFinderException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('PropertyFinder publish exception', [
                'listing_id' => $listing->id,
                'pf_id'      => $listing->pf_id,
                'error'      => $e->getMessage(),
            ]);

            throw new PropertyFinderException(
                'Listing publication error: ' . $e->getMessage(),
                500,
                $e
            );
        }
    }
}