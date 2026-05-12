<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// We don't need a real listing or company, just the action to test payload building
$action = app(\App\Actions\PropertyFinder\Listing\CreateListingAction::class);

$data = [
    'agent_id' => 12345,
    'location_id' => 184,
    'listing_type' => 'sale',
    'category' => 'residential',
    'property_type' => 'apartment',
    'price' => 10000,
    'price_currency' => 'AED',
    'size_sqft' => 177.95,
    'title_en' => 'test listing title',
    'description_en' => 'test description that is long enough to pass any local validation if it were to run',
    'bedrooms' => 1,
    'bathrooms' => 1,
    'images' => ['https://example.com/img.jpg'],
    'reference' => 'VW-123',
    'emirate_id' => 3,
    'amenities' => ['Central AC', 'Built-in Wardrobes', 'private-gym', 'Maids Room']
];

// Call buildPfPayload directly via Reflection
$reflection = new ReflectionClass($action);
$method = $reflection->getMethod('buildPfPayload');
$method->setAccessible(true);

// Mock user if needed, but buildPfPayload only uses agent?->pf_agent_id
$user = new \App\Models\User();
$user->pf_agent_id = 12345;

$payload = $method->invoke($action, $data, $user);

echo "--- CREATION PAYLOAD ---\n";
echo json_encode($payload, JSON_PRETTY_PRINT) . "\n\n";

// Test Update payload
$updateAction = app(\App\Actions\PropertyFinder\Listing\UpdateListingAction::class);
$updateReflection = new ReflectionClass($updateAction);
$updateMethod = $updateReflection->getMethod('buildPfUpdatePayload');
$updateMethod->setAccessible(true);

$updateData = [
    'listing_type' => 'rent',
    'property_type' => 'villa',
    'price' => 15000,
];

$updatePayload = $updateMethod->invoke($updateAction, $updateData);

echo "--- UPDATE PAYLOAD ---\n";
echo json_encode($updatePayload, JSON_PRETTY_PRINT) . "\n";
