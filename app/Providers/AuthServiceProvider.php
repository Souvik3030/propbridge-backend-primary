<?php

namespace App\Providers;

use App\Models\PropertyFinderListing;
use App\Policies\PropertyFinderListingPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        PropertyFinderListing::class => PropertyFinderListingPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}