<?php
declare(strict_types=1);

namespace App\Jobs;

use App\Models\OffplanLocation;
use App\Models\OffplanDeveloper;
use App\Models\OffplanProject;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\Middleware\RateLimited;

class ProcessProject implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly array $projectData) {}

    public function handle(): void
    {
        DB::transaction(function () {

            // ── 1. Location ──────────────────────────────────────────────────────
            $locationData = $this->projectData['location'] ?? [];
            if (empty($locationData) && isset($this->projectData['location_name'])) {
                $location = OffplanLocation::firstOrCreate([
                    'country'       => 'UAE',
                    'city'          => 'Dubai',
                    'community'     => $this->projectData['location_name'] ?? 'Unknown',
                    'sub_community' => null,
                ]);
            } else {
                $location = OffplanLocation::firstOrCreate(
                    [
                        'country'       => 'UAE',
                        'city'          => $locationData['city']['name'] ?? $locationData[0]['name'] ?? 'Unknown',
                        'community'     => $locationData['community']['name'] ?? $locationData[1]['name'] ?? 'Unknown',
                        'sub_community' => $locationData['sub_community']['name'] ?? $locationData[2]['name'] ?? null,
                    ],
                    [
                        'lat' => $locationData['coordinates']['lat'] ?? null,
                        'lng' => $locationData['coordinates']['lng'] ?? null,
                    ]
                );
            }

            // ── 2. Developer ─────────────────────────────────────────────────────
            $developerData = $this->projectData['developer'] ?? $this->projectData['agency'] ?? null;
            $developerId   = null;

            if ($developerData) {
                $devSourceId = (string) ($developerData['id'] ?? $developerData['externalID'] ?? '');
                if ($devSourceId) {
                    $developer = OffplanDeveloper::updateOrCreate(
                        ['source_id' => $devSourceId],
                        [
                            'name' => $developerData['name'] ?? $developerData['title'] ?? 'Unknown Developer',
                            'logo' => $developerData['logo_url'] ?? $developerData['logo']['url'] ?? null,
                        ]
                    );
                    $developerId = $developer->id;
                }
            }

            // ── 3. Price ─────────────────────────────────────────────────────────
            $priceData = $this->projectData['price'] ?? [];
            $priceMin  = 0;
            $priceMax  = null;

            if (is_array($priceData)) {
                $priceMin = $priceData['min'] ?? 0;
                $priceMax = $priceData['max'] ?? null;
            } elseif (is_numeric($priceData)) {
                $priceMin = (float) $priceData;
            }

            // ── 4. Area ──────────────────────────────────────────────────────────
            $areaData    = $this->projectData['area'] ?? [];
            $areaMin     = $areaData['min'] ?? null;
            $areaMax     = $areaData['max'] ?? null;
            $areaBuiltUp = $areaData['built_up'] ?? null;

            // ── 5. Rooms / Bedrooms ───────────────────────────────────────────────
            $rooms    = $this->projectData['unit_rooms'] ?? $this->projectData['rooms'] ?? null;
            $roomsArr = [];
            $bedrooms = null;

            if (is_array($rooms)) {
                $roomsArr = array_values(array_unique(array_map('intval', $rooms)));
                $bedrooms = $roomsArr[0] ?? null;
            } elseif (is_numeric($rooms)) {
                $bedrooms = (int) $rooms;
                $roomsArr = [$bedrooms];
            }

            // ── 6. Type ──────────────────────────────────────────────────────────
            $typeData = $this->projectData['type'] ?? $this->projectData['category'] ?? [];
            if (is_array($typeData) && isset($typeData[0]) && is_array($typeData[0])) {
                // Bayut returns type as an array of objects
                $typeMain = $typeData[0]['name'] ?? null;
                $typeSub  = $typeData[1]['name'] ?? null;
            } else {
                $typeMain = $typeData['main'] ?? $this->projectData['product'] ?? null;
                $typeSub  = $typeData['sub'] ?? null;
            }

            // ── 7. Status ────────────────────────────────────────────────────────
            $statusData       = $this->projectData['status'] ?? [];
            $completionStatus = $statusData['completion_status']
                ?? $this->projectData['completion_status']
                ?? $this->projectData['purpose']
                ?? null;
            $completionDate   = $statusData['completion_date']
                ?? $this->projectData['completion_date']
                ?? null;

            // ── 8. Units ─────────────────────────────────────────────────────────
            $unitsCount = $this->projectData['units_count']
                ?? $this->projectData['unitsCount']
                ?? 0;

            // ── 9. Upsert Project ─────────────────────────────────────────────────
            $project = OffplanProject::updateOrCreate(
                [
                    'source'    => 'bayut',
                    'source_id' => (string) ($this->projectData['id'] ?? $this->projectData['externalID'] ?? $this->projectData['referenceNumber'] ?? ''),
                ],
                [
                    'location_id'      => $location->id,
                    'developer_id'     => $developerId,
                    'title'            => $this->projectData['name'] ?? $this->projectData['title'] ?? 'Untitled Project',
                    'description'      => $this->projectData['description'] ?? null,
                    'price'            => $priceMin,
                    'price_max'        => $priceMax,
                    'area_min'         => $areaMin,
                    'area_max'         => $areaMax,
                    'area_built_up'    => $areaBuiltUp,
                    'bedrooms'         => $bedrooms,
                    'rooms'            => $roomsArr,
                    'units_count'      => (int) $unitsCount,
                    'purpose'          => $this->projectData['purpose'] ?? null,
                    'type_main'        => $typeMain,
                    'type_sub'         => $typeSub,
                    'completion_status' => $completionStatus,
                    'completion_date'  => $completionDate,
                    'amenities'        => $this->projectData['amenities'] ?? [],
                    'payment_plans'    => $this->projectData['payment_plan'] ?? $this->projectData['payment_plans'] ?? [],
                    'documents'        => $this->projectData['documents'] ?? [],
                ]
            );

            // ── 10. Images ────────────────────────────────────────────────────────
            $media  = $this->projectData['media'] ?? $this->projectData['photos'] ?? [];
            $photos = [];

            if (is_array($media) && isset($media['photos'])) {
                $photos = $media['photos'];
            } elseif (is_array($media)) {
                $photos = array_map(fn($p) => is_array($p) ? ($p['url'] ?? '') : $p, $media);
            }

            if (!empty($photos)) {
                $incomingUrls = array_filter($photos);
                $existingUrls = $project->images()->pluck('url')->toArray();

                $urlsToDelete = array_diff($existingUrls, $incomingUrls);
                if (!empty($urlsToDelete)) {
                    $project->images()->whereIn('url', $urlsToDelete)->delete();
                }

                $urlsToInsert = array_diff($incomingUrls, $existingUrls);
                if (!empty($urlsToInsert)) {
                    $project->images()->createMany(
                        array_map(fn($url) => ['url' => $url], $urlsToInsert)
                    );
                }
            }

            // ── 11. Update developer project_count ────────────────────────────────
            if ($developerId) {
                OffplanDeveloper::where('id', $developerId)->update([
                    'project_count' => OffplanProject::where('developer_id', $developerId)->count(),
                ]);
            }
        });
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Project Sync Failed permanently.', [
            'project_id' => $this->projectData['id'] ?? 'unknown',
            'error'      => $exception->getMessage(),
        ]);
    }

    public function middleware(): array
    {
        return [new RateLimited('bayut-db-writes')];
    }
}