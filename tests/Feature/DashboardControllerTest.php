<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DocumentProcessing;
use App\Models\User;
use App\Models\UserCredit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    #[Test]
    public function dashboardDisplaysDefaultStatsForGuest(): void
    {
        $response = $this->get('/dashboard');

        $response->assertStatus(200)
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Dashboard')
                    ->has('stats')
                    ->where('stats.credits_balance', 0)
                    ->where('stats.total_documents', 0)
                    ->where('stats.processed_today', 0)
                    ->where('recentDocuments', []),
            );
    }

    #[Test]
    public function dashboardDisplaysUserStatsWhenAuthenticated(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        UserCredit::factory()->create(['user_id' => $user->id, 'balance' => 250.00]);

        // Create some documents
        DocumentProcessing::factory()->count(3)->create([
            'user_id' => $user->id,
            'status' => 'completed',
        ]);

        DocumentProcessing::factory()->create([
            'user_id' => $user->id,
            'status' => 'failed',
        ]);

        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response->assertStatus(200)
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Dashboard')
                    ->has('stats')
                    ->where('stats.credits_balance', 250)
                    ->where('stats.total_documents', 4)
                    ->where('stats.processed_today', 0) // No documents created today
                    ->has('recentDocuments'),
            );
    }

    #[Test]
    public function dashboardShowsRecentDocuments(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        UserCredit::factory()->create(['user_id' => $user->id]);

        // Create documents with specific filenames
        $documents = DocumentProcessing::factory()->count(3)->create([
            'user_id' => $user->id,
            'original_filename' => 'test-contract.pdf',
            'file_size' => 4096, // 2 pages
        ]);

        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response->assertStatus(200)
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Dashboard')
                    ->has('recentDocuments', 3)
                    ->has(
                        'recentDocuments.0',
                        fn (Assert $document) => $document
                            ->has('id')
                            ->where('title', 'test-contract.pdf')
                            ->has('status')
                            ->has('created_at')
                            ->where('pages_count', 2),
                    ),
            );
    }

    #[Test]
    public function dashboardLimitsRecentDocumentsToFive(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        UserCredit::factory()->create(['user_id' => $user->id]);

        // Create 10 documents
        DocumentProcessing::factory()->count(10)->create([
            'user_id' => $user->id,
        ]);

        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response->assertStatus(200)
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Dashboard')
                    ->has('recentDocuments', 5), // Should be limited to 5
            );
    }

    #[Test]
    public function dashboardShowsCreditsBalanceCorrectly(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        UserCredit::factory()->create(['user_id' => $user->id, 'balance' => 150.75]);

        // Create some documents
        DocumentProcessing::factory()->count(8)->create([
            'user_id' => $user->id,
            'status' => 'completed',
        ]);

        DocumentProcessing::factory()->count(2)->create([
            'user_id' => $user->id,
            'status' => 'failed',
        ]);

        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response->assertStatus(200)
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Dashboard')
                    ->where('stats.credits_balance', 150.75)
                    ->where('stats.total_documents', 10),
            );
    }

    #[Test]
    public function dashboardHandlesZeroDocuments(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        UserCredit::factory()->create(['user_id' => $user->id, 'balance' => 100.0]);

        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response->assertStatus(200)
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Dashboard')
                    ->where('stats.credits_balance', 100) // Default initial balance
                    ->where('stats.total_documents', 0)
                    ->where('stats.processed_today', 0)
                    ->where('recentDocuments', []),
            );
    }

    #[Test]
    public function homeRouteUsesSameDashboardController(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200)
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Dashboard')
                    ->has('stats')
                    ->has('recentDocuments'),
            );
    }
}
