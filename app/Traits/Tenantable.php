<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use App\Models\Company;

trait Tenantable
{
    protected static function bootTenantable(): void
    {
        // 1. READ PROTECTION (Global Scope)
        static::addGlobalScope('company', function (Builder $builder) {
            
            // Bypass 1: Console/CLI Commands
            if (app()->runningInConsole() && !app()->bound('current_tenant_id')) {
                return;
            }

            // 🔥 THE INFINITE LOOP KILLER (Semaphore Lock) 🔥
            // Yeh ensure karega ki auth()->user() resolve hote waqt scope loop mein na phase
            static $isResolvingAuth = false;

            if ($isResolvingAuth) {
                return; // Agar user resolve ho raha hai, toh scope ko chup chap bypass karne do
            }

            $isResolvingAuth = true; // Lock lagao
            $user = auth()->user();  // Safely user fetch karo (ab yeh loop nahi banayega)
            $isResolvingAuth = false; // Lock khol do

            // Bypass 2: Agar user login nahi hai (Guest) ya Superadmin hai
            if (!$user || $user->hasRole('superadmin')) {
                return;
            }

            // Strict Enforcement for Logged-In Tenant Users
            $companyId = $user->company_id;
            
            if ($companyId) {
                // Table name prefix kiya taaki aage chalkar JOIN queries crash na hon
                $builder->where($builder->getModel()->getTable() . '.company_id', $companyId);
            } else {
                $builder->whereRaw('1 = 0'); // Fallback: Agar kisi user ki company udh gayi ho toh data block kar do
            }
        });

        // 2. WRITE PROTECTION (Creating Event)
        static::creating(function ($model) {
            if (app()->runningInConsole() && !app()->bound('current_tenant_id')) {
                return;
            }

            static $isResolvingAuth = false;
            
            if (!$isResolvingAuth) {
                $isResolvingAuth = true;
                $user = auth()->user();
                $isResolvingAuth = false;

                if ($user && !$user->hasRole('superadmin')) {
                    $model->company_id ??= $user->company_id;
                }
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}