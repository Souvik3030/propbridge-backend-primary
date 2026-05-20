<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Requests\PropertyFinder\StoreListingRequest;
use Illuminate\Support\Facades\Validator;

$validAmenities = config('propertyfinder.amenities');

$testCases = [
    [
        'name' => 'Valid amenities',
        'data' => ['amenities' => ['central-ac', 'security']],
        'expected' => true
    ],
    [
        'name' => 'Invalid amenities',
        'data' => ['amenities' => ['invalid-amenity', 'central-ac']],
        'expected' => false
    ],
];

$request = new StoreListingRequest();
$rules = $request->rules();

// We only care about amenities for this test
$amenityRules = [
    'amenities' => $rules['amenities'],
    'amenities.*' => $rules['amenities.*'],
];

foreach ($testCases as $case) {
    $validator = Validator::make($case['data'], $amenityRules);
    $passes = $validator->passes();
    echo "Test Case: {$case['name']}\n";
    echo "Data: " . json_encode($case['data']) . "\n";
    echo "Passes: " . ($passes ? 'YES' : 'NO') . "\n";
    if (!$passes) {
        echo "Errors: " . json_encode($validator->errors()->all()) . "\n";
    }
    echo "-------------------\n";
}
