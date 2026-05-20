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

    protected $description = 'Seed DldDeveloper and DldActiveProject from existing transactions';

    public function handle()
    {
        $this->info('Starting to seed developers and projects...');

        // 1. Create a default Developer for missing ones
        $defaultDev = \App\Models\DldDeveloper::firstOrCreate([
            'name' => 'Generic Developer LLC'
        ], [
            'license_number' => 'DEV-001',
            'registration_date' => now()->subYears(2),
            'expiry_date' => now()->addYears(2),
            'phone_number' => '041234567'
        ]);

        // 2. Fetch distinct projects from transactions
        $projects = \Illuminate\Support\Facades\DB::table('dld_transactions')
            ->whereNotNull('project_en')
            ->select('project_en', 'area_en')
            ->distinct()
            ->get();

        $this->info('Found ' . $projects->count() . ' distinct projects. Creating them...');

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

            // 3. Update transactions to link to this project
            \Illuminate\Support\Facades\DB::table('dld_transactions')
                ->where('project_en', $p->project_en)
                ->update(['project_id' => $activeProject->id]);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Seeding complete! Now your Active Projects and Developers have data, and Transactions are linked.');
    }
}
