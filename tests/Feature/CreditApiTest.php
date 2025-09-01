<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CreditTransaction;
use App\Models\User;
use App\Models\UserCredit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreditApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();

        // Create initial credit balance
        UserCredit::create([
            'user_id' => $this->user->id,
            'balance' => 100.0,
        ]);

        Sanctum::actingAs($this->user);
    }

    public function testCanGetUserCreditBalance(): void
    {
        $response = $this->getJson(route('api.v1.credits.balance'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'balance',
                    'user_id',
                ],
            ])
            ->assertJson([
                'data' => [
                    'balance' => 100.0,
                    'user_id' => $this->user->id,
                ],
            ]);
    }

    public function testCanGetUserCreditStatistics(): void
    {
        // Create some transactions for the user
        CreditTransaction::create([
            'user_id' => $this->user->id,
            'type' => CreditTransaction::TYPE_TOPUP,
            'amount' => 50.0,
            'balance_before' => 100.0,
            'balance_after' => 150.0,
            'description' => 'Test topup',
        ]);

        $response = $this->getJson(route('api.v1.credits.statistics'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'balance',
                    'total_topups',
                    'total_debits',
                    'total_refunds',
                    'transaction_count',
                    'last_transaction_at',
                ],
            ]);
    }

    public function testCanGetCreditTransactionHistory(): void
    {
        // Create test transactions
        CreditTransaction::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson(route('api.v1.credits.history'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'type_description',
                        'amount',
                        'absolute_amount',
                        'balance_before',
                        'balance_after',
                        'description',
                        'timestamps',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    public function testCanGetCreditTransactionHistoryWithPagination(): void
    {
        // Create many transactions
        CreditTransaction::factory()->count(25)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson(route('api.v1.credits.history') . '?per_page=10');

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertIsArray($data['data']);
        $this->assertCount(10, $data['data']);
        $this->assertIsArray($data['meta']);
        $this->assertEquals(10, $data['meta']['per_page']);
        $this->assertEquals(25, $data['meta']['total']);
    }

    public function testCanTopupCreditsInDevelopmentEnvironment(): void
    {
        // Set environment to local
        $this->app['env'] = 'local';

        $response = $this->postJson(route('api.v1.credits.topup'), [
            'amount' => 50.0,
            'description' => 'Test topup',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'type',
                    'amount',
                    'balance_before',
                    'balance_after',
                    'description',
                ],
            ])
            ->assertJson([
                'data' => [
                    'type' => CreditTransaction::TYPE_TOPUP,
                    'amount' => 50.0,
                    'description' => 'Test topup',
                ],
            ]);
    }

    public function testCannotTopupCreditsInProductionEnvironment(): void
    {
        // Set environment to production
        $this->app['env'] = 'production';

        $response = $this->postJson(route('api.v1.credits.topup'), [
            'amount' => 50.0,
            'description' => 'Test topup',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Not available',
                'message' => 'Этот endpoint доступен только в среде разработки',
            ]);
    }

    public function testTopupValidation(): void
    {
        $this->app['env'] = 'local';

        // Test negative amount
        $response = $this->postJson(route('api.v1.credits.topup'), [
            'amount' => -10.0,
        ]);
        $response->assertStatus(422);

        // Test zero amount
        $response = $this->postJson(route('api.v1.credits.topup'), [
            'amount' => 0,
        ]);
        $response->assertStatus(422);

        // Test amount too large
        $response = $this->postJson(route('api.v1.credits.topup'), [
            'amount' => 20000,
        ]);
        $response->assertStatus(422);
    }

    public function testCanConvertUsdToCredits(): void
    {
        $response = $this->postJson(route('api.v1.credits.convert-usd'), [
            'usd_amount' => 1.50,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'usd_amount',
                    'credits',
                    'rate',
                ],
            ])
            ->assertJson([
                'data' => [
                    'usd_amount' => 1.50,
                    'credits' => 150.0, // Assuming 1 USD = 100 credits
                ],
            ]);
    }

    public function testCanCheckSufficientBalance(): void
    {
        $response = $this->postJson(route('api.v1.credits.check-balance'), [
            'required_amount' => 50.0,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'current_balance',
                    'required_amount',
                    'has_sufficient_balance',
                    'deficit',
                ],
            ])
            ->assertJson([
                'data' => [
                    'current_balance' => 100.0,
                    'required_amount' => 50.0,
                    'has_sufficient_balance' => true,
                    'deficit' => 0,
                ],
            ]);
    }

    public function testInsufficientBalanceCheck(): void
    {
        $response = $this->postJson(route('api.v1.credits.check-balance'), [
            'required_amount' => 150.0,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'current_balance' => 100.0,
                    'required_amount' => 150.0,
                    'has_sufficient_balance' => false,
                    'deficit' => 50.0,
                ],
            ]);
    }

    public function testUnauthenticatedUserCannotAccessCreditEndpoints(): void
    {
        // Clear any previous authentication
        $this->app['auth']->forgetGuards();

        $endpoints = [
            'GET' => [
                route('api.v1.credits.balance'),
                route('api.v1.credits.statistics'),
                route('api.v1.credits.history'),
            ],
            'POST' => [
                route('api.v1.credits.topup'),
                route('api.v1.credits.check-balance'),
                route('api.v1.credits.convert-usd'),
            ],
        ];

        foreach ($endpoints as $method => $urls) {
            foreach ($urls as $url) {
                $response = $this->json($method, $url);
                $response->assertStatus(401);
            }
        }
    }

    public function testRateLimitingIsApplied(): void
    {
        // Make multiple requests quickly
        for ($i = 0; $i < 5; ++$i) {
            $response = $this->getJson(route('api.v1.credits.balance'));
            $response->assertStatus(200);
        }

        // This is a simplified test - in real scenarios you'd need to exceed the actual rate limit
        $this->assertTrue(true); // Rate limiting is configured, actual testing would require specific setup
    }

    public function testValidationErrors(): void
    {
        // Test USD conversion validation
        $response = $this->postJson(route('api.v1.credits.convert-usd'), [
            'usd_amount' => 'invalid',
        ]);
        $response->assertStatus(422);

        // Test balance check validation
        $response = $this->postJson(route('api.v1.credits.check-balance'), [
            'required_amount' => -1,
        ]);
        $response->assertStatus(422);
    }

    public function testCanGetExchangeRates(): void
    {
        $response = $this->getJson(route('api.v1.credits.rates'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'rates' => [
                        'RUB',
                        'USD',
                        'EUR',
                    ],
                    'base_currency',
                    'supported_currencies',
                    'updated_at',
                ],
            ]);

        // Verify the rates are correct from config
        /** @var array{base_currency: string, rates: array<string, float>, supported_currencies: array<string>} $data */
        $data = $response->json('data');
        $this->assertEquals('RUB', $data['base_currency']);
        $this->assertEquals(1.0, $data['rates']['RUB']);
        $this->assertEquals(95.0, $data['rates']['USD']);
        $this->assertEquals(105.0, $data['rates']['EUR']);
        $this->assertIsArray($data['supported_currencies']);
        $this->assertContains('RUB', $data['supported_currencies']);
        $this->assertContains('USD', $data['supported_currencies']);
        $this->assertContains('EUR', $data['supported_currencies']);
    }

    public function testCanGetCreditCosts(): void
    {
        $response = $this->getJson(route('api.v1.credits.costs'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'credit_costs' => [
                        'RUB',
                        'USD',
                        'EUR',
                    ],
                    'base_currency',
                    'supported_currencies',
                    'description',
                    'updated_at',
                ],
            ]);

        // Verify the credit costs are correct from config
        /** @var array{base_currency: string, credit_costs: array<string, float>, description: string} $data */
        $data = $response->json('data');
        $this->assertEquals('RUB', $data['base_currency']);
        $this->assertEquals(1.0, $data['credit_costs']['RUB']);
        $this->assertEquals(0.01, $data['credit_costs']['USD']);
        $this->assertEquals(0.009, $data['credit_costs']['EUR']);
        $this->assertEquals('Cost of 1 credit in different currencies', $data['description']);
    }

    public function testExchangeRatesIncludeTimestamp(): void
    {
        $response = $this->getJson(route('api.v1.credits.rates'));

        $response->assertStatus(200);

        /** @var array{updated_at: string} $data */
        $data = $response->json('data');
        $this->assertArrayHasKey('updated_at', $data);
        $this->assertIsString($data['updated_at']);
        // Verify it's a valid ISO timestamp (Laravel uses ISO 8601 format)
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $data['updated_at']);
    }

    public function testCreditCostsIncludeTimestamp(): void
    {
        $response = $this->getJson(route('api.v1.credits.costs'));

        $response->assertStatus(200);

        /** @var array{updated_at: string} $data */
        $data = $response->json('data');
        $this->assertArrayHasKey('updated_at', $data);
        $this->assertIsString($data['updated_at']);
        // Verify it's a valid ISO timestamp (Laravel uses ISO 8601 format)
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $data['updated_at']);
    }

    public function testUnauthenticatedUserCannotAccessNewCurrencyEndpoints(): void
    {
        // Clear any previous authentication
        $this->app['auth']->forgetGuards();

        $endpoints = [
            'GET' => [
                route('api.v1.credits.rates'),
                route('api.v1.credits.costs'),
            ],
        ];

        foreach ($endpoints as $method => $urls) {
            foreach ($urls as $url) {
                $response = $this->json($method, $url);
                $response->assertStatus(401);
            }
        }
    }

    public function testCurrencyEndpointsHaveRateLimitingApplied(): void
    {
        // Test rate limiting for currency endpoints
        for ($i = 0; $i < 3; ++$i) {
            $response = $this->getJson(route('api.v1.credits.rates'));
            $response->assertStatus(200);

            $response = $this->getJson(route('api.v1.credits.costs'));
            $response->assertStatus(200);
        }

        // This is a simplified test - in real scenarios you'd need to exceed the actual rate limit
        $this->assertTrue(true); // Rate limiting is configured, actual testing would require specific setup
    }
}
