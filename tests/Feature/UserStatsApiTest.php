<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DocumentProcessing;
use App\Models\User;
use App\Models\UserCredit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserStatsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    #[Test]
    public function userCanGetTheirStatistics(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');
        
        UserCredit::factory()->create(['user_id' => $user->id, 'balance' => 500.00]);

        // Create some documents for testing
        DocumentProcessing::factory()->count(5)->create(['user_id' => $user->id]);
        DocumentProcessing::factory()->count(2)->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'completed_at' => Carbon::today(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/user/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'credits_balance',
                    'total_documents',
                    'processed_today',
                    'last_activity',
                ],
            ])
            ->assertJson([
                'message' => 'Статистика пользователя',
                'data' => [
                    'credits_balance' => 500.00,
                    'total_documents' => 7,
                    'processed_today' => 2,
                ],
            ]);
    }

    #[Test]
    public function unauthenticatedUserCannotAccessStats(): void
    {
        $response = $this->getJson('/api/v1/user/stats');

        $response->assertStatus(401);
    }

    #[Test]
    public function userStatsAreCached(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');
        
        UserCredit::factory()->create(['user_id' => $user->id, 'balance' => 100.00]);

        Sanctum::actingAs($user);

        // First request
        $response1 = $this->getJson('/api/v1/user/stats');
        $response1->assertStatus(200);

        // Create more documents
        DocumentProcessing::factory()->count(3)->create(['user_id' => $user->id]);

        // Second request should return cached data (same total_documents)
        $response2 = $this->getJson('/api/v1/user/stats');
        $response2->assertStatus(200)
            ->assertJson([
                'data' => [
                    'total_documents' => 0, // Cached value
                ],
            ]);
    }

    #[Test]
    public function userStatsHandlesEmptyData(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');
        
        UserCredit::factory()->create(['user_id' => $user->id, 'balance' => 0.00]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/user/stats');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'credits_balance' => 0.00,
                    'total_documents' => 0,
                    'processed_today' => 0,
                ],
            ]);
    }

    #[Test]
    public function userStatsCountsOnlyProcessedToday(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');
        
        UserCredit::factory()->create(['user_id' => $user->id, 'balance' => 100.00]);

        // Create documents completed today
        DocumentProcessing::factory()->count(2)->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'completed_at' => Carbon::today(),
        ]);

        // Create documents completed yesterday
        DocumentProcessing::factory()->count(3)->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'completed_at' => Carbon::yesterday(),
        ]);

        // Create documents that failed today
        DocumentProcessing::factory()->create([
            'user_id' => $user->id,
            'status' => 'failed',
            'completed_at' => Carbon::today(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/user/stats');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'total_documents' => 6,
                    'processed_today' => 3, // 2 completed + 1 failed today
                ],
            ]);
    }

    #[Test]
    public function userStatsApiIsRateLimited(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');
        
        UserCredit::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        // Make many requests to test rate limiting
        for ($i = 0; $i < 65; ++$i) {
            $response = $this->getJson('/api/v1/user/stats');

            if ($i < 60) {
                $response->assertStatus(200);
            } else {
                $response->assertStatus(429); // Too Many Requests
                break;
            }
        }
    }
}
