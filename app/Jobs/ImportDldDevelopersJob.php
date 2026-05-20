<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use App\Models\DldDeveloper;
use Carbon\Carbon;

class ImportDldDevelopersJob implements ShouldQueue
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
        $headerMap = array_flip($header);

        $developersToInsert = [];
        
        while (($row = fgetcsv($file)) !== false) {
            if (empty($row[0])) continue;

            $name = $row[$headerMap['DEVELOPER_EN'] ?? -1] ?? null;
            $licenseNumber = $row[$headerMap['LICENSE_NUMBER'] ?? -1] ?? null;
            $registrationDate = $row[$headerMap['REGISTRATION_DATE'] ?? -1] ?? null;
            $expiryDate = $row[$headerMap['LICENSE_EXPIRY_DATE'] ?? -1] ?? null;
            $phone = $row[$headerMap['PHONE'] ?? -1] ?? null;

            if (!$name || !$licenseNumber) {
                continue;
            }

            try {
                $regDate = $registrationDate ? Carbon::parse($registrationDate)->toDateString() : now()->toDateString();
                $expDate = $expiryDate ? Carbon::parse($expiryDate)->toDateString() : now()->addYear()->toDateString();
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

            if (count($developersToInsert) >= 500) {
                DldDeveloper::upsert($developersToInsert, ['license_number'], ['name', 'registration_date', 'expiry_date', 'phone_number', 'updated_at']);
                $developersToInsert = [];
            }
        }

        if (count($developersToInsert) > 0) {
            DldDeveloper::upsert($developersToInsert, ['license_number'], ['name', 'registration_date', 'expiry_date', 'phone_number', 'updated_at']);
        }

        fclose($file);
    }
}
