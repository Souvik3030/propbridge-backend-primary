<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PropertyFinderComplianceLog;
use App\Models\PropertyFinderListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class OpsAlertsController extends Controller
{
    /**
     * GET /api/v1/ops/alerts
     *
     * Derived operational alerts scoped to the authenticated user's company.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $company = $user->company;
        $companyId = $user->company_id;
        $limit = min(max((int) $request->query('limit', 50), 1), 100);

        $alerts = collect();

        if ($company && !$company->is_active) {
            $alerts->push([
                'id' => 'company-inactive',
                'type' => 'company',
                'severity' => 'critical',
                'title' => 'Company account is inactive',
                'message' => 'Publishing and operational workflows may be blocked until the company is reactivated.',
                'status' => 'open',
                'created_at' => $company->updated_at?->toISOString(),
                'meta' => [
                    'company_id' => $company->id,
                    'company_name' => $company->name,
                ],
            ]);
        }

        $failedListings = PropertyFinderListing::forCompany($companyId)
            ->with('agent')
            ->where(function ($query) {
                $query->where('compliance_status', 'failed')
                    ->orWhere('status', PropertyFinderListing::STATUS_COMPLIANCE_FAILED);
            })
            ->latest('updated_at')
            ->limit($limit)
            ->get();

        foreach ($failedListings as $listing) {
            $alerts->push($this->listingAlert(
                $listing,
                'compliance_failed',
                'critical',
                'Listing failed compliance',
                'Resolve the compliance issues before publishing this listing.'
            ));
        }

        $pendingListings = PropertyFinderListing::forCompany($companyId)
            ->with('agent')
            ->whereIn('status', [
                PropertyFinderListing::STATUS_DRAFT,
                PropertyFinderListing::STATUS_UNDER_REVIEW,
            ])
            ->latest('updated_at')
            ->limit($limit)
            ->get();

        foreach ($pendingListings as $listing) {
            $alerts->push($this->listingAlert(
                $listing,
                'pending_approval',
                'warning',
                'Listing pending approval',
                'This listing is not live yet and may need review or portal approval.'
            ));
        }

        $staleListings = PropertyFinderListing::forCompany($companyId)
            ->with('agent')
            ->whereNotNull('permit_number')
            ->where(function ($query) {
                $query->whereNull('last_compliance_check_at')
                    ->orWhere('last_compliance_check_at', '<', now()->subDays(7));
            })
            ->latest('updated_at')
            ->limit($limit)
            ->get();

        foreach ($staleListings as $listing) {
            $alerts->push($this->listingAlert(
                $listing,
                'stale_compliance',
                'info',
                'Compliance check is stale',
                'Run a fresh compliance check to keep permit data current.'
            ));
        }

        $warningLogs = PropertyFinderComplianceLog::with(['listing', 'agent'])
            ->where('company_id', $companyId)
            ->whereIn('status', ['warning', 'failed'])
            ->latest()
            ->limit($limit)
            ->get();

        foreach ($warningLogs as $log) {
            $alerts->push([
                'id' => 'compliance-log-' . $log->id,
                'type' => 'compliance_log',
                'severity' => $log->status === 'failed' ? 'critical' : 'warning',
                'title' => $log->status === 'failed' ? 'Compliance check failed' : 'Compliance warning',
                'message' => $this->formatDiffMessage($log->diffs ?? []),
                'status' => 'open',
                'created_at' => $log->created_at?->toISOString(),
                'meta' => [
                    'log_id' => $log->id,
                    'listing_id' => $log->listing?->id,
                    'reference' => $log->listing?->pf_reference,
                    'permit_number' => $log->permit_number,
                    'agent_name' => $log->agent?->name,
                    'diffs' => $log->diffs ?? [],
                ],
            ]);
        }

        $sortedAlerts = $this->sortAlerts($alerts)
            ->unique('id')
            ->take($limit)
            ->values();

        return response()->json([
            'data' => $sortedAlerts,
            'summary' => [
                'total' => $sortedAlerts->count(),
                'critical' => $sortedAlerts->where('severity', 'critical')->count(),
                'warning' => $sortedAlerts->where('severity', 'warning')->count(),
                'info' => $sortedAlerts->where('severity', 'info')->count(),
            ],
            'meta' => [
                'limit' => $limit,
            ],
        ]);
    }

    private function listingAlert(
        PropertyFinderListing $listing,
        string $type,
        string $severity,
        string $title,
        string $message
    ): array {
        return [
            'id' => $type . '-' . $listing->id,
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'status' => 'open',
            'created_at' => $listing->updated_at?->toISOString(),
            'meta' => [
                'listing_id' => $listing->id,
                'reference' => $listing->pf_reference,
                'title' => $listing->title_en,
                'listing_status' => $listing->status,
                'compliance_status' => $listing->compliance_status,
                'permit_number' => $listing->permit_number,
                'agent_name' => $listing->agent?->name,
                'issues' => $this->extractIssues($listing),
            ],
        ];
    }

    private function extractIssues(PropertyFinderListing $listing): array
    {
        $snapshot = $listing->compliance_snapshot ?? [];
        $validationDiffs = $listing->validation_diffs ?? [];

        return array_values(array_filter([
            ...((array) ($snapshot['errors'] ?? [])),
            ...((array) ($snapshot['warnings'] ?? [])),
            ...((array) $validationDiffs),
        ]));
    }

    private function formatDiffMessage(array $diffs): string
    {
        if (empty($diffs)) {
            return 'Review the latest compliance check details.';
        }

        return implode(' | ', array_slice(array_map('strval', $diffs), 0, 3));
    }

    private function sortAlerts(Collection $alerts): Collection
    {
        $severityRank = [
            'critical' => 0,
            'warning' => 1,
            'info' => 2,
        ];

        return $alerts->sortBy([
            fn (array $alert) => $severityRank[$alert['severity']] ?? 99,
            fn (array $alert) => -strtotime($alert['created_at'] ?? 'now'),
        ]);
    }
}
