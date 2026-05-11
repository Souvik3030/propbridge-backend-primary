<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Property Finder Atlas API v1 Configuration
    |--------------------------------------------------------------------------
    | Base URL: https://atlas.propertyfinder.com
    | Auth: POST /v1/auth/token with apiKey/apiSecret, then Bearer JWT
    |--------------------------------------------------------------------------
    */

    'api' => [
        'base_url'    => env('PROPERTYFINDER_API_BASE_URL', 'https://atlas.propertyfinder.com'),
        'timeout'     => env('PROPERTYFINDER_API_TIMEOUT', 30),
        'retry_times' => 3,
        'retry_sleep' => 1000, // ms
        'rate_limit'  => 60,   // requests per minute
    ],

    'webhook' => [
        'secret'    => env('PROPERTYFINDER_WEBHOOK_SECRET'),
        'tolerance' => 300, // seconds — replay attack window
        // Header used by PF for signature: X-PropertyFinder-Signature
        // Header used by PF for timestamp: X-PropertyFinder-Timestamp
    ],

    /*
    |--------------------------------------------------------------------------
    | UAE Emirates — All 7, with PF API IDs matching the official docs
    |--------------------------------------------------------------------------
    | IDs: Dubai=1, Abu Dhabi=2, Sharjah=3, Ajman=4, RAK=5, Fujairah=6, UAQ=7
    |--------------------------------------------------------------------------
    */
    'emirates' => [
        1 => [
            'id'               => 1,
            'key'              => 'dubai',
            'label'            => 'Dubai',
            'authority'        => 'DLD / RERA',
            'requires_permit'  => true,
            'body'             => 'DLD',
            'exempt_areas'     => ['DIFC', 'JAFZA'],
        ],
        2 => [
            'id'               => 2,
            'key'              => 'abu_dhabi',
            'label'            => 'Abu Dhabi',
            'authority'        => 'ADREC / ADM',
            'requires_permit'  => true,
            'body'             => 'ADREC',
            'exempt_areas'     => [],
        ],
        3 => [
            'id'               => 3,
            'key'              => 'sharjah',
            'label'            => 'Sharjah',
            'authority'        => 'SAAR',
            'requires_permit'  => false,
            'body'             => null,
            'exempt_areas'     => [],
        ],
        4 => [
            'id'               => 4,
            'key'              => 'ajman',
            'label'            => 'Ajman',
            'authority'        => 'ARRA',
            'requires_permit'  => false,
            'body'             => null,
            'exempt_areas'     => [],
        ],
        5 => [
            'id'               => 5,
            'key'              => 'ras_al_khaimah',
            'label'            => 'Ras Al Khaimah',
            'authority'        => 'RAK Municipality',
            'requires_permit'  => false,
            'body'             => null,
            'exempt_areas'     => [],
        ],
        6 => [
            'id'               => 6,
            'key'              => 'fujairah',
            'label'            => 'Fujairah',
            'authority'        => 'Fujairah Municipality',
            'requires_permit'  => false,
            'body'             => null,
            'exempt_areas'     => [],
        ],
        7 => [
            'id'               => 7,
            'key'              => 'umm_al_quwain',
            'label'            => 'Umm Al Quwain',
            'authority'        => 'UAQ Municipality',
            'requires_permit'  => false,
            'body'             => null,
            'exempt_areas'     => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Listing Types (PF API v2: listing_type field)
    |--------------------------------------------------------------------------
    */
    'listing_types' => ['sale', 'rent'],

    /*
    |--------------------------------------------------------------------------
    | Categories (PF API v2: category field)
    |--------------------------------------------------------------------------
    */
    'categories' => ['residential', 'commercial', 'off_plan'],

    /*
    |--------------------------------------------------------------------------
    | Property Types per Category (PF API v2: property_type field)
    |--------------------------------------------------------------------------
    | Matches Section 4c of the PF API docs exactly.
    |--------------------------------------------------------------------------
    */
    'property_types' => [
        'residential' => [
            'apartment',
            'villa',
            'townhouse',
            'penthouse',
            'hotel_apartment',
        ],
        'commercial' => [
            'office',
            'retail',
            'warehouse',
        ],
        'off_plan' => [
            'apartment',
            'villa',
            'townhouse',
            'penthouse',
        ],
        // Flat list for any-category validation
        'all' => [
            'apartment',
            'villa',
            'townhouse',
            'penthouse',
            'hotel_apartment',
            'land',
            'office',
            'retail',
            'warehouse',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rent Frequency Options
    |--------------------------------------------------------------------------
    */
    'rent_frequencies' => ['yearly', 'monthly', 'weekly', 'daily'],

    /*
    |--------------------------------------------------------------------------
    | Furnished Options
    |--------------------------------------------------------------------------
    */
    'furnished_options' => ['furnished', 'unfurnished', 'partly_furnished'],

    /*
    |--------------------------------------------------------------------------
    | Ownership Type Options (for sale listings)
    |--------------------------------------------------------------------------
    */
    'ownership_types' => ['freehold', 'leasehold'],

    /*
    |--------------------------------------------------------------------------
    | Zoning Types (for land/plot listings)
    |--------------------------------------------------------------------------
    */
    'zoning_types' => ['residential', 'commercial', 'mixed', 'industrial'],

    /*
    |--------------------------------------------------------------------------
    | Fitted Options (for office/retail/warehouse)
    |--------------------------------------------------------------------------
    */
    'fitted_options' => ['yes', 'no', 'partially'],

    /*
    |--------------------------------------------------------------------------
    | Listing Statuses (local — matching PF API v2 statuses)
    |--------------------------------------------------------------------------
    */
    'listing_statuses' => [
        'draft'              => 'Draft — created locally, not yet submitted to PF',
        'active'             => 'Active — live on propertyfinder.ae',
        'under_review'       => 'Under Review — queued for PF manual QA',
        'inactive'           => 'Inactive — unpublished from PF',
        'compliance_failed'  => 'Compliance Failed — failed PF compliance check',
    ],

    /*
    |--------------------------------------------------------------------------
    | Unpublish Reasons (PF API v2 accepted values)
    |--------------------------------------------------------------------------
    */
    'unpublish_reasons' => [
        'sold',
        'rented',
        'duplicate',
        'incorrect_info',
        'temporary_hold',
        'other',
    ],

    /*
    |--------------------------------------------------------------------------
    | PF API v2 Compliance Error Codes
    |--------------------------------------------------------------------------
    */
    'compliance_error_codes' => [
        'PERMIT_MISSING'      => 'Permit number not provided for this emirate.',
        'PERMIT_EXPIRED'      => 'Permit number found but has expired.',
        'PERMIT_INVALID'      => 'Permit number not found in authority database.',
        'IMAGE_MIN'           => 'Fewer than the required minimum images uploaded.',
        'DESCRIPTION_SHORT'   => 'Description is less than 50 characters.',
        'PRICE_ZERO'          => 'Price field is 0 or missing.',
        'AGENT_INACTIVE'      => 'Assigned agent is no longer active.',
        'BEDROOMS_MISSING'    => 'Residential listing has no bedroom count.',
    ],

];
