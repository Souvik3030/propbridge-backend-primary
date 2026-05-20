<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DldDeveloper extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'license_number',
        'registration_date',
        'expiry_date',
        'phone_number',
    ];

    public function activeProjects()
    {
        return $this->hasMany(DldActiveProject::class, 'developer_id');
    }
}
