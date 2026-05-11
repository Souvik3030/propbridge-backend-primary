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

    // 🔥 FAANG STANDARD: Fault Tolerance. API downtime ko handle karega.
    public int $tries = 3;
    
    // 🔥 FAANG STANDARD: Strict Readonly property for the queue payload
    public function __construct(public readonly array $projectData) {}

    public function handle(): void
    {
        // 🔥 FAANG STANDARD: Atomic Database Transaction
        DB::transaction(function () {
            
            // 1. Sync Location (Idempotent)
            $locationData = $this->projectData['location'] ?? [];
            $location = OffplanLocation::firstOrCreate(
                [
                    'city' => $locationData['city']['name'] ?? 'Unknown',
                    'community' => $locationData['community']['name'] ?? 'Unknown',
                    'sub_community' => $locationData['sub_community']['name'] ?? null,
                ],
                [
                    'lat' => $locationData['coordinates']['lat'] ?? null,
                    'lng' => $locationData['coordinates']['lng'] ?? null,
                ]
            );

            // 2. Sync Developer
            $developerData = $this->projectData['developer'] ?? null;
            $developerId = null;
            
            if ($developerData && isset($developerData['id'])) {
                $developer = OffplanDeveloper::updateOrCreate(
                    ['source_id' => (string) $developerData['id']], 
                    [
                        'name' => $developerData['name'] ?? 'Unknown Developer',
                        'logo' => $developerData['logo_url'] ?? null,
                    ]
                );
                $developerId = $developer->id;
            }

            // 3. Sync Main Project
            $project = OffplanProject::updateOrCreate(
                ['source_id' => (string) $this->projectData['id']],
                [
                    'location_id' => $location->id,
                    'developer_id' => $developerId,
                    'title' => $this->projectData['name'] ?? 'Untitled Project',
                    'price' => $this->projectData['price']['min'] ?? 0,
                    'bedrooms' => $this->projectData['rooms'][0] ?? null,
                    'type_main' => $this->projectData['category']['main'] ?? null,
                    'type_sub' => $this->projectData['category']['sub'] ?? null,
                    'amenities' => $this->projectData['amenities'] ?? [],
                    'payment_plans' => $this->projectData['payment_plan'] ?? [],
                ]
            );

            // 4. 🔥 FAANG FIX: Smart Image Sync (Prevent Write Amplification)
            $media = $this->projectData['media'] ?? [];
            if (!empty($media['photos'])) {
                
                $incomingUrls = $media['photos'];
                
                // Fetch existing URLs from database
                $existingUrls = $project->images()->pluck('url')->toArray();

                // Find URLs to DELETE (in DB, but missing from incoming API data)
                $urlsToDelete = array_diff($existingUrls, $incomingUrls);
                if (!empty($urlsToDelete)) {
                    $project->images()->whereIn('url', $urlsToDelete)->delete();
                }

                // Find URLs to INSERT (in incoming API data, but missing from DB)
                $urlsToInsert = array_diff($incomingUrls, $existingUrls);
                if (!empty($urlsToInsert)) {
                    $imagesData = array_map(function ($url) {
                        return ['url' => $url];
                    }, $urlsToInsert);
                    
                    $project->images()->createMany($imagesData);
                }
                
                // Note: Images that are in BOTH arrays are completely ignored (0 queries run).
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
            'error' => $exception->getMessage()
        ]);
    }

    public function middleware(): array
    {
        return [new RateLimited('bayut-db-writes')];
    }
}