<?php
declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class WarmInvestmentCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:warm-investment-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pre-calculates and warms the database cache for the investment tools dashboard';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting investment tools cache pre-calculation...');

        // 1. Force flush cache tags/keys
        Cache::forget('tools_area_comparison');
        Cache::forget('tools_rental_yields');

        // 2. Resolve InvestmentToolsController and trigger caching
        $controller = app()->make(\App\Http\Controllers\Api\InvestmentToolsController::class);
        
        $this->info('Warming Area Comparison cache...');
        $controller->areaComparison(new \Illuminate\Http\Request());

        $this->info('Warming Rental Yields cache...');
        $controller->rentalYields(new \Illuminate\Http\Request());

        $this->info('Successfully primed 12-hour cache for DLD/Ejari data analytics!');
        return Command::SUCCESS;
    }
}
