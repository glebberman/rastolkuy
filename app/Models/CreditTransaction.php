<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Модель для логирования транзакций с кредитами.
 *
 * @property int $id
 * @property int $user_id ID пользователя
 * @property string $type Тип транзакции
 * @property float $amount Сумма транзакции
 * @property float $balance_before Баланс до транзакции
 * @property float $balance_after Баланс после транзакции
 * @property string $description Описание транзакции
 * @property array<string, mixed>|null $metadata Дополнительные данные
 * @property string|null $reference_id Внешний ID для связи
 * @property string|null $reference_type Тип связанного объекта
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CreditTransaction extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Типы транзакций.
     */
    public const string TYPE_TOPUP = 'topup';
    public const string TYPE_DEBIT = 'debit';
    public const string TYPE_REFUND = 'refund';

    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'metadata',
        'reference_id',
        'reference_type',
    ];

    protected $casts = [
        'amount' => 'float',
        'balance_before' => 'float',
        'balance_after' => 'float',
        'metadata' => 'array',
    ];

    /**
     * Проверяет, является ли транзакция пополнением.
     */
    public function isTopup(): bool
    {
        return $this->type === self::TYPE_TOPUP;
    }

    /**
     * Проверяет, является ли транзакция списанием.
     */
    public function isDebit(): bool
    {
        return $this->type === self::TYPE_DEBIT;
    }

    /**
     * Проверяет, является ли транзакция возвратом.
     */
    public function isRefund(): bool
    {
        return $this->type === self::TYPE_REFUND;
    }

    /**
     * Получает абсолютное значение суммы транзакции.
     */
    public function getAbsoluteAmount(): float
    {
        return abs($this->amount);
    }

    /**
     * Получает человекочитаемое описание типа транзакции.
     */
    public function getTypeDescription(): string
    {
        return match ($this->type) {
            self::TYPE_TOPUP => 'Пополнение',
            self::TYPE_DEBIT => 'Списание',
            self::TYPE_REFUND => 'Возврат',
            default => 'Неизвестный тип',
        };
    }

    /**
     * Получает пользователя, которому принадлежит транзакция.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Скоуп для получения транзакций конкретного пользователя.
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * Скоуп для получения транзакций определенного типа.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Скоуп для получения транзакций пополнения.
     */
    public function scopeTopups(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_TOPUP);
    }

    /**
     * Скоуп для получения транзакций списания.
     */
    public function scopeDebits(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_DEBIT);
    }

    /**
     * Скоуп для получения транзакций возврата.
     */
    public function scopeRefunds(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_REFUND);
    }

    /**
     * Скоуп для получения транзакций за период.
     */
    public function scopeInPeriod(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Скоуп для получения транзакций с внешней ссылкой.
     */
    public function scopeWithReference(Builder $query, string $referenceType, string $referenceId): Builder
    {
        return $query->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId);
    }
}
