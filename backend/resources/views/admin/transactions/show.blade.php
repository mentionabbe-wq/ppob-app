@extends('admin.layouts.app')
@section('title', 'Detail Transaksi '.$transaction->invoice_no)

@php
    $rupiah = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
    $card = 'rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800';
@endphp

@section('content')

<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

    <div class="{{ $card }} lg:col-span-2">
        <div class="mb-6 flex items-start justify-between">
            <div>
                <div class="text-sm text-slate-500">Invoice</div>
                <div class="text-xl font-semibold">{{ $transaction->invoice_no }}</div>
                <div class="mt-1 font-mono text-xs text-slate-500">ref_id: {{ $transaction->ref_id }}</div>
            </div>
            @include('admin.partials.status-badge', ['status' => $transaction->status])
        </div>

        <dl class="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
            @foreach ([
                'Pengguna' => $transaction->user?->name.' ('.$transaction->user?->email.')',
                'Produk' => $transaction->product_name,
                'Kategori' => $transaction->product?->category?->name,
                'Provider' => $transaction->provider?->name,
                'Nomor Tujuan' => $transaction->customer_no,
                'Nama Pelanggan' => $transaction->customer_name ?? '—',
                'Serial Number' => $transaction->serial_number ?? '—',
                'Pesan Provider' => $transaction->provider_message ?? '—',
                'Dibuat' => $transaction->created_at->format('d/m/Y H:i:s'),
                'Selesai' => $transaction->completed_at?->format('d/m/Y H:i:s') ?? '—',
            ] as $label => $value)
                <div>
                    <dt class="text-xs text-slate-500">{{ $label }}</dt>
                    <dd class="mt-0.5 break-words">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>

        <div class="mt-6 border-t border-slate-200 pt-4 dark:border-slate-700">
            <h3 class="mb-3 font-semibold">Rincian Harga</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-slate-500">Harga modal</span><span>{{ $rupiah($transaction->base_price) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Harga jual</span><span>{{ $rupiah($transaction->sell_price) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Biaya admin</span><span>{{ $rupiah($transaction->admin_fee) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Diskon</span><span>-{{ $rupiah($transaction->discount) }}</span></div>
                <div class="flex justify-between border-t border-slate-200 pt-2 font-semibold dark:border-slate-700">
                    <span>Total dibayar</span><span>{{ $rupiah($transaction->total_paid) }}</span>
                </div>
                <div class="flex justify-between font-semibold text-emerald-600"><span>Keuntungan</span><span>{{ $rupiah($transaction->profit) }}</span></div>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        {{-- Aksi --}}
        <div class="{{ $card }}">
            <h3 class="mb-4 font-semibold">Tindakan</h3>

            <form method="POST" action="{{ route('admin.transactions.sync', $transaction) }}" class="mb-3">
                @csrf
                <button class="w-full rounded-lg bg-slate-100 py-2 text-sm hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600">
                    Sinkronkan Status ke Provider
                </button>
            </form>

            @role('super-admin|admin|finance')
                @if ($transaction->isRefundable())
                    <form method="POST" action="{{ route('admin.transactions.refund', $transaction) }}" class="mb-3 space-y-2"
                          onsubmit="return confirm('Kembalikan {{ $rupiah($transaction->total_paid) }} ke saldo pengguna?')">
                        @csrf
                        <input name="reason" required maxlength="255" placeholder="Alasan refund"
                               class="w-full rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
                        <button class="w-full rounded-lg bg-amber-600 py-2 text-sm text-white hover:bg-amber-700">Refund</button>
                    </form>
                @endif

                @if (in_array($transaction->status->value, ['failed', 'refunded'], true))
                    <form method="POST" action="{{ route('admin.transactions.resend', $transaction) }}"
                          onsubmit="return confirm('Kirim ulang transaksi ini ke provider?')">
                        @csrf
                        <button class="w-full rounded-lg bg-brand-600 py-2 text-sm text-white hover:bg-brand-700">Kirim Ulang</button>
                    </form>
                @endif
            @endrole
        </div>

        {{-- Mutasi saldo terkait --}}
        <div class="{{ $card }}">
            <h3 class="mb-3 font-semibold">Mutasi Saldo</h3>
            <ul class="space-y-2 text-sm">
                @forelse ($transaction->mutations as $mutation)
                    <li class="flex justify-between">
                        <span class="text-slate-500">{{ $mutation->type->label() }}</span>
                        <span class="{{ (float) $mutation->amount < 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                            {{ $rupiah($mutation->amount) }}
                        </span>
                    </li>
                @empty
                    <li class="text-slate-500">Belum ada mutasi.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>

{{-- Log API --}}
<div class="{{ $card }} mt-4">
    <h3 class="mb-4 font-semibold">Log Komunikasi Provider</h3>
    <div class="space-y-3">
        @forelse ($transaction->apiLogs as $log)
            <details class="rounded-lg border border-slate-200 p-3 text-sm dark:border-slate-700">
                <summary class="cursor-pointer">
                    <span class="font-mono text-xs">{{ strtoupper($log->direction) }} {{ $log->method }} {{ $log->endpoint }}</span>
                    <span class="ml-2 text-xs text-slate-500">{{ $log->http_code }} · {{ $log->duration_ms }}ms · {{ $log->created_at->format('H:i:s') }}</span>
                </summary>
                <pre class="mt-2 overflow-x-auto rounded bg-slate-900 p-3 text-xs text-slate-100">{{ json_encode(['request' => $log->request_payload, 'response' => $log->response_payload], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </details>
        @empty
            <p class="text-sm text-slate-500">Belum ada log.</p>
        @endforelse
    </div>
</div>

@endsection
