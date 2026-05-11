<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Company;

$users = User::with('company')->get();

foreach ($users as $user) {
    echo "ID: {$user->id} | Email: {$user->email} | Company: {$user->company->name} ({$user->company->slug})\n";
}
