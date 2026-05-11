<?php

declare(strict_types=1);

namespace App\Actions\PropertyFinder\Webhook;

use App\Models\PropertyFinderListing;
use Illuminate\Support\Facades\Log;

/**
 * Handle incoming PropertyFinder listing lifecycle webhook events.
 *
 * Supported PF API v2 event types:
 *  listing.published    → status: active
 *  listing.unpublished  → status: inactive
 *  listing.under_review → status: under_review
 *  listing.rejected     → status: compliance_failed
 *  listing.expired      → status: inactive
 *  listing.updated      → sync local data
 *  listing_price_issue         → compliance_failed
 *  listing_trakheesi_invalid   → compliance_failed (Dubai RERA issue)
 *  adrec_sub_permit_required   → compliance_failed (Abu Dhabi issue)
 */
class HandleListingWebhookAction
{
    /**
     * Handle a PropertyFinder webhook event.
     *
     * @param  string  $event  The event type from the webhook payload
     * @param  array   $data   The event data payload
     */
    public function execute(string $event, array $data): void
    {
        // PF sends the listing ID as 'id' or 'listing_id' in the data payload
        $pfId = $data['id'] ?? $data['listing_id'] ?? null;

        if (!$pfId) {
            Log::warning('PropertyFinder webhook received without listing ID', [
                'event' => $event,
                'data'  => $data,
            ]);
            return;
        }

        $listing = PropertyFinderListing::where('pf_id', $pfId)->first();

        if (!$listing) {
            Log::warning('PropertyFinder webhook received for unknown listing', [
                'event' => $event,
                'pf_id' => $pfId,
            ]);
            return;
        }

        $this->processEvent($listing, $event, $data);

        Log::info('PropertyFinder webhook processed', [
            'event'      => $event,
            'listing_id' => $listing->id,
            'pf_id'      => $pfId,
            'new_status' => $listing->fresh()->status,
        ]);
    }

    private function processEvent(PropertyFinderListing $listing, string $event, array $data): void
    {
        switch ($event) {
            // ── Status transitions ──────────────────────────────────────────────

            case 'listing.published':
                $listing->update([
                    'status'       => PropertyFinderListing::STATUS_ACTIVE,
                    'published_at' => now(),
                ]);
                break;

            case 'listing.unpublished':
                $listing->update([
                    'status'       => PropertyFinderListing::STATUS_INACTIVE,
                    'published_at' => null,
                ]);
                break;

            case 'listing.under_review':
                $listing->update([
                    'status' => PropertyFinderListing::STATUS_UNDER_REVIEW,
                ]);
                break;

            case 'listing.rejected':
            case 'listing_price_issue':
            case 'listing_trakheesi_invalid':   // Dubai RERA trakheesi permit issue
            case 'adrec_sub_permit_required':    // Abu Dhabi ADREC sub-permit needed
                $existingDiffs = $listing->validation_diffs ?? [];
                $listing->update([
                    'status'           => PropertyFinderListing::STATUS_COMPLIANCE_FAILED,
                    'validation_diffs' => array_merge($existingDiffs, [
                        "Webhook [{$event}]: " . ($data['message'] ?? 'Check PropertyFinder Dashboard for details.')
                    ]),
                ]);
                break;

            case 'listing.expired':
                $listing->update([
                    'status'       => PropertyFinderListing::STATUS_INACTIVE,
                    'published_at' => null,
                ]);
                break;

            case 'listing.updated':
                // PF updated the listing — sync status
                if (isset($data['status'])) {
                    $listing->update(['status' => $data['status']]);
                }
                break;

            default:
                Log::info('PropertyFinder unhandled webhook event', [
                    'event' => $event,
                    'pf_id' => $listing->pf_id,
                ]);
                break;
        }
    }
}
