<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invitation extends Model
{
    use Tenantable,SoftDeletes;
    protected $fillable = [
        'company_id', 'email', 'role', 'token', 
        'sent_at', 'expires_at', 'used_at'
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}