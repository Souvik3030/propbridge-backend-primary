<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class OffplanLocation extends Model
{

    use HasUuids;
    protected $fillable = [
        'country',
        'city',
        'community',
        'sub_community',
        'cluster',
        'lat',
        'lng'
    ];
}
