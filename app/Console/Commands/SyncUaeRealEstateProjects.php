<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Project\FetchUaeRealEstatePropertiesAction;
use App\Jobs\ProcessProject;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class SyncUaeRealEstateProjects extends Command
{
    protected $signature = 'app:sync-uae-real-estate-projects
        {--start-page=0 : First RapidAPI page to fetch}
        {--max-pages=0 : Maximum pages to fetch, 0 means until an empty page}
        {--langs=ar : API langs query parameter}
        {--mode=all : Use all for broad import or sample for the previous narrow test payload}';

    protected $description = 'Fetch UAE real estate projects/properties from RapidAPI and store them locally.';

    public function handle(FetchUaeRealEstatePropertiesAction $fetcher): int
    {
        $page = max(0, (int) $this->option('start-page'));
        $maxPages = max(0, (int) $this->option('max-pages'));
        $langs = (string) $this->option('langs');
        $mode = (string) $this->option('mode');
        $processed = 0;
        $fetchedPages = 0;

        do {
            $this->info("Fetching RapidAPI properties page {$page}...");

            $response = $fetcher->execute($page, $this->payload($mode), $langs);
            if (!$response->successful()) {
                if ($response->status() === 429) {
                    $this->warn('RapidAPI quota/rate limit reached. Import stopped cleanly and can be resumed later with --start-page=' . $page . '.');
                    $this->line($response->body());

                    break;
                }

                $this->error("RapidAPI request failed with HTTP {$response->status()}.");
                $this->line($response->body());

                return Command::FAILURE;
            }

            $json = $response->json();
            $items = $this->extractItems(is_array($json) ? $json : []);

            if (empty($items)) {
                $this->warn("No items found on page {$page}; stopping.");
                break;
            }

            foreach ($items as $item) {
                ProcessProject::dispatchSync($this->normalizeItem($item));
                $processed++;
            }

            $this->info("Stored " . count($items) . " items from page {$page}.");
            $page++;
            $fetchedPages++;
        } while ($maxPages === 0 || $fetchedPages < $maxPages);

        $this->info("Import complete. Stored/updated {$processed} projects.");

        return Command::SUCCESS;
    }

    private function payload(string $mode): array
    {
        if ($mode === 'sample') {
            return [
                'purpose' => 'for-sale',
                'categories' => ['apartments', 'villas'],
                'locations_ids' => [2, 3],
                'index' => 'popular',
                'is_completed' => false,
                'agent_ids' => [2248813],
                'agency_ids' => [103201],
                'developer_ids' => [12],
                'rooms' => [0, 1, 2, 3],
                'baths' => [0, 1, 2, 3, 4],
                'price_min' => 1300000,
                'price_max' => 26000000,
                'has_video' => false,
                'has_360_tour' => false,
                'has_floorplan' => false,
                'amenities' => ['Security Staff', 'Intercom'],
                'area_min' => 300,
                'area_max' => 20000,
                'completion_date' => '31-12-2030',
                'sale_type' => 'any',
            ];
        }

        return [
            'purpose' => 'for-sale',
            'index' => 'popular',
            'sale_type' => 'any',
        ];
    }

    private function extractItems(array $json): array
    {
        foreach ([
            'properties',
            'results',
            'data',
            'hits',
            'items',
            'projects',
            'response.results',
            'response.properties',
            'response.hits',
        ] as $path) {
            $items = Arr::get($json, $path);
            if (is_array($items) && !empty($items)) {
                return array_values($items);
            }
        }

        if (array_is_list($json)) {
            return $json;
        }

        return [];
    }

    private function normalizeItem(array $item): array
    {
        $location = $this->normalizeLocation($item);
        $developer = $this->normalizeDeveloper($item);
        $photos = $this->normalizePhotos($item);
        $price = $this->normalizeRange($item, ['price', 'price_min', 'priceMin'], ['price_max', 'priceMax']);
        $area = $this->normalizeRange($item, ['area', 'area_min', 'areaMin', 'size'], ['area_max', 'areaMax']);
        $rooms = Arr::get($item, 'rooms', Arr::get($item, 'details.bedrooms', Arr::get($item, 'bedrooms')));
        $completionStatus = Arr::get($item, 'project.completion_status')
            ?? Arr::get($item, 'details.completion_status')
            ?? (($item['is_completed'] ?? false) ? 'completed' : 'off_plan');
        $completionDate = Arr::get($item, 'details.completion_details.completion_date')
            ?? Arr::get($item, 'completion_details.completion_date')
            ?? Arr::get($item, 'project.completion_date')
            ?? ($item['completion_date'] ?? Arr::get($item, 'completion.date'));

        return [
            '_source' => 'uae-real-estate2',
            'id' => (string) ($item['id'] ?? $item['externalID'] ?? $item['property_id'] ?? $item['referenceNumber'] ?? md5(json_encode($item))),
            'reference_number' => $item['reference_number'] ?? $item['referenceNumber'] ?? null,
            'name' => $item['title'] ?? $item['name'] ?? $item['heading'] ?? 'Untitled Project',
            'title_ar' => $item['title_ar'] ?? null,
            'description' => $item['description'] ?? Arr::get($item, 'description.text'),
            'location' => $location,
            'developer' => $developer,
            'price' => $price,
            'area' => $area,
            'unit_rooms' => is_array($rooms) ? array_values($rooms) : ($rooms !== null ? [(int) $rooms] : []),
            'bathrooms' => Arr::get($item, 'details.bathrooms'),
            'is_furnished' => Arr::get($item, 'details.is_furnished'),
            'type' => $this->normalizeType($item),
            'status' => [
                'completion_status' => $completionStatus,
                'completion_date' => $completionDate,
            ],
            'units_count' => $item['units_count'] ?? $item['unitsCount'] ?? 0,
            'purpose' => $item['purpose'] ?? 'for-sale',
            'amenities' => array_values((array) ($item['amenities'] ?? [])),
            'keywords' => array_values((array) ($item['keywords'] ?? [])),
            'amenities_ar' => array_values((array) ($item['amenities_ar'] ?? [])),
            'keywords_ar' => array_values((array) ($item['keywords_ar'] ?? [])),
            'payment_plan' => $item['payment_plans']
                ?? Arr::get($item, 'project.payment_plans', $item['payment_plan'] ?? $item['paymentPlan'] ?? []),
            'documents' => $item['documents'] ?? [],
            'media' => $photos,
            'permit_number' => Arr::get($item, 'legal.permit_number'),
            'bayut_url' => Arr::get($item, 'meta.url'),
            'agency_payload' => $item['agency'] ?? null,
            'agent_payload' => $item['agent'] ?? null,
            'verification_payload' => $item['verification'] ?? null,
            'legal_payload' => $item['legal'] ?? null,
            'offplan_payload' => $item['offplan_details'] ?? null,
            'raw_payload' => $item,
            'investment_score' => $item['score'] ?? $item['indy_score'] ?? null,
        ];
    }

    private function normalizeLocation(array $item): array
    {
        $location = $item['location'] ?? $item['locations'] ?? [];
        $first = is_array($location) && array_is_list($location) ? ($location[0] ?? []) : [];
        $second = is_array($location) && array_is_list($location) ? ($location[1] ?? []) : [];
        $third = is_array($location) && array_is_list($location) ? ($location[2] ?? []) : [];

        return [
            'city' => ['name' => Arr::get($item, 'location.city.name', Arr::get($item, 'city.name', Arr::get($first, 'name', $item['city'] ?? 'Dubai')))],
            'community' => ['name' => Arr::get($item, 'location.community.name', Arr::get($item, 'community.name', Arr::get($second, 'name', $item['location_name'] ?? 'Dubai')))],
            'sub_community' => ['name' => Arr::get($item, 'location.sub_community.name', Arr::get($item, 'sub_community.name', Arr::get($third, 'name')))],
            'coordinates' => [
                'lat' => Arr::get($item, 'location.coordinates.lat', Arr::get($item, 'coordinates.lat', Arr::get($item, 'geography.lat'))),
                'lng' => Arr::get($item, 'location.coordinates.lng', Arr::get($item, 'coordinates.lng', Arr::get($item, 'geography.lng'))),
            ],
        ];
    }

    private function normalizeDeveloper(array $item): ?array
    {
        $developer = Arr::get($item, 'project.developer') ?? $item['developer'] ?? null;
        if (!is_array($developer)) {
            return null;
        }

        return [
            'id' => (string) ($developer['id'] ?? $developer['externalID'] ?? md5($developer['name'] ?? 'unknown')),
            'name' => $developer['name'] ?? $developer['title'] ?? 'Unknown Developer',
            'logo_url' => $developer['logo_url']
                ?? Arr::get($developer, 'logo.url')
                ?? $developer['logo']
                ?? null,
        ];
    }

    private function normalizeRange(array $item, array $minKeys, array $maxKeys): array
    {
        $firstMinKey = $minKeys[0];
        $raw = $item[$firstMinKey] ?? null;

        if (is_array($raw)) {
            return [
                'min' => $raw['min'] ?? $raw['value'] ?? null,
                'max' => $raw['max'] ?? $raw['value'] ?? null,
                'built_up' => $raw['built_up'] ?? $raw['builtUp'] ?? null,
                'unit' => $raw['unit'] ?? null,
            ];
        }

        $min = null;
        foreach ($minKeys as $key) {
            if (isset($item[$key]) && is_numeric($item[$key])) {
                $min = (float) $item[$key];
                break;
            }
        }

        $max = null;
        foreach ($maxKeys as $key) {
            if (isset($item[$key]) && is_numeric($item[$key])) {
                $max = (float) $item[$key];
                break;
            }
        }

        return ['min' => $min, 'max' => $max, 'built_up' => null, 'unit' => null];
    }

    private function normalizeType(array $item): array
    {
        if (isset($item['type']) && is_array($item['type'])) {
            return [
                ['name' => $item['type']['main'] ?? 'Residential'],
                ['name' => $item['type']['sub'] ?? 'Property'],
            ];
        }

        $category = $item['category'] ?? $item['categories'] ?? null;
        $categoryName = is_array($category) ? ($category['name'] ?? $category[0] ?? null) : $category;

        return [
            ['name' => 'Residential'],
            ['name' => $categoryName ?: 'Property'],
        ];
    }

    private function normalizePhotos(array $item): array
    {
        $photos = Arr::get($item, 'media.photos', $item['photos'] ?? $item['images'] ?? $item['coverPhoto'] ?? $item['cover_photo'] ?? []);
        $coverPhoto = Arr::get($item, 'media.cover_photo');

        if ($coverPhoto) {
            $photos = is_array($photos) ? array_merge([$coverPhoto], $photos) : [$coverPhoto, $photos];
        }

        if (is_string($photos)) {
            return [$photos];
        }

        if (is_array($photos) && isset($photos['url'])) {
            return [$photos['url']];
        }

        if (!is_array($photos)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($photo) {
            return is_array($photo) ? ($photo['url'] ?? $photo['src'] ?? null) : $photo;
        }, $photos)));
    }
}
