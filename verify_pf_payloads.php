<?php
require 'vendor/autoload.php';

// Mock dependencies for CreateListingAction
$validate = new class extends \App\Actions\PropertyFinder\Listing\ValidateDependentFieldsAction {
    public function __construct() {}
    public function execute(array $data): void {}
};
$client = new class extends \App\Services\PropertyFinderApiClient {
    public function __construct() {}
};
$log = new class extends \App\Actions\PropertyFinder\Compliance\LogComplianceCheckAction {
    public function __construct() {}
};

$createAction = new \App\Actions\PropertyFinder\Listing\CreateListingAction($validate, $client, $log);

$data = [
    'price' => 10000,
    'title_en' => 'Test Listing Title',
    'description_en' => 'This is a test description that is long enough to pass validation.',
    'bedrooms' => 2,
    'bathrooms' => 2,
    'location_id' => 123,
    'listing_type' => 'sale',
    'category' => 'residential',
    'property_type' => 'apartment',
    'images' => ['https://example.com/img1.jpg'],
];

$reflection = new ReflectionClass($createAction);
$method = $reflection->getMethod('buildPfPayload');
$method->setAccessible(true);
$payload = $method->invoke($createAction, $data, null);

echo "--- CreateListingAction Payload ---\n";
echo json_encode($payload, JSON_PRETTY_PRINT) . "\n\n";

// Test UpdateListingAction
$updateAction = new \App\Actions\PropertyFinder\Listing\UpdateListingAction($validate, $client);
$reflectionUpdate = new ReflectionClass($updateAction);
$methodUpdate = $reflectionUpdate->getMethod('buildPfUpdatePayload');
$methodUpdate->setAccessible(true);

$updateData = [
    'price' => 15000,
    'bedrooms' => 3,
    'title' => 'Updated Title',
];
$updatePayload = $methodUpdate->invoke($updateAction, $updateData);

echo "--- UpdateListingAction Payload ---\n";
echo json_encode($updatePayload, JSON_PRETTY_PRINT) . "\n";
