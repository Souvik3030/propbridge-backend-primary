<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DldTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_number',
        'instance_date',
        'group_en',
        'procedure_en',
        'is_offplan_en',
        'is_free_hold_en',
        'usage_en',
        'area_en',
        'prop_type_en',
        'prop_sb_type_en',
        'trans_value',
        'procedure_area',
        'actual_area',
        'rooms_en',
        'parking',
        'nearest_metro_en',
        'nearest_mall_en',
        'nearest_landmark_en',
        'total_buyer',
        'total_seller',
        'master_project_en',
        'project_en',
    ];
}
