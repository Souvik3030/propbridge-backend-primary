<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Project\FetchBayutOffplanDataAction; // 🔥 Corrected namespace
use App\Jobs\ProcessProject;
use Illuminate\Console\Command;

class SyncBayutFull extends Command
{
    protected $signature = 'app:sync-bayut-full';
    protected $description = 'Sync Bayut Offplan Projects with Queue Throttling';

    /**
     * 🔥 FAANG STANDARD: Clean Dispatching
     * Throttling logic is now moved to the Job Middleware and AppServiceProvider.
     */
    public function handle(FetchBayutOffplanDataAction $action): int
    {
        $page = 0;
        $emptyCount = 0;
        $maxEmpty = 5;

        $this->info("🚀 Starting high-performance full sync...");

        do {
            $this->info("Fetching page: $page");

            // Execute the Action (which now includes the Redis Quota Guard)
            $data = $action->execute($page);

            // If Quota Guard aborted the sync, we stop the loop
            if ($data === null) {
                $this->error("❌ Sync aborted: API Daily Quota reached.");
                break;
            }

            if (!isset($data['results']) || empty($data['results'])) {
                $emptyCount++;
                $this->warn("⚠️ Empty results on page: $page. Attempt $emptyCount/$maxEmpty");
            } else {
                $emptyCount = 0;

                foreach ($data['results'] as $item) {
                    // 🔥 FAANG FIX: No more manual delays!
                    // We dispatch instantly. The 'bayut-db-writes' rate limiter 
                    // in AppServiceProvider will handle the smooth ingestion.
                    ProcessProject::dispatch($item);
                }

                $this->info("✅ Page $page dispatched (" . count($data['results']) . " projects queued)");
            }

            $page++;
            
            // Respect the API provider's rate limit between page fetches
            sleep(1); 

        } while ($emptyCount < $maxEmpty);

        $this->info("🎉 Sync Command finished. Queue workers will now process the data.");
        
        return Command::SUCCESS;
    }
}