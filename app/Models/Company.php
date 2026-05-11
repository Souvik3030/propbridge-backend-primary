<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasUuids;

    protected $fillable = [
        'name', 'license_number', 'domain', 'slug', 'logo_url', 'plan',
        // Property Finder Atlas API v1: API key/secret issue a JWT.
        'pf_api_token',
        'pf_client_id', 'pf_client_secret',
        // Shared
        'pf_webhook_secret', 'pf_enabled',
        'bitrix_oauth_token', 'settings', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'          => 'boolean',
            'pf_enabled'         => 'boolean',
            'settings'           => 'array',
            // PF API v2 token — encrypted at rest
            'pf_api_token'       => 'encrypted',
            // Legacy credentials
            'pf_client_secret'   => 'encrypted',
            'pf_webhook_secret'  => 'encrypted',
            'bitrix_oauth_token' => 'encrypted',
        ];
    }

    // 🔥 SINGLE BOOT METHOD: Contains both Slug Generation AND Cache Invalidation
    protected static function boot()
    {
        parent::boot();

        // 1. Logic for Slug Generation (Before creation)
        static::creating(function ($company) {
            if (empty($company->slug)) {
                $originalSlug = Str::slug($company->name);
                $slug = $originalSlug;
                $count = 1;

                while (self::withoutGlobalScopes()->where('slug', $slug)->exists()) {
                    $slug = "{$originalSlug}-{$count}";
                    $count++;
                }

                $company->slug = $slug;
            }
        });

        // 2. Logic for Cache Invalidation (After creation, update, or delete)
        $clearDashboardCache = fn () => \Illuminate\Support\Facades\Cache::forget('superadmin_dashboard_stats');

        static::created($clearDashboardCache);
        static::updated($clearDashboardCache);
        static::deleted($clearDashboardCache);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($q, $search) {
            $searchTerm = '%' . $search . '%';
            $q->where(function ($subQ) use ($searchTerm) {
                $subQ->where('name', 'like', $searchTerm)
                    ->orWhere('domain', 'like', $searchTerm);
            });
        });

        $query->when($filters['plan'] ?? null, function ($q, $plan) {
            $q->where('plan', $plan);
        });

        $query->when($filters['slug'] ?? null, function ($q, $slug) {
            $q->where('slug', $slug);
        });

        $query->when(isset($filters['status']), function ($q) use ($filters) {
            $isActive = $filters['status'] === 'active' ? 1 : 0;
            $q->where('is_active', $isActive);
        });
    }

    /**
     * Determine if PropertyFinder is enabled and configured for this company.
     * Atlas API v1 uses an API key and secret to issue a JWT.
     */
    public function hasPropertyFinderEnabled(): bool
    {
        if (!$this->pf_enabled) {
            return false;
        }

        return !empty($this->pf_client_id) && !empty($this->pf_client_secret);
    }

    /**
     * Get PropertyFinder credentials.
     * pf_client_id stores the Atlas apiKey and pf_client_secret stores the apiSecret.
     */
    public function getPropertyFinderCredentials(): ?array
    {
        if (!$this->hasPropertyFinderEnabled()) {
            return null;
        }

        return [
            'api_key'        => $this->pf_client_id,
            'api_secret'     => $this->pf_client_secret,
            'client_id'      => $this->pf_client_id,
            'client_secret'  => $this->pf_client_secret,
            'webhook_secret' => $this->pf_webhook_secret,
        ];
    }
}
