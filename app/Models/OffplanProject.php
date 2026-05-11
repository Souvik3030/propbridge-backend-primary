<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class OffplanProject extends Model
{
    use HasUuids;
    protected $fillable = [
        'source_id', 'location_id', 'developer_id', 'title',
        'price', 'bedrooms', 'type_main', 'type_sub',
        'amenities', 'payment_plans', 'purpose'
    ];

    protected $casts = [
        'amenities' => 'array',
        'payment_plans' => 'array',
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
