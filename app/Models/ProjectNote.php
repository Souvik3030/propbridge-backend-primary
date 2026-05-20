<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ProjectNote extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'offplan_project_id',
        'content',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function project()
    {
        return $this->belongsTo(OffplanProject::class, 'offplan_project_id');
    }
}
