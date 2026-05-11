<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\PropertyFinder\Compliance\CheckComplianceAction;
use App\Actions\PropertyFinder\Compliance\LogComplianceCheckAction;
use App\Actions\PropertyFinder\Compliance\ValidateComplianceAction;
use App\Actions\PropertyFinder\Listing\CreateListingAction;
use App\Actions\PropertyFinder\Listing\PublishListingAction;
use App\Actions\PropertyFinder\Listing\UnpublishListingAction;
use App\Actions\PropertyFinder\Listing\UpdateListingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\PropertyFinder\StoreListingRequest;
use App\Http\Requests\PropertyFinder\UpdateListingRequest;
use App\Http\Resources\PropertyFinderListingResource;
use App\Models\PropertyFinderListing;
use App\Services\PropertyFinderApiClient;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PropertyFinderListingController extends Controller
{
    use AuthorizesRequests;

    /**
     * List all listings for the current company.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PropertyFinderListing::class);

        $listings = PropertyFinderListing::query()
            ->where('company_id', $request->user()->company_id)
            ->with(['agent'])
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('listing_type'), fn ($q, $type) => $q->where('listing_type', $type))
            ->when($request->input('emirate_id'), fn ($q, $emirate) => $q->where('emirate_id', $emirate))
            ->latest()
            ->paginate($request->input('per_page', 15));

        return PropertyFinderListingResource::collection($listings);
    }

    /**
     * Create a new listing (local draft + submit to PF API).
     */
    public function store(
        StoreListingRequest $request,
        CreateListingAction $action
    ): JsonResponse {
        $validated = $request->validated();
        
        // Find local user by pf_agent_id if integer provided, fallback to current user
        $agent = \App\Models\User::where('company_id', $request->user()->company_id)
            ->where('pf_agent_id', $validated['agent_id'])
            ->first() ?? $request->user();

        $listing = $action->execute(
            $validated,
            $request->user()->company,
            $agent
        );

        return response()->json([
            'message' => 'Listing created as draft. Run compliance check before publishing.',
            'listing' => new PropertyFinderListingResource($listing),
        ], 201);
    }

    /**
     * Show listing details.
     */
    public function show(PropertyFinderListing $listing): PropertyFinderListingResource
    {
        $this->authorize('view', $listing);

        return new PropertyFinderListingResource($listing->load(['agent', 'company']));
    }

    /**
     * Update a listing (partial PATCH — only changed fields).
     */
    public function update(
        UpdateListingRequest $request,
        PropertyFinderListing $listing,
        UpdateListingAction $action
    ): JsonResponse {
        $this->authorize('update', $listing);

        $updatedListing = $action->execute($listing, $request->validated());

        return response()->json([
            'message' => 'Listing updated successfully.',
            'listing' => new PropertyFinderListingResource($updatedListing),
        ]);
    }

    /**
     * Delete a listing (soft delete — local only).
     */
    public function destroy(PropertyFinderListing $listing): JsonResponse
    {
        $this->authorize('delete', $listing);

        $listing->delete();

        return response()->json(['message' => 'Listing deleted successfully.']);
    }

    /**
     * Run compliance check via PF API (GET /listings/{id}/compliance).
     * Must be called after creating the listing and before publishing.
     */
    public function compliance(
        PropertyFinderListing $listing,
        CheckComplianceAction $checkAction,
        LogComplianceCheckAction $logAction
    ): JsonResponse {
        $this->authorize('view', $listing);

        $updatedListing = $checkAction->execute($listing);

        // Log compliance check for audit
        $logAction->execute(
            $updatedListing->company,
            $updatedListing->agent,
            $updatedListing,
            $updatedListing->compliance_snapshot ?? [],
            'manual'
        );

        $snapshot = $updatedListing->compliance_snapshot ?? [];

        return response()->json([
            'listing_id'    => $updatedListing->id,
            'pf_id'         => $updatedListing->pf_id,
            'compliant'     => $updatedListing->isCompliant(),
            'can_publish'   => $updatedListing->canPublish(),
            'errors'        => $snapshot['errors'] ?? [],
            'warnings'      => $snapshot['warnings'] ?? [],
            'permit_valid'  => $snapshot['permit_valid'] ?? null,
            'permit_status' => $snapshot['permit_status'] ?? null,
            'checked_at'    => $updatedListing->last_compliance_check_at?->toIso8601String(),
            'listing'       => new PropertyFinderListingResource($updatedListing),
        ]);
    }

    /**
     * Run local pre-validation (no PF API call).
     * Use this to check before creating or after a compliance failure.
     */
    public function validate(
        PropertyFinderListing $listing,
        ValidateComplianceAction $validateAction
    ): JsonResponse {
        $this->authorize('view', $listing);

        $result = $validateAction->execute($listing);

        return response()->json([
            'listing_id' => $listing->id,
            'is_valid'   => $result['is_valid'],
            'errors'     => $result['errors'],
            'warnings'   => $result['warnings'],
        ]);
    }

    /**
     * Publish listing to PropertyFinder (POST /listings/{id}/publish).
     */
    public function publish(
        PropertyFinderListing $listing,
        PublishListingAction $action
    ): JsonResponse {
        $this->authorize('update', $listing);

        $publishedListing = $action->execute($listing);

        return response()->json([
            'message' => $publishedListing->isUnderReview()
                ? 'Listing submitted for review. Average review time: 2-4 hours.'
                : 'Listing is now live on PropertyFinder.',
            'listing' => new PropertyFinderListingResource($publishedListing),
        ]);
    }

    /**
     * Unpublish listing from PropertyFinder (POST /listings/{id}/unpublish).
     */
    public function unpublish(
        Request $request,
        PropertyFinderListing $listing,
        UnpublishListingAction $action
    ): JsonResponse {
        $this->authorize('update', $listing);

        $request->validate([
            'reason' => ['nullable', Rule::in(config('propertyfinder.unpublish_reasons', []))],
        ]);

        $unpublishedListing = $action->execute($listing, $request->input('reason'));

        return response()->json([
            'message' => 'Listing has been unpublished from PropertyFinder.',
            'listing' => new PropertyFinderListingResource($unpublishedListing),
        ]);
    }

    public function agents(Request $request, PropertyFinderApiClient $client): JsonResponse
    {
        $this->authorize('viewAny', PropertyFinderListing::class);

        $company = $request->user()->company;

        try {
            $response = $client->get($company, 'users', $request->all());
            return response()->json($response);
        } catch (\App\Exceptions\PropertyFinder\PropertyFinderException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], $e->getCode() ?: 400);
        }
    }

    /**
     * Return the static list of UAE Emirates from config.
     * No PF API call — serves as the first step of the location picker.
     * Frontend: pick an emirate → then call /locations?emirate_id={id}
     */
    public function emirates(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PropertyFinderListing::class);

        $emirates = collect(config('propertyfinder.emirates', []))
            ->map(fn ($e) => [
                'id'    => $e['id'],
                'key'   => $e['key'],
                'label' => $e['label'],
            ])
            ->values();

        return response()->json(['data' => $emirates]);
    }

    /**
     * Get emirate rules for frontend dynamic UI.
     */
    public function emirateRules(int $id, \App\Services\PropertyFinder\EmiratePermitService $service): JsonResponse
    {
        $config = config('propertyfinder.emirates.'.$id);
        
        if (!$config) {
            return response()->json(['error' => 'Invalid emirate'], 404);
        }

        return response()->json([
            'emirate_id'      => $id,
            'key'             => $config['key'],
            'label'           => $config['label'],
            'requires_permit' => $config['requires_permit'] ?? false,
            'regulatory_body' => $config['body'] ?? null,
            'permit_label'    => $service->getPermitLabel($id),
            'license_label'   => $id === 2 ? 'Broker License Number' : 'Real Estate Company License (ORN)',
            'exempt_areas'    => $config['exempt_areas'] ?? [],
        ]);
    }

    public function locations(Request $request, PropertyFinderApiClient $client): JsonResponse
    {
        $this->authorize('viewAny', PropertyFinderListing::class);

        $company = $request->user()->company;

        $params = $request->all();

        // Support 'query' as an alias for 'search' if frontend uses that
        if (!isset($params['search']) && isset($params['query'])) {
            $params['search'] = $params['query'];
        }

        // PropertyFinder API STRICTLY requires either 'search' (min 2 chars) or 'ids'.
        $hasSearch = isset($params['search']) && strlen($params['search']) >= 2;
        $hasIds = !empty($params['ids']);

        if (!$hasSearch && !$hasIds) {
            $emirateId = $params['emirate_id'] ?? null;
            $parentId = $params['parent_id'] ?? null;
            
            // If they provided a parent_id but no search, we fetch the parent's name from PF
            // and use it as the default search to populate the frontend's child dropdown!
            if ($parentId) {
                try {
                    $parentResp = $client->get($company, 'locations', ['ids' => $parentId]);
                    $parentName = $parentResp['data'][0]['name'] ?? null;
                    if ($parentName) {
                        $params['search'] = $parentName;
                    } else {
                        // Fallback if parent not found
                        $params['search'] = 'Al'; 
                    }
                } catch (\Exception $e) {
                    $params['search'] = 'Al';
                }
            }
            // If no parent_id, but emirate_id is provided, use the emirate's name as a default search
            elseif ($emirateId) {
                $emirates = config('propertyfinder.emirates', []);
                $emirateName = $emirates[$emirateId]['label'] ?? 'Al'; // Fallback to 'Al'
                $params['search'] = $emirateName;
            } else {
                // If neither search, ids, parent_id, nor emirate_id is provided, gracefully return empty
                return response()->json([]);
            }
        }

        try {
            $locationsResponse = $client->get($company, 'locations', $params);

            // Format data for the frontend UI logic
            $formattedLocations = collect($locationsResponse['data'] ?? [])->map(function ($item) {
                $city = '-';
                $community = '-';
                $subCommunity = '-';
                $building = '-';
                $pathNameParts = [];

                if (!empty($item['tree']) && is_array($item['tree'])) {
                    foreach ($item['tree'] as $node) {
                        $type = $node['type'] ?? '';
                        $name = $node['name'] ?? '';
                        $pathNameParts[] = $name;

                        if ($type === 'CITY' || $type === 'EMIRATE') {
                            $city = $name;
                        } elseif ($type === 'COMMUNITY') {
                            $community = $name;
                        } elseif ($type === 'SUBCOMMUNITY') {
                            $subCommunity = $name;
                        } elseif ($type === 'TOWER' || $type === 'BUILDING') {
                            $building = $name;
                        }
                    }
                } else {
                    $pathNameParts[] = $item['name'] ?? '';
                    $city = $item['name'] ?? '-';
                }

                $pathName = implode(' - ', $pathNameParts);

                // Try to extract coordinates
                $lat = $item['coordinates']['lat'] ?? $item['latitude'] ?? '';
                $lng = $item['coordinates']['lng'] ?? $item['coordinates']['lon'] ?? $item['longitude'] ?? '';

                // Emirate mapping (e.g., "Dubai" -> "dubai", "Abu Dhabi" -> "abu_dhabi")
                $uaeEmirate = strtolower(str_replace(' ', '_', $city));
                if ($uaeEmirate === '-') {
                    $uaeEmirate = '';
                }

                return [
                    'id'            => $item['id'] ?? '',
                    'location'      => $pathName,
                    'city'          => $city,
                    'community'     => $community,
                    'sub_community' => $subCommunity,
                    'building'      => $building,
                    'latitude'      => $lat,
                    'longitude'     => $lng,
                    'uae_emirate'   => $uaeEmirate,
                ];
            })->values()->toArray();

            return response()->json($formattedLocations);
        } catch (\App\Exceptions\PropertyFinder\PropertyFinderException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], $e->getCode() ?: 400);
        }
    }

    /**
     * Proxy to fetch compliance data BEFORE a listing is created.
     * GET /api/auth/propertyfinder/compliances/{permitNumber}
     */
    public function fetchCompliance(
        string $permitNumber,
        Request $request,
        PropertyFinderApiClient $client
    ): JsonResponse {
        $this->authorize('viewAny', PropertyFinderListing::class);
        $company = $request->user()->company;

        if (empty($company->license_number)) {
            return response()->json([
                'message' => 'Company license number (ORN) is not configured in company settings.'
            ], 400);
        }

        try {
            // PF Compliance API path: /v1/compliances/{permitNumber}/{licenseNumber}
            $path = "compliances/{$permitNumber}/{$company->license_number}";
            
            // Forward query parameters (like permitType=...)
            $response = $client->get($company, $path, $request->all());

            return response()->json($response);
        } catch (\App\Exceptions\PropertyFinder\PropertyFinderException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], $e->getCode() ?: 400);
        }
    }
}
