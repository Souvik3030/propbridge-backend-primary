<?php
declare(strict_types=1);

namespace App\Actions\Project;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FetchBayutOffplanDataAction
{
    // The API allows 1500 calls per day, we stop at 1450 to be safe.
    private const DAILY_QUOTA_LIMIT = 1450; 

    public function execute(int $page = 1, array $filters = [])
    {
        // 🛡️ 1. Generate a daily unique cache key (e.g., 'bayut_api_calls_2026_04_01')
        $cacheKey = 'bayut_api_calls_' . now()->format('Y_m_d');
        
        // 🛡️ 2. Read current usage
        $callsToday = (int) Cache::get($cacheKey, 0);

        // 🛡️ 3. THE CIRCUIT BREAKER
        if ($callsToday >= self::DAILY_QUOTA_LIMIT) {
            Log::critical("Bayut API Quota Guard: Daily limit of {" . self::DAILY_QUOTA_LIMIT . "} reached. Sync aborted.");
            return null;
        }

        // 🚀 4. Execute the actual API Call
        $payload = array_merge([
            'page' => (int) $page,
            'hitsPerPage' => 25,
        ], $filters);

        $response = Http::withHeaders([
            'X-RapidAPI-Key' => \Illuminate\Support\Facades\Config::get('services.bayut.key'),
            'X-RapidAPI-Host' => \Illuminate\Support\Facades\Config::get('services.bayut.host')
        ])
        ->retry(3, 100) 
        ->timeout(30)
        ->post('https://' . \Illuminate\Support\Facades\Config::get('services.bayut.host') . '/new_projects_search', $payload);

        // 📈 5. Safely Increment the Quota Counter
        Cache::add($cacheKey, 0, now()->endOfDay()); 
        Cache::increment($cacheKey);

        return $response->json();
    }
}