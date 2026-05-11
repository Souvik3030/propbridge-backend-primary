<?php

use App\Models\Company;
use Illuminate\Support\Facades\Crypt;
use App\Services\PropertyFinderApiClient;

$apiKey = 'gIDlY.wEW4iYfLrSmjYZLiTAlrXdr0RndLq4ZLyA';
$apiSecret = 'lWxDyziUJSqnBE89Pi24NIEPUv6uiYVK';

echo "Updating credentials for 'superadmin' and 'vortwexweb' companies...\n";

// Find companies by name
$companies = Company::where('name', 'like', '%superadmin%')
    ->orWhere('name', 'like', '%vortwexweb%')
    ->get();

if ($companies->isEmpty()) {
    echo "No companies found matching 'superadmin' or 'vortwexweb'.\n";
    return;
}

foreach ($companies as $company) {
    echo "Updating company: {$company->name} (ID: {$company->id})\n";
    
    $company->pf_client_id = $apiKey;
    $company->pf_client_secret = Crypt::encrypt($apiSecret);
    $company->save();
    
    echo "Credentials updated for {$company->name}.\n";

    echo "Testing PropertyFinder authentication for {$company->name}...\n";
    
    try {
        $client = app(PropertyFinderApiClient::class);
        
        // Let's do a simple GET request to check authentication.
        // E.g., getting listing types, or just fetching a list of listings
        // We just need the auth to pass. If it fails, PropertyFinderException is thrown.
        // To force an auth token fetch, we can call a lightweight endpoint or just get auth token directly using reflection,
        // but the easiest is just a simple GET request that requires auth.
        // Using /v1/listings as a test (with limit=1)
        
        $response = $client->get($company, 'listings', ['limit' => 1]);
        
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
