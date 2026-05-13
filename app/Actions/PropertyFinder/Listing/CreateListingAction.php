<?php

declare(strict_types=1);

namespace App\Actions\PropertyFinder\Listing;

use App\Actions\PropertyFinder\Compliance\LogComplianceCheckAction;
use App\Models\Company;
use App\Models\PropertyFinderListing;
use App\Models\User;
use App\Services\PropertyFinderApiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Create a new PropertyFinder listing.
 */
class CreateListingAction
{
    public function __construct(
        private ValidateDependentFieldsAction $validateDependentFields,
        private PropertyFinderApiClient $client,
        private LogComplianceCheckAction $logCompliance
    ) {}

    /**
     * Execute the listing creation flow.
     * 
     * @param array $data Input data from StoreListingRequest
     * @param Company $company The company owning the listing
     * @param User|null $agent The agent assigned to the listing
     */
    public function execute(array $data, Company $company, ?User $agent): PropertyFinderListing
    {
        // Step 1: Pre-validation of dependent fields
        $this->validateDependentFields->execute($data);

        return DB::transaction(function () use ($data, $company, $agent) {

            // Step 2: Create local draft record
            $listing = PropertyFinderListing::create(
                $this->buildLocalData($data, $company, $agent)
            );

            // Step 3: Submit to PF API — POST /listings
            try {
                if (isset($data['images'])) {
                    $data['images'] = $this->uploadImagesIfFiles($data['images']);
                    $listing->update(['images' => $data['images']]);
                }
                
                $payload  = $this->buildPfPayload($data, $agent, $company);

                // TEMPORARY: Log full payload for debugging
                Log::info('PropertyFinder listing creation payload', [
                    'listing_id' => $listing->id,
                    'payload'    => $payload
                ]);

                // Safety: Validate created_by.id is present
                if (empty($payload['createdBy']['id'])) { // Changed from created_by
    throw new \Exception('Missing required field: createdBy.id (PF User/Agent ID)');
}

                $pfData   = $this->client->post($company, 'listings', $payload);

                // Step 4: Map PF response back to local record
                $listing->update([
                    'pf_id'            => $pfData['id'] ?? null,
                    'pf_reference'     => $pfData['reference'] ?? null,
                    'status'           => $pfData['status'] ?? PropertyFinderListing::STATUS_DRAFT,
                    'validation_diffs' => null, // Clear any previous errors
                ]);

                Log::info('PropertyFinder listing created and synced', [
                    'listing_id'   => $listing->id,
                    'pf_id'        => $listing->pf_id,
                    'pf_reference' => $listing->pf_reference,
                    'company_id'   => $company->id,
                    'emirate_id'   => $data['emirate_id'] ?? null,
                ]);

            } catch (\Throwable $e) {
                // PF API call failed — mark as draft with note, don't rollback DB record
                // (user can retry publishing later)
                Log::error('PropertyFinder listing creation on PF API failed', [
                    'listing_id' => $listing->id,
                    'error'      => $e->getMessage(),
                ]);

                $listing->update([
                    'status'          => PropertyFinderListing::STATUS_COMPLIANCE_FAILED,
                    'validation_diffs' => ['PF API submission failed: ' . $e->getMessage()],
                ]);
            }

            return $listing->fresh();
        });
    }

