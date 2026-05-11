<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\PropertyFinder\Webhook\HandleListingWebhookAction;
use App\Http\Controllers\Controller;
use App\Http\Middleware\VerifyPropertyFinderWebhookSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PropertyFinderWebhookController extends Controller
{
    public function __construct()
    {
        $this->middleware(VerifyPropertyFinderWebhookSignature::class);
    }

    /**
     * Handle incoming PropertyFinder webhooks
     */
    public function handle(Request $request, HandleListingWebhookAction $action): JsonResponse
    {
        $payload = $request->all();
        $event = $payload['event'] ?? 'unknown';
        $data = $payload['data'] ?? [];

        Log::info('PropertyFinder webhook received', ['event' => $event]);

        try {
            $action->execute($event, $data);
            
            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('PropertyFinder webhook handling error', [
                'event' => $event,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal processing error'
            ], 500);
        }
    }
}