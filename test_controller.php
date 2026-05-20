<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$request = Illuminate\Http\Request::create('/api/propertyfinder/locations', 'GET', ['search' => 'dubai marina']);
$user = App\Models\User::first();
$request->setUserResolver(function () use ($user) { return $user; });

$controller = new App\Http\Controllers\Api\PropertyFinderListingController();
$client = new App\Services\PropertyFinderApiClient();
$response = $controller->locations($request, $client);
file_put_contents('test_controller_res.json', $response->getContent());
