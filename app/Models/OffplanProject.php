<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class OffplanProject extends Model
{
    use HasUuids;

    protected $fillable = [
        'source', 'source_id', 'location_id', 'developer_id',
        'title', 'description', 'slug',
        'price', 'price_max',
        'area_min', 'area_max', 'area_built_up',
        'bedrooms', 'rooms', 'units_count',
        'type_main', 'type_sub', 'purpose',
        'completion_status', 'completion_date',
        'amenities', 'payment_plans', 'documents',
        'has_ads', 'property_ad_count',
        'investment_score', 'estimated_yield',
        'dld_avg_price_sqft', 'dld_transactions_count',
    ];

    protected $casts = [
        'amenities'     => 'array',
        'payment_plans' => 'array',
        'documents'     => 'array',
        'rooms'         => 'array',
        'completion_date' => 'date',
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
