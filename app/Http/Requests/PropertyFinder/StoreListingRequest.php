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
        // If the frontend sends the structured JSON, we flatten it here so the
        // existing rules and actions continue to work without modification.

        // Agent & Location
        if ($this->has('assignedTo.id')) {
            $this->merge(['agent_id' => $this->input('assignedTo.id')]);
        }
        if ($this->has('location.id')) {
            $this->merge(['location_id' => $this->input('location.id')]);
        }

        // Classification
        if ($this->has('type') && !$this->has('property_type')) {
            $this->merge(['property_type' => $this->input('type')]);
        }
        if ($this->has('category')) {
            // Ensure category is in snake_case if it comes as camelCase
            $this->merge(['category' => \Illuminate\Support\Str::snake($this->input('category'))]);
        }
        if ($this->has('projectStatus')) {
            $this->merge(['project_status' => $this->input('projectStatus')]);
        }

        // Title & Description
        if ($this->has('title.en')) {
            $this->merge(['title_en' => $this->input('title.en')]);
        }
        if ($this->has('title.ar')) {
            $this->merge(['title_ar' => $this->input('title.ar')]);
        }
        if ($this->has('description.en')) {
            $this->merge(['description_en' => $this->input('description.en')]);
        }
        if ($this->has('description.ar')) {
            $this->merge(['description_ar' => $this->input('description.ar')]);
        }

        // Pricing & Size
        if ($this->has('price.amounts')) {
            $amounts = $this->input('price.amounts');
            $type = $this->input('price.type', 'sale');
            
            // Map PF frequency slugs to our internal listing_type
            $listingType = in_array($type, ['yearly', 'monthly', 'weekly', 'daily']) ? 'rent' : 'sale';
            $this->merge(['listing_type' => $listingType]);
            
            if ($listingType === 'rent') {
                $this->merge(['rent_frequency' => $type]);
            }

            // Extract the price value
            $price = $amounts[$type] ?? 0;
            if ($price == 0) {
                // Fallback to first non-zero amount
                $price = array_values(array_filter($amounts, fn($v) => $v > 0))[0] ?? 0;
            }
            $this->merge(['price' => $price]);

            if ($this->has('price.onRequest')) {
                $this->merge(['price_on_request' => $this->input('price.onRequest')]);
            }
        }

        if ($this->has('builtUpArea') || $this->has('size')) {
            $this->merge(['size_sqft' => $this->input('builtUpArea') ?? $this->input('size')]);
        }

        // Emirate Mapping
        if ($this->has('uaeEmirate')) {
            $slug = \Illuminate\Support\Str::slug($this->input('uaeEmirate'));
            $emirateMapping = [
                'dubai'             => 1,
                'abu-dhabi'         => 2,
                'sharjah'           => 3,
                'ajman'             => 4,
                'rak'               => 5,
                'fujairah'          => 6,
                'uaq'               => 7,
                'northern-emirates' => 1, // Default or specific mapping
            ];
            if (isset($emirateMapping[$slug])) {
                $this->merge(['emirate_id' => $emirateMapping[$slug]]);
            }
        }

        // Media
        if ($this->has('media.images') && is_array($this->input('media.images'))) {
            $images = array_map(function($img) {
                return is_array($img) ? ($img['original']['url'] ?? null) : $img;
            }, $this->input('media.images'));
            $this->merge(['images' => array_filter($images)]);
        }

        // Compliance
        if ($this->has('compliance')) {
            $this->merge([
                'permit_number' => $this->input('compliance.listingAdvertisementNumber'),
                'permit_type'   => $this->input('compliance.type'),
                'license_number' => $this->input('compliance.issuingClientLicenseNumber'),
                'advertisement_license_issuance_date' => $this->input('compliance.advertisementLicenseIssuanceDate'),
            ]);
        }

        // Specs (Handle "studio" and "none" strings)
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
                'amenities' => array_map(function($v) {
                    return is_string($v) ? str_replace('_', '-', $v) : $v;
                }, $this->amenities)
            ]);
        }
    }

    public function rules(): array
    {
        $listingType  = $this->input('listing_type');
        $category     = $this->input('category');
        $propertyType = $this->input('property_type');
        $emirateId    = (int) $this->input('emirate_id', 0);
        $locationName = $this->input('location_name', '');
        
        $permitService = app(\App\Services\PropertyFinder\EmiratePermitService::class);
        $needsPermit   = $permitService->requiresPermit($emirateId, $locationName);

        return [

            // ── Core required fields ──────────────────────────────────────────

            // reference: User's custom CRM reference
            'reference'     => ['nullable', 'string', 'max:100'],

            // agent_id: PF agent ID integer (sent by frontend)
            'agent_id'      => ['required', 'integer', 'min:1'],

            // location_id: PF location ID from GET /locations
            'location_id'   => ['required', 'integer', 'min:1'],

            // emirate_id: PF API numeric emirate ID (1-7)
            'emirate_id'    => ['required', 'integer', Rule::in([1, 2, 3, 4, 5, 6, 7])],

            // listing_type: replaces 'purpose'
            'listing_type'  => ['required', Rule::in(['sale', 'rent'])],

            // category
            'category'      => ['required', Rule::in(['residential', 'commercial', 'off_plan'])],

            // property_type: replaces 'type'
            'property_type' => [
                'required',
                Rule::in(config('propertyfinder.property_types.all', [
                    'apartment', 'villa', 'townhouse', 'penthouse', 'hotel_apartment',
                    'land', 'office', 'retail', 'warehouse',
                ])),
            ],

            // price: required for all listings, must be > 0
            'price'         => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'price_currency'=> ['nullable', 'string', 'size:3'],

            // size_sqft: required for all listings per PF docs
            'size_sqft'     => ['required', 'numeric', 'min:1'],

            // title: 10-150 chars per PF API docs
            'title_en'      => ['required_without:title', 'string', 'min:10', 'max:150'],
            'title'         => ['required_without:title_en', 'string', 'min:10', 'max:150'],
            'title_ar'      => ['nullable', 'string', 'min:10', 'max:150'],

            // description: min 50 chars per PF API docs
            'description_en'=> ['required_without:description', 'string', 'min:50'],
            'description'   => ['required_without:description_en', 'string', 'min:50'],
            'description_ar'=> ['nullable', 'string', 'min:50'],

            // images: at least 1, max 30 per PF API docs
            'images'        => ['required', 'array', 'min:1', 'max:30'],
            'images.*'      => ['required'], // Each item can be a URL string OR a file object
            'images_files'  => ['nullable', 'array'],
            'images_files.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],

            // ── Section 4a: Emirate-based permit requirements ─────────────────

            // permit_number: dynamic requirement based on Emirate and exempt areas
            'permit_number' => [
                Rule::requiredIf($needsPermit),
                'nullable', 'string', 'max:100',
            ],
            
            // permit_license_number & permit_id (from compliance API)
            'permit_license_number' => ['nullable', 'string', 'max:100'],
            'permit_id'             => ['nullable', 'string', 'max:100'],
            'advertisement_number'  => ['nullable', 'string', 'max:100'],

            // building_name: required for Dubai(1) and Abu Dhabi(2)
            'building_name' => [
                Rule::requiredIf(in_array($emirateId, [1, 2], true)),
                'nullable', 'string', 'max:255',
            ],

            // ── Section 4b: Listing type dependencies ─────────────────────────

            // rent_frequency: required_if listing_type = rent
            'rent_frequency' => [
                Rule::requiredIf($listingType === 'rent'),
                'nullable',
                Rule::in(['yearly', 'monthly', 'weekly', 'daily']),
            ],

            // cheques: optional, 1-12
            'cheques' => ['nullable', 'integer', 'min:1', 'max:12'],

            // available_from: optional ISO date
            'available_from' => ['nullable', 'date', 'date_format:Y-m-d'],

            // ownership_type: required_if listing_type = sale
            'ownership_type' => [
                Rule::requiredIf($listingType === 'sale'),
                'nullable',
                Rule::in(['freehold', 'leasehold']),
            ],

            // ── Section 4b: Category dependencies ────────────────────────────

            // bedrooms: required_if category = residential (0 = studio)
            'bedrooms' => [
                Rule::requiredIf($category === 'residential'),
                'nullable', 'integer', 'min:0', 'max:20',
            ],

            // bathrooms: required_if category = residential
            'bathrooms' => [
                Rule::requiredIf($category === 'residential'),
                'nullable', 'integer', 'min:1', 'max:20',
            ],

            // off_plan required fields
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

            // floor_number: recommended for apartment/penthouse
            'floor_number' => ['nullable', 'integer', 'min:0'],

            // number_of_floors: recommended for villa/townhouse
            'number_of_floors' => ['nullable', 'integer', 'min:1'],

            // private_pool: required_if penthouse (defaults false)
            'private_pool' => ['nullable', 'boolean'],

            // hotel_name: required_if hotel_apartment
            'hotel_name' => [
                Rule::requiredIf($propertyType === 'hotel_apartment'),
                'nullable', 'string', 'max:255',
            ],

            // plot_size_sqft: required_if villa|townhouse|land
            'plot_size_sqft' => [
                Rule::requiredIf(in_array($propertyType, ['villa', 'townhouse', 'land'], true)),
                'nullable', 'numeric', 'min:1',
            ],

            // zoning_type: required_if land
            'zoning_type' => [
                Rule::requiredIf($propertyType === 'land'),
                'nullable',
                Rule::in(['residential', 'commercial', 'mixed', 'industrial']),
            ],

            // fitted: required_if office|retail|warehouse
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

            // project_status (optional, useful for off_plan)
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