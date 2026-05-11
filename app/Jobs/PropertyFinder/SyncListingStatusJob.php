<?php

declare(strict_types=1);

namespace App\Jobs\PropertyFinder;

use App\Actions\PropertyFinder\Listing\SyncListingStatusAction;
use App\Models\PropertyFinderListing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncListingStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private string $listingId
    ) {}

    public function handle(SyncListingStatusAction $action): void
    {
        $listing = PropertyFinderListing::find($this->listingId);

        if (!$listing) {
            Log::warning('SyncListingStatusJob: Listing not found', [
                'listing_id' => $this->listingId,
            ]);
            return;
        }

        try {
            $action->execute($listing);
        } catch (\Exception $e) {
            Log::error('SyncListingStatusJob failed', [
                'listing_id' => $this->listingId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}