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
 * Модель для хранения баланса кредитов пользователей.
 *
 * @property int $id
 * @property int $user_id ID пользователя
 * @property float $balance Текущий баланс кредитов
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static UserCredit create(array<string, mixed> $attributes = [])
 */
class UserCredit extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'balance',
    ];

    protected $casts = [
        'balance' => 'float',
    ];

    /**
     * Проверяет, достаточно ли кредитов для операции.
     */
    public function hasSufficientBalance(float $amount): bool
    {
        return $this->balance >= $amount;
    }

    /**
     * Увеличивает баланс на указанную сумму.
     */
    public function addBalance(float $amount): void
    {
        $this->increment('balance', $amount);
    }

    /**
     * Уменьшает баланс на указанную сумму.
     */
    public function subtractBalance(float $amount): void
    {
        $this->decrement('balance', $amount);
    }

    /**
     * Получает пользователя, которому принадлежит баланс.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Скоуп для получения кредитов конкретного пользователя.
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * Скоуп для получения пользователей с положительным балансом.
     */
    public function scopeWithPositiveBalance(Builder $query): Builder
    {
        return $query->where('balance', '>', 0);
    }

    /**
     * Скоуп для получения пользователей с недостаточным балансом.
     */
    public function scopeWithInsufficientBalance(Builder $query, float $requiredAmount): Builder
    {
        return $query->where('balance', '<', $requiredAmount);
    }
}
