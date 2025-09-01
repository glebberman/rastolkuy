<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Credit;

use Illuminate\Foundation\Http\FormRequest;

class CreditHistoryRequest extends FormRequest
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
            'per_page' => 'sometimes|integer|min:1|max:100',
        ];
    }

    /**
     * Get the per page value with default.
     */
    public function getPerPage(): int
    {
        $perPageRaw = $this->input('per_page', 20);

        return is_numeric($perPageRaw) ? (int) $perPageRaw : 20;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'per_page.integer' => 'Количество элементов на странице должно быть числом',
            'per_page.min' => 'Минимальное количество элементов на странице: 1',
            'per_page.max' => 'Максимальное количество элементов на странице: 100',
        ];
    }
}
