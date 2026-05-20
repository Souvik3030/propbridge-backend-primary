<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PropertyFinderListing;
use App\Actions\PropertyFinder\Listing\PublishListingAction;
use App\Exceptions\PropertyFinder\PropertyFinderException;

// Find the draft listing I just created
$listing = PropertyFinderListing::where('status', 'draft')
    ->where('permit_number', 'RERA-DRAFT-001')
    ->first();

if (!$listing) {
    echo "Listing not found.\n";
    exit(1);
}

echo "Attempting to publish Listing ID: " . $listing->id . " to Property Finder...\n";

try {
    $action = app(PublishListingAction::class);
    $updatedListing = $action->execute($listing);
    
    echo "SUCCESS!\n";
    echo "Property Finder ID (pf_id): " . $updatedListing->pf_id . "\n";
    echo "Status: " . $updatedListing->status . "\n";
    
} catch (PropertyFinderException $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    echo "HTTP Status: " . $e->getCode() . "\n";
} catch (\Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
}
