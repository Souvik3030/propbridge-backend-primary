<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$action = app(\App\Actions\PropertyFinder\Listing\CreateListingAction::class);
$user = \App\Models\User::first();
if (!$user) {
    echo "No user found\n"; exit;
}
$company = $user->company;

$data = [
    'agent_id' => $user->id,
    'location_id' => 184,
    'listing_type' => 'sale',
    'category' => 'residential',
    'property_type' => 'apartment',
    'price' => 10000,
    'price_currency' => 'AED',
    'size_sqft' => 177.95,
    'title_en' => 'test listing',
    'description_en' => 'test desc',
    'bedrooms' => 1,
    'bathrooms' => 1,
    'images' => ['http://example.com/img.jpg'],
    'reference' => 'VW-123',
    'emirate_id' => 3
];

// Call buildPfPayload directly via Reflection
$reflection = new ReflectionClass($action);
$method = $reflection->getMethod('buildPfPayload');
$method->setAccessible(true);
$payload = $method->invoke($action, $data, $user);

echo json_encode($payload, JSON_PRETTY_PRINT);
