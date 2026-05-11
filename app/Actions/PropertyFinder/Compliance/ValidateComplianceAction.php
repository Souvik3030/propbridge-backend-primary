<?php

declare(strict_types=1);

namespace App\Actions\PropertyFinder\Compliance;

use App\Models\PropertyFinderListing;

/**
 * Local pre-validation before submitting to PF API.
 *
 * This runs WITHOUT making any API call — it checks the listing
 * data against known PF API compliance error codes so you can
 * catch obvious issues before wasting an API call.
 *
 * PF API Compliance Error Codes handled:
 *  PERMIT_MISSING     — permit_number not provided for this emirate
 *  IMAGE_MIN          — fewer than 1 image
 *  DESCRIPTION_SHORT  — description < 50 chars
 *  PRICE_ZERO         — price is 0 or missing
 *  AGENT_INACTIVE     — assigned agent is not active (is_active = false)
 *  BEDROOMS_MISSING   — residential listing without bedroom count
 */
class ValidateComplianceAction
{
    /**
     * Pre-validate a listing and return any local compliance errors.
     *
     * @return array  List of local error strings (empty = no issues found locally)
     */
    public function execute(PropertyFinderListing $listing): array
    {
        $errors   = [];
        $warnings = [];

        $listingType  = $listing->listing_type ?? $listing->purpose;
        $category     = $listing->category;
        $propertyType = $listing->property_type ?? $listing->type;
        $emirateId    = (int) $listing->emirate_id;

        // ── PERMIT checks ──────────────────────────────────────────────────────

        // Dubai (1) and Abu Dhabi (2): permit always required
        if (in_array($emirateId, [1, 2], true) && empty($listing->permit_number)) {
            $errors[] = 'PERMIT_MISSING: permit_number is required for this emirate.';
        }

        // Dubai (1) + sale: DLD permit required
        if ($emirateId === 1 && $listingType === 'sale' && empty($listing->dld_permit_number)) {
            $errors[] = 'PERMIT_MISSING: dld_permit_number is required for Dubai sale listings.';
        }

        // Ajman (4) + sale: permit required
        if ($emirateId === 4 && $listingType === 'sale' && empty($listing->permit_number)) {
            $errors[] = 'PERMIT_MISSING: permit_number is required for Ajman sale listings (ARRA).';
        }

        // RAK (5) + sale: permit required
        if ($emirateId === 5 && $listingType === 'sale' && empty($listing->permit_number)) {
            $errors[] = 'PERMIT_MISSING: permit_number is required for RAK sale listings.';
        }

        // Sharjah (3), Fujairah (6), UAQ (7): permit is optional — warn only
        if (in_array($emirateId, [3, 6, 7], true) && empty($listing->permit_number)) {
            $warnings[] = 'PERMIT_RECOMMENDED: permit_number is optional for this emirate but improves listing quality.';
        }

        // ── IMAGE check ────────────────────────────────────────────────────────

        $images = $listing->images ?? [];
        if (empty($images) || count($images) < 1) {
            $errors[] = 'IMAGE_MIN: At least 1 image is required for publication. 3+ recommended.';
        }

        // ── DESCRIPTION check ──────────────────────────────────────────────────

        $description = $listing->description_en ?? '';
        if (strlen($description) < 50) {
            $errors[] = 'DESCRIPTION_SHORT: Description must be at least 50 characters (currently ' . strlen($description) . ' chars).';
        }

        // ── PRICE check ────────────────────────────────────────────────────────

        if (empty($listing->price) || (float) $listing->price <= 0) {
            $errors[] = 'PRICE_ZERO: Price must be greater than 0.';
        }

        // ── AGENT check ────────────────────────────────────────────────────────

        if ($listing->agent && $listing->agent->is_active === false) {
            $errors[] = 'AGENT_INACTIVE: Assigned agent is inactive. Reassign to an active agent.';
        }

        if ($listing->agent && empty($listing->agent->pf_agent_id)) {
            $warnings[] = 'AGENT_NO_PF_ID: Assigned agent does not have a PropertyFinder agent ID. Run agent sync first.';
        }

        // ── BEDROOMS check ─────────────────────────────────────────────────────

        if ($category === 'residential' && !isset($listing->bedrooms)) {
            $errors[] = 'BEDROOMS_MISSING: bedrooms is required for residential listings (use 0 for studio).';
        }

        // ── BUILDING NAME check ────────────────────────────────────────────────

        if (in_array($emirateId, [1, 2], true) && empty($listing->building_name)) {
            $warnings[] = 'BUILDING_NAME: building_name is required for Dubai and Abu Dhabi listings.';
        }

        // ── OWNERSHIP TYPE check ────────────────────────────────────────────────

        if ($listingType === 'sale' && empty($listing->ownership_type)) {
            $warnings[] = 'OWNERSHIP_TYPE: ownership_type is required for sale listings.';
        }

        // ── RENT FREQUENCY check ───────────────────────────────────────────────

        if ($listingType === 'rent' && empty($listing->rent_frequency)) {
            $errors[] = 'RENT_FREQUENCY_MISSING: rent_frequency is required for rent listings.';
        }

        return [
            'errors'   => $errors,
            'warnings' => $warnings,
            'is_valid' => empty($errors),
        ];
    }
}