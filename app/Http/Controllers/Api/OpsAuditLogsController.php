<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OpsAuditLogsController extends Controller
{
    /**
     * GET /api/v1/ops/audit-logs
     *
     * Returns paginated, searchable, and filtered audit trail logs for the company.
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

        $query = AuditLog::with('user:id,name,email')
            ->where('company_id', $companyId)
            ->latest('created_at');

        // Filter by specific action (e.g. user.suspended, listing.created)
        if ($request->filled('action')) {
            $query->where('action', $request->query('action'));
        }

        // Filter by resource type (e.g. App\Models\PropertyFinderListing)
        if ($request->filled('resource_type')) {
            $query->where('resource_type', $request->query('resource_type'));
        }

        // Filter by broker/agent user
        if ($request->filled('agent_id')) {
            $query->where('user_id', $request->query('agent_id'));
        }

        // Search text (searches user name or changes payload)
        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', $search)
                  ->orWhere('resource_id', 'like', $search)
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', $search)
                        ->orWhere('email', 'like', $search);
                  });
            });
        }

        $paginatedLogs = $query->paginate($perPage, ['*'], 'page', $page);

        // Map logs into beautiful UI-consumable presentation models
        $formattedLogs = $paginatedLogs->getCollection()->map(function (AuditLog $log) {
            return [
                'id' => $log->id,
                'action' => $log->action,
                'resource_type' => $log->resource_type,
                'resource_id' => $log->resource_id,
                'changes' => $log->changes ?? [],
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at?->toISOString(),
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ] : [
                    'id' => 'system',
                    'name' => 'System Engine',
                    'email' => 'system@propbridge.com',
                ]
            ];
        });

        // Get unique listing of actions logged to populate filter dropdowns in UI
        $availableActions = AuditLog::where('company_id', $companyId)
            ->distinct()
            ->pluck('action');

        return response()->json([
            'data' => $formattedLogs,
            'filters' => [
                'actions' => $availableActions,
            ],
            'meta' => [
                'current_page' => $paginatedLogs->currentPage(),
                'per_page' => $paginatedLogs->perPage(),
                'total' => $paginatedLogs->total(),
                'last_page' => $paginatedLogs->lastPage(),
                'from' => $paginatedLogs->firstItem(),
                'to' => $paginatedLogs->lastItem(),
            ],
            'links' => [
                'first' => $paginatedLogs->url(1),
                'last' => $paginatedLogs->url($paginatedLogs->lastPage()),
                'prev' => $paginatedLogs->previousPageUrl(),
                'next' => $paginatedLogs->nextPageUrl(),
            ]
        ]);
    }

    /**
     * GET /api/v1/ops/audit-logs/export
     *
     * Streams filtered audit logs as a high-performance CSV file.
     */
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Unauthorized');
        }

        $companyId = $user->company_id;

        $query = AuditLog::with('user:id,name,email')
            ->where('company_id', $companyId)
            ->latest('created_at');

        if ($request->filled('action')) {
            $query->where('action', $request->query('action'));
        }

        if ($request->filled('resource_type')) {
            $query->where('resource_type', $request->query('resource_type'));
        }

        if ($request->filled('agent_id')) {
            $query->where('user_id', $request->query('agent_id'));
        }

        $filename = 'audit-log-export-' . now()->format('Y-m-d-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($query) {
            $file = fopen('php://output', 'w');
            
            // Add UTF-8 BOM for proper Excel formatting
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Write Headers
            fputcsv($file, [
                'Log ID',
                'Timestamp',
                'Actor Name',
                'Actor Email',
                'Action Event',
                'Resource Type',
                'Resource ID',
                'Changes / Details',
                'IP Address',
                'User Agent'
            ]);

            // Stream DB query in chunks of 500 rows to limit RAM consumption
            $query->chunk(500, function ($logs) use ($file) {
                foreach ($logs as $log) {
                    fputcsv($file, [
                        $log->id,
                        $log->created_at?->toISOString() ?? 'N/A',
                        $log->user->name ?? 'System Engine',
                        $log->user->email ?? 'system@propbridge.com',
                        $log->action,
                        $log->resource_type,
                        $log->resource_id,
                        json_encode($log->changes ?? []),
                        $log->ip_address,
                        $log->user_agent
                    ]);
                }
            });

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
