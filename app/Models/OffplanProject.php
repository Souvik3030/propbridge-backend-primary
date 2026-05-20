<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class OffplanProject extends Model
{
    use HasUuids;

    protected $fillable = [
        'source', 'source_id', 'location_id', 'developer_id',
        'reference_number',
        'title', 'title_ar', 'description', 'slug',
        'price', 'price_max',
        'area_min', 'area_max', 'area_built_up', 'area_unit',
        'bedrooms', 'bathrooms', 'is_furnished', 'rooms', 'units_count',
        'type_main', 'type_sub', 'purpose',
        'completion_status', 'completion_date', 'permit_number', 'bayut_url',
        'amenities', 'keywords', 'amenities_ar', 'keywords_ar', 'payment_plans', 'documents',
        'agency_payload', 'agent_payload', 'verification_payload', 'legal_payload', 'offplan_payload', 'raw_payload',
        'has_ads', 'property_ad_count',
        'investment_score', 'estimated_yield',
        'dld_avg_price_sqft', 'dld_transactions_count',
    ];

    protected $casts = [
        'amenities'     => 'array',
        'keywords'      => 'array',
        'amenities_ar'  => 'array',
        'keywords_ar'   => 'array',
        'payment_plans' => 'array',
        'documents'     => 'array',
        'agency_payload' => 'array',
        'agent_payload' => 'array',
        'verification_payload' => 'array',
        'legal_payload' => 'array',
        'offplan_payload' => 'array',
        'raw_payload' => 'array',
        'rooms'         => 'array',
        'completion_date' => 'date',
        'is_furnished' => 'boolean',
    ];

    protected $with = ['images', 'location'];

    public function location()
    {
        return $this->belongsTo(OffplanLocation::class);
    }

    public function developer()
    {
        return $this->belongsTo(OffplanDeveloper::class);
    }

    public function images()
    {
        return $this->hasMany(OffplanProjectImage::class, 'project_id');
    }
}
