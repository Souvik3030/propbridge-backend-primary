<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Company;
use App\Models\PropertyFinderListing;
use App\Models\User;

$company = Company::whereHas('users', function($q) {
    $q->whereHas('roles', function($rq) {
        $rq->where('name', 'superadmin');
    });
})->first();

if (!$company) {
    echo "No Superadmin Company found.\n";
    exit(1);
}

$company->update([
    'pf_client_id' => 'FRyNI.Ywuqfir6hKRwMLtHaifMk7YlXUJSDIfuFX',
    'pf_client_secret' => 'rmUJWrK9yPBmLAEpGpRN1da2R5XgbPIQ',
    'pf_enabled' => 1
]);

echo "Updated Company: " . $company->name . "\n";

// Create a draft listing
$superadmin = User::whereHas('roles', function($q) {
    $q->where('name', 'superadmin');
})->first();

$listing = PropertyFinderListing::create([
    'company_id' => $company->id,
    'agent_id' => $superadmin->id,
    'emirate' => 'dubai',
    'permit_number' => 'RERA-DRAFT-001',
    'license_number' => 'ORN-DRAFT-001',
    'permit_type' => 'rera',
    'pf_location_id' => 1,
    'category' => 'residential',
    'type' => 'apartment',
    'purpose' => 'sale',
    'project_status' => 'completed',
    'title_en' => 'Draft Listing for Testing',
    'description_en' => 'This is a draft listing created automatically for testing purposes.',
    'price' => 1000000,
    'size' => 1000,
    'size_unit' => 'sqft',
    'status' => 'draft',
    'images' => ['https://images.propertyfinder.ae/property/1.jpg']
]);

echo "Created Draft Listing ID: " . $listing->id . "\n";
