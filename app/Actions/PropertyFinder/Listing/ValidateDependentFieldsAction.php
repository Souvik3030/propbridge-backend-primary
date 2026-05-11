<?php

declare(strict_types=1);

namespace App\Actions\PropertyFinder\Listing;

use App\Exceptions\PropertyFinder\PropertyFinderException;

/**
 * Validate all dependent fields per PF API v2 docs.
 *
 * Implements the complete dependency matrix from sections 4a, 4b, 4c:
 *  4a — Emirate-based dependencies (permit, building_name, DLD permit)
 *  4b — Listing type & category dependencies (rent_frequency, bedrooms, off-plan fields)
 *  4c — Property type dependencies (plot_size, private_pool, hotel_name, fitted, zoning)
 */
class ValidateDependentFieldsAction
{
    /**
     * Validate all dependent fields.
     *
     * @param  array $data  The full listing data (validated input)
     * @throws PropertyFinderException If required dependent fields are missing
     */
    public function execute(array $data): void
    {
        $errors = [];

        $emirateId   = (int) ($data['emirate_id'] ?? 0);
        $listingType = $data['listing_type'] ?? null;
        $category    = $data['category'] ?? null;
        $propertyType = $data['property_type'] ?? null;

        // ── Section 4a: Emirate-based Dependencies ───────────────────────────

        // Dubai (emirate_id = 1)
        if ($emirateId === 1) {
            if (empty($data['permit_number'])) {
                $errors[] = 'PERMIT_MISSING: permit_number is required for Dubai (RERA permit).';
            }
            if ($listingType === 'sale' && empty($data['dld_permit_number'])) {
                $errors[] = 'DLD_PERMIT_MISSING: dld_permit_number is required for Dubai sale listings.';
            }
            if (empty($data['building_name'])) {
                $errors[] = 'BUILDING_NAME_REQUIRED: building_name is required for Dubai listings.';
            }
            if ($category === 'off_plan' && empty($data['developer_name'])) {
                $errors[] = 'DEVELOPER_NAME_REQUIRED: developer_name is required for off-plan listings in Dubai.';
            }
        }

        // Abu Dhabi (emirate_id = 2)
        if ($emirateId === 2) {
            if (empty($data['permit_number'])) {
                $errors[] = 'PERMIT_MISSING: permit_number is required for Abu Dhabi (ADREC/ADM permit).';
            }
            if (empty($data['building_name'])) {
                $errors[] = 'BUILDING_NAME_REQUIRED: building_name is required for Abu Dhabi listings.';
            }
        }

        // Sharjah (emirate_id = 3): permit optional but recommended — no hard errors
        // handled by warnings in CheckComplianceAction

        // Ajman (emirate_id = 4): permit required for sales only
        if ($emirateId === 4 && $listingType === 'sale') {
            if (empty($data['permit_number'])) {
                $errors[] = 'PERMIT_MISSING: permit_number is required for Ajman sale listings (ARRA permit).';
            }
        }

        // Ras Al Khaimah (emirate_id = 5): permit required for sales, optional for rental
        if ($emirateId === 5 && $listingType === 'sale') {
            if (empty($data['permit_number'])) {
                $errors[] = 'PERMIT_MISSING: permit_number is required for RAK sale listings.';
            }
        }

        // Fujairah (6) and UAQ (7): permit optional — no errors

        // ── Section 4b: Listing Type Dependencies ────────────────────────────

        // Sale listings
        if ($listingType === 'sale') {
            if (empty($data['price']) || (float)$data['price'] <= 0) {
                $errors[] = 'PRICE_ZERO: price is required and must be greater than 0 for sale listings.';
            }
            if (empty($data['ownership_type'])) {
                $errors[] = 'OWNERSHIP_TYPE_REQUIRED: ownership_type is required for sale listings.';
            }
        }

        // Rent listings
        if ($listingType === 'rent') {
            if (empty($data['price']) || (float)$data['price'] <= 0) {
                $errors[] = 'PRICE_ZERO: price is required and must be greater than 0 for rent listings.';
            }
            if (empty($data['rent_frequency'])) {
                $errors[] = 'RENT_FREQUENCY_REQUIRED: rent_frequency is required for rent listings (yearly/monthly/weekly/daily).';
            }
        }

        // ── Section 4b: Category Dependencies ───────────────────────────────

        // Residential category
        if ($category === 'residential') {
            if (!isset($data['bedrooms'])) {
                $errors[] = 'BEDROOMS_MISSING: bedrooms is required for residential listings (use 0 for studio).';
            }
            if (empty($data['bathrooms'])) {
                $errors[] = 'BATHROOMS_MISSING: bathrooms is required for residential listings.';
            }
            if (empty($data['size_sqft']) && empty($data['size'])) {
                $errors[] = 'SIZE_REQUIRED: size_sqft is required for residential listings.';
            }
        }

        // Commercial category
        if ($category === 'commercial') {
            if (empty($data['size_sqft']) && empty($data['size'])) {
                $errors[] = 'SIZE_REQUIRED: size_sqft is required for commercial listings.';
            }
        }

        // Off-plan category
        if ($category === 'off_plan') {
            if (empty($data['developer_name'])) {
                $errors[] = 'DEVELOPER_NAME_REQUIRED: developer_name is required for off-plan listings.';
            }
            if (empty($data['project_name'])) {
                $errors[] = 'PROJECT_NAME_REQUIRED: project_name is required for off-plan listings.';
            }
            if (empty($data['completion_date'])) {
                $errors[] = 'COMPLETION_DATE_REQUIRED: completion_date is required for off-plan listings.';
            }
        }

        // available_from date
        if (isset($data['available_from']) && !empty($data['available_from'])) {
            if (!self::isValidDate($data['available_from'])) {
                $errors[] = 'AVAILABLE_FROM_INVALID: available_from must be a valid date in YYYY-MM-DD format.';
            }
        }

        // parking spaces
        if (isset($data['has_parking']) && $data['has_parking'] === true) {
            if (!isset($data['parking']) || (int)$data['parking'] < 1) {
                $errors[] = 'PARKING_SPACES_REQUIRED: parking spaces count required when has_parking is true.';
            }
        }

        // ── Section 4c: Property Type Dependencies ───────────────────────────

        // Apartment
        if ($propertyType === 'apartment') {
            if (!isset($data['bedrooms'])) {
                $errors[] = 'BEDROOMS_MISSING: bedrooms is required for apartment listings.';
            }
            if (empty($data['bathrooms'])) {
                $errors[] = 'BATHROOMS_MISSING: bathrooms is required for apartment listings.';
            }
            // floor_number is recommended but not hard-required
        }

        // Villa / Townhouse
        if (in_array($propertyType, ['villa', 'townhouse'], true)) {
            if (!isset($data['bedrooms'])) {
                $errors[] = 'BEDROOMS_MISSING: bedrooms is required for villa/townhouse listings.';
            }
            if (empty($data['bathrooms'])) {
                $errors[] = 'BATHROOMS_MISSING: bathrooms is required for villa/townhouse listings.';
            }
            if (empty($data['plot_size_sqft'])) {
                $errors[] = 'PLOT_SIZE_REQUIRED: plot_size_sqft is required for villa/townhouse listings.';
            }
            // number_of_floors is recommended
        }

        // Penthouse
        if ($propertyType === 'penthouse') {
            if (!isset($data['bedrooms'])) {
                $errors[] = 'BEDROOMS_MISSING: bedrooms is required for penthouse listings.';
            }
            if (empty($data['bathrooms'])) {
                $errors[] = 'BATHROOMS_MISSING: bathrooms is required for penthouse listings.';
            }
            if (empty($data['floor_number']) && $data['floor_number'] !== 0) {
                $errors[] = 'FLOOR_NUMBER_REQUIRED: floor_number is required for penthouse listings.';
            }
            // private_pool defaults to false, not required to be explicitly set
        }

        // Hotel Apartment
        if ($propertyType === 'hotel_apartment') {
            if (!isset($data['bedrooms'])) {
                $errors[] = 'BEDROOMS_MISSING: bedrooms is required for hotel apartment listings.';
            }
            if (empty($data['bathrooms'])) {
                $errors[] = 'BATHROOMS_MISSING: bathrooms is required for hotel apartment listings.';
            }
            if (empty($data['hotel_name'])) {
                $errors[] = 'HOTEL_NAME_REQUIRED: hotel_name is required for hotel_apartment listings.';
            }
        }

        // Land / Plot
        if ($propertyType === 'land') {
            if (empty($data['plot_size_sqft'])) {
                $errors[] = 'PLOT_SIZE_REQUIRED: plot_size_sqft is required for land/plot listings.';
            }
            if (empty($data['zoning_type'])) {
                $errors[] = 'ZONING_TYPE_REQUIRED: zoning_type is required for land/plot listings (residential/commercial/mixed/industrial).';
            }
        }

        // Office / Retail / Warehouse
        if (in_array($propertyType, ['office', 'retail', 'warehouse'], true)) {
            if (empty($data['size_sqft']) && empty($data['size'])) {
                $errors[] = 'SIZE_REQUIRED: size_sqft is required for office/retail/warehouse listings.';
            }
            if (empty($data['fitted'])) {
                $errors[] = 'FITTED_REQUIRED: fitted is required for office/retail/warehouse listings (yes/no/partially).';
            }
        }

        // ── Common fields ──────────────────────────────────────────────────────

        // Images
        if (empty($data['images']) || !is_array($data['images']) || count($data['images']) < 1) {
            $errors[] = 'IMAGE_MIN: At least one image is required.';
        }

        // Description
        $description = $data['description'] ?? $data['description_en'] ?? '';
        if (strlen($description) < 50) {
            $errors[] = 'DESCRIPTION_SHORT: description must be at least 50 characters.';
        }

        // Title
        $title = $data['title'] ?? $data['title_en'] ?? '';
        if (strlen($title) < 10 || strlen($title) > 150) {
            $errors[] = 'TITLE_INVALID: title must be between 10 and 150 characters.';
        }

        if (!empty($errors)) {
            throw new PropertyFinderException(
                'Dependent field validation failed: ' . implode(' | ', $errors),
                422,
                null,
                ['errors' => $errors]
            );
        }
    }

    private static function isValidDate(string $date): bool
    {
        return (bool) \DateTime::createFromFormat('Y-m-d', $date);
    }
}
