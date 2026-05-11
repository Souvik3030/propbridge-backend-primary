<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Company;
use App\Models\User;
use App\Models\PropertyFinderListing;

// 1. Find the vortexweb company
$company = Company::where('slug', 'vortexweb')->first();
if (!$company) {
    // Fallback to superadmin company if slug doesn't match
    $company = Company::whereHas('users', function($q) {
        $q->whereHas('roles', function($rq) {
            $rq->where('name', 'superadmin');
        });
    })->first();
}

if (!$company) {
    echo "Company not found.\n";
    exit(1);
}

echo "Syncing agents for company: " . $company->name . "\n";

$agentsData = [
    [
        'email' => "muhammed.fasil@vortexweb.org",
        'id' => 276041,
        'name' => "Muhammed Fasil"
    ],
    [
        'email' => "aaryan@vortexweb.cloud",
        'id' => 606208,
        'name' => "aaryan daboria"
    ],
    [
        'email' => "abhijeet.sarkar@vortexweb.org",
        'id' => 606332,
        'name' => "ABHIJEET SARKAR"
    ]
];

foreach ($agentsData as $data) {
    $user = User::where('email', $data['email'])->first();
    if ($user) {
        $user->update(['pf_agent_id' => (string)$data['id']]);
        echo "Updated User: " . $user->email . " with PF ID: " . $data['id'] . "\n";
    } else {
        echo "User not found for email: " . $data['email'] . "\n";
    }
}

// 2. Update the draft listing to use the first agent (Muhammed Fasil)
$agent = User::where('email', "muhammed.fasil@vortexweb.org")->first();
if ($agent) {
    $listing = PropertyFinderListing::where('company_id', $company->id)->first();
    if ($listing) {
        $listing->update(['agent_id' => $agent->id]);
        echo "Updated Listing " . $listing->id . " to agent: " . $agent->email . "\n";
    }
}
