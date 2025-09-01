<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Credit;

use Illuminate\Foundation\Http\FormRequest;

class CreditTopupRequest extends FormRequest
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
     * @return array<string, array<mixed>|\Illuminate\Contracts\Validation\ValidationRule|string>
     */
    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:1|max:10000',
            'description' => 'sometimes|string|max:255',
        ];
    }

    /**
     * Get the amount as float.
     */
    public function getAmount(): float
    {
        /** @var float|int|numeric-string */
        $amount = $this->validated('amount');

        return (float) $amount;
    }

    /**
     * Get the description with default.
     */
    public function getDescription(): string
    {
        /** @var string|null */
        $description = $this->validated('description');

        return $description ?? 'Test credit topup';
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Сумма пополнения обязательна',
            'amount.numeric' => 'Сумма должна быть числом',
            'amount.min' => 'Минимальная сумма пополнения: 1',
            'amount.max' => 'Максимальная сумма пополнения: 10000',
            'description.string' => 'Описание должно быть строкой',
            'description.max' => 'Максимальная длина описания: 255 символов',
        ];
    }
}
