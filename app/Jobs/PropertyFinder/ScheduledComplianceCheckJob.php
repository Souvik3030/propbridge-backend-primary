<?php

declare(strict_types=1);

namespace App\Jobs\PropertyFinder;

use App\Actions\PropertyFinder\Compliance\CheckComplianceAction;
use App\Actions\PropertyFinder\Compliance\ValidateComplianceAction;
use App\Actions\PropertyFinder\Compliance\LogComplianceCheckAction;
use App\Models\PropertyFinderListing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScheduledComplianceCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private string $listingId
    ) {}

    public function handle(
        CheckComplianceAction $checkComplianceAction,
        ValidateComplianceAction $validateComplianceAction,
        LogComplianceCheckAction $logComplianceAction
    ): void {
        $listing = PropertyFinderListing::find($this->listingId);

        if (!$listing) {
            return;
        }

        try {
            // Check compliance
            $complianceResponse = $checkComplianceAction->execute(
                $listing->company,
                $listing->emirate,
                $listing->permit_number,
                $listing->license_number
            );

            // Validate
            $diffs = $validateComplianceAction->execute(
                $listing->emirate,
                $listing->toArray(),
                $complianceResponse
            );

            // Log
            $logComplianceAction->execute(
                $listing->company,
                $listing->agent,
                $listing->emirate,
                $listing->permit_number,
                $listing->license_number,
                $complianceResponse,
                $listing->toArray(),
                $diffs,
                'scheduled'
            );

            // Update listing snapshot
            $listing->update([
                'compliance_snapshot' => $complianceResponse,
                'validation_diffs' => $diffs,
            ]);

        } catch (\Exception $e) {
            Log::error('ScheduledComplianceCheckJob failed', [
                'listing_id' => $this->listingId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}