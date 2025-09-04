<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Credit;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CheckBalanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string|ValidationRule>
     */
    public function rules(): array
    {
        return [
            'required_amount' => 'required|numeric|min:0|max:1000000',
        ];
    }

    /**
     * Get the required amount as float.
     */
    public function getRequiredAmount(): float
    {
        /** @var float|int|numeric-string $amount */
        $amount = $this->validated('required_amount');

        return (float) $amount;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required_amount.required' => 'Требуемая сумма обязательна',
            'required_amount.numeric' => 'Требуемая сумма должна быть числом',
            'required_amount.min' => 'Требуемая сумма не может быть отрицательной',
            'required_amount.max' => 'Максимальная проверяемая сумма: 1,000,000',
        ];
    }
}
