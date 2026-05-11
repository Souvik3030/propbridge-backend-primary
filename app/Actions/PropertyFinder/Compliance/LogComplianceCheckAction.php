<?php

declare(strict_types=1);

namespace App\Actions\PropertyFinder\Compliance;

use App\Models\Company;
use App\Models\User;
use App\Models\PropertyFinderComplianceLog;
use App\Models\PropertyFinderListing;

/**
 * Log a compliance check result for audit and tracking.
 *
 * Used after GET /listings/{id}/compliance to persist the result.
 */
class LogComplianceCheckAction
{
    /**
     * Log compliance check results.
     *
     * @param  Company               $company
     * @param  User|null             $agent
     * @param  PropertyFinderListing $listing
     * @param  array                 $complianceResponse  Full response from PF API
     * @param  string                $source              pre_save|update|manual|schedule
     */
    public function execute(
        Company $company,
        ?User $agent,
        PropertyFinderListing $listing,
        array $complianceResponse,
        string $source
    ): PropertyFinderComplianceLog {
        $isCompliant  = (bool) ($complianceResponse['compliant'] ?? false);
        $errors       = $complianceResponse['errors'] ?? [];
        $warnings     = $complianceResponse['warnings'] ?? [];
        $permitStatus = $complianceResponse['permit_status'] ?? null;

        return PropertyFinderComplianceLog::create([
            'company_id'                  => $company->id,
            'agent_id'                    => $agent?->id,
            'property_finder_listing_id'  => $listing->id,
            'emirate'                     => $listing->emirate ?? '',
            'permit_number'               => $listing->permit_number ?? '',
            'license_number'              => $listing->license_number,
            'status'                      => $isCompliant ? 'success' : (empty($errors) ? 'warning' : 'failed'),
            'response_body'               => $complianceResponse,
            'diffs'                       => array_merge($errors, $warnings),
            'source'                      => $source,
        ]);
    }
}