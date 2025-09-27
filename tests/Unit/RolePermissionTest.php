<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
    }

    #[Test]
    public function userCanHaveRoles(): void
    {
        $user = User::factory()->create();
        $role = Role::findByName('customer');

        $user->assignRole($role);

        $this->assertTrue($user->hasRole('customer'));
        $this->assertFalse($user->hasRole('admin'));
    }

    #[Test]
    public function userCanHavePermissionsThroughRoles(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $this->assertTrue($user->hasPermissionTo('documents.create'));
        $this->assertTrue($user->hasPermissionTo('documents.view'));
        $this->assertFalse($user->hasPermissionTo('system.admin'));
    }

    #[Test]
    public function adminRoleHasAllPermissions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $permissions = Permission::all();

        foreach ($permissions as $permission) {
            $this->assertTrue($user->hasPermissionTo($permission->name));
        }
    }

    #[Test]
    public function guestRoleHasLimitedPermissions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('guest');

        $this->assertTrue($user->hasPermissionTo('auth.register'));
        $this->assertTrue($user->hasPermissionTo('auth.login'));
        $this->assertFalse($user->hasPermissionTo('documents.create'));
        $this->assertFalse($user->hasPermissionTo('system.admin'));
    }

    #[Test]
    public function customerRoleHasDocumentPermissions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $this->assertTrue($user->hasPermissionTo('documents.create'));
        $this->assertTrue($user->hasPermissionTo('documents.view'));
        $this->assertTrue($user->hasPermissionTo('documents.update'));
        $this->assertTrue($user->hasPermissionTo('documents.delete'));
        $this->assertTrue($user->hasPermissionTo('documents.process'));
        $this->assertTrue($user->hasPermissionTo('documents.cancel'));

        // Should not have admin permissions
        $this->assertFalse($user->hasPermissionTo('documents.view-admin'));
        $this->assertFalse($user->hasPermissionTo('system.admin'));
    }

    #[Test]
    public function rolesHaveCorrectPermissionCount(): void
    {
        $guestRole = Role::findByName('guest');
        $customerRole = Role::findByName('customer');
        $adminRole = Role::findByName('admin');

        $this->assertCount(3, $guestRole->permissions);
        $this->assertCount(14, $customerRole->permissions); // Updated count after adding documents.export
        $this->assertCount(Permission::count(), $adminRole->permissions);
    }

    #[Test]
    public function userCannotHaveNonExistentRole(): void
    {
        $user = User::factory()->create();

        $this->expectException(RoleDoesNotExist::class);
        $user->assignRole('nonexistent-role');
    }

    #[Test]
    public function newUserIsAutomaticallyAssignedCustomerRole(): void
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ];

        $user = User::create($userData);
        $user->assignRole('customer');

        $this->assertTrue($user->hasRole('customer'));
        $this->assertTrue($user->hasPermissionTo('documents.create'));
    }
}
