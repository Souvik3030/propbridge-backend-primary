<?php

use App\Models\Company;
use Illuminate\Support\Facades\Crypt;
use App\Services\PropertyFinderApiClient;

$apiKey = 'gIDlY.wEW4iYfLrSmjYZLiTAlrXdr0RndLq4ZLyA';
$apiSecret = 'lWxDyziUJSqnBE89Pi24NIEPUv6uiYVK';

echo "Updating credentials for 'superadmin' and 'vortwexweb' companies...\n";

// Find companies by name
$companies = Company::where('name', 'like', '%superadmin%')
    ->orWhere('name', 'like', '%vortexweb%')
    ->get();

if ($companies->isEmpty()) {
    echo "No companies found matching 'superadmin' or 'vortexweb'.\n";
    return;
}

foreach ($companies as $company) {
    echo "Updating company: {$company->name} (ID: {$company->id})\n";
    
    $company->pf_client_id = $apiKey;
    $company->pf_client_secret = $apiSecret;
    $company->pf_enabled = true;
    $company->save();    
    echo "Credentials updated for {$company->name}.\n";

    echo "Testing PropertyFinder authentication for {$company->name}...\n";
    
    try {
        $client = app(PropertyFinderApiClient::class);
        
        // To force an auth token fetch and verify credentials, we will just call a private method via reflection
        // or just use GetPropertyFinderTokenAction. Wait, the token generation is done on first request.
        // Let's use the GetPropertyFinderTokenAction directly to ensure the token generation works.
        $tokenAction = app(\App\Actions\PropertyFinder\Auth\GetPropertyFinderTokenAction::class);
        $token = $tokenAction->execute($company);
        
        // Since the token is only used locally to get a JWT, wait, GetPropertyFinderTokenAction only returns the api key!
        // The token is actually fetched in PropertyFinderApiClient via getAccessToken().
        // Let's use reflection to call getAccessToken() to verify it works.
        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('getAccessToken');
        $method->setAccessible(true);
        $token = $method->invoke($client, $company);
        
        echo "SUCCESS! Token successfully generated for {$company->name}.\n";
        
        echo "SUCCESS! Authentication works for {$company->name}.\n";
        
    } catch (\App\Exceptions\PropertyFinder\PropertyFinderException $e) {
        echo "FAILED authentication for {$company->name}.\n";
        echo "Error: " . $e->getMessage() . "\n";
    } catch (\Exception $e) {
        echo "An unexpected error occurred for {$company->name}.\n";
        echo "Error: " . $e->getMessage() . "\n";
    }
    
    echo "----------------------------------------\n";
}

echo "Done.\n";
