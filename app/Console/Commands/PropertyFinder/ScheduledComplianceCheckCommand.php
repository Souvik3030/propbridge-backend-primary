<?php

declare(strict_types=1);

namespace App\Console\Commands\PropertyFinder;

use App\Actions\PropertyFinder\Compliance\CheckComplianceAction;
use App\Actions\PropertyFinder\Compliance\LogComplianceCheckAction;
use App\Models\PropertyFinderListing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled compliance check for active listings.
 *
 * Runs GET /listings/{id}/compliance on PF API for all active/under_review
 * listings to detect permit expirations or rule changes.
 */
class ScheduledComplianceCheckCommand extends Command
{
    protected $signature = 'propertyfinder:compliance-check {--company= : Filter by company UUID}';
    protected $description = 'Re-run PF API compliance checks for active listings to detect permit expirations';

    public function handle(
        CheckComplianceAction $checkAction,
        LogComplianceCheckAction $logAction
    ): int {
        $this->info('Starting scheduled PropertyFinder compliance check...');

        // Only check listings that exist on PF and are active or under review
        $query = PropertyFinderListing::query()
            ->whereNotNull('pf_id')
            ->whereIn('status', [
                PropertyFinderListing::STATUS_ACTIVE,
                PropertyFinderListing::STATUS_UNDER_REVIEW,
                PropertyFinderListing::STATUS_COMPLIANCE_FAILED,
            ])
            ->whereNull('deleted_at')
            ->with(['company', 'agent']);

        if ($this->option('company')) {
            $query->where('company_id', $this->option('company'));
        }

        $listings = $query->get();

        $this->info("Found {$listings->count()} listings to check.");

        $compliant   = 0;
        $nonCompliant = 0;
        $errored     = 0;

        foreach ($listings as $listing) {
            try {
                $this->comment("Checking listing ID: {$listing->id} (pf_id: {$listing->pf_id})");

                $updatedListing = $checkAction->execute($listing);

                // Log to compliance audit table
                $logAction->execute(
                    $updatedListing->company,
                    $updatedListing->agent,
                    $updatedListing,
                    $updatedListing->compliance_snapshot ?? [],
                    'schedule'
                );

                if ($updatedListing->isCompliant()) {
                    $this->info("  ✓ Listing {$listing->id} is compliant.");
                    $compliant++;
                } else {
                    $errors = $updatedListing->compliance_snapshot['errors'] ?? [];
                    $this->warn("  ✗ Listing {$listing->id} has compliance issues: " . implode(', ', $errors));
                    $nonCompliant++;
                }

            } catch (\Throwable $e) {
                $this->error("  Error checking listing {$listing->id}: " . $e->getMessage());
                Log::error('Scheduled compliance check failed', [
                    'listing_id' => $listing->id,
                    'pf_id'      => $listing->pf_id,
                    'error'      => $e->getMessage(),
                ]);
                $errored++;
            }
        }

        $this->info("Compliance check complete. Compliant: {$compliant}, Issues: {$nonCompliant}, Errors: {$errored}");

        return Command::SUCCESS;
    }
}