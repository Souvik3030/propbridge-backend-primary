<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verify PropertyFinder webhook signature.
 *
 * PF sends these headers with every webhook:
 *  - X-PropertyFinder-Signature: HMAC-SHA256 of (timestamp + '.' + payload)
 *  - X-PropertyFinder-Timestamp: Unix timestamp of request
 *
 * Note: The action ValidateWebhookSignatureAction uses the same header names.
 * This middleware is the first line of defence — it validates before the
 * controller even sees the request.
 */
class VerifyPropertyFinderWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        // FIX: was using X-PF-Signature — now consistent with ValidateWebhookSignatureAction
        $signature = $request->header('X-PropertyFinder-Signature');
        $timestamp = $request->header('X-PropertyFinder-Timestamp');

        if (!$signature) {
            Log::warning('PropertyFinder webhook rejected: missing X-PropertyFinder-Signature header');
            return response()->json(['error' => 'Missing webhook signature'], 401);
        }

        $secret = config('propertyfinder.webhook.secret');

        if (!$secret) {
            // Secret not configured — allow through but log a critical warning
            Log::critical('PropertyFinder webhook secret (PROPERTYFINDER_WEBHOOK_SECRET) is not configured. Webhook signature not verified!');
            return $next($request);
        }

        // Replay attack prevention: reject requests older than tolerance (default 300s)
        if ($timestamp) {
            $tolerance = config('propertyfinder.webhook.tolerance', 300);
            if (abs(time() - (int) $timestamp) > $tolerance) {
                Log::warning('PropertyFinder webhook rejected: timestamp too old (possible replay attack)', [
                    'timestamp' => $timestamp,
                    'drift'     => abs(time() - (int) $timestamp),
                ]);
                return response()->json(['error' => 'Webhook timestamp expired'], 401);
            }

            // Verify signature (same as ValidateWebhookSignatureAction)
            $payload           = $request->getContent();
            $expectedSignature = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

            if (!hash_equals($expectedSignature, $signature)) {
                Log::warning('PropertyFinder webhook rejected: invalid signature');
                return response()->json(['error' => 'Invalid webhook signature'], 401);
            }
        } else {
            // No timestamp header — fall back to simple payload verification
            $payload           = $request->getContent();
            $expectedSignature = hash_hmac('sha256', $payload, $secret);

            if (!hash_equals($expectedSignature, $signature)) {
                Log::warning('PropertyFinder webhook rejected: invalid signature (no timestamp)');
                return response()->json(['error' => 'Invalid webhook signature'], 401);
            }
        }

        return $next($request);
    }
}
