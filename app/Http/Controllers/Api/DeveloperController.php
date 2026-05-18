<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeveloperResource;
use App\Models\OffplanDeveloper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeveloperController extends Controller
{
    /**
     * GET /api/v1/developers
     *
     * List all developers with optional search.
     */
    public function index(Request $request): JsonResponse
    {
        $query = OffplanDeveloper::query();

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $sortBy = $request->query('sort_by', 'name');
        match ($sortBy) {
            'project_count' => $query->orderByDesc('project_count'),
            default         => $query->orderBy('name'),
        };

        $developers = $query->paginate((int) ($request->query('per_page', 30)));

        return response()->json([
            'data' => DeveloperResource::collection($developers->items()),
            'meta' => [
                'current_page' => $developers->currentPage(),
                'per_page'     => $developers->perPage(),
                'total'        => $developers->total(),
                'last_page'    => $developers->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/developers/{id}
     *
     * Get developer details + their projects.
     */
    public function show(string $id): JsonResponse
    {
        $developer = OffplanDeveloper::with(['projects.location', 'projects.images'])
            ->where('id', $id)
            ->orWhere('source_id', $id)
            ->firstOrFail();

        return response()->json([
            'data' => new DeveloperResource($developer),
        ]);
    }
}
