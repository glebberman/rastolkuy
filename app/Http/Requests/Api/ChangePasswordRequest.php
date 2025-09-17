<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'confirmed',
                'min:8',
            ],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Необходимо указать текущий пароль',
            'password.required' => 'Необходимо указать новый пароль',
            'password.min' => 'Пароль должен содержать минимум :min символов',
            'password.confirmed' => 'Подтверждение пароля не совпадает',
            'password_confirmation.required' => 'Необходимо подтвердить новый пароль',
        ];
    }
}
