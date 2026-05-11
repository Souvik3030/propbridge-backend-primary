<?php

declare(strict_types=1);

namespace App\Actions\PropertyFinder\Listing;

use App\Exceptions\PropertyFinder\PropertyFinderException;
use App\Models\PropertyFinderListing;
use App\Services\PropertyFinderApiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Update an existing PropertyFinder listing.
 *
 * CORRECT ENDPOINT: PATCH /listings/{listing_id} (partial update)
 *
 * Notes:
 *  - Only send changed fields (PATCH = partial update)
 *  - images field is a FULL REPLACE — always include all images (old + new)
 *  - After significant changes, re-run compliance check
 *  - Can update listing in any status: draft, active, under_review
 */
class UpdateListingAction
{
    public function __construct(
        private ValidateDependentFieldsAction $validateDependentFields,
        private PropertyFinderApiClient $client
    ) {}

    /**
     * Update a listing: validate → update locally → PATCH to PF API.
     */
    public function execute(PropertyFinderListing $listing, array $data): PropertyFinderListing
    {
        // Validate dependent fields for the merged data (existing + new)
        $mergedData = array_merge($this->listingToArray($listing), $data);
        $this->validateDependentFields->execute($mergedData);

        return DB::transaction(function () use ($listing, $data, $mergedData) {

            // Update local record (only the changed fields)
            $listing->update($this->filterUpdateData($data));

            // If listing exists on PF, send PATCH to PF API
            if ($listing->pf_id) {
                try {
                    $pfPayload = $this->buildPfUpdatePayload($data);

                    if (!empty($pfPayload)) {
                        $this->client->patch(
                            $listing->company,
                            "listings/{$listing->pf_id}",
                            $pfPayload
                        );
                    }

                    Log::info('PropertyFinder listing updated on PF API', [
                        'listing_id' => $listing->id,
                        'pf_id'      => $listing->pf_id,
                        'fields_updated' => array_keys($pfPayload),
                    ]);

                } catch (\Throwable $e) {
                    Log::error('PropertyFinder listing PF API update failed', [
                        'listing_id' => $listing->id,
                        'pf_id'      => $listing->pf_id,
                        'error'      => $e->getMessage(),
                    ]);

                    throw new PropertyFinderException(
                        'Failed to update listing on PropertyFinder: ' . $e->getMessage(),
                        $e instanceof PropertyFinderException ? $e->getStatusCode() : 500,
                        $e
                    );
                }
            }

            Log::info('PropertyFinder listing updated', [
                'listing_id' => $listing->id,
                'fields'     => array_keys($data),
            ]);

            return $listing->fresh();
        });
    }

    /**
     * Map local DB field names to PF API v2 field names for PATCH.
     * Only include fields that were actually provided in the update request.
     */
    private function buildPfUpdatePayload(array $data): array
    {
        // PF API accepted fields for PATCH /listings/{id}
        $pfFieldMap = [
            'price'            => 'price',
            'description'      => 'description',
            'title'            => 'title',
            'agent_id'         => 'agent_id',  // Note: this is pf_agent_id on client side
            'permit_number'    => 'permit_number',
            'images'           => 'images',     // FULL REPLACE — caller must include all images
            'available_from'   => 'available_from',
            'price_on_request' => 'price_on_request',
            'furnished'        => 'furnished',
            'amenities'        => 'amenities',  // FULL REPLACE
            'rent_frequency'   => 'rent_frequency',
            'cheques'          => 'cheques',
            'floor_number'     => 'floor_number',
            'parking'          => 'parking',
            'bedrooms'         => 'bedrooms',
            'bathrooms'        => 'bathrooms',
            'building_name'    => 'building_name',
            'dld_permit_number' => 'dld_permit_number',
            'developer_name'   => 'developer_name',
            'project_name'     => 'project_name',
            'completion_date'  => 'completion_date',
            'virtual_tour'     => 'virtual_tour',
            'floor_plan'       => 'floor_plan',
            'hotel_name'       => 'hotel_name',
            'zoning_type'      => 'zoning_type',
            'fitted'           => 'fitted',
            'ownership_type'   => 'ownership_type',
        ];

        $payload = [];

        foreach ($pfFieldMap as $localKey => $pfKey) {
            if (array_key_exists($localKey, $data)) {
                $payload[$pfKey] = $data[$localKey];
            }
        }

        // Special: pf_agent_id on the user maps to agent_id in PF API
        if (isset($data['agent_pf_id'])) {
            $payload['agent_id'] = (int) $data['agent_pf_id'];
        }

        return $payload;
    }

    /**
     * Filter data for local DB update — only known fillable fields.
     */
    private function filterUpdateData(array $data): array
    {
        $allowed = [
            'listing_type', 'property_type', 'category', 'location_id',
            'permit_number', 'license_number', 'building_name', 'dld_permit_number',
            'title_en', 'description_en', 'title_ar', 'description_ar',
            'price', 'price_on_request', 'ownership_type',
            'size', 'plot_size_sqft',
            'bedrooms', 'bathrooms', 'floor_number', 'number_of_floors',
            'private_pool', 'hotel_name', 'parking', 'furnished',
            'rent_frequency', 'cheques', 'available_from',
            'fitted', 'zoning_type',
            'developer_name', 'project_name', 'completion_date', 'payment_plan',
            'images', 'amenities', 'virtual_tour', 'floor_plan',
            'agent_id',
        ];

        return array_intersect_key($data, array_flip($allowed));
    }

    /**
     * Convert listing model to array for merge validation.
     */
    private function listingToArray(PropertyFinderListing $listing): array
    {
        return [
            'listing_type'  => $listing->listing_type ?? $listing->purpose,
            'property_type' => $listing->property_type ?? $listing->type,
            'category'      => $listing->category,
            'emirate_id'    => $listing->emirate_id,
            'permit_number' => $listing->permit_number,
            'building_name' => $listing->building_name,
            'dld_permit_number' => $listing->dld_permit_number,
            'price'         => $listing->price,
            'size_sqft'     => $listing->size,
            'bedrooms'      => $listing->bedrooms,
            'bathrooms'     => $listing->bathrooms,
            'rent_frequency' => $listing->rent_frequency,
            'ownership_type' => $listing->ownership_type,
            'developer_name' => $listing->developer_name,
            'project_name'  => $listing->project_name,
            'completion_date' => $listing->completion_date,
            'images'        => $listing->images ?? [],
            'title'         => $listing->title_en,
            'description'   => $listing->description_en,
            'plot_size_sqft' => $listing->plot_size_sqft,
            'floor_number'  => $listing->floor_number,
            'hotel_name'    => $listing->hotel_name,
            'zoning_type'   => $listing->zoning_type,
            'fitted'        => $listing->fitted,
        ];
    }
}