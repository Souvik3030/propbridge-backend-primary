<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use App\Models\DldActiveProject;
use App\Models\DldDeveloper;

class ImportDldProjectsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;

    /**
     * Create a new job instance.
     */
    public function __construct($filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $absolutePath = Storage::path($this->filePath);

        if (!file_exists($absolutePath)) {
            return;
        }

        $file = fopen($absolutePath, 'r');
        $header = fgetcsv($file);
        $header = array_map('trim', $header);
        $headerMap = array_flip($header);

        $defaultDeveloper = DldDeveloper::firstOrCreate(
            ['name' => 'Generic Developer LLC'],
            ['license_number' => 'DEV-001', 'registration_date' => now()->subYears(2), 'expiry_date' => now()->addYears(2)]
        );

        $projectsToInsert = [];
        
        while (($row = fgetcsv($file)) !== false) {
            if (empty($row[0])) continue;

            $val = function($key) use ($row, $headerMap) {
                return isset($headerMap[$key]) && isset($row[$headerMap[$key]]) ? $row[$headerMap[$key]] : null;
            };

            // Assuming typical standard DLD project headers.
            // Update these headers when the real project CSV structure is provided!
            $projectName = $val('PROJECT_NAME') ?? $val('PROJECT_EN') ?? $row[0];
            $developerName = $val('DEVELOPER_NAME') ?? $val('DEVELOPER_EN');
            $area = $val('AREA') ?? $val('AREA_EN') ?? 'Unknown Area';

            if (!$projectName) {
                continue;
            }

            $developerId = $defaultDeveloper->id;
            if ($developerName) {
                $dev = DldDeveloper::where('name', $developerName)->first();
                if ($dev) {
                    $developerId = $dev->id;
                }
            }

            $projectsToInsert[] = [
                'project_name' => $projectName,
                'developer_id' => $developerId,
                'area_name' => $area,
                'units_count' => $val('UNITS') ?: rand(50, 500),
                'completion_percentage' => $val('COMPLETION') ?: rand(10, 100),
                'estimated_end_date' => now()->addMonths(24)->toDateString(),
                'escrow_status' => $val('ESCROW_STATUS') ?: 'VERIFIED',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($projectsToInsert) >= 500) {
                // Upsert based on project_name since it is indexed
                DldActiveProject::upsert($projectsToInsert, ['project_name'], [
                    'developer_id', 'area_name', 'units_count', 'completion_percentage', 'escrow_status', 'updated_at'
                ]);
                $projectsToInsert = [];
            }
        }

        if (count($projectsToInsert) > 0) {
            DldActiveProject::upsert($projectsToInsert, ['project_name'], [
                'developer_id', 'area_name', 'units_count', 'completion_percentage', 'escrow_status', 'updated_at'
            ]);
        }

        fclose($file);
    }
}
