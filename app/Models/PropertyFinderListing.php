<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PropertyFinderListing extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    // ─── PF API v2 Status Constants ────────────────────────────────────────────
    const STATUS_DRAFT             = 'draft';
    const STATUS_ACTIVE            = 'active';
    const STATUS_UNDER_REVIEW      = 'under_review';
    const STATUS_INACTIVE          = 'inactive';
    const STATUS_COMPLIANCE_FAILED = 'compliance_failed';

    // ─── Listing Type Constants ─────────────────────────────────────────────────
    const TYPE_SALE = 'sale';
    const TYPE_RENT = 'rent';

    protected $fillable = [
        // Core identifiers
        'company_id',
        'agent_id',
        'pf_id',
        'pf_reference',
        'pf_listing_url',

        // Location
        'location_id',     // PF API location_id from GET /locations
        'emirate',         // human-readable key (legacy compat)
        'emirate_id',      // PF API numeric ID (1-7)

        // Permit & compliance
        'permit_number',
        'permit_type',
        'license_number',
        'building_name',

        // Classification
        'listing_type',    // sale | rent  (replaces 'purpose')
        'property_type',   // apartment | villa | townhouse | etc. (replaces 'type')
        'category',        // residential | commercial | off_plan
        'project_status',  // off_plan | off_plan_primary | completed | completed_primary

        // Old fields kept for backward compat
        'purpose',
        'type',
        'pf_location_id',

        // Titles & descriptions
        'title_en',
        'title_ar',
        'description_en',
        'description_ar',

        // Pricing
        'price',
        'price_on_request',
        'ownership_type',

        // Dimensions
        'size',
        'size_unit',
        'plot_size_sqft',

        // Specs
        'bedrooms',
        'bathrooms',
        'floor_number',
        'number_of_floors',
        'private_pool',
        'hotel_name',
        'parking',
        'furnished',

        // Rental
        'rent_frequency',
        'cheques',
        'available_from',

        // Commercial
        'fitted',

        // Land
        'zoning_type',

        // Off-plan
        'developer_name',
        'project_name',
        'completion_date',
        'payment_plan',

        // Media
        'images',
        'amenities',
        'virtual_tour',
        'floor_plan',

        // Status & compliance
        'status',
        'is_exempt_area',
        'compliance_status',
        'can_publish',
        'compliance_snapshot',
        'validation_diffs',
        'last_compliance_check_at',
        'published_at',
        'unpublish_reason',
    ];

    protected $casts = [
        'images'                   => 'array',
        'amenities'                => 'array',
        'compliance_snapshot'      => 'array',
        'validation_diffs'         => 'array',
        'last_compliance_check_at' => 'datetime',
        'published_at'             => 'datetime',
        'price'                    => 'decimal:2',
        'size'                     => 'decimal:2',
        'plot_size_sqft'           => 'decimal:2',
        'private_pool'             => 'boolean',
        'price_on_request'         => 'boolean',
        'available_from'           => 'date',
        'completion_date'          => 'date',
        'bedrooms'                 => 'integer',
        'bathrooms'                => 'integer',
        'floor_number'             => 'integer',
        'parking'                  => 'integer',
        'cheques'                  => 'integer',
        'emirate_id'               => 'integer',
        'location_id'              => 'integer',
        'is_exempt_area'           => 'boolean',
        'can_publish'              => 'boolean',
    ];

    // ─── Relationships ──────────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function complianceLogs(): HasMany
    {
        return $this->hasMany(PropertyFinderComplianceLog::class);
    }

    // ─── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByEmirate($query, int $emirateId)
    {
        return $query->where('emirate_id', $emirateId);
    }

    // ─── Status Helpers ─────────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isUnderReview(): bool
    {
        return $this->status === self::STATUS_UNDER_REVIEW;
    }

    public function isInactive(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    public function isComplianceFailed(): bool
    {
        return $this->status === self::STATUS_COMPLIANCE_FAILED;
    }

    /**
     * A listing is compliant if:
     * - It has no validation diffs (local pre-validation passed)
     * - Its status is not compliance_failed
     * After running GET /listings/{id}/compliance, compliance_snapshot['compliant'] should be true.
     */
    public function isCompliant(): bool
    {
        // If we have a compliance snapshot from PF API, use that as the source of truth
        if (isset($this->compliance_snapshot['compliant'])) {
            return (bool) $this->compliance_snapshot['compliant'];
        }

        // Fall back to local validation
        return $this->status !== self::STATUS_COMPLIANCE_FAILED
            && empty($this->validation_diffs);
    }

    /**
     * A listing can be published if:
     * - It exists on PF (has a pf_id) OR it is a draft ready to be submitted
     * - Its local status is draft or inactive (not under_review — that resets the queue)
     * - It has passed compliance
     */
    public function canPublish(): bool
    {
        $publishableStatuses = [self::STATUS_DRAFT, self::STATUS_INACTIVE];

        return in_array($this->status, $publishableStatuses, true)
            && $this->isCompliant();
    }

    /**
     * Whether this listing's emirate requires a permit number.
     */
    public function requiresPermit(): bool
    {
        $emirateConfig = config("propertyfinder.emirates.{$this->emirate_id}", []);

        // Always required for Dubai (1) and Abu Dhabi (2)
        if (in_array($this->emirate_id, [1, 2], true)) {
            return true;
        }

        // Ajman (4) and RAK (5): required for sales
        if (in_array($this->emirate_id, [4, 5], true)) {
            return $this->listing_type === self::TYPE_SALE;
        }

        return $emirateConfig['permit_required'] ?? false;
    }

    /**
     * Whether this listing requires a building name (Dubai or Abu Dhabi).
     */
    public function requiresBuildingName(): bool
    {
        return in_array($this->emirate_id, [1, 2], true);
    }
}