    /**
     * Build the local DB record data from input.
     */
    private function buildLocalData(array $data, Company $company, ?User $agent): array
    {
        return [
            'company_id'       => $company->id,
            'agent_id'         => $agent?->id,

            // PF location
            'location_id'      => $data['location_id'],
            'reference'        => $data['reference'] ?? null,

            // Emirate
            'emirate'          => $data['emirate'] ?? $this->resolveEmirateKey($data['emirate_id'] ?? 0),
            'emirate_id'       => $data['emirate_id'] ?? null,

            // Permits
            'permit_number'    => $data['permit_number'] ?? null,
            'permit_type'      => $data['permit_type'] ?? null,
            'license_number'   => $company->license_number,
            'building_name'    => $data['building_name'] ?? null,
            'dld_permit_number' => $data['dld_permit_number'] ?? null,
            'advertisement_number' => $data['advertisement_number'] ?? null,

            // Classification
            'listing_type'     => $data['listing_type'],
            'category'         => $data['category'],
            'property_type'    => $data['property_type'],
            'project_status'   => $data['project_status'] ?? null,

            // Legacy compat
            'purpose'          => $data['listing_type'],
            'type'             => $data['property_type'],
            'pf_location_id'   => $data['location_id'],

            // Title & description
            'title_en'         => $data['title_en'] ?? $data['title'] ?? '',
            'title_ar'         => $data['title_ar'] ?? null,
            'description_en'   => $data['description_en'] ?? $data['description'] ?? '',
            'description_ar'   => $data['description_ar'] ?? null,

            // Pricing
            'price'            => $data['price'],
            'price_on_request' => $data['price_on_request'] ?? false,
            'ownership_type'   => $data['ownership_type'] ?? null,

            // Size
            'size'             => $data['size_sqft'] ?? $data['size'] ?? null,
            'size_unit'        => 'sqft',
            'plot_size_sqft'   => $data['plot_size_sqft'] ?? null,

            // Specs
            'bedrooms'         => $data['bedrooms'] ?? null,
            'bathrooms'        => $data['bathrooms'] ?? null,
            'floor_number'     => $data['floor_number'] ?? null,
            'number_of_floors' => $data['number_of_floors'] ?? null,
            'private_pool'     => $data['private_pool'] ?? false,
            'hotel_name'       => $data['hotel_name'] ?? null,
            'parking'          => $data['parking'] ?? null,
            'furnished'        => $data['furnished'] ?? null,

            // Rental
            'rent_frequency'   => $data['rent_frequency'] ?? null,
            'cheques'          => $data['cheques'] ?? null,
            'available_from'   => $data['available_from'] ?? null,

            // Commercial
            'fitted'           => $data['fitted'] ?? null,

            // Land
            'zoning_type'      => $data['zoning_type'] ?? null,

            // Off-plan
            'developer_name'   => $data['developer_name'] ?? null,
            'project_name'     => $data['project_name'] ?? null,
            'completion_date'  => $data['completion_date'] ?? null,
            'payment_plan'     => $data['payment_plan'] ?? null,

            // Media
            'images'           => $data['images'],
            'amenities'        => $data['amenities'] ?? [],
            'virtual_tour'     => $data['virtual_tour'] ?? null,
            'floor_plan'       => $data['floor_plan'] ?? null,

            // New Location metadata
            'pf_location_name' => $data['pf_location_name'] ?? null,
            'pf_city'          => $data['pf_city'] ?? null,
            'pf_community'     => $data['pf_community'] ?? null,
            'pf_subcommunity'  => $data['pf_subcommunity'] ?? null,
            'pf_building'      => $data['pf_building'] ?? null,
            'uae_emirate'      => $data['uae_emirate'] ?? null,
            'latitude'         => $data['latitude'] ?? null,
            'longitude'        => $data['longitude'] ?? null,
            'price_currency'   => $data['price_currency'] ?? 'AED',

            // Initial status
            'status'           => PropertyFinderListing::STATUS_DRAFT,
        ];
    }

    /**
     * Build the payload for PF API POST /listings.
     * Matches the detailed schema provided.
     */
    // private function buildPfPayload(array $data, ?User $agent, Company $company): array
    // {
    //     $agentId = (int) (isset($data['agent_id']) ? $data['agent_id'] : ($agent?->pf_agent_id ?? 0));
    //     $listingType = $data['listing_type'] ?? 'sale';
    //     $rentFrequency = $data['rent_frequency'] ?? 'yearly';

