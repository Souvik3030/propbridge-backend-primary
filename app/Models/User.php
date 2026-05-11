<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Traits\Tenantable;
use App\Models\Company;
use Illuminate\Database\Eloquent\Casts\Attribute;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, HasRoles, HasUuids, Tenantable; 

    protected $fillable = [
        'company_id', 
        'pf_agent_id',
        'name', 
        'email', 
        'password', 
        'phone', 
        'brn', 
        'is_active', 
        'email_verified_at', 
        'last_login_at'
    ];

    protected $hidden = [
        'password', 
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    protected function role(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->roles->first()?->name,
        );
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    protected static function booted(): void
    {
        // 🔥 FAANG FIX: Instantly bust the dashboard cache when a user changes
        $clearDashboardCache = fn () => \Illuminate\Support\Facades\Cache::forget('superadmin_dashboard_stats');
        
        static::created($clearDashboardCache);
        static::updated($clearDashboardCache);
        static::deleted($clearDashboardCache);
    }
}