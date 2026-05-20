<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Storage;

$disk = Storage::disk('s3');
$filename = 'verification-test-' . time() . '.txt';
try {
    $disk->put($filename, 'test content');
    $url = $disk->url($filename);
    echo "Uploaded to: " . $url . "\n";
} catch (\Exception $e) {
    echo "Failed to upload: " . $e->getMessage() . "\n";
    if ($e->getPrevious()) {
        echo "Previous: " . $e->getPrevious()->getMessage() . "\n";
    }
    exit(1);
}

// Try to fetch it via HTTP
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status Code: " . $httpcode . "\n";
if ($httpcode == 200) {
    echo "SUCCESS: The file is publicly accessible!\n";
} else {
    echo "FAILED: The file is NOT publicly accessible. Still getting an error.\n";
}

// Cleanup
$disk->delete($filename);
