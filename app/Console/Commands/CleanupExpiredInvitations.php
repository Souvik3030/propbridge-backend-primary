<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Invitation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupExpiredInvitations extends Command
{
    protected $signature = 'app:cleanup-expired-invitations';
    protected $description = 'Soft-deletes invitations that have passed their expiry date to free up quota seats.';

    public function handle(): int
    {
        // 🔥 FAANG Standard: Bulk Update Query
        // Instead of fetching all records into RAM and deleting them one by one,
        // this executes a single lightning-fast SQL query: 
        // UPDATE invitations SET deleted_at = NOW() WHERE expires_at <= NOW() AND used_at IS NULL
        $deletedCount = Invitation::query()
            ->where('expires_at', '<=', now())
            ->whereNull('used_at')
            ->delete();

        if ($deletedCount > 0) {
            $this->info("✅ Cleaned up {$deletedCount} expired invitations.");
            Log::info("Quota Garbage Collector: Soft-deleted {$deletedCount} expired invitations.");
        } else {
            $this->info("No expired invitations to clean up.");
        }

        return Command::SUCCESS;
    }
}