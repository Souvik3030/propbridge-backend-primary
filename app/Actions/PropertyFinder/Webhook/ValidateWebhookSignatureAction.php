<?php

declare(strict_types=1);

namespace App\Actions\PropertyFinder\Webhook;

use App\Models\Company;
use Illuminate\Http\Request;

class ValidateWebhookSignatureAction
{
    /**
     * Validate PropertyFinder webhook signature
     */
    public function execute(Request $request, Company $company): bool
    {
        $signature = $request->header('X-PropertyFinder-Signature');
        $timestamp = $request->header('X-PropertyFinder-Timestamp');
        
        if (!$signature || !$timestamp) {
            return false;
        }

        // Check timestamp (prevent replay attacks)
        $tolerance = config('propertyfinder.webhook.tolerance', 300);
        if (abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        // Verify signature
        $credentials = $company->getPropertyFinderCredentials();
        if (!$credentials || !isset($credentials['webhook_secret'])) {
            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $timestamp . '.' . $payload, $credentials['webhook_secret']);

        return hash_equals($expectedSignature, $signature);
    }
}