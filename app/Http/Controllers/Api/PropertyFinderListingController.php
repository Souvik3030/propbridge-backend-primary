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
use Illuminate\Support\Facades\Log;
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

   public function store(
    StoreListingRequest $request,
    CreateListingAction $action,
    PropertyFinderApiClient $client
): JsonResponse {
    $validated = $request->validated();
    $company   = $request->user()->company;

    $portalPf = $validated['portal_pf'] ?? true;

    if ($portalPf) {
        try {
            $liveResponse = $client->get($company, 'users');
            $liveAgents   = collect($liveResponse['data'] ?? []);

            if ($liveAgents->isEmpty()) {
                return response()->json([
                    'message' => 'No active agents found in your PropertyFinder account.',
                ], 422);
            }

            $requestedId = (int) ($validated['agent_id'] ?? 0);

            // Find the agent in PF's live list
            $matchedAgent = $liveAgents->first(function ($a) use ($requestedId) {
                if ((int) ($a['id'] ?? 0) === $requestedId) {
                    return true;
                }
                if ((int) ($a['publicProfile']['id'] ?? 0) === $requestedId) {
                    return true;
                }
                return false;
            });

            if (!$matchedAgent) {
                return response()->json([
                    'message'      => "Agent ID {$requestedId} is not valid for your PropertyFinder account.",
                    'valid_agents' => $liveAgents->map(fn($a) => [
                        'pf_id'            => $a['id'],
                        'pf_profile_id'    => $a['publicProfile']['id'] ?? null,
                        'name'             => $a['name'] ?? $a['fullName'] ?? '—',
                        'email'            => $a['email'] ?? '—',
                    ])->values(),
                ], 422);
            }

            $pfAgentId = (int) ($matchedAgent['publicProfile']['id'] ?? $matchedAgent['id']);
            $validated['agent_id'] = $pfAgentId;

            // Optional: persist pf_agent_id on the local user record if not set
            $localAgent = \App\Models\User::find($request->user()->id);
            if ($localAgent && empty($localAgent->pf_agent_id)) {
                $localAgent->update(['pf_agent_id' => $pfAgentId]);
            }

        } catch (\Exception $e) {
            Log::error('PF agent verification failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'PF Agent Verification Failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    $listing = $action->execute($validated, $company, $request->user());

    return response()->json([
        'message' => 'Listing created. Check validation_diffs for PF submission status.',
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
    // public function update(
    //     UpdateListingRequest $request,
    //     PropertyFinderListing $listing,
    //     UpdateListingAction $action,
    //     PropertyFinderApiClient $client
    // ): JsonResponse {
    //     $this->authorize('update', $listing);

    //     $validated = $request->validated();

    //     // If agent_id is in the PATCH, verify it against live PF agents
    //     if (isset($validated['agent_id'])) {
    //         try {
    //             $liveAgents = collect($client->get($listing->company, 'users')['data'] ?? []);
    //             $requestedId = (int) $validated['agent_id'];
                
    //             $matchedAgent = $liveAgents->first(fn($a) =>
    //                 (int) ($a['id'] ?? 0) === $requestedId ||
    //                 (int) ($a['publicProfile']['id'] ?? 0) === $requestedId
    //             );

    //             if (!$matchedAgent) {
    //                 return response()->json(['message' => "Agent ID {$requestedId} is not valid."], 422);
    //             }

    //             $validated['agent_id'] = (int) ($matchedAgent['publicProfile']['id'] ?? $matchedAgent['id']);

    //             // Optional: link current local user to this PF agent if not already linked
    //             $localUser = $request->user();
    //             if ($localUser && empty($localUser->pf_agent_id)) {
    //                 $localUser->update(['pf_agent_id' => $validated['agent_id']]);
    //             }
    //         } catch (\Exception $e) {
    //             Log::error('PF agent verification failed on update', ['error' => $e->getMessage()]);
    //             return response()->json(['message' => 'Agent verification failed: ' . $e->getMessage()], 500);
    //         }
    //     }

    //     $updatedListing = $action->execute($listing, $validated);

    //     return response()->json([
    //         'message' => 'Listing updated successfully.',
    //         'listing' => new PropertyFinderListingResource($updatedListing),
    //     ]);
    // }

    /**
     * Update a listing (partial PATCH — only changed fields).
     */
    public function update(
        UpdateListingRequest $request,
        PropertyFinderListing $listing,
        UpdateListingAction $action,
        PropertyFinderApiClient $client
    ): JsonResponse {
        $this->authorize('update', $listing);

        $validated = $request->validated();

        // 1. Handle frontend sending nested 'assignedTo.id' instead of 'agent_id'
        $requestedAgentId = $validated['agent_id'] ?? $request->input('assignedTo.id');

        // If an agent is specified in the PATCH, verify and map it to the LOCAL user
        $portalPf = $validated['portal_pf'] ?? $listing->portal_pf;
        
        if ($requestedAgentId && $portalPf) {
            try {
                $liveAgents = collect($client->get($listing->company, 'users')['data'] ?? []);
                $requestedId = (int) $requestedAgentId;
                
                $matchedAgent = $liveAgents->first(fn($a) =>
                    (int) ($a['id'] ?? 0) === $requestedId ||
                    (int) ($a['publicProfile']['id'] ?? 0) === $requestedId
                );

                if (!$matchedAgent) {
                    return response()->json(['message' => "Agent ID {$requestedId} is not valid."], 422);
                }

                $pfAgentId = (int) ($matchedAgent['publicProfile']['id'] ?? $matchedAgent['id']);

                // Find the local user associated with this PF agent ID
                $localUser = \App\Models\User::where('pf_agent_id', $pfAgentId)
                                             ->where('company_id', $listing->company_id)
                                             ->first();

                // IMPORTANT: The Action expects the LOCAL user ID, not the PF ID.
                if ($localUser) {
                    $validated['agent_id'] = $localUser->id;
                } else {
                    // Fallback: Link current user if no other user has this PF agent ID
                    $currentUser = $request->user();
                    if (empty($currentUser->pf_agent_id)) {
                        $currentUser->update(['pf_agent_id' => $pfAgentId]);
                    }
                    $validated['agent_id'] = $currentUser->id;
                }

                // Remove assignedTo so it doesn't pollute the validated array
                unset($validated['assignedTo']);

            } catch (\Exception $e) {
                Log::error('PF agent verification failed on update', ['error' => $e->getMessage()]);
                return response()->json(['message' => 'Agent verification failed: ' . $e->getMessage()], 500);
            }
        } elseif ($requestedAgentId) {
            // If we are NOT checking PF (portal_pf is false), just map assignedTo directly to agent_id 
            // assuming the frontend passed a valid local user ID or PF ID. 
            // It's safer to just set agent_id.
            unset($validated['assignedTo']);
        }

        // 2. Sanitize Payload for PATCH
        // Strip read-only or complex nested objects sent by the frontend. 
        // Passing these causes the PF API HMAC signature calculation to fail and throw the 403 Auth error.
        $readOnlyFields = ['media', 'location', 'createdBy', 'uaeEmirate', 'assignedTo', 'status'];
        foreach ($readOnlyFields as $field) {
            unset($validated[$field]);
        }

        // 3. If unpublishing, do it BEFORE the update to avoid PF API state machine conflict
        // (If we PUT changes to a Live listing, it enters 'validation_requested', which blocks unpublish)
        $newStatus = $request->input('status');
        $normalizedStatus = $newStatus ? strtolower($newStatus) : null;
        
        if ($normalizedStatus && in_array($normalizedStatus, ['unpublish', 'save as draft', 'archived'])) {
            if ($listing->portal_pf && $listing->pf_id && $listing->status === \App\Models\PropertyFinderListing::STATUS_ACTIVE) {
                try {
                    $listing = app(\App\Actions\PropertyFinder\Listing\UnpublishListingAction::class)->execute($listing);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Status update via save failed (unpublish)', ['error' => $e->getMessage()]);
                    return response()->json([
                        'message' => 'Failed to unpublish listing on Property Finder: ' . $e->getMessage(),
                        'listing' => new PropertyFinderListingResource($listing),
                    ], 422);
                }
            }
        }

        // 4. Perform the actual data update (PATCH/PUT)
        $listing = $action->execute($listing, $validated);

        // 5. Update local status for draft/archived if requested
        if ($normalizedStatus && in_array($normalizedStatus, ['save as draft', 'archived', 'draft'])) {
             $localStatusMap = [
                 'save as draft' => \App\Models\PropertyFinderListing::STATUS_DRAFT,
                 'draft'         => \App\Models\PropertyFinderListing::STATUS_DRAFT,
                 'archived'      => \App\Models\PropertyFinderListing::STATUS_ARCHIVED,
             ];
             if (isset($localStatusMap[$normalizedStatus])) {
                 $listing->update(['status' => $localStatusMap[$normalizedStatus]]);
             }
        }

        // 6. If publishing, do it AFTER the update so the latest data goes live
        if ($normalizedStatus && $normalizedStatus === 'live') {
            if ($listing->portal_pf && $listing->pf_id && !in_array($listing->status, [\App\Models\PropertyFinderListing::STATUS_ACTIVE, \App\Models\PropertyFinderListing::STATUS_UNDER_REVIEW])) {
                try {
                    $listing = app(\App\Actions\PropertyFinder\Listing\PublishListingAction::class)->execute($listing);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Status update via save failed (publish)', ['error' => $e->getMessage()]);
                    return response()->json([
                        'message' => 'Listing updated locally, but failed to publish on Property Finder: ' . $e->getMessage(),
                        'listing' => new PropertyFinderListingResource($listing),
                    ], 422);
                }
            }
        }

        return response()->json([
            'message' => 'Listing updated successfully.',
            'listing' => new PropertyFinderListingResource($listing),
        ]);
    }

    /**
     * Delete a listing (local and on Property Finder).
     */
    public function destroy(PropertyFinderListing $listing, PropertyFinderApiClient $client): JsonResponse
    {
        $this->authorize('delete', $listing);

        if ($listing->pf_id) {
            try {
                $client->delete($listing->company, 'listings/' . $listing->pf_id);
            } catch (\Exception $e) {
                Log::warning("Failed to delete listing {$listing->pf_id} from PF: " . $e->getMessage());
            }
        }

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

        if (!$listing->portal_pf) {
            return response()->json([
                'message' => 'PropertyFinder is not selected for this listing. Compliance checks are not available.'
            ], 400);
        }

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

        // Perform the validation check
        $result = $validateAction->execute($listing);

        return response()->json([
            'listing_id' => $listing->id,
            'is_valid'   => $result['is_valid'] ?? false,
            'errors'     => $result['errors'] ?? [],
            'warnings'   => $result['warnings'] ?? [],
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

        if (!$listing->portal_pf) {
            return response()->json([
                'message' => 'Cannot publish to PropertyFinder because it is not selected for this listing.'
            ], 400);
        }

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
