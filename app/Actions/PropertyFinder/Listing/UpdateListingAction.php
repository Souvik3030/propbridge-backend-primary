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

            // Process image uploads if files are provided
            if (isset($data['images'])) {
                $data['images'] = $this->uploadImagesIfFiles($data['images']);
            }

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
        $payload = [];

        // 1. Handle price object if price is provided
        if (isset($data['price'])) {
            $payload['price'] = [
                'value'    => (float) $data['price'],
                'currency' => $data['price_currency'] ?? 'AED',
            ];
            if (isset($data['price_on_request'])) {
                $payload['price']['on_request'] = (bool) $data['price_on_request'];
            }
        } elseif (isset($data['price_on_request'])) {
             // Only price_on_request changed? Still need it inside price object
             $payload['price'] = [
                 'on_request' => (bool) $data['price_on_request']
             ];
        }

        // 2. Handle title object
        if (isset($data['title_en']) || isset($data['title'])) {
            $payload['title'] = [
                'en' => $data['title_en'] ?? $data['title']
            ];
            if (isset($data['title_ar'])) {
                $payload['title']['ar'] = $data['title_ar'];
            }
        } elseif (isset($data['title_ar'])) {
            $payload['title'] = ['ar' => $data['title_ar']];
        }

        // 3. Handle description object
        if (isset($data['description_en']) || isset($data['description'])) {
            $payload['description'] = [
                'en' => $data['description_en'] ?? $data['description']
            ];
            if (isset($data['description_ar'])) {
                $payload['description']['ar'] = $data['description_ar'];
            }
        } elseif (isset($data['description_ar'])) {
            $payload['description'] = ['ar' => $data['description_ar']];
        }

        // 4. Handle all other fields
        $otherFields = [
            'agent_id'         => 'agent_id',
            'permit_number'    => 'permit_number',
            'images'           => 'images',
            'available_from'   => 'available_from',
            'furnished'        => 'furnished',
            'amenities'        => 'amenities',
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
            'listing_type'     => 'type',
            'property_type'    => 'property_type',
            'size'             => 'size_sqft',
        ];

        foreach ($otherFields as $localKey => $pfKey) {
            if (array_key_exists($localKey, $data)) {
                $value = $data[$localKey];

                // Cast to string if needed
                if (in_array($pfKey, ['bedrooms', 'bathrooms'])) {
                    $value = ($value !== null) ? (string) $value : null;
                }

                $payload[$pfKey] = $value;
            }
        }

        // Special: pf_agent_id or agent_pf_id mapping
        if (isset($data['agent_pf_id'])) {
            $payload['agent_id'] = (int) $data['agent_pf_id'];
        }

        return array_filter($payload, fn($v) => $v !== null);
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

    /**
     * Detect any UploadedFile objects in the images array and upload them to S3.
     */
    private function uploadImagesIfFiles(array $images): array
    {
        $urls = [];
        foreach ($images as $image) {
            if ($image instanceof \Illuminate\Http\UploadedFile) {
                $filename = \Illuminate\Support\Str::uuid()->toString() . '.' . $image->getClientOriginalExtension();
                $path = $image->storeAs('listings', $filename, ['disk' => 's3']);
                $urls[] = \Illuminate\Support\Facades\Storage::disk('s3')->url($path);
            } else {
                $urls[] = (string) $image;
            }
        }
        return $urls;
    }
}