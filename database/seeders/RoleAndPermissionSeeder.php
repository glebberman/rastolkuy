<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // User management
            'users.view',
            'users.create',
            'users.update',
            'users.delete',

            // Document processing
            'documents.view',
            'documents.create',
            'documents.update',
            'documents.delete',
            'documents.process',
            'documents.cancel',
            'documents.view-admin',
            'documents.stats',

            // Authentication
            'auth.register',
            'auth.login',
            'auth.refresh-token',
            'auth.update-profile',
            'auth.verify-email',
            'auth.reset-password',
            'auth.stats',

            // System administration
            'system.admin',
            'system.view-logs',
            'system.manage-settings',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions
        $guestRole = Role::create(['name' => 'guest']);
        $guestRole->givePermissionTo([
            'auth.register',
            'auth.login',
            'auth.reset-password',
        ]);

        $customerRole = Role::create(['name' => 'customer']);
        $customerRole->givePermissionTo([
            // All guest permissions
            'auth.register',
            'auth.login',
            'auth.reset-password',
            // Customer-specific permissions
            'auth.refresh-token',
            'auth.update-profile',
            'auth.verify-email',
            'auth.stats',
            'documents.view',
            'documents.create',
            'documents.update',
            'documents.delete',
            'documents.process',
            'documents.cancel',
        ]);

        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::all());
    }
}
