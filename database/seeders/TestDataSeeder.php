<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create the Master Company
        $company = Company::create([
            'name' => 'vortexweb',
            'domain' => 'www.vortexweb.cloud',
            'plan' => 'enterprise', 
        ]);

        $this->command->info('Company created: vortexweb');

        // 2. Create the Genesis Superadmin User
        $superadmin = User::create([
            'company_id' => $company->id,
            'name' => 'vortexweb',
            'email' => 'admin@vortexweb.com', 
            'password' => Hash::make('SuperSecret@123!'),
            // 🔥 THE FIX: Removed the 'role' column from this array
            'is_active' => 1, 
            'email_verified_at' => now(), 
        ]);

        // 3. Assign the Spatie Role (This handles the pivot table automatically!)
        $superadmin->assignRole('superadmin');

        $this->command->info('Superadmin created: admin@vortexweb.com / SuperSecret@123!');

        // 4. Generate an active invitation code
        DB::table('invitations')->insert([
            'company_id' => $company->id,
            'email' => 'test@agent.com', 
            'role' => 'agent', // This is fine here, as your invitations table still has a role column
            'token' => 'TEST-INVITE-1234', 
            'expires_at' => now()->addDays(7),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Test Invitation Code generated: TEST-INVITE-1234 (Role: agent)');
    }
}