<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Project\SearchProjectsAction;
use App\Actions\Project\SearchLocationsAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectLocationResource;
use App\Http\Resources\ProjectResource;
use App\Models\OffplanProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class OffplanProjectController extends Controller
{
    /**
     * POST /api/v1/projects/search
     * GET  /api/v1/projects
     *
     * Unified search & listing with full filter/sort support.
     */
    public function search(Request $request, SearchProjectsAction $action): JsonResponse
    {
        $filters   = $request->isMethod('POST') ? $request->all() : $request->query();
        $paginated = $action->execute($filters);

        return $this->paginatedProjectResponse($paginated);
    }

    /**
     * GET /api/v1/projects/all
     *
     * Frontend-friendly paginated project list from the local database.
     */
    public function all(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $page = max((int) $request->query('page', 1), 1);

        $paginated = OffplanProject::with(['location', 'developer', 'images'])
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);

        return $this->paginatedProjectResponse($paginated);
    }

    /**
     * GET /api/v1/projects/filter-options
     *
     * Dynamic filter aggregation cached for 1 hour.
     */
    public function filterOptions(): JsonResponse
    {
        $options = \Illuminate\Support\Facades\Cache::remember('projects:filter-options', 3600, function () {
            // Aggregate active developers
            $developers = OffplanProject::whereNotNull('developer_id')
                ->join('offplan_developers', 'offplan_projects.developer_id', '=', 'offplan_developers.id')
                ->select('offplan_developers.source_id as id', 'offplan_developers.name', \Illuminate\Support\Facades\DB::raw('count(*) as project_count'))
                ->groupBy('offplan_developers.source_id', 'offplan_developers.name')
                ->orderByDesc('project_count')
                ->get();

            // Aggregate active communities/areas
            $areas = OffplanProject::whereNotNull('location_id')
                ->join('offplan_locations', 'offplan_projects.location_id', '=', 'offplan_locations.id')
                ->select('offplan_locations.community as name', \Illuminate\Support\Facades\DB::raw('count(*) as project_count'))
                ->whereNotNull('offplan_locations.community')
                ->groupBy('offplan_locations.community')
                ->orderByDesc('project_count')
                ->get();

            return [
                'developers' => $developers,
                'areas'      => $areas,
            ];
        });

        return response()->json([
            'data' => $options
        ]);
    }

    /**
     * GET /api/v1/projects/{id}
     *
     * Returns a single project by UUID or source_id.
     */
    public function show(string $id): JsonResponse
    {
        $project = OffplanProject::with(['location', 'developer', 'images'])
            ->where('id', $id)
            ->orWhere('source_id', $id)
            ->firstOrFail();

        return response()->json([
            'data' => new ProjectResource($project),
        ]);
    }

    /**
     * GET /api/v1/projects/locations
     * (kept for backward compatibility alongside the legacy /locations_search endpoint)
     */
    public function locations(Request $request, SearchLocationsAction $action): JsonResponse
    {
        $locations = $action->execute($request->query('query'));

        return response()->json([
            'results' => ProjectLocationResource::collection($locations),
        ]);
    }

    private function paginatedProjectResponse(LengthAwarePaginator $paginated): JsonResponse
    {
        return response()->json([
            'data' => ProjectResource::collection($paginated->getCollection()),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
            'links' => [
                'first' => $paginated->url(1),
                'last' => $paginated->url($paginated->lastPage()),
                'prev' => $paginated->previousPageUrl(),
                'next' => $paginated->nextPageUrl(),
            ],
        ]);
    }
}
