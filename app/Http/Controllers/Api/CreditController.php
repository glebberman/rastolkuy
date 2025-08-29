<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CreditTransactionResource;
use App\Services\CreditService;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

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
    public function balance(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        try {
            $balance = $this->creditService->getBalance($user);

            return response()->json([
                'message' => 'Баланс кредитов пользователя',
                'data' => [
                    'balance' => $balance,
                    'user_id' => $user->id,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve balance',
                'message' => 'Не удалось получить баланс кредитов',
            ], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Получить статистику кредитов пользователя.
     */
    public function statistics(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        try {
            $statistics = $this->creditService->getUserStatistics($user);

            return response()->json([
                'message' => 'Статистика кредитов пользователя',
                'data' => $statistics,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve statistics',
                'message' => 'Не удалось получить статистику кредитов',
            ], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Получить историю транзакций кредитов.
     */
    public function history(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        try {
            $perPageRaw = $request->input('per_page', 20);
            $perPage = is_numeric($perPageRaw) ? (int) $perPageRaw : 20;

            $transactions = $this->creditService->getTransactionHistory($user, $perPage);

            return response()->json([
                'message' => 'История транзакций кредитов',
                'data' => CreditTransactionResource::collection($transactions->items()),
                'meta' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                    'from' => $transactions->firstItem(),
                    'to' => $transactions->lastItem(),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve transaction history',
                'message' => 'Не удалось получить историю транзакций',
            ], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Пополнить баланс кредитов (для тестирования).
     * В production это должно быть интегрировано с платежной системой.
     */
    public function topup(Request $request): JsonResponse
    {
        // В production среде этот endpoint должен быть защищен
        // и интегрирован с платежной системой
        if (!app()->environment('local')) {
            return response()->json([
                'error' => 'Not available',
                'message' => 'Этот endpoint доступен только в среде разработки',
            ], ResponseAlias::HTTP_FORBIDDEN);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        $request->validate([
            'amount' => 'required|numeric|min:1|max:10000',
            'description' => 'string|max:255',
        ]);

        try {
            // @phpstan-ignore-next-line
            $amount = (float) $request->input('amount');
            // @phpstan-ignore-next-line
            $description = (string) ($request->input('description') ?? 'Test credit topup');

            $transaction = $this->creditService->addCredits(
                $user,
                $amount,
                $description,
                'manual_topup',
                null,
                ['source' => 'test_endpoint'],
            );

            return response()->json([
                'message' => 'Кредиты успешно добавлены',
                'data' => new CreditTransactionResource($transaction),
            ], ResponseAlias::HTTP_CREATED);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => 'Invalid request',
                'message' => $e->getMessage(),
            ], ResponseAlias::HTTP_BAD_REQUEST);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to add credits',
                'message' => 'Не удалось добавить кредиты',
            ], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Конвертировать USD в кредиты для расчета стоимости.
     */
    public function convertUsdToCredits(Request $request): JsonResponse
    {
        $request->validate([
            'usd_amount' => 'required|numeric|min:0',
        ]);

        try {
            // @phpstan-ignore-next-line
            $usdAmount = (float) $request->input('usd_amount');
            $credits = $this->creditService->convertUsdToCredits($usdAmount);

            return response()->json([
                'message' => 'Конвертация USD в кредиты',
                'data' => [
                    'usd_amount' => $usdAmount,
                    'credits' => $credits,
                    'rate' => config('credits.usd_to_credits_rate', 100),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Conversion failed',
                'message' => 'Не удалось выполнить конвертацию',
            ], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Проверить, достаточно ли кредитов для операции.
     */
    public function checkSufficientBalance(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $request->validate([
            'required_amount' => 'required|numeric|min:0',
        ]);

        try {
            // @phpstan-ignore-next-line
            $requiredAmount = (float) $request->input('required_amount');
            $currentBalance = $this->creditService->getBalance($user);
            $hasSufficient = $this->creditService->hasSufficientBalance($user, $requiredAmount);

            return response()->json([
                'message' => 'Проверка баланса кредитов',
                'data' => [
                    'current_balance' => $currentBalance,
                    'required_amount' => $requiredAmount,
                    'has_sufficient_balance' => $hasSufficient,
                    'deficit' => $hasSufficient ? 0 : ($requiredAmount - $currentBalance),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Balance check failed',
                'message' => 'Не удалось проверить баланс',
            ], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
