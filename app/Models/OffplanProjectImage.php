<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class OffplanProjectImage extends Model
{
   use HasUuids;
   protected $fillable = ['project_id', 'url'];
}
