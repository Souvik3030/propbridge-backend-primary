<?php
declare(strict_types=1);

namespace App\Actions\Project;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FetchBayutOffplanDataAction
{
    // Bayut ki daily limit 1600 hai, hum 1500 par safely ruk jayenge
    private const DAILY_QUOTA_LIMIT = 1500; 

    public function execute(int $page = 1)
    {
        // 🛡️ 1. Generate a daily unique cache key (e.g., 'bayut_api_calls_2026_04_01')
        $cacheKey = 'bayut_api_calls_' . now()->format('Y_m_d');
        
        // 🛡️ 2. Read current usage
        $callsToday = Cache::get($cacheKey, 0);

        // 🛡️ 3. THE CIRCUIT BREAKER
        if ($callsToday >= self::DAILY_QUOTA_LIMIT) {
            Log::critical("Bayut API Quota Guard: Daily limit of {" . self::DAILY_QUOTA_LIMIT . "} reached. Sync aborted to prevent API ban/overages.");
            return null; // Gracefully abort the process
        }

        // 🚀 4. Execute the actual API Call
        $response = Http::withHeaders([
            'X-RapidAPI-Key' => config('services.bayut.key'),
            'X-RapidAPI-Host' => 'bayut.p.rapidapi.com'
        ])->get('https://bayut.p.rapidapi.com/properties/list', [
            // your parameters here
        ]);

        // 📈 5. Safely Increment the Quota Counter
        // Ensure the key exists with an end-of-day expiry so it resets at midnight
        Cache::add($cacheKey, 0, now()->endOfDay()); 
        Cache::increment($cacheKey);

        return $response->json();
    }
}