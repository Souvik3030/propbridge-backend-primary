<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use App\Models\DldTransaction;
use Carbon\Carbon;

class ImportDldTransactionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;
    
    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;
    
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
        // Trim headers to avoid invisible spaces issue
        $header = array_map('trim', $header);
        $headerMap = array_flip($header);

        $recordsToInsert = [];
        
        while (($row = fgetcsv($file)) !== false) {
            if (empty($row[0])) continue;

            // Optional helper to get value safely
            $val = function($key) use ($row, $headerMap) {
                return isset($headerMap[$key]) && isset($row[$headerMap[$key]]) ? $row[$headerMap[$key]] : null;
            };

            $instanceDateStr = $val('INSTANCE_DATE');
            $instanceDate = $instanceDateStr ? Carbon::parse($instanceDateStr)->toDateTimeString() : null;

            $recordsToInsert[] = [
                'transaction_number' => $val('TRANSACTION_NUMBER'),
                'instance_date' => $instanceDate,
                'group_en' => $val('GROUP_EN'),
                'procedure_en' => $val('PROCEDURE_EN'),
                'is_offplan_en' => $val('IS_OFFPLAN_EN'),
                'is_free_hold_en' => $val('IS_FREE_HOLD_EN'),
                'usage_en' => $val('USAGE_EN'),
                'area_en' => $val('AREA_EN'),
                'prop_type_en' => $val('PROP_TYPE_EN'),
                'prop_sb_type_en' => $val('PROP_SB_TYPE_EN'),
                'trans_value' => $val('TRANS_VALUE') ?: 0,
                'procedure_area' => $val('PROCEDURE_AREA') ?: 0,
                'actual_area' => $val('ACTUAL_AREA') ?: 0,
                'rooms_en' => $val('ROOMS_EN'),
                'parking' => $val('PARKING'),
                'nearest_metro_en' => $val('NEAREST_METRO_EN'),
                'nearest_mall_en' => $val('NEAREST_MALL_EN'),
                'nearest_landmark_en' => $val('NEAREST_LANDMARK_EN'),
                'total_buyer' => $val('TOTAL_BUYER') ?: 0,
                'total_seller' => $val('TOTAL_SELLER') ?: 0,
                'master_project_en' => $val('MASTER_PROJECT_EN'),
                'project_en' => $val('PROJECT_EN'),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($recordsToInsert) >= 1000) {
                // Since dld_transactions has no unique identifier in the current schema (except id),
                // we use simple insert. In a real scenario with a primary transaction key, we'd upsert.
                DldTransaction::insert($recordsToInsert);
                $recordsToInsert = [];
            }
        }

        if (count($recordsToInsert) > 0) {
            DldTransaction::insert($recordsToInsert);
        }

        fclose($file);
    }
}
