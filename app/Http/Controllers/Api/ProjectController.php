<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Project\SearchProjectsAction;
use App\Actions\Project\SearchLocationsAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectLocationResource;
use App\Http\Resources\ProjectResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Legacy controller kept for backward compatibility with
 * POST /new_projects_search  and  GET /locations_search
 *
 * New clients should use the v1 endpoints via OffplanProjectController.
 */
class ProjectController extends Controller
{
    public function search(Request $request, SearchProjectsAction $action)
    {
        $paginatedProjects = $action->execute($request->all());
        return ProjectResource::collection($paginatedProjects);
    }

    public function locations(Request $request, SearchLocationsAction $action): JsonResponse
    {
        $locations = $action->execute($request->query('query'));

        return response()->json([
            'results' => ProjectLocationResource::collection($locations)
        ]);
    }
}