    //     $payload = [
    //         'age'           => (int) ($data['age'] ?? 0),
    //         'amenities'     => [], // Filled below
    //         'assignedTo'    => ['id' => $agentId],
    //         'availableFrom' => $data['available_from'] ?? null,
    //         'bathrooms'     => (string) ($data['bathrooms'] ?? '0'),
    //         'bedrooms'      => (string) ($data['bedrooms'] ?? '0'),
    //         'builtUpArea'   => (int) ($data['size_sqft'] ?? $data['size'] ?? 0),
    //         'category'      => $data['category'] ?? 'residential',
    //         'compliance'    => [
    //             'advertisementLicenseIssuanceDate' => $data['advertisement_license_issuance_date'] ?? null,
    //             'listingAdvertisementNumber'       => $data['permit_number'] ?? $data['advertisement_number'] ?? '',
    //             'type'                             => $data['permit_type'] ?? 'rera',
    //             'issuingClientLicenseNumber'       => $company->license_number ?? $data['license_number'] ?? '',
    //             'userConfirmedDataIsCorrect'       => true,
    //         ],
    //         'createdBy'     => ['id' => $agentId],
    //         'description'   => [
    //             'en' => $data['description_en'] ?? $data['description'] ?? '',
    //             'ar' => $data['description_ar'] ?? null,
    //         ],
    //         'developer'     => $data['developer_name'] ?? null,
    //         'finishingType' => $data['finishing_type'] ?? 'fully-finished',
    //         'floorNumber'   => (string) ($data['floor_number'] ?? ''),
    //         'furnishingType' => $data['furnishing_type'] ?? 'unfurnished',
    //         'hasGarden'     => (bool) ($data['has_garden'] ?? false),
    //         'hasKitchen'    => (bool) ($data['has_kitchen'] ?? false),
    //         'hasParkingOnSite' => (bool) ($data['has_parking_on_site'] ?? false),
    //         'landNumber'    => $data['land_number'] ?? null,
    //         'location'      => ['id' => (int) $data['location_id']],
    //         'media'         => [
    //             'images' => array_map(fn($url) => [
    //                 'original' => ['url' => $url]
    //             ], $data['images'] ?? []),
    //             'videos' => [
    //                 'default' => $data['video_url'] ?? null,
    //                 'view360' => $data['virtual_tour'] ?? null,
    //             ]
    //         ],
    //         'mojDeedLocationDescription' => $data['moj_deed_location_description'] ?? null,
    //         'numberOfFloors' => (int) ($data['number_of_floors'] ?? 0),
    //         'ownerName'      => $data['owner_name'] ?? null,
    //         'parkingSlots'   => (int) ($data['parking'] ?? $data['parking_slots'] ?? 0),
    //         'plotNumber'     => $data['plot_number'] ?? null,
    //         'plotSize'       => (int) ($data['plot_size_sqft'] ?? $data['plot_size'] ?? 0),
    //         'price'          => [
    //             'amounts'   => [
    //                 'daily'   => ($listingType === 'rent' && $rentFrequency === 'daily')   ? (int) $data['price'] : 0,
    //                 'monthly' => ($listingType === 'rent' && $rentFrequency === 'monthly') ? (int) $data['price'] : 0,
    //                 'sale'    => ($listingType === 'sale')                                 ? (int) $data['price'] : 0,
    //                 'weekly'  => ($listingType === 'rent' && $rentFrequency === 'weekly')  ? (int) $data['price'] : 0,
    //                 'yearly'  => ($listingType === 'rent' && $rentFrequency === 'yearly')  ? (int) $data['price'] : 0,
    //             ],
    //             'downpayment'           => (int) ($data['downpayment'] ?? 0),
    //             'minimalRentalPeriod'   => (int) ($data['minimal_rental_period'] ?? 0),
    //             'mortgage'              => [
    //                 'comment' => $data['mortgage_comment'] ?? null,
    //                 'enabled' => (bool) ($data['mortgage_enabled'] ?? false),
    //             ],
    //             'numberOfCheques'       => (int) ($data['cheques'] ?? 0),
    //             'numberOfMortgageYears' => (int) ($data['mortgage_years'] ?? 0),
    //             'obligation'            => [
    //                 'comment' => $data['obligation_comment'] ?? null,
    //                 'enabled' => (bool) ($data['obligation_enabled'] ?? false),
    //             ],
    //             'onRequest'             => (bool) ($data['price_on_request'] ?? false),
    //             'paymentMethods'        => is_string($data['payment_methods'] ?? null) ? explode(',', $data['payment_methods']) : ($data['payment_methods'] ?? []),
    //             'type'                  => $listingType === 'sale' ? 'sale' : $rentFrequency,
    //             'utilitiesInclusive'    => (bool) ($data['utilities_inclusive'] ?? false),
    //             'valueAffected'         => [
    //                 'comment' => $data['value_affected_comment'] ?? null,
    //                 'enabled' => (bool) ($data['value_affected_enabled'] ?? false),
    //             ]
    //         ],
    //         'projectStatus' => $data['project_status'] ?? 'completed',
    //         'reference'     => $data['reference'] ?? null,
    //         'size'          => (int) ($data['size_sqft'] ?? $data['size'] ?? 0),
    //         'street'        => [
    //             'direction' => $data['street_direction'] ?? 'North',
    //             'width'     => (int) ($data['street_width'] ?? 0),
    //         ],
    //         'title'         => [
    //             'en' => $data['title_en'] ?? $data['title'] ?? '',
    //             'ar' => $data['title_ar'] ?? null,
    //         ],
    //         'type'          => $data['property_type'] ?? 'apartment',
    //         'uaeEmirate'    => $data['emirate'] ?? $this->resolveEmirateKey((int) ($data['emirate_id'] ?? 0)),
    //         'unitNumber'    => $data['unit_number'] ?? null,
    //         // Sale-listing fields
    //         'ownershipType' => $data['ownership_type'] ?? null,
    //         // Building name (required for Dubai + Abu Dhabi)
    //         'buildingName'  => $data['building_name'] ?? null,
    //     ];

