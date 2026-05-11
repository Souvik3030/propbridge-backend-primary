<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PropertyFinder\PropertyFinderException;
use App\Models\Company;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Central HTTP client for Property Finder Atlas API v1 calls.
 *
 * - Issues a JWT via POST /v1/auth/token with apiKey/apiSecret
 * - Sets Authorization: Bearer <token> on every authenticated request
 * - Handles 429 rate limiting (Retry-After header)
 * - Maps HTTP error codes to PropertyFinderException
 * - Eliminates duplicated Http:: setup across all Action classes
 */
class PropertyFinderApiClient
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $baseUrl = rtrim(config('propertyfinder.api.base_url', 'https://atlas.propertyfinder.com'), '/');
        $this->baseUrl = Str::endsWith($baseUrl, '/v1')
            ? Str::beforeLast($baseUrl, '/v1')
            : $baseUrl;
        $this->timeout = (int) config('propertyfinder.api.timeout', 30);
    }

    /**
     * Make a GET request to the PF API.
     */
    public function get(Company $company, string $path, array $query = []): array
    {
        return $this->sendRequestWithRetry('get', $company, $path, $query);
    }

    /**
     * Make a POST request to the PF API.
     */
    public function post(Company $company, string $path, array $body = []): array
    {
        return $this->sendRequestWithRetry('post', $company, $path, $body);
    }

    /**
     * Make a PATCH request to the PF API (partial update).
     */
    public function patch(Company $company, string $path, array $body = []): array
    {
        return $this->sendRequestWithRetry('patch', $company, $path, $body);
    }

    /**
     * Make a PUT request to the PF API.
     */
    public function put(Company $company, string $path, array $body = []): array
    {
        return $this->sendRequestWithRetry('put', $company, $path, $body);
    }

    /**
     * Make a DELETE request to the PF API.
     */
    public function delete(Company $company, string $path): array
    {
        return $this->sendRequestWithRetry('delete', $company, $path);
    }

    /**
     * Execute a request and retry once if 401 Unauthorized (token expired).
     */
    private function sendRequestWithRetry(string $method, Company $company, string $path, array $data = []): array
    {
        $response = $this->buildRequest($company)->{$method}($this->url($path), $data);

        // If unauthorized and it's not the auth endpoint itself, clear cache and retry once
        if ($response->status() === 401 && !Str::contains($path, 'auth/token')) {
            $this->clearAccessTokenCache($company);
            $response = $this->buildRequest($company)->{$method}($this->url($path), $data);
        }

        return $this->handleResponse($response, strtoupper($method), $path);
    }

    /**
     * Clear the cached access token for this company.
     */
    private function clearAccessTokenCache(Company $company): void
    {
        $credentials = $company->getPropertyFinderCredentials();
        if ($credentials) {
            $apiKey = $credentials['api_key'] ?? $credentials['client_id'] ?? null;
            if ($apiKey) {
                $cacheKey = sprintf('propertyfinder:%s:access_token:%s', $company->id, sha1($apiKey));
                Cache::forget($cacheKey);
            }
        }
    }

    /**
     * Build an Http pending request with PF Atlas API v1 bearer auth.
     */
    private function buildRequest(Company $company): \Illuminate\Http\Client\PendingRequest
    {
        return $this->baseRequest()
            ->withToken($this->getAccessToken($company));
    }

    private function baseRequest(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout($this->timeout)
            ->asJson()
            ->acceptJson();
    }

    /**
     * Fetch and cache the Atlas JWT. PF tokens are short lived, so cache modestly.
     */
    private function getAccessToken(Company $company): string
    {
        $credentials = $company->getPropertyFinderCredentials();

        if (!$credentials) {
            throw new PropertyFinderException(
                'PropertyFinder is not enabled or configured for this company.',
                401,
                null,
                ['company_id' => $company->id]
            );
        }

        $apiKey = $credentials['api_key'] ?? $credentials['client_id'] ?? null;
        $apiSecret = $credentials['api_secret'] ?? $credentials['client_secret'] ?? null;

        if (!$apiKey || !$apiSecret) {
            throw new PropertyFinderException(
                'PropertyFinder API key and secret are not configured for this company.',
                401,
                null,
                ['company_id' => $company->id]
            );
        }

        $cacheKey = sprintf('propertyfinder:%s:access_token:%s', $company->id, sha1($apiKey));

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($company, $apiKey, $apiSecret): string {
            $response = $this->baseRequest()
                ->post($this->url('auth/token'), [
                    'apiKey'    => $apiKey,
                    'apiSecret' => $apiSecret,
                ]);

            $data = $this->handleResponse($response, 'POST', 'auth/token');
            $token = $data['accessToken'] ?? null;

            if (!$token) {
                throw new PropertyFinderException(
                    'PropertyFinder auth succeeded but no accessToken was returned.',
                    401,
                    null,
                    ['company_id' => $company->id]
                );
            }

            return $token;
        });
    }

    /**
     * Handle the HTTP response:
     * - 429: rate limited — throw with Retry-After info
     * - 4xx: client error — throw with response body
     * - 5xx: server error — throw with retry suggestion
     * - 2xx: return parsed JSON
     */
    private function handleResponse(Response $response, string $method, string $path): array
    {
        if ($response->successful()) {
            // 200 OK or 201 Created — return parsed JSON (empty array if no body)
            return $response->json() ?? [];
        }

        $status  = $response->status();
        $body    = $response->body();
        $context = ['method' => $method, 'path' => $path, 'status' => $status];

        Log::error('PropertyFinder API error', array_merge($context, ['body' => $body]));

        $message = match ($status) {
            400 => "Bad request to PropertyFinder API ({$path}): {$body}",
            401 => "Unauthorised - check your PropertyFinder API key/secret. Path: {$path}",
            403 => "Forbidden — you do not have permission for this action. Path: {$path}",
            404 => "Not found — the listing_id, agent_id or location_id does not exist. Path: {$path}",
            409 => "Conflict — duplicate listing detected (same permit number already exists). Path: {$path}",
            422 => "Validation failed — compliance issues or missing dependent fields. Path: {$path}: {$body}",
            429 => $this->buildRateLimitMessage($response, $path),
            500 => "PropertyFinder internal error. Retry after 60 seconds. Path: {$path}",
            default => "PropertyFinder API error {$status}. Path: {$path}: {$body}",
        };

        throw new PropertyFinderException($message, $status, null, $context);
    }

    private function buildRateLimitMessage(Response $response, string $path): string
    {
        $retryAfter = $response->header('Retry-After') ?? '60';
        return "Rate limited by PropertyFinder API. Retry after {$retryAfter} seconds. Path: {$path}";
    }

    private function url(string $path): string
    {
        $path = ltrim($path, '/');
        $path = Str::startsWith($path, 'v1/')
            ? Str::after($path, 'v1/')
            : $path;

        return $this->baseUrl . '/v1/' . $path;
    }
}
