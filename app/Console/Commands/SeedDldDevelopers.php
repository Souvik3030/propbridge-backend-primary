<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SeedDldDevelopers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dld:seed-developers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed DLD Developers specifically from the developers CSV';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $csvPath = storage_path('app/public/developers-2026-05-20.csv');
        if (!file_exists($csvPath)) {
            $this->error("Developers CSV not found at {$csvPath}.");
            return;
        }

        $this->info('Importing developers from CSV...');
        $file = fopen($csvPath, 'r');
        
        $header = fgetcsv($file);
        $headerMap = array_flip($header);
        
        $developersToInsert = [];
        $count = 0;
        
        while (($row = fgetcsv($file)) !== false) {
            if (empty($row[0])) continue; // Skip empty rows
            
            $name = $row[$headerMap['DEVELOPER_EN'] ?? -1] ?? null;
            $licenseNumber = $row[$headerMap['LICENSE_NUMBER'] ?? -1] ?? null;
            $registrationDate = $row[$headerMap['REGISTRATION_DATE'] ?? -1] ?? null;
            $expiryDate = $row[$headerMap['LICENSE_EXPIRY_DATE'] ?? -1] ?? null;
            $phone = $row[$headerMap['PHONE'] ?? -1] ?? null;
            
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
