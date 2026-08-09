<?php

declare(strict_types=1);

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'customer_no' => ['required', 'string', 'min:4', 'max:40', 'regex:/^[0-9A-Za-z._@-]+$/'],
            'ref_id' => ['nullable', 'string', 'min:8', 'max:64', 'alpha_dash'],
            'promo_code' => ['nullable', 'string', 'max:40'],
            'pin' => ['nullable', 'string', 'digits:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_no.regex' => 'Nomor tujuan hanya boleh berisi huruf, angka, titik, dan strip.',
            'ref_id.alpha_dash' => 'ref_id hanya boleh huruf, angka, strip, dan garis bawah.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // ref_id adalah kunci idempotency; bila klien tidak mengirim,
        // server membuatkan satu agar transaksi tetap dapat dilacak.
        $this->merge([
            'customer_no' => trim((string) $this->input('customer_no')),
            'ref_id' => $this->input('ref_id') ?: 'TRX'.Str::upper(Str::random(16)),
        ]);
    }
}
