<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create
                            {--email= : Admin email address}
                            {--password= : Admin password}
                            {--name=Administrator : Admin name}
                            {--force : Force creation even if user exists}';

    protected $description = 'Create an admin user with default permissions';

    public function handle(): int
    {
        $this->info('🔧 Creating Admin User...');
        $this->newLine();

        // Get input data
        /** @var string|null $emailOption */
        $emailOption = $this->option('email');
        // @phpstan-ignore-next-line
        $email = $emailOption ?: $this->ask('Admin Email', (string) (config('app.admin_email') ?? 'admin@example.com'));
        /** @var string|null $passwordOption */
        $passwordOption = $this->option('password');
        $password = $passwordOption ?: $this->secret('Admin Password');
        /** @var string|null $nameOption */
        $nameOption = $this->option('name');
        $name = $nameOption ?: 'Administrator';

        // Validate input
        $validator = Validator::make([
            'email' => $email,
            'password' => $password,
            'name' => $name,
        ], [
            'email' => 'required|email',
            'password' => 'required|min:8',
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            $this->error('❌ Validation failed:');

            foreach ($validator->errors()->all() as $error) {
                $this->error("  • {$error}");
            }

            return self::FAILURE;
        }

        // Check if user exists
        $existingUser = User::where('email', $email)->first();

        if ($existingUser && !$this->option('force')) {
            $this->error('❌ User with email \'' . $email . '\' already exists!');
            $this->info('Use --force to update existing user.');

            return self::FAILURE;
        }

        try {
            // Create or update admin role
            $adminRole = Role::firstOrCreate(['name' => 'admin']);

            // Create basic permissions
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

            // Assign permissions to admin role
            $adminRole->givePermissionTo($permissions);

            // Create or update user
            if ($existingUser) {
                $user = $existingUser;
                $user->update([
                    'name' => $name,
                    // @phpstan-ignore-next-line
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                ]);
                $action = 'updated';
            } else {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    // @phpstan-ignore-next-line
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                ]);
                $action = 'created';
            }

            // Assign admin role
            if (!$user->hasRole('admin')) {
                $user->assignRole('admin');
            }

            $this->newLine();
            $this->info("✅ Admin user successfully {$action}!");
            $this->table(['Field', 'Value'], [
                ['Name', $user->name],
                ['Email', $user->email],
                ['Role', 'admin'],
                ['Permissions', implode(', ', $permissions)],
            ]);

            $this->newLine();
            $this->warn('⚠️  Please save the admin credentials securely and change the password after first login!');

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error("❌ Error creating admin user: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
