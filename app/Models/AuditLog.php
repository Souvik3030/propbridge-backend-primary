<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    const UPDATED_AT = null; 

    protected $guarded = [];

    protected $casts = [
        'changes' => 'array', 
    ];

    /**
     * The user (admin) who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Polymorphic relation to get the target (User, Company, Listing, etc.)
     */
    public function resource(): MorphTo
    {
        return $this->morphTo();
    }
}