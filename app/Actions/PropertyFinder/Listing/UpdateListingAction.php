<?php

declare(strict_types=1);

namespace App\Actions\PropertyFinder\Listing;

use App\Exceptions\PropertyFinder\PropertyFinderException;
use App\Models\PropertyFinderListing;
use App\Models\User;
use App\Services\PropertyFinderApiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Update an existing PropertyFinder listing.
 *
 * Uses PUT /listings/{listing_id} — Property Finder's PATCH endpoint rejects
 * Bearer JWT auth with an HMAC signature error for all accounts; PUT works correctly.
 *
 * PUT = full replace, so we merge existing listing data with incoming changes
 * before building the payload to satisfy all required fields.
 */
class UpdateListingAction
{
    public function __construct(
        private ValidateDependentFieldsAction $validateDependentFields,
        private PropertyFinderApiClient $client
    ) {}

    /**
     * Update a listing: validate → update locally → PUT to PF API (full replace).
     */
    public function execute(PropertyFinderListing $listing, array $data): PropertyFinderListing
    {
        // Validate dependent fields for the merged data (existing + new)
        $mergedData = array_merge($this->listingToArray($listing), $data);
        $this->validateDependentFields->execute($mergedData);

        $listing->loadMissing('company');
        $company = $listing->company()->firstOrFail();
        $creds   = $company->getPropertyFinderCredentials();
        Log::debug('UpdateListingAction company creds check', [
            'company_id' => $company->id,
            'has_key'    => !empty($creds['api_key'] ?? $creds['client_id'] ?? null),
            'has_secret' => !empty($creds['api_secret'] ?? $creds['client_secret'] ?? null),
        ]);

        return DB::transaction(function () use ($listing, $data, $mergedData) {

            // Process image uploads if files are provided
            if (isset($data['images'])) {
                $data['images'] = $this->uploadImagesIfFiles($data['images']);
            }

            // Update local record (only the changed fields)
            $localData = $this->filterUpdateData($data);

            // Resolve PF Agent ID (integer) to local User ID (UUID) for the local DB record.
            // The controller sends the local user UUID as agent_id. We confirm it is valid.
            // We also derive the PF integer agent ID here so the PATCH payload uses the
            // correct integer, not a UUID cast to int (which yields 0 → 403 Forbidden).
            $pfAgentId = null;
            if (isset($localData['agent_id'])) {
                $providedId = $localData['agent_id'];

                // 1. Try finding the local user directly (UUID passed from controller)
                $localUser = User::find($providedId);

                // 2. Fallback: look up by pf_agent_id integer
                if (!$localUser) {
                    $localUser = User::where('pf_agent_id', $providedId)->first();
                }

                if ($localUser) {
                    $localData['agent_id'] = $localUser->id;          // store local UUID in DB
                    $pfAgentId             = (int) $localUser->pf_agent_id; // integer for PF API
                    if (!$pfAgentId) {
                        Log::warning('UpdateListingAction: Local user found but pf_agent_id is empty.', [
                            'user_id'    => $localUser->id,
                            'listing_id' => $listing->id,
                        ]);
                        $pfAgentId = null;
                    }
                } else {
                    Log::warning('UpdateListingAction: Could not resolve agent_id to a local user.', [
                        'provided_id' => $providedId,
                        'listing_id'  => $listing->id,
                    ]);
                    unset($localData['agent_id']);
                }
            }

            $listing->update($localData);

            // If listing exists on PF, send PUT to PF API (full replace).
            // NOTE: PATCH is broken on PF's side — their CDN rejects Bearer JWT with an
            // HMAC error. PUT passes auth correctly and accepts partial fields too.
            if ($listing->pf_id) {
                try {
                    // Merge existing listing data with incoming changes for a full PUT payload.
                    // Re-fresh listing to get latest local state after update().
                    $listing->refresh();
                    $pfPayload = $this->buildPfPutPayload($listing, $data, $pfAgentId);

                    Log::info('PropertyFinder PUT payload', [
                        'listing_id' => $listing->id,
                        'pf_id'      => $listing->pf_id,
                        'payload'    => $pfPayload,
                    ]);

                    $this->client->put(
                        $listing->company,
                        "listings/{$listing->pf_id}",
                        $pfPayload
                    );

                    Log::info('PropertyFinder listing updated on PF API via PUT', [
                        'listing_id'     => $listing->id,
                        'pf_id'          => $listing->pf_id,
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
     * Build a full PUT payload by merging the existing listing with incoming changes.
     *
     * PUT requires all fields — we read the current state from the DB record
     * and overlay only the fields that were actually updated.
     *
     * @param PropertyFinderListing $listing   Fresh DB record (after local update)
     * @param array                 $data      Validated incoming changes
     * @param int|null              $pfAgentId Resolved PF integer agent ID
     */
    private function buildPfPutPayload(PropertyFinderListing $listing, array $data, ?int $pfAgentId = null): array
    {
        // Resolve agent ID: prefer the explicitly resolved pfAgentId,
        // fall back to the agent on the listing record.
        $agentId = $pfAgentId;
        if (!$agentId && $listing->agent_id) {
            $agent = \App\Models\User::find($listing->agent_id);
            $agentId = $agent ? (int) $agent->pf_agent_id : null;
        }

        $listingType   = $listing->listing_type ?? $listing->purpose ?? 'sale';
        $rentFrequency = $listing->rent_frequency ?? 'yearly';
        $priceType     = $listingType === 'rent' ? $rentFrequency : 'sale';
        $price         = (float) $listing->price;

        // Emirate slug mapping
        $emirateId = (int) ($listing->emirate_id ?? 0);
        $uaeEmirate = match ($emirateId) {
            1       => 'dubai',
            2       => 'abu_dhabi',
            default => 'northern_emirates',
        };

        // Images — always send current full set
        $images = $listing->images ?? [];
        if (isset($data['images']) && is_array($data['images'])) {
            $images = $data['images'];
        }

        $beds  = (string) ($listing->bedrooms ?? '0');
        $baths = (string) ($listing->bathrooms ?? '0');

        $payload = [
            'age'           => 0,
            'amenities'     => $this->resolveAmenities($data['amenities'] ?? $listing->amenities),
            'assignedTo'    => $agentId ? ['id' => $agentId] : null,
            'createdBy'     => $agentId ? ['id' => $agentId] : null,
            'availableFrom' => $listing->available_from,
            'bathrooms'     => $baths === '0' ? 'none' : $baths,
            'bedrooms'      => $beds  === '0' ? 'studio' : $beds,
            'builtUpArea'   => (int) $listing->size,
            'category'      => $listing->category ?? 'residential',
            'compliance'    => [
                'listingAdvertisementNumber' => $listing->permit_number ?? '',
                'type'                       => $listing->permit_type ?? 'rera',
                'issuingClientLicenseNumber' => $listing->license_number ?? '',
                'userConfirmedDataIsCorrect' => true,
            ],
            'description'   => [
                'en' => $data['description_en'] ?? $data['description'] ?? $listing->description_en ?? '',
                'ar' => $data['description_ar'] ?? $listing->description_ar,
            ],
            'developer'     => $listing->developer_name,
            'finishingType' => 'fully-finished',
            'floorNumber'   => (string) ($listing->floor_number ?? ''),
            'furnishingType' => $listing->furnished ?? 'unfurnished',
            'hasGarden'     => false,
            'hasKitchen'    => false,
            'hasParkingOnSite' => false,
            'location'      => ['id' => (int) ($data['location_id'] ?? $listing->location_id)],
            'media'         => [
                'images' => array_map(fn($url) => [
                    'original' => ['url' => $url],
                    'caption'  => '',
                ], $images),
                'videos' => new \stdClass(),
            ],
            'numberOfFloors'  => 0,
            'parkingSlots'    => (int) ($listing->parking ?? 0),
            'plotSize'        => (int) ($listing->plot_size_sqft ?? 0),
            'price'           => [
                'amounts' => [
                    'daily'   => ($listingType === 'rent' && $rentFrequency === 'daily')   ? $price : 0,
                    'monthly' => ($listingType === 'rent' && $rentFrequency === 'monthly') ? $price : 0,
                    'sale'    => ($listingType === 'sale')                                  ? $price : 0,
                    'weekly'  => ($listingType === 'rent' && $rentFrequency === 'weekly')   ? $price : 0,
                    'yearly'  => ($listingType === 'rent' && $rentFrequency === 'yearly')   ? $price : 0,
                ],
                'downpayment'     => 0,
                'mortgage'        => ['comment' => null, 'enabled' => false],
                'numberOfCheques' => (int) ($listing->cheques ?? 0),
                'obligation'      => ['comment' => null, 'enabled' => false],
                'onRequest'       => (bool) ($listing->price_on_request ?? false),
                'paymentMethods'  => [],
                'type'            => $priceType,
                'utilitiesInclusive' => false,
                'valueAffected'   => ['comment' => null, 'enabled' => false],
            ],
            'projectStatus' => $listing->project_status ?? 'completed',
            'reference'     => $data['reference'] ?? $listing->pf_reference ?? $listing->reference,
            'size'          => (int) $listing->size,
            'street'        => ['direction' => 'North', 'width' => 0],
            'title'         => [
                'en' => $data['title_en'] ?? $data['title'] ?? $listing->title_en ?? '',
                'ar' => $data['title_ar'] ?? $listing->title_ar,
            ],
            'type'          => $data['property_type'] ?? $listing->property_type ?? $listing->type ?? 'apartment',
            'uaeEmirate'    => $uaeEmirate,
            'buildingName'  => $data['building_name'] ?? $listing->building_name,
            'ownershipType' => $data['ownership_type'] ?? $listing->ownership_type,
        ];

        // Override price if price field was explicitly updated
        if (isset($data['price'])) {
            $newPrice = (float) $data['price'];
            $newType  = ($data['listing_type'] ?? $listingType) === 'rent'
                ? ($data['rent_frequency'] ?? $rentFrequency)
                : 'sale';
            $payload['price']['amounts'] = [
                'daily'   => ($newType === 'daily')   ? $newPrice : 0,
                'monthly' => ($newType === 'monthly') ? $newPrice : 0,
                'sale'    => ($newType === 'sale')    ? $newPrice : 0,
                'weekly'  => ($newType === 'weekly')  ? $newPrice : 0,
                'yearly'  => ($newType === 'yearly')  ? $newPrice : 0,
            ];
            $payload['price']['type'] = $newType;
        }

        // Override purpose/listing_type if updated
        if (isset($data['listing_type'])) {
            $payload['purpose'] = $data['listing_type'];
        } else {
            $payload['purpose'] = $listingType;
        }

        // Remove null values recursively
        return $this->removeNullsRecursively($payload);
    }

    private function resolveAmenities(mixed $amenities): array
    {
        if (empty($amenities)) {
            return [];
        }
        $arr = is_string($amenities) ? explode(',', $amenities) : (array) $amenities;
        return array_values(array_unique(array_map(
            fn($v) => \Illuminate\Support\Str::slug(trim($v)),
            $arr
        )));
    }

    private function removeNullsRecursively(array $array): array
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $value = $this->removeNullsRecursively($value);
            }
            if ($value === null) {
                unset($array[$key]);
            }
        }
        return $array;
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
            'price', 'price_on_request', 'ownership_type', 'price_currency',
            'size', 'size_sqft', 'plot_size_sqft',
            'bedrooms', 'bathrooms', 'floor_number', 'number_of_floors',
            'private_pool', 'hotel_name', 'parking', 'furnished',
            'rent_frequency', 'cheques', 'available_from',
            'fitted', 'zoning_type',
            'developer_name', 'project_name', 'completion_date', 'payment_plan',
            'images', 'amenities', 'virtual_tour', 'floor_plan',
            'agent_id', 'emirate_id', 'uae_emirate', 'latitude', 'longitude',
            'pf_location_name', 'pf_city', 'pf_community', 'pf_subcommunity', 'pf_building',
            'price_currency',
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