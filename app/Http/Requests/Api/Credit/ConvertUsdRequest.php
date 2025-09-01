<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Credit;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConvertUsdRequest extends FormRequest
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
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        return [
            'usd_amount' => 'required|numeric|min:0|max:100000',
        ];
    }

    /**
     * Get the USD amount as float.
     */
    public function getUsdAmount(): float
    {
        /** @var float|int|numeric-string $amount */
        $amount = $this->validated('usd_amount');

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
            'usd_amount.required' => 'Сумма в долларах обязательна',
            'usd_amount.numeric' => 'Сумма должна быть числом',
            'usd_amount.min' => 'Сумма не может быть отрицательной',
            'usd_amount.max' => 'Максимальная сумма для конвертации: $100,000',
        ];
    }
}
