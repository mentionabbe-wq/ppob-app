<?php

declare(strict_types=1);

namespace App\Http\Requests\Deposit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'amount' => [
                'required', 'numeric',
                'min:'.config('ppob.deposit.min'),
                'max:'.config('ppob.deposit.max'),
            ],
            'method' => ['required', Rule::in(config('ppob.deposit.methods'))],
            'channel' => ['nullable', 'string', 'max:40', 'required_unless:method,qris'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'Deposit minimal Rp'.number_format((float) config('ppob.deposit.min'), 0, ',', '.').'.',
            'amount.max' => 'Deposit maksimal Rp'.number_format((float) config('ppob.deposit.max'), 0, ',', '.').'.',
            'channel.required_unless' => 'Pilih bank atau kanal pembayaran.',
        ];
    }
}
