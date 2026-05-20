<?php

if (function_exists('app')) {
    $app = app();
} else {
    require __DIR__ . '/vendor/autoload.php';
    $app = require __DIR__ . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
}

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

$companies = DB::table('companies')->get();

foreach ($companies as $company) {
    $updateData = [];

    $fields = [
        'pf_client_secret',
        'pf_webhook_secret',
        'pf_api_token',
        'bitrix_oauth_token'
    ];

    foreach ($fields as $field) {
        $value = $company->{$field};
        if ($value) {
            try {
                // Try to decrypt. If it succeeds, it's already encrypted properly.
                Crypt::decrypt($value);
            } catch (\Throwable $e) {
                // If it fails, it's plaintext or corrupt. We must encrypt it.
                $updateData[$field] = Crypt::encrypt($value);
            }
        }
    }

    if (!empty($updateData)) {
        DB::table('companies')->where('id', $company->id)->update($updateData);
        echo "Re-encrypted company ID: {$company->id}\n";
    }
}

echo "Done fixing encryption.\n";