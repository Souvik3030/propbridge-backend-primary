<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyFinderComplianceLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'company_id',
        'agent_id',
        'property_finder_listing_id',
        'emirate',
        'permit_number',
        'license_number',
        'status',
        'response_body',
        'diffs',
        'source',
    ];

    protected $casts = [
        'response_body' => 'array',
        'diffs' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(PropertyFinderListing::class, 'property_finder_listing_id');
    }
}
