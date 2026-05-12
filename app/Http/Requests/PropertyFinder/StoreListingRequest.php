<?php

declare(strict_types=1);

namespace App\Http\Requests\PropertyFinder;

use App\Models\PropertyFinderListing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for creating a new PropertyFinder listing.
 *
 * Implements all dependent field rules from PF API v2 docs:
 *  Section 4a — Emirate-based dependencies
 *  Section 4b — Listing type & category dependencies
 *  Section 4c — Property type dependencies
 */
class StoreListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PropertyFinderListing::class);
    }

    protected function prepareForValidation(): void
    {
        // ── Nested Payload Flattening ───────────────────────────────────────
        // We map the incoming JSON structure to the flat fields the validator expects.

        // 1. Identity & Location
        $agentId = $this->input('assignedTo.id') ?? $this->input('agentId') ?? $this->input('agent_id');
        if ($agentId) $this->merge(['agent_id' => $agentId]);

        $locId = $this->input('location.id') ?? $this->input('locationId') ?? $this->input('location_id');
        if ($locId) $this->merge(['location_id' => $locId]);

        $locName = $this->input('location.name') ?? $this->input('locationName') ?? $this->input('location_name');
        if ($locName) $this->merge(['location_name' => $locName]);

        // 2. Classification & Emirate
        if ($this->has('type') && !$this->has('property_type')) {
            $this->merge(['property_type' => $this->input('type')]);
        }
        if ($this->has('category')) {
            $this->merge(['category' => \Illuminate\Support\Str::snake($this->input('category'))]);
        }
        
        // Emirate Detection (Critical for permit logic)
        $uaeEmirate = $this->input('uaeEmirate') ?? $this->input('emirate');
        if ($uaeEmirate) {
            $slug = \Illuminate\Support\Str::slug((string) $uaeEmirate);
            $emirateMapping = [
                'dubai'             => 1,
                'abu-dhabi'         => 2,
                'sharjah'           => 3,
                'ajman'             => 4,
                'rak'               => 5,
                'ras-al-khaimah'    => 5,
                'fujairah'          => 6,
                'uaq'               => 7,
                'umm-al-quwain'     => 7,
                'northern-emirates' => 1,
            ];
            if (isset($emirateMapping[$slug])) {
                $this->merge(['emirate_id' => $emirateMapping[$slug]]);
            }
        }

        // 3. Title & Description
        if ($this->has('title.en')) {
            $this->merge(['title_en' => $this->input('title.en'), 'title' => $this->input('title.en')]);
        }
        if ($this->has('title.ar')) {
            $this->merge(['title_ar' => $this->input('title.ar')]);
        }
        if ($this->has('description.en')) {
            $this->merge(['description_en' => $this->input('description.en'), 'description' => $this->input('description.en')]);
        }
        if ($this->has('description.ar')) {
            $this->merge(['description_ar' => $this->input('description.ar')]);
        }

        // 4. Pricing & Size
        if ($this->has('price.amounts')) {
            $amounts = $this->input('price.amounts');
            $type = $this->input('price.type', 'sale');
            $listingType = in_array($type, ['yearly', 'monthly', 'weekly', 'daily']) ? 'rent' : 'sale';
            $this->merge(['listing_type' => $listingType]);
            if ($listingType === 'rent') $this->merge(['rent_frequency' => $type]);

            $priceValue = $amounts[$type] ?? array_values(array_filter($amounts, fn($v) => $v > 0))[0] ?? 0;
            $this->merge(['price' => $priceValue]);
            if ($this->has('price.onRequest')) $this->merge(['price_on_request' => $this->input('price.onRequest')]);
        }

        if ($this->has('builtUpArea') || $this->has('size')) {
            $this->merge(['size_sqft' => $this->input('builtUpArea') ?? $this->input('size')]);
        }

        if ($this->has('ownershipType')) {
            $this->merge(['ownership_type' => $this->input('ownershipType')]);
        }

        // 5. Building & Permits
        $building = $this->input('buildingName') ?? $this->input('building_name') ?? $this->input('building');
        if ($building) $this->merge(['building_name' => $building]);

        $permit = $this->input('compliance.listingAdvertisementNumber') 
               ?? $this->input('compliance.permitNumber')
               ?? $this->input('permitNumber')
               ?? $this->input('permit_number');
        if ($permit) $this->merge(['permit_number' => $permit]);

        // 6. Media
        if ($this->has('media.images') && is_array($this->input('media.images'))) {
            $images = array_map(fn($img) => is_array($img) ? ($img['original']['url'] ?? null) : $img, $this->input('media.images'));
            $this->merge(['images' => array_filter($images)]);
        }

        // 7. Specs
        if ($this->has('bedrooms')) {
            $val = $this->input('bedrooms');
            if ($val === 'studio') $this->merge(['bedrooms' => 0]);
            elseif (is_numeric($val)) $this->merge(['bedrooms' => (int)$val]);
        }
        if ($this->has('bathrooms')) {
            $val = $this->input('bathrooms');
            if ($val === 'none') $this->merge(['bathrooms' => 0]);
            elseif (is_numeric($val)) $this->merge(['bathrooms' => (int)$val]);
        }

        // ── Original Amenities logic ────────────────────────────────────────
        if ($this->has('amenities') && is_array($this->amenities)) {
            $this->merge([
                'amenities' => array_map(fn($v) => is_string($v) ? str_replace('_', '-', $v) : $v, $this->amenities)
            ]);
        }
    }

    public function rules(): array
    {
        $listingType  = $this->input('listing_type');
        $category     = $this->input('category');
        $propertyType = $this->input('property_type');
        $emirateId    = (int) $this->input('emirate_id', 0);
        
        // ── Location Name Resolution for Permit Check ───────────────────────
        $locationName = $this->input('location_name');

        // Fallback: If they only sent an ID, fetch the name from the DB (if you store locations locally)
        if (!$locationName && $this->input('location_id')) {
            // Example if you have a Location model:
            // $locationName = \App\Models\Location::find($this->input('location_id'))?->name;
        }

        // Cast to string to prevent passing null to the service
        $locationName = (string) $locationName;
        
        $permitService = app(\App\Services\PropertyFinder\EmiratePermitService::class);
        $needsPermit   = $permitService->requiresPermit($emirateId, $locationName);

        return [

            // ── Core required fields ──────────────────────────────────────────

            'reference'     => ['nullable', 'string', 'max:100'],
            'agent_id'      => ['required', 'integer', 'min:1'],
            'location_id'   => ['required', 'integer', 'min:1'],
            'emirate_id'    => ['required', 'integer', Rule::in([1, 2, 3, 4, 5, 6, 7])],
            'listing_type'  => ['required', Rule::in(['sale', 'rent'])],
            'category'      => ['required', Rule::in(['residential', 'commercial', 'off_plan'])],
            'property_type' => [
                'required',
                Rule::in(config('propertyfinder.property_types.all', [
                    'apartment', 'villa', 'townhouse', 'penthouse', 'hotel_apartment',
                    'land', 'office', 'retail', 'warehouse',
                ])),
            ],
            'price'         => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'price_currency'=> ['nullable', 'string', 'size:3'],
            'size_sqft'     => ['required', 'numeric', 'min:1'],
            'title_en'      => ['required_without:title', 'string', 'min:10', 'max:150'],
            'title'         => ['required_without:title_en', 'string', 'min:10', 'max:150'],
            'title_ar'      => ['nullable', 'string', 'min:10', 'max:150'],
            'description_en'=> ['required_without:description', 'string', 'min:50'],
            'description'   => ['required_without:description_en', 'string', 'min:50'],
            'description_ar'=> ['nullable', 'string', 'min:50'],
            'images'        => ['required', 'array', 'min:1', 'max:30'],
            'images.*'      => ['required'], 
            'images_files'  => ['nullable', 'array'],
            'images_files.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],

            // ── Section 4a: Emirate-based permit requirements ─────────────────

            // Dynamically required based on EmiratePermitService
            'permit_number' => [
                Rule::requiredIf($needsPermit),
                'nullable', 'string', 'max:100',
            ],
            
            'permit_license_number' => ['nullable', 'string', 'max:100'],
            'permit_id'             => ['nullable', 'string', 'max:100'],
            'advertisement_number'  => ['nullable', 'string', 'max:100'],

            // Required specifically for Dubai (1) and Abu Dhabi (2)
            'building_name' => [
                Rule::requiredIf(in_array($emirateId, [1, 2], true)),
                'nullable', 'string', 'max:255',
            ],

            // ── Section 4b: Listing type dependencies ─────────────────────────

            'rent_frequency' => [
                Rule::requiredIf($listingType === 'rent'),
                'nullable',
                Rule::in(['yearly', 'monthly', 'weekly', 'daily']),
            ],
            'cheques' => ['nullable', 'integer', 'min:1', 'max:12'],
            'available_from' => ['nullable', 'date', 'date_format:Y-m-d'],
            'ownership_type' => [
                Rule::requiredIf($listingType === 'sale'),
                'nullable',
                Rule::in(['freehold', 'leasehold']),
            ],

            // ── Section 4b: Category dependencies ────────────────────────────

            'bedrooms' => [
                Rule::requiredIf($category === 'residential'),
                'nullable', 'integer', 'min:0', 'max:20',
            ],
            'bathrooms' => [
                Rule::requiredIf($category === 'residential'),
                'nullable', 'integer', 'min:1', 'max:20',
            ],
            'developer_name'  => [
                Rule::requiredIf($category === 'off_plan'),
                'nullable', 'string', 'max:255',
            ],
            'project_name'    => [
                Rule::requiredIf($category === 'off_plan'),
                'nullable', 'string', 'max:255',
            ],
            'completion_date' => [
                Rule::requiredIf($category === 'off_plan'),
                'nullable', 'date', 'date_format:Y-m-d',
            ],
            'payment_plan'    => ['nullable', 'string', 'max:2000'],

            // ── Section 4c: Property type dependencies ────────────────────────

            'floor_number' => ['nullable', 'integer', 'min:0'],
            'number_of_floors' => ['nullable', 'integer', 'min:1'],
            'private_pool' => ['nullable', 'boolean'],
            'hotel_name' => [
                Rule::requiredIf($propertyType === 'hotel_apartment'),
                'nullable', 'string', 'max:255',
            ],
            'plot_size_sqft' => [
                Rule::requiredIf(in_array($propertyType, ['villa', 'townhouse', 'land'], true)),
                'nullable', 'numeric', 'min:1',
            ],
            'zoning_type' => [
                Rule::requiredIf($propertyType === 'land'),
                'nullable',
                Rule::in(['residential', 'commercial', 'mixed', 'industrial']),
            ],
            'fitted' => [
                Rule::requiredIf(in_array($propertyType, ['office', 'retail', 'warehouse'], true)),
                'nullable',
                Rule::in(['yes', 'no', 'partially']),
            ],

            // ── Optional enrichment fields ────────────────────────────────────

            'furnished'       => ['nullable', Rule::in(['furnished', 'unfurnished', 'partly_furnished'])],
            'parking'         => ['nullable', 'integer', 'min:0'],
            'virtual_tour'    => ['nullable', 'url', 'max:2000'],
            'floor_plan'      => ['nullable', 'url', 'max:2000'],
            'price_on_request' => ['nullable', 'boolean'],
            'amenities'       => ['nullable', 'array'],
            'amenities.*'     => ['string', Rule::in(config('propertyfinder.amenities', []))],
            'project_status'  => ['nullable', Rule::in([
                'off_plan', 'off_plan_primary', 'completed', 'completed_primary', 'off_plan_under_construction'
            ])],
        ];
    }

    public function messages(): array
    {
        return [
            // Emirates
            'emirate_id.required' => 'Emirates ID is required. Use 1=Dubai, 2=Abu Dhabi, 3=Sharjah, 4=Ajman, 5=RAK, 6=Fujairah, 7=UAQ.',
            'emirate_id.in'       => 'Invalid emirate ID. Must be one of: 1 (Dubai), 2 (Abu Dhabi), 3 (Sharjah), 4 (Ajman), 5 (RAK), 6 (Fujairah), 7 (UAQ).',

            // Core PF fields
            'agent_id.required'    => 'Agent ID is required. Fetch agents from GET /propertyfinder/agents.',
            'location_id.required' => 'Location ID is required. Fetch locations from GET /propertyfinder/locations.',
            'listing_type.required' => 'Listing type is required: sale or rent.',
            'property_type.required' => 'Property type is required (apartment, villa, office, etc.).',

            // Pricing
            'price.min'           => 'Price must be greater than 0 AED.',
            'size_sqft.min'       => 'Size must be greater than 0 sq ft.',

            // Content
            'title_en.min'        => 'Title (English) must be at least 10 characters.',
            'title_en.max'        => 'Title (English) must not exceed 150 characters.',
            'description_en.min'  => 'Description (English) must be at least 50 characters.',
            'images.required'     => 'At least 1 image URL is required.',
            'images.min'          => 'At least 1 image is required for a listing.',
            'images.max'          => 'Maximum 30 images allowed.',

            // Permits
            'permit_number.required'      => 'Permit number is required for this emirate.',
            'building_name.required'      => 'Building name is required for Dubai and Abu Dhabi listings.',

            // Listing type
            'rent_frequency.required'  => 'Rent frequency is required for rent listings (yearly/monthly/weekly/daily).',
            'ownership_type.required'  => 'Ownership type is required for sale listings (freehold/leasehold).',

            // Category
            'bedrooms.required'        => 'Bedrooms count is required for residential listings. Use 0 for studio.',
            'bathrooms.required'       => 'Bathrooms count is required for residential listings.',
            'developer_name.required'  => 'Developer name is required for off-plan listings.',
            'project_name.required'    => 'Project name is required for off-plan listings.',
            'completion_date.required' => 'Completion date is required for off-plan listings.',

            // Property type
            'hotel_name.required'    => 'Hotel name is required for hotel apartment listings.',
            'plot_size_sqft.required' => 'Plot size is required for villa, townhouse, and land listings.',
            'zoning_type.required'   => 'Zoning type is required for land/plot listings.',
            'fitted.required'        => 'Fitted status is required for office, retail, and warehouse listings.',
        ];
    }
}