<?php

declare(strict_types=1);

namespace App\Http\Requests\Export;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Запрос на скачивание экспорта по токену.
 */
final class DownloadExportRequest extends FormRequest
{
    /**
     * Определяет, авторизован ли пользователь для выполнения этого запроса.
     */
    public function authorize(): bool
    {
        // Скачивание по токену доступно без авторизации
        return true;
    }

    /**
     * Правила валидации для запроса.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => [
                'required',
                'string',
                'size:64', // Токен всегда 64 символа
                'regex:/^[a-zA-Z0-9]+$/', // Только буквы и цифры
            ],
        ];
    }

    /**
     * Кастомные сообщения об ошибках валидации.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.required' => 'Токен скачивания обязателен для заполнения.',
            'token.string' => 'Токен должен быть строкой.',
            'token.size' => 'Неверный формат токена.',
            'token.regex' => 'Токен содержит недопустимые символы.',
        ];
    }

    /**
     * Получает токен скачивания.
     */
    public function getToken(): string
    {
        $token = $this->validated('token');

        return is_string($token) ? $token : '';
    }
}