<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SeedDldProjects extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dld:seed-projects';

    protected $description = 'Seed DldDeveloper and DldActiveProject from existing transactions and developer CSV';

    public function handle()
    {
        $this->info('Starting to seed developers and projects...');

        // 1. Seed Developers from CSV
        $this->seedDevelopersFromCsv();

        // 2. Create a default Developer for missing ones
        $defaultDev = \App\Models\DldDeveloper::firstOrCreate([
            'name' => 'Generic Developer LLC'
        ], [
            'license_number' => 'DEV-001',
            'registration_date' => now()->subYears(2),
            'expiry_date' => now()->addYears(2),
            'phone_number' => '041234567'
        ]);

        // 3. Fetch distinct projects from transactions
        $projects = \Illuminate\Support\Facades\DB::table('dld_transactions')
            ->whereNotNull('project_en')
            ->select('project_en', 'area_en')
            ->distinct()
            ->get();

        $this->info('Found ' . $projects->count() . ' distinct projects in transactions. Creating them...');

        $bar = $this->output->createProgressBar($projects->count());

        foreach ($projects as $p) {
            $activeProject = \App\Models\DldActiveProject::firstOrCreate([
                'project_name' => $p->project_en,
            ], [
                'developer_id' => $defaultDev->id,
                'area_name' => $p->area_en ?? 'Unknown Area',
                'units_count' => rand(50, 500),
                'completion_percentage' => rand(10, 100),
                'estimated_end_date' => now()->addMonths(rand(1, 36)),
                'escrow_status' => 'VERIFIED',
                'is_active' => true,
            ]);

            // 4. Update transactions to link to this project
            \Illuminate\Support\Facades\DB::table('dld_transactions')
                ->where('project_en', $p->project_en)
                ->update(['project_id' => $activeProject->id]);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Seeding complete! Now your Active Projects and Developers have data, and Transactions are linked.');
    }

    protected function seedDevelopersFromCsv()
    {
        $csvPath = storage_path('app/public/developers-2026-05-20.csv');
        if (!file_exists($csvPath)) {
            $this->warn("Developers CSV not found at {$csvPath}. Skipping CSV import.");
            return;
        }

        $this->info('Importing developers from CSV...');
        $file = fopen($csvPath, 'r');
        
        $header = fgetcsv($file);
        
        // Map headers to index
        $headerMap = array_flip($header);
        
        $developersToInsert = [];
        $count = 0;
        
        while (($row = fgetcsv($file)) !== false) {
            if (empty($row[0])) continue; // Skip empty rows
            
            $name = $row[$headerMap['DEVELOPER_EN']] ?? null;
            $licenseNumber = $row[$headerMap['LICENSE_NUMBER']] ?? null;
            $registrationDate = $row[$headerMap['REGISTRATION_DATE']] ?? null;
            $expiryDate = $row[$headerMap['LICENSE_EXPIRY_DATE']] ?? null;
            $phone = $row[$headerMap['PHONE']] ?? null;
            
            if (!$name || !$licenseNumber) {
                continue;
            }
            
            // Format dates
            try {
                $regDate = $registrationDate ? \Carbon\Carbon::parse($registrationDate)->toDateString() : now()->toDateString();
                $expDate = $expiryDate ? \Carbon\Carbon::parse($expiryDate)->toDateString() : now()->addYear()->toDateString();
            } catch (\Exception $e) {
                $regDate = now()->toDateString();
                $expDate = now()->addYear()->toDateString();
            }
            
            $developersToInsert[] = [
                'name' => $name,
                'license_number' => $licenseNumber,
                'registration_date' => $regDate,
                'expiry_date' => $expDate,
                'phone_number' => $phone,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            $count++;
            
            // Chunk inserts
            if (count($developersToInsert) >= 500) {
                \App\Models\DldDeveloper::upsert($developersToInsert, ['license_number'], ['name', 'registration_date', 'expiry_date', 'phone_number', 'updated_at']);
                $developersToInsert = [];
            }
        }
        
        if (count($developersToInsert) > 0) {
            \App\Models\DldDeveloper::upsert($developersToInsert, ['license_number'], ['name', 'registration_date', 'expiry_date', 'phone_number', 'updated_at']);
        }
        
        fclose($file);
        $this->info("Successfully imported {$count} developers from CSV.");
    }
}
