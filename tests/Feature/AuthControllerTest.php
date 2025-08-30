<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
    }

    public function testUserCanRegister(): void
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson(route('api.v1.auth.register'), $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'email_verified_at',
                    'created_at',
                    'updated_at',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    public function testRegistrationRequiresValidData(): void
    {
        $response = $this->postJson(route('api.v1.auth.register'), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function testRegistrationRejectsInvalidEmail(): void
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'invalid-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson(route('api.v1.auth.register'), $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function testRegistrationRejectsDuplicateEmail(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson(route('api.v1.auth.register'), $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function testUserCanLogin(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson(route('api.v1.auth.login'), [
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                    ],
                    'token',
                ],
            ]);
    }

    public function testLoginRejectsInvalidCredentials(): void
    {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson(route('api.v1.auth.login'), [
            'email' => 'john@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422);
    }

    public function testAuthenticatedUserCanGetProfile(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson(route('api.v1.auth.user'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'email',
                ],
            ]);
    }

    public function testUnauthenticatedUserCannotGetProfile(): void
    {
        $response = $this->getJson(route('api.v1.auth.user'));

        $response->assertStatus(401);
    }

    public function testAuthenticatedUserCanUpdateProfile(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);
        $user->assignRole('customer');
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson(route('api.v1.auth.update-user'), [
            'name' => 'New Name',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
        ]);
    }

    public function testUserCanLogout(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson(route('api.v1.auth.logout'));

        $response->assertStatus(200);

        // Token should be deleted
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function testUserCanRefreshToken(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson(route('api.v1.auth.refresh'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'user',
                    'token',
                ],
            ]);
    }

    public function testForgotPasswordSendsResetEmail(): void
    {
        $user = User::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson(route('api.v1.auth.forgot-password'), [
            'email' => 'john@example.com',
        ]);

        $response->assertStatus(200);
    }

    public function testForgotPasswordRejectsNonExistentEmail(): void
    {
        $response = $this->postJson(route('api.v1.auth.forgot-password'), [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function testPasswordResetValidation(): void
    {
        $response = $this->postJson(route('api.v1.auth.reset-password'), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token', 'email', 'password']);
    }

    public function testRateLimitingForLogin(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Make 11 requests (limit is 10 per minute)
        for ($i = 0; $i < 11; ++$i) {
            $response = $this->postJson(route('api.v1.auth.login'), [
                'email' => 'john@example.com',
                'password' => 'wrongpassword',
            ]);
        }

        // The 11th request should be rate limited
        $response->assertStatus(429);
    }

    public function testRateLimitingForRegistration(): void
    {
        // Make 6 registration requests (limit is 5 per minute)
        for ($i = 0; $i < 6; ++$i) {
            $this->postJson(route('api.v1.auth.register'), [
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);
        }

        // The 6th request should be rate limited
        $response = $this->postJson(route('api.v1.auth.register'), [
            'name' => 'User 6',
            'email' => 'user6@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(429);
    }
}
