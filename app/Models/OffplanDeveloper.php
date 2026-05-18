<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class OffplanDeveloper extends Model
{
    use HasUuids;

    protected $fillable = ['source_id', 'name', 'logo', 'project_count'];

    public function projects()
    {
        return $this->hasMany(OffplanProject::class, 'developer_id');
    }
}
