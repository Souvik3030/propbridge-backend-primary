<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
                // 1. ALWAYS run the roles seeder first so the roles exist in the DB
            RoleAndPermissionSeeder::class,

                // 2. Then run your test data seeder (if you created it)
            TestDataSeeder::class,
        ]);
    }
}
