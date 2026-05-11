<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Define Permissions based on Blueprint Matrix
        $permissions = [
            // Company & User Management
            'view all companies',
            'manage companies',
            'manage company profile', // 🔥 Specific to company details
            'manage all users',
            'manage company users',
            'manage invitations',     // 🔥 Consolidated Invitation logic

            // Listings (PropertyFinder)
            'view listings',
            'create listings',
            'edit any listing',
            'edit company listings',
            'edit own listings',
            'delete any listing',
            'delete company listings',
            'delete own listings',

            // Publishing
            'publish any listing',
            'publish company listings',
            'publish own listings',

            // Leads (Placeholders)
            'view all leads',
            'view company leads',
            'view own leads',

            // System & Tools
            'view stats',
            'manage roles and permissions', // 🔥 Management UI
            'impersonate users',            // 🔥 Superadmin tool
            'access admin panel',
            'export data',
            'generate brochures',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // 2. Create Roles & Assign Permissions

        // SUPER ADMIN
        $superAdmin = Role::findOrCreate('superadmin', 'web');
        $superAdmin->givePermissionTo(Permission::all());

        // ADMIN (Company Level)
        $admin = Role::findOrCreate('admin', 'web');
        $admin->givePermissionTo([
            'manage company users',
            'manage company profile',
            'manage invitations',
            'view listings',
            'create listings',
            'edit company listings',
            'delete company listings',
            'publish company listings',
            'view company leads',
            'export data',
            'generate brochures',
        ]);

        // AGENT (User Level)
        $agent = Role::findOrCreate('agent', 'web');
        $agent->givePermissionTo([
            'view listings',
            'create listings',
            'edit own listings',
            'delete own listings',
            'publish own listings',
            'view own leads',
            'export data',
            'generate brochures',
        ]);

        // OWNER (View Only Level)
        $owner = Role::findOrCreate('owner', 'web');
        $owner->givePermissionTo([
            'view listings',
            'view own leads',
        ]);
        // 3. Assign Superadmin role to the first user found (Development Convenience)
        $firstUser = \App\Models\User::first();
        if ($firstUser) {
            $firstUser->assignRole($superAdmin);
        }
    }
}
