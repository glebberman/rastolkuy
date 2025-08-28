<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin role if it doesn't exist
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        
        // Create basic permissions if they don't exist
        $permissions = [
            'manage_users',
            'manage_documents',
            'view_admin_panel',
            'manage_settings',
            'view_statistics',
        ];
        
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
        
        // Assign all permissions to admin role
        $adminRole->givePermissionTo($permissions);
        
        // Create default admin user
        $adminEmail = env('APP_USER_ADMIN_EMAIL', 'admin@example.com');
        $adminPassword = env('APP_USER_ADMIN_DEFAULT_PASSWORD', 'password123');
        
        $adminUser = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Administrator',
                'email' => $adminEmail,
                'password' => Hash::make($adminPassword),
                'email_verified_at' => now(),
            ]
        );
        
        // Assign admin role to user
        $adminUser->assignRole('admin');
        
        $this->command->info("Admin user created with email: {$adminEmail}");
        $this->command->info("Default password: {$adminPassword}");
        $this->command->warn("Please change the default password after first login!");
    }
}