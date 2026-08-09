<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'email' => ['required', 'email:rfc,dns', 'max:191', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'regex:/^(\+62|62|0)8[1-9][0-9]{6,11}$/', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'referral_code' => ['nullable', 'string', 'size:8', 'exists:users,referral_code'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Format nomor HP tidak valid (contoh: 081234567890).',
            'referral_code.exists' => 'Kode referral tidak ditemukan.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'referral_code' => $this->filled('referral_code')
                ? strtoupper(trim((string) $this->input('referral_code')))
                : null,
        ]);
    }
}
