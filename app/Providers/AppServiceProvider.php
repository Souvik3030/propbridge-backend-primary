<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 🚀 FAANG FIX: Superadmin Bypass
        // Grant all permissions to users with the 'superadmin' role
        \Illuminate\Support\Facades\Gate::before(function ($user, $capability) {
            return $user->hasRole('superadmin') ? true : null;
        });

        \Illuminate\Support\Facades\RateLimiter::for('bayut-db-writes', function ($job) {
            return Limit::perSecond(5);
        });
    }
}
