<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$company = App\Models\Company::first();
$client = new App\Services\PropertyFinderApiClient();
$locations = $client->get($company, 'locations', ['search' => 'dubai']);
echo json_encode($locations['data'][0], JSON_PRETTY_PRINT);
