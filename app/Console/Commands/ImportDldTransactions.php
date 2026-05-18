<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ImportDldTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-dld-transactions {file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import DLD transactions from a CSV file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        ini_set('memory_limit', '1024M');
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("File not found: $filePath");
            return Command::FAILURE;
        }

        $file = fopen($filePath, 'r');
        $header = fgetcsv($file);

        if (!$header) {
            $this->error("Empty CSV file");
            return Command::FAILURE;
        }

        $chunkSize = 1000;
        $batch = [];
        $totalImported = 0;

        while (($row = fgetcsv($file)) !== false) {
            if (count($header) !== count($row)) {
                continue;
            }
            
            $data = array_combine($header, $row);

            $batch[] = [
                'transaction_number'  => isset($data['TRANSACTION_NUMBER']) ? substr($data['TRANSACTION_NUMBER'], 0, 255) : null,
                'instance_date'       => $data['INSTANCE_DATE'] ?? null,
                'group_en'            => isset($data['GROUP_EN']) ? substr($data['GROUP_EN'], 0, 255) : null,
                'procedure_en'        => isset($data['PROCEDURE_EN']) ? substr($data['PROCEDURE_EN'], 0, 255) : null,
                'is_offplan_en'       => isset($data['IS_OFFPLAN_EN']) ? substr($data['IS_OFFPLAN_EN'], 0, 255) : null,
                'is_free_hold_en'     => isset($data['IS_FREE_HOLD_EN']) ? substr($data['IS_FREE_HOLD_EN'], 0, 255) : null,
                'usage_en'            => isset($data['USAGE_EN']) ? substr($data['USAGE_EN'], 0, 255) : null,
                'area_en'             => isset($data['AREA_EN']) ? substr($data['AREA_EN'], 0, 255) : null,
                'prop_type_en'        => isset($data['PROP_TYPE_EN']) ? substr($data['PROP_TYPE_EN'], 0, 255) : null,
                'prop_sb_type_en'     => isset($data['PROP_SB_TYPE_EN']) ? substr($data['PROP_SB_TYPE_EN'], 0, 255) : null,
                'trans_value'         => $this->parseDecimal($data['TRANS_VALUE'] ?? 0),
                'procedure_area'      => $this->parseDecimal($data['PROCEDURE_AREA'] ?? 0),
                'actual_area'         => $this->parseDecimal($data['ACTUAL_AREA'] ?? 0),
                'rooms_en'            => isset($data['ROOMS_EN']) ? substr($data['ROOMS_EN'], 0, 255) : null,
                'parking'             => isset($data['PARKING']) ? substr($data['PARKING'], 0, 255) : null,
                'nearest_metro_en'    => isset($data['NEAREST_METRO_EN']) ? substr($data['NEAREST_METRO_EN'], 0, 255) : null,
                'nearest_mall_en'     => isset($data['NEAREST_MALL_EN']) ? substr($data['NEAREST_MALL_EN'], 0, 255) : null,
                'nearest_landmark_en' => isset($data['NEAREST_LANDMARK_EN']) ? substr($data['NEAREST_LANDMARK_EN'], 0, 255) : null,
                'total_buyer'         => (int) ($data['TOTAL_BUYER'] ?? 0),
                'total_seller'        => (int) ($data['TOTAL_SELLER'] ?? 0),
                'master_project_en'   => isset($data['MASTER_PROJECT_EN']) ? substr($data['MASTER_PROJECT_EN'], 0, 255) : null,
                'project_en'          => isset($data['PROJECT_EN']) ? substr($data['PROJECT_EN'], 0, 255) : null,
                'created_at'          => now(),
                'updated_at'          => now(),
            ];

            if (count($batch) >= $chunkSize) {
                try {
                    \Illuminate\Support\Facades\DB::table('dld_transactions')->insert($batch);
                } catch (\Exception $e) {
                    // Fall back to insertOrIgnore on chunk failure
                    \Illuminate\Support\Facades\DB::table('dld_transactions')->insertOrIgnore($batch);
                }
                $totalImported += count($batch);
                $this->info("Imported $totalImported rows...");
                $batch = [];
            }
        }

        if (!empty($batch)) {
            try {
                \Illuminate\Support\Facades\DB::table('dld_transactions')->insert($batch);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\DB::table('dld_transactions')->insertOrIgnore($batch);
            }
            $totalImported += count($batch);
        }

        fclose($file);

        $this->info("Total Imported $totalImported transactions successfully.");

        return Command::SUCCESS;
    }

    private function parseDecimal($value): float
    {
        return (float) str_replace(',', '', (string) $value);
    }
}
