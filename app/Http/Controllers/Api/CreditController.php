<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Credit\CheckBalanceRequest;
use App\Http\Requests\Api\Credit\ConvertUsdRequest;
use App\Http\Requests\Api\Credit\CreditBalanceRequest;
use App\Http\Requests\Api\Credit\CreditCostsRequest;
use App\Http\Requests\Api\Credit\CreditHistoryRequest;
use App\Http\Requests\Api\Credit\CreditStatisticsRequest;
use App\Http\Requests\Api\Credit\CreditTopupRequest;
use App\Http\Requests\Api\Credit\ExchangeRatesRequest;
use App\Http\Responses\Api\Credit\CheckBalanceResponse;
use App\Http\Responses\Api\Credit\ConvertUsdResponse;
use App\Http\Responses\Api\Credit\CreditBalanceResponse;
use App\Http\Responses\Api\Credit\CreditCostsResponse;
use App\Http\Responses\Api\Credit\CreditErrorResponse;
use App\Http\Responses\Api\Credit\CreditHistoryResponse;
use App\Http\Responses\Api\Credit\CreditStatisticsResponse;
use App\Http\Responses\Api\Credit\CreditTopupResponse;
use App\Http\Responses\Api\Credit\ExchangeRatesResponse;
use App\Services\CreditService;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use InvalidArgumentException;
use RuntimeException;

class CreditController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CreditService $creditService,
    ) {
    }

    /**
     * Получить баланс кредитов текущего пользователя.
     */
    public function balance(CreditBalanceRequest $request): CreditBalanceResponse|CreditErrorResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        try {
            $balance = $this->creditService->getBalance($user);

            return new CreditBalanceResponse($user, $balance);
        } catch (Exception $e) {
            return CreditErrorResponse::internalServerError(
                'Failed to retrieve balance',
                'Не удалось получить баланс кредитов',
            );
        }
    }

    /**
     * Получить статистику кредитов пользователя.
     */
    public function statistics(CreditStatisticsRequest $request): CreditStatisticsResponse|CreditErrorResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        try {
            $statistics = $this->creditService->getUserStatistics($user);

            return new CreditStatisticsResponse($statistics);
        } catch (Exception $e) {
            return CreditErrorResponse::internalServerError(
                'Failed to retrieve statistics',
                'Не удалось получить статистику кредитов',
            );
        }
    }

    /**
     * Получить историю транзакций кредитов.
     */
    public function history(CreditHistoryRequest $request): CreditHistoryResponse|CreditErrorResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        try {
            $perPage = $request->getPerPage();
            $transactions = $this->creditService->getTransactionHistory($user, $perPage);

            return new CreditHistoryResponse($transactions);
        } catch (Exception $e) {
            return CreditErrorResponse::internalServerError(
                'Failed to retrieve transaction history',
                'Не удалось получить историю транзакций',
            );
        }
    }

    /**
     * Пополнить баланс кредитов (для тестирования).
     * В production это должно быть интегрировано с платежной системой.
     */
    public function topup(CreditTopupRequest $request): CreditTopupResponse|CreditErrorResponse
    {
        // В production среде этот endpoint должен быть защищен
        // и интегрирован с платежной системой
        if (!app()->environment('local')) {
            return CreditErrorResponse::forbidden(
                'Not available',
                'Этот endpoint доступен только в среде разработки',
            );
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        try {
            $amount = $request->getAmount();
            $description = $request->getDescription();

            $transaction = $this->creditService->addCredits(
                $user,
                $amount,
                $description,
                'manual_topup',
                null,
                ['source' => 'test_endpoint'],
            );

            return new CreditTopupResponse($transaction);
        } catch (InvalidArgumentException $e) {
            return CreditErrorResponse::badRequest(
                'Invalid request',
                $e->getMessage(),
            );
        } catch (Exception $e) {
            return CreditErrorResponse::internalServerError(
                'Failed to add credits',
                'Не удалось добавить кредиты',
            );
        }
    }

    /**
     * Конвертировать USD в кредиты для расчета стоимости.
     */
    public function convertUsdToCredits(ConvertUsdRequest $request): ConvertUsdResponse|CreditErrorResponse
    {
        try {
            $usdAmount = $request->getUsdAmount();
            $credits = $this->creditService->convertUsdToCredits($usdAmount);

            return new ConvertUsdResponse($usdAmount, $credits);
        } catch (Exception $e) {
            return CreditErrorResponse::internalServerError(
                'Conversion failed',
                'Не удалось выполнить конвертацию',
            );
        }
    }

    /**
     * Проверить, достаточно ли кредитов для операции.
     */
    public function checkSufficientBalance(CheckBalanceRequest $request): CheckBalanceResponse|CreditErrorResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        try {
            $requiredAmount = $request->getRequiredAmount();
            $currentBalance = $this->creditService->getBalance($user);
            $hasSufficient = $this->creditService->hasSufficientBalance($user, $requiredAmount);
            $deficit = $hasSufficient ? 0 : ($requiredAmount - $currentBalance);

            return new CheckBalanceResponse($currentBalance, $requiredAmount, $hasSufficient, $deficit);
        } catch (Exception $e) {
            return CreditErrorResponse::internalServerError(
                'Balance check failed',
                'Не удалось проверить баланс',
            );
        }
    }

    /**
     * Получить курсы обмена валют.
     */
    public function exchangeRates(ExchangeRatesRequest $request): ExchangeRatesResponse|CreditErrorResponse
    {
        try {
            $rates = $this->creditService->getExchangeRates();
            $baseCurrency = $this->creditService->getBaseCurrency();
            $supportedCurrencies = $this->creditService->getSupportedCurrencies();

            return new ExchangeRatesResponse($rates, $baseCurrency, $supportedCurrencies);
        } catch (InvalidArgumentException $e) {
            return CreditErrorResponse::invalidConfiguration(
                'Invalid configuration',
                'Некорректная конфигурация валют',
                $e->getMessage(),
            );
        } catch (RuntimeException $e) {
            return CreditErrorResponse::configurationError(
                'Configuration error',
                'Ошибка конфигурации валютной системы',
                $e->getMessage(),
            );
        } catch (Exception $e) {
            return CreditErrorResponse::internalServerError(
                'Failed to retrieve exchange rates',
                'Не удалось получить курсы валют',
            );
        }
    }

    /**
     * Получить стоимость кредитов в разных валютах.
     */
    public function creditCosts(CreditCostsRequest $request): CreditCostsResponse|CreditErrorResponse
    {
        try {
            $creditCosts = $this->creditService->getCreditCostInCurrencies();
            $baseCurrency = $this->creditService->getBaseCurrency();
            $supportedCurrencies = $this->creditService->getSupportedCurrencies();

            return new CreditCostsResponse($creditCosts, $baseCurrency, $supportedCurrencies);
        } catch (InvalidArgumentException $e) {
            return CreditErrorResponse::invalidConfiguration(
                'Invalid configuration',
                'Некорректная конфигурация валют',
                $e->getMessage(),
            );
        } catch (RuntimeException $e) {
            return CreditErrorResponse::configurationError(
                'Configuration error',
                'Ошибка конфигурации валютной системы',
                $e->getMessage(),
            );
        } catch (Exception $e) {
            return CreditErrorResponse::internalServerError(
                'Failed to retrieve credit costs',
                'Не удалось получить стоимость кредитов',
            );
        }
    }
}
