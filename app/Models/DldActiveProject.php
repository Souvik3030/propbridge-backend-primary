<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DldActiveProject extends Model
{
    protected $fillable = [
        'project_name',
        'developer_id',
        'area_name',
        'units_count',
        'completion_percentage',
        'estimated_end_date',
        'escrow_status',
        'is_active',
    ];

    public function developer()
    {
        return $this->belongsTo(DldDeveloper::class, 'developer_id');
    }

    public function transactions()
    {
        return $this->hasMany(DldTransaction::class, 'project_id');
    }
}
