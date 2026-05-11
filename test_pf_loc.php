<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$client = new App\Services\PropertyFinderApiClient();
$res = $client->get(App\Models\Company::first(), 'locations', ['search' => 'dubai marina']);
file_put_contents('pf_locations.json', json_encode($res['data'], JSON_PRETTY_PRINT));
