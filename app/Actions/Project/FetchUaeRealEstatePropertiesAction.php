<?php
declare(strict_types=1);

namespace App\Actions\Project;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class FetchUaeRealEstatePropertiesAction
{
    public function execute(int $page = 0, array $payload = [], string $langs = 'ar'): Response
    {
        $host = (string) Config::get('services.uae_real_estate.host');

        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'x-rapidapi-host' => $host,
            'x-rapidapi-key' => Config::get('services.uae_real_estate.key'),
        ])
            ->acceptJson()
            ->retry(3, 250, throw: false)
            ->timeout(45)
            ->post("https://{$host}/properties_search?page={$page}&langs={$langs}", $payload);
    }
}