    //     // Amenities handling: convert to slugs
    //     if (!empty($data['amenities'])) {
    //         $amenities = is_string($data['amenities']) ? explode(',', $data['amenities']) : $data['amenities'];
    //         $payload['amenities'] = array_values(array_unique(array_map(
    //             fn($v) => \Illuminate\Support\Str::slug(trim($v)),
    //             (array) $amenities
    //         )));
    //     }

    //     // Clean up: return all fields as PF API expects many of these even if empty/null in some versions,
    //     // but we'll use array_filter to keep it tidy for non-required fields.
    //     return array_filter($payload, fn($v) => $v !== null);
    // }

 private function buildPfPayload(array $data, ?User $agent, Company $company): array
{
      $agentId = (int) $data['agent_id'];
    
    $listingType = $data['listing_type'] ?? 'sale';
    $rentFrequency = $data['rent_frequency'] ?? 'yearly';

        // 1. Map Emirate exactly to PF's strict 3 values
        $emirateId = (int) ($data['emirate_id'] ?? 0);
        $pfEmirate = match ($emirateId) {
            1 => 'dubai',
            2 => 'abu_dhabi',
            default => 'northern_emirates', // Maps Sharjah (3), Ajman (4), etc., correctly
        };

        // Prepare video data
        $videoData = array_filter([
            'default' => $data['video_url'] ?? null,
            'view360' => $data['virtual_tour'] ?? null,
        ]);

        $payload = [
            'age'           => (int) ($data['age'] ?? 0),
            'amenities'     => [], // Filled below
            'assignedTo'    => ['id' => $agentId],
            'availableFrom' => $data['available_from'] ?? null,
            'bathrooms'     => (string) ($data['bathrooms'] ?? '0'),
            'bedrooms'      => (string) ($data['bedrooms'] ?? '0'),
            'builtUpArea'   => (int) ($data['size_sqft'] ?? $data['size'] ?? 0),
            'category'      => $data['category'] ?? 'residential',
            'compliance'    => [
                'advertisementLicenseIssuanceDate' => $data['advertisement_license_issuance_date'] ?? null,
                'listingAdvertisementNumber'       => $data['permit_number'] ?? $data['advertisement_number'] ?? '',
                'type'                             => $data['permit_type'] ?? 'rera',
                'issuingClientLicenseNumber'       => $company->license_number ?? $data['license_number'] ?? 'PENDING_LICENSE',
                'userConfirmedDataIsCorrect'       => true,
            ],
            'createdBy'     => ['id' => $agentId],
            'description'   => [
                'en' => $data['description_en'] ?? $data['description'] ?? '',
                'ar' => $data['description_ar'] ?? null,
            ],
            'developer'     => $data['developer_name'] ?? null,
            'finishingType' => $data['finishing_type'] ?? 'fully-finished',
            'floorNumber'   => (string) ($data['floor_number'] ?? ''),
            'furnishingType' => $data['furnishing_type'] ?? 'unfurnished',
            'hasGarden'     => (bool) ($data['has_garden'] ?? false),
            'hasKitchen'    => (bool) ($data['has_kitchen'] ?? false),
            'hasParkingOnSite' => (bool) ($data['has_parking_on_site'] ?? false),
            'landNumber'    => $data['land_number'] ?? null,
            'location'      => ['id' => (int) $data['location_id']],
            'media'         => [
                'images' => array_map(fn($url) => [
                    'original' => ['url' => $url]
                ], $data['images'] ?? []),
                
                // 🚀 THE FIX: (object) forces PHP to encode this as {} instead of []
                'videos' => !empty($videoData) ? (object)$videoData : new \stdClass(),
            ],
            'mojDeedLocationDescription' => $data['moj_deed_location_description'] ?? null,
            'numberOfFloors' => (int) ($data['number_of_floors'] ?? 0),
            'ownerName'      => $data['owner_name'] ?? null,
            'parkingSlots'   => (int) ($data['parking'] ?? $data['parking_slots'] ?? 0),
            'plotNumber'     => $data['plot_number'] ?? null,
            'plotSize'       => (int) ($data['plot_size_sqft'] ?? $data['plot_size'] ?? 0),
            'price'          => [
                'amounts'   => [
                    'daily'   => ($listingType === 'rent' && $rentFrequency === 'daily')   ? (int) $data['price'] : 0,
                    'monthly' => ($listingType === 'rent' && $rentFrequency === 'monthly') ? (int) $data['price'] : 0,
                    'sale'    => ($listingType === 'sale')                                 ? (int) $data['price'] : 0,
                    'weekly'  => ($listingType === 'rent' && $rentFrequency === 'weekly')  ? (int) $data['price'] : 0,
                    'yearly'  => ($listingType === 'rent' && $rentFrequency === 'yearly')  ? (int) $data['price'] : 0,
                ],
                'downpayment'           => (int) ($data['downpayment'] ?? 0),
                'minimalRentalPeriod'   => (int) ($data['minimal_rental_period'] ?? 0),
                'mortgage'              => [
                    'comment' => $data['mortgage_comment'] ?? null,
                    'enabled' => (bool) ($data['mortgage_enabled'] ?? false),
                ],
                'numberOfCheques'       => (int) ($data['cheques'] ?? 0),
                'numberOfMortgageYears' => (int) ($data['mortgage_years'] ?? 0),
                'obligation'            => [
                    'comment' => $data['obligation_comment'] ?? null,
                    'enabled' => (bool) ($data['obligation_enabled'] ?? false),
                ],
                'onRequest'             => (bool) ($data['price_on_request'] ?? false),
                'paymentMethods'        => is_string($data['payment_methods'] ?? null) ? explode(',', $data['payment_methods']) : ($data['payment_methods'] ?? []),
                'type'                  => $listingType === 'sale' ? 'sale' : $rentFrequency,
                'utilitiesInclusive'    => (bool) ($data['utilities_inclusive'] ?? false),
                'valueAffected'         => [
                    'comment' => $data['value_affected_comment'] ?? null,
                    'enabled' => (bool) ($data['value_affected_enabled'] ?? false),
                ]
            ],
            'projectStatus' => $data['project_status'] ?? 'completed',
            'reference'     => $data['reference'] ?? null,
            'size'          => (int) ($data['size_sqft'] ?? $data['size'] ?? 0),
            'street'        => [
                'direction' => $data['street_direction'] ?? 'North',
                'width'     => (int) ($data['street_width'] ?? 0),
            ],
            'title'         => [
                'en' => $data['title_en'] ?? $data['title'] ?? '',
                'ar' => $data['title_ar'] ?? null,
            ],
            'type'          => $data['property_type'] ?? 'apartment',
            'uaeEmirate'    => $pfEmirate, // <--- Fixed here to strictly match PF's requirements
            'unitNumber'    => $data['unit_number'] ?? null,
            'ownershipType' => $data['ownership_type'] ?? null,
            'buildingName'  => $data['building_name'] ?? null,
        ];

        if (!empty($data['amenities'])) {
            $amenities = is_string($data['amenities']) ? explode(',', $data['amenities']) : $data['amenities'];
            $payload['amenities'] = array_values(array_unique(array_map(
                fn($v) => \Illuminate\Support\Str::slug(trim($v)),
                (array) $amenities
            )));
        }

        // 2. Run the recursive null remover to prevent nested JSON errors
        return $this->removeNullsRecursively($payload);
    }

    /**
     * Recursively strips null values from an array to satisfy PF Schema requirements.
     */
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

  
    private function resolveEmirateKey(int $emirateId): string
    {
        return config("propertyfinder.emirates.{$emirateId}.key", 'unknown');
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