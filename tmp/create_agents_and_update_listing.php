<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Company;
use App\Models\User;
use App\Models\PropertyFinderListing;
use Illuminate\Support\Str;

$company = Company::where('slug', 'vortexweb')->first();

if (!$company) {
    echo "vortexweb company not found.\n";
    exit(1);
}

$agentsData = [
    [
        'email' => "muhammed.fasil@vortexweb.org",
        'id' => 276041,
        'firstName' => "Muhammed",
        'lastName' => "Fasil"
    ],
    [
        'email' => "aaryan@vortexweb.cloud",
        'id' => 606208,
        'firstName' => "aaryan",
        'lastName' => "daboria"
    ],
    [
        'email' => "abhijeet.sarkar@vortexweb.org",
        'id' => 606332,
        'firstName' => "ABHIJEET",
        'lastName' => "SARKAR"
    ]
];

foreach ($agentsData as $data) {
    $user = User::updateOrCreate(
        ['email' => $data['email']],
        [
            'company_id' => $company->id,
            'name' => $data['firstName'] . ' ' . $data['lastName'],
            'password' => bcrypt(Str::random(16)),
            'pf_agent_id' => (string)$data['id'],
            'is_active' => true,
        ]
    );
    echo "Synced User: " . $user->email . " with PF ID: " . $data['id'] . "\n";
}

// Update the draft listing to use Muhammed Fasil
$agent = User::where('email', "muhammed.fasil@vortexweb.org")->first();
$listing = PropertyFinderListing::where('company_id', $company->id)->first();

if ($agent && $listing) {
    $listing->update(['agent_id' => $agent->id]);
    echo "Updated Listing " . $listing->id . " to agent: " . $agent->email . "\n";
}
