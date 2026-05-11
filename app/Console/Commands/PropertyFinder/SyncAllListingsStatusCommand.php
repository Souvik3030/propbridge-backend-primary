<?php

declare(strict_types=1);

namespace App\Console\Commands\PropertyFinder;

use App\Actions\PropertyFinder\Listing\SyncListingStatusAction;
use App\Models\PropertyFinderListing;
use Illuminate\Console\Command;

class SyncAllListingsStatusCommand extends Command
{
    protected $signature = 'pf:sync-statuses {--company= : Filter by company UUID}';
    protected $description = 'Sync status of all live PF listings from PropertyFinder API';

    public function handle(SyncListingStatusAction $syncAction): int
    {
        // FIX: was using 'pf_listing_id' (doesn't exist) — correct field is 'pf_id'
        // FIX: status values updated to use PF API v2 constants (active/under_review, not published)
        $query = PropertyFinderListing::query()
            ->whereNotNull('pf_id')
            ->whereIn('status', [
                PropertyFinderListing::STATUS_ACTIVE,
                PropertyFinderListing::STATUS_UNDER_REVIEW,
                PropertyFinderListing::STATUS_DRAFT,
            ]);

        if ($this->option('company')) {
            $query->where('company_id', $this->option('company'));
        }

        $listings = $query->with('company')->get();

        $this->info("Found {$listings->count()} listings to sync");

        $synced  = 0;
        $failed  = 0;

        foreach ($listings as $listing) {
            try {
                $syncAction->execute($listing);
                $this->comment("  ✓ Synced listing {$listing->id} (pf_id: {$listing->pf_id})");
                $synced++;
            } catch (\Throwable $e) {
                $this->error("  ✗ Failed listing {$listing->id}: " . $e->getMessage());
                $failed++;
            }
        }

        $this->info("Done. Synced: {$synced}, Failed: {$failed}");

        return Command::SUCCESS;
    }
}