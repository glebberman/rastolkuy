<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => [
                'required',
                'string',
                Rule::exists('roles', 'name'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'role.required' => ' >;L >1O70B5;L=0',
            'role.exists' => '#:070==0O @>;L =5 ACI5AB2C5B',
        ];
    }
}
