<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Company;
use App\Models\PropertyFinderListing;
use App\Actions\PropertyFinder\Auth\SyncPropertyFinderAgentsAction;
use App\Actions\PropertyFinder\Listing\PublishListingAction;

$company = Company::whereHas('users', function($q) {
    $q->whereHas('roles', function($rq) {
        $rq->where('name', 'superadmin');
    });
})->first();

if (!$company) {
    echo "No Superadmin Company found.\n";
    exit(1);
}

echo "Syncing agents for company: " . $company->name . "...\n";
$syncAction = app(SyncPropertyFinderAgentsAction::class);
$syncAction->execute($company);

// Now try to publish the listing
$listing = PropertyFinderListing::where('company_id', $company->id)
    ->where('status', 'draft')
    ->first();

if (!$listing) {
    echo "No draft listing found.\n";
    exit(1);
}

echo "Attempting to publish listing " . $listing->id . "...\n";
try {
    $publishAction = app(PublishListingAction::class);
    $listing = $publishAction->execute($listing);
    echo "SUCCESS! PropertyFinder ID: " . $listing->pf_id . "\n";
} catch (\Exception $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}
