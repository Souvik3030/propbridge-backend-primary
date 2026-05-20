<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PropertyFinderComplianceLog;
use App\Models\PropertyFinderListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpsComplianceController extends Controller
{
    /**
     * GET /api/v1/ops/compliance
     *
     * Operational compliance dashboard scoped to the authenticated user's company.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $companyId = $user->company_id;
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $page = max((int) $request->query('page', 1), 1);

        $baseListings = PropertyFinderListing::forCompany($companyId);

        $total = (clone $baseListings)->count();
        $passed = (clone $baseListings)->whereIn('compliance_status', ['passed', 'exempt'])->count();
        $failed = (clone $baseListings)
            ->where(function ($query) {
                $query->where('compliance_status', 'failed')
                    ->orWhere('status', PropertyFinderListing::STATUS_COMPLIANCE_FAILED);
            })
            ->count();
        $pending = max(0, $total - $passed - $failed);
        $canPublish = (clone $baseListings)->where('can_publish', true)->count();

        $issues = PropertyFinderListing::forCompany($companyId)
            ->with('agent')
            ->where(function ($query) {
                $query->where('compliance_status', 'failed')
                    ->orWhere('status', PropertyFinderListing::STATUS_COMPLIANCE_FAILED)
                    ->orWhereNotNull('validation_diffs');
            })
            ->latest('updated_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $recentLogs = PropertyFinderComplianceLog::with(['agent', 'listing'])
            ->where('company_id', $companyId)
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (PropertyFinderComplianceLog $log) => [
                'id' => $log->id,
                'status' => $log->status,
                'source' => $log->source,
                'permit_number' => $log->permit_number,
                'license_number' => $log->license_number,
                'emirate' => $log->emirate,
                'agent' => $log->agent ? [
                    'id' => $log->agent->id,
                    'name' => $log->agent->name,
                ] : null,
                'listing' => $log->listing ? [
                    'id' => $log->listing->id,
                    'reference' => $log->listing->pf_reference,
                    'title' => $log->listing->title_en,
                ] : null,
                'diffs' => $log->diffs ?? [],
                'checked_at' => $log->created_at?->toISOString(),
            ])
            ->values();

        return response()->json([
            'summary' => [
                'total' => $total,
                'passed' => $passed,
                'failed' => $failed,
                'pending' => $pending,
                'can_publish' => $canPublish,
                'compliance_rate' => $total > 0 ? round(($passed / $total) * 100, 1) : 0,
            ],
            'data' => $issues->getCollection()->map(fn (PropertyFinderListing $listing) => [
                'id' => $listing->id,
                'reference' => $listing->pf_reference,
                'title' => $listing->title_en,
                'status' => $listing->status,
                'compliance_status' => $listing->compliance_status,
                'can_publish' => (bool) $listing->can_publish,
                'permit_number' => $listing->permit_number,
                'license_number' => $listing->license_number,
                'emirate' => $listing->uae_emirate ?: $listing->emirate,
                'agent' => $listing->agent ? [
                    'id' => $listing->agent->id,
                    'name' => $listing->agent->name,
                ] : null,
                'issues' => $this->extractIssues($listing),
                'last_checked_at' => $listing->last_compliance_check_at?->toISOString(),
                'updated_at' => $listing->updated_at?->toISOString(),
            ])->values(),
            'recent_logs' => $recentLogs,
            'meta' => [
                'current_page' => $issues->currentPage(),
                'per_page' => $issues->perPage(),
                'total' => $issues->total(),
                'last_page' => $issues->lastPage(),
                'from' => $issues->firstItem(),
                'to' => $issues->lastItem(),
            ],
            'links' => [
                'first' => $issues->url(1),
                'last' => $issues->url($issues->lastPage()),
                'prev' => $issues->previousPageUrl(),
                'next' => $issues->nextPageUrl(),
            ],
        ]);
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
}
