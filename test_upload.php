<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Storage;

$filename = 'test-upload-' . time() . '.txt';
$path = 'media/' . $filename;
$content = "Hello, PropBridge! This file works perfectly. S3 permissions are fully fixed!";

Storage::disk('s3')->put($path, $content);

$url = Storage::disk('s3')->url($path);
echo "Uploaded successfully: $url\n";
