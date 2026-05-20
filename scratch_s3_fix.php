<?php

require __DIR__ . '/vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$bucketName = $_ENV['AWS_BUCKET'];
$region = $_ENV['AWS_DEFAULT_REGION'];

$s3Client = new S3Client([
    'version' => 'latest',
    'region'  => $region,
    'credentials' => [
        'key'    => $_ENV['AWS_ACCESS_KEY_ID'],
        'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
    ],
]);

try {
    echo "1. Turning off 'Block Public Access'...\n";
    $s3Client->putPublicAccessBlock([
        'Bucket' => $bucketName,
        'PublicAccessBlockConfiguration' => [
            'BlockPublicAcls' => false,
            'IgnorePublicAcls' => false,
            'BlockPublicPolicy' => false,
            'RestrictPublicBuckets' => false,
        ],
    ]);
    echo "✅ Block Public Access turned off.\n\n";

    echo "2. Setting Object Ownership to 'BucketOwnerPreferred'...\n";
    $s3Client->putBucketOwnershipControls([
        'Bucket' => $bucketName,
        'OwnershipControls' => [
            'Rules' => [
                ['ObjectOwnership' => 'BucketOwnerPreferred'],
            ],
        ],
    ]);
    echo "✅ Object Ownership updated.\n\n";

    echo "3. Adding Public Read Bucket Policy...\n";
    $policy = json_encode([
        'Version' => '2012-10-17',
        'Statement' => [
            [
                'Sid' => 'PublicReadGetObject',
                'Effect' => 'Allow',
                'Principal' => '*',
                'Action' => 's3:GetObject',
                'Resource' => "arn:aws:s3:::$bucketName/*"
            ]
        ]
    ]);

    $s3Client->putBucketPolicy([
        'Bucket' => $bucketName,
        'Policy' => $policy,
    ]);
    echo "✅ Bucket Policy added successfully.\n\n";

    echo "🎉 S3 Bucket is now configured for public uploads!\n";

} catch (AwsException $e) {
    echo "❌ AWS Error: " . $e->getAwsErrorMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
