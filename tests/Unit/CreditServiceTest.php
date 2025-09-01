<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\CreditTransaction;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Tests\TestCase;

class CreditServiceTest extends TestCase
{
    use RefreshDatabase;

    private CreditService $creditService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->creditService = app(CreditService::class);

        // Set test configuration
        Config::set('credits.initial_balance', 100);
        Config::set('credits.maximum_balance', 10000);
        Config::set('credits.usd_to_credits_rate', 100);
    }

    public function testGetBalanceCreatesInitialRecord(): void
    {
        $user = User::factory()->create();

        $balance = $this->creditService->getBalance($user);

        $this->assertEquals(100, $balance);
        $this->assertDatabaseHas('user_credits', [
            'user_id' => $user->id,
            'balance' => 100,
        ]);
    }

    public function testAddCredits(): void
    {
        $user = User::factory()->create();

        $transaction = $this->creditService->addCredits(
            $user,
            50.0,
            'Test topup',
            'test',
            'test123',
        );

        $this->assertEquals(CreditTransaction::TYPE_TOPUP, $transaction->type);
        $this->assertEquals(50.0, $transaction->amount);
        $this->assertEquals(100.0, $transaction->balance_before);
        $this->assertEquals(150.0, $transaction->balance_after);
        $this->assertEquals('Test topup', $transaction->description);

        $this->assertEquals(150.0, $this->creditService->getBalance($user));
    }

    public function testAddCreditsThrowsExceptionForNegativeAmount(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be positive');

        $this->creditService->addCredits($user, -10.0, 'Test');
    }

    public function testAddCreditsThrowsExceptionWhenExceedsMaxBalance(): void
    {
        $user = User::factory()->create();
        Config::set('credits.maximum_balance', 120);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('would exceed maximum balance');

        $this->creditService->addCredits($user, 50.0, 'Test');
    }

    public function testDebitCredits(): void
    {
        $user = User::factory()->create();

        $transaction = $this->creditService->debitCredits(
            $user,
            30.0,
            'Test debit',
            'document_processing',
            'doc123',
        );

        $this->assertEquals(CreditTransaction::TYPE_DEBIT, $transaction->type);
        $this->assertEquals(-30.0, $transaction->amount);
        $this->assertEquals(100.0, $transaction->balance_before);
        $this->assertEquals(70.0, $transaction->balance_after);

        $this->assertEquals(70.0, $this->creditService->getBalance($user));
    }

    public function testDebitCreditsThrowsExceptionForInsufficientBalance(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient balance');

        $this->creditService->debitCredits($user, 150.0, 'Test');
    }

    public function testHasSufficientBalance(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($this->creditService->hasSufficientBalance($user, 50.0));
        $this->assertTrue($this->creditService->hasSufficientBalance($user, 100.0));
        $this->assertFalse($this->creditService->hasSufficientBalance($user, 150.0));
    }

    public function testRefundCredits(): void
    {
        $user = User::factory()->create();

        $transaction = $this->creditService->refundCredits(
            $user,
            25.0,
            'Test refund',
            'failed_processing',
            'doc456',
        );

        $this->assertEquals(CreditTransaction::TYPE_REFUND, $transaction->type);
        $this->assertEquals(25.0, $transaction->amount);
        $this->assertEquals(100.0, $transaction->balance_before);
        $this->assertEquals(125.0, $transaction->balance_after);

        $this->assertEquals(125.0, $this->creditService->getBalance($user));
    }

    public function testConvertUsdToCredits(): void
    {
        $credits = $this->creditService->convertUsdToCredits(1.50);

        $this->assertEquals(150.0, $credits);
    }

    public function testConvertCreditsToUsd(): void
    {
        $usd = $this->creditService->convertCreditsToUsd(150.0);

        $this->assertEquals(1.50, $usd);
    }

    public function testGetUserStatistics(): void
    {
        $user = User::factory()->create();

        // Add some transactions
        $this->creditService->addCredits($user, 50.0, 'Topup 1');
        $this->creditService->debitCredits($user, 25.0, 'Debit 1');
        $this->creditService->refundCredits($user, 10.0, 'Refund 1');

        $stats = $this->creditService->getUserStatistics($user);

        $this->assertEquals(135.0, $stats['balance']); // 100 + 50 - 25 + 10
        $this->assertEquals(50.0, $stats['total_topups']);
        $this->assertEquals(25.0, $stats['total_debits'], 'Debug: ' . json_encode($stats));
        $this->assertEquals(10.0, $stats['total_refunds']);
        $this->assertEquals(3, $stats['transaction_count']);
        $this->assertNotNull($stats['last_transaction_at']);
    }

    public function testCreateInitialBalance(): void
    {
        $user = User::factory()->create();

        $userCredit = $this->creditService->createInitialBalance($user);

        $this->assertEquals($user->id, $userCredit->user_id);
        $this->assertEquals(100.0, $userCredit->balance);

        // Should create a welcome bonus transaction
        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $user->id,
            'type' => CreditTransaction::TYPE_TOPUP,
            'amount' => 100.0,
            'description' => 'Welcome bonus credits',
        ]);
    }

    public function testGetExchangeRates(): void
    {
        // Set test configuration
        Config::set('credits.exchange_rates', [
            'RUB' => 1.0,
            'USD' => 95.0,
            'EUR' => 105.0,
        ]);

        $rates = $this->creditService->getExchangeRates();

        $this->assertIsArray($rates);
        $this->assertEquals(1.0, $rates['RUB']);
        $this->assertEquals(95.0, $rates['USD']);
        $this->assertEquals(105.0, $rates['EUR']);
    }

    public function testGetCreditCostInCurrencies(): void
    {
        // Set test configuration
        Config::set('credits.credit_cost', [
            'RUB' => 1.0,
            'USD' => 0.01,
            'EUR' => 0.009,
        ]);

        $costs = $this->creditService->getCreditCostInCurrencies();

        $this->assertIsArray($costs);
        $this->assertEquals(1.0, $costs['RUB']);
        $this->assertEquals(0.01, $costs['USD']);
        $this->assertEquals(0.009, $costs['EUR']);
    }

    public function testGetBaseCurrency(): void
    {
        Config::set('credits.default_currency', 'RUB');

        $baseCurrency = $this->creditService->getBaseCurrency();

        $this->assertEquals('RUB', $baseCurrency);
    }

    public function testGetSupportedCurrencies(): void
    {
        Config::set('credits.supported_currencies', ['RUB', 'USD', 'EUR']);

        $currencies = $this->creditService->getSupportedCurrencies();

        $this->assertIsArray($currencies);
        $this->assertEquals(['RUB', 'USD', 'EUR'], $currencies);
    }

    public function testConvertCurrencySameCurrency(): void
    {
        $amount = $this->creditService->convertCurrency(100.0, 'RUB', 'RUB');

        $this->assertEquals(100.0, $amount);
    }

    public function testConvertCurrencyFromBaseCurrency(): void
    {
        // Set test configuration
        Config::set('credits.default_currency', 'RUB');
        Config::set('credits.exchange_rates', [
            'RUB' => 1.0,
            'USD' => 95.0,
            'EUR' => 105.0,
        ]);

        $amount = $this->creditService->convertCurrency(95.0, 'RUB', 'USD');

        $this->assertEquals(9025.0, $amount); // 95 * 95 = 9025
    }

    public function testConvertCurrencyToBaseCurrency(): void
    {
        // Set test configuration
        Config::set('credits.default_currency', 'RUB');
        Config::set('credits.exchange_rates', [
            'RUB' => 1.0,
            'USD' => 95.0,
            'EUR' => 105.0,
        ]);

        $amount = $this->creditService->convertCurrency(95.0, 'USD', 'RUB');

        $this->assertEquals(1.0, $amount); // 95 / 95 = 1
    }

    public function testConvertCurrencyBetweenNonBaseCurrencies(): void
    {
        // Set test configuration
        Config::set('credits.default_currency', 'RUB');
        Config::set('credits.exchange_rates', [
            'RUB' => 1.0,
            'USD' => 95.0,
            'EUR' => 105.0,
        ]);

        $amount = $this->creditService->convertCurrency(95.0, 'USD', 'EUR');

        $this->assertEquals(105.0, $amount); // (95 / 95) * 105 = 105
    }

    public function testConvertCurrencyUnsupportedCurrency(): void
    {
        // Set test configuration
        Config::set('credits.exchange_rates', [
            'RUB' => 1.0,
            'USD' => 95.0,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported currency conversion: GBP to USD');

        $this->creditService->convertCurrency(100.0, 'GBP', 'USD');
    }

    public function testGetCreditCostInCurrency(): void
    {
        // Set test configuration
        Config::set('credits.credit_cost', [
            'RUB' => 1.0,
            'USD' => 0.01,
            'EUR' => 0.009,
        ]);

        $cost = $this->creditService->getCreditCostInCurrency('USD');

        $this->assertEquals(0.01, $cost);
    }

    public function testGetCreditCostInCurrencyUnsupportedCurrency(): void
    {
        // Set test configuration
        Config::set('credits.credit_cost', [
            'RUB' => 1.0,
            'USD' => 0.01,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Credit cost not configured for currency: GBP');

        $this->creditService->getCreditCostInCurrency('GBP');
    }
}
