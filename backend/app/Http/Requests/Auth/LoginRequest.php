<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:191'],
            'password' => ['required', 'string'],
            'fcm_token' => ['nullable', 'string', 'max:512'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
    }
}
