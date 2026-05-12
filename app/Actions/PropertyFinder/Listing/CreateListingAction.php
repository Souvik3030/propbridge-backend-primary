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
 * 
 * Flow:
 * 1. Local validation (ValidateDependentFieldsAction)
 * 2. Create local record in 'draft' status
 * 3. Submit to PF API Atlas v2 (POST /listings)
 * 4. Update local record with PF response (pf_id, status)
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
                
                $payload  = $this->buildPfPayload($data, $agent);

                // TEMPORARY: Log full payload for debugging
                Log::info('PropertyFinder listing creation payload', [
                    'listing_id' => $listing->id,
                    'payload'    => $payload
                ]);

                // Safety: Validate created_by.id is present
                if (empty($payload['created_by']['id'])) {
                    throw new \Exception('Missing required field: created_by.id (PF User/Agent ID)');
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

            // Initial status
            'status'           => PropertyFinderListing::STATUS_DRAFT,
        ];
    }

    /**
     * Build the payload for PF API POST /listings.
     * Field names match the PF API v2 spec exactly.
     */
    private function buildPfPayload(array $data, ?User $agent): array
    {
        $payload = [
            'agent_id'     => (string) (isset($data['agent_id']) ? $data['agent_id'] : ($agent?->pf_agent_id ?? '')),
            'location_id'  => (int) $data['location_id'],
            'listing_type' => $data['listing_type'], // sale | rent
            'type'         => $data['property_type'], // apartment | villa | etc.
            'category'     => $data['category'],
            'price'        => [
                'value' => (float) $data['price'],
                'currency' => $data['price_currency'] ?? 'AED',
            ],
            'size_sqft'    => (float) ($data['size_sqft'] ?? $data['size'] ?? 0),
            'title'        => [
                'en' => $data['title_en'] ?? $data['title'] ?? ''
            ],
            'description'  => [
                'en' => $data['description_en'] ?? $data['description'] ?? ''
            ],
            'reference'    => $data['reference'] ?? null,
            'images'       => $data['images'],
            'created_by'   => [
                'id'   => (string) (isset($data['agent_id']) ? $data['agent_id'] : ($agent?->pf_agent_id ?? '')),
                'type' => 'agent',
            ],
        ];

        if (!empty($data['title_ar'])) {
            $payload['title']['ar'] = $data['title_ar'];
        }
        if (!empty($data['description_ar'])) {
            $payload['description']['ar'] = $data['description_ar'];
        }
        
        if (isset($data['price_on_request'])) {
            $payload['price']['on_request'] = (bool) $data['price_on_request'];
        }

        // Conditional fields — only include if present
        $optionalFields = [
            'bedrooms', 'bathrooms', 'furnished', 'floor_number', 'parking',
            'rent_frequency', 'cheques', 'available_from', 'permit_number',
            'dld_permit_number', 'building_name', 'ownership_type', 'advertisement_number',
            'developer_name', 'project_name', 'completion_date', 'payment_plan',
            'plot_size_sqft', 'number_of_floors', 'hotel_name',
            'zoning_type', 'fitted', 'virtual_tour', 'floor_plan',
            'amenities',
        ];

        foreach ($optionalFields as $field) {
            if (isset($data[$field]) && $data[$field] !== null && $data[$field] !== '') {
                // PF API expects bedrooms and bathrooms as strings
                if (in_array($field, ['bedrooms', 'bathrooms'])) {
                    $payload[$field] = (string) $data[$field];
                } elseif ($field === 'amenities') {
                    $amenities = is_string($data[$field]) ? explode(',', $data[$field]) : $data[$field];
                    $payload[$field] = array_values(array_unique(array_map(
                        fn($v) => \Illuminate\Support\Str::slug(trim($v)),
                        (array) $amenities
                    )));
                } else {
                    $payload[$field] = $data[$field];
                }
            }
        }

        // private_pool is boolean — include if true
        if (!empty($data['private_pool'])) {
            $payload['private_pool'] = (bool) $data['private_pool'];
        }

        return array_filter($payload, fn($v) => $v !== null);
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