@extends('admin.layouts.app')
@section('title', 'Deposit '.$deposit->code)

@php
    $rupiah = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
    $card = 'rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800';
@endphp

@section('content')

<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

    <div class="{{ $card }} lg:col-span-2">
        <div class="mb-6 flex items-start justify-between">
            <div>
                <div class="text-sm text-slate-500">Kode Deposit</div>
                <div class="text-xl font-semibold">{{ $deposit->code }}</div>
            </div>
            @include('admin.partials.status-badge', ['status' => $deposit->status])
        </div>

        <dl class="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
            @foreach ([
                'Pengguna' => $deposit->user?->name,
                'Email' => $deposit->user?->email,
                'Saldo Saat Ini' => $rupiah($deposit->user?->wallet?->balance ?? 0),
                'Metode' => str($deposit->method)->headline().' '.strtoupper((string) $deposit->channel),
                'Nominal' => $rupiah($deposit->amount),
                'Kode Unik' => $deposit->unique_code ?: '—',
                'Total Transfer' => $rupiah($deposit->total_amount),
                'Virtual Account' => $deposit->va_number ?? '—',
                'Diajukan' => $deposit->created_at->format('d/m/Y H:i'),
                'Kedaluwarsa' => $deposit->expired_at?->format('d/m/Y H:i') ?? '—',
                'Dibayar' => $deposit->paid_at?->format('d/m/Y H:i') ?? '—',
                'Diverifikasi oleh' => $deposit->approver?->name ?? '—',
            ] as $label => $value)
                <div>
                    <dt class="text-xs text-slate-500">{{ $label }}</dt>
                    <dd class="mt-0.5">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>

        @if ($deposit->reject_reason)
            <div class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:bg-rose-950 dark:text-rose-200">
                <strong>Alasan penolakan:</strong> {{ $deposit->reject_reason }}
            </div>
        @endif
    </div>

    <div class="space-y-4">
        @if ($deposit->proof_path)
            <div class="{{ $card }}">
                <h3 class="mb-3 font-semibold">Bukti Transfer</h3>
                <a href="{{ Storage::disk('public')->url($deposit->proof_path) }}" target="_blank" rel="noopener">
                    <img src="{{ Storage::disk('public')->url($deposit->proof_path) }}" alt="Bukti transfer"
                         class="w-full rounded-lg border border-slate-200 dark:border-slate-700">
                </a>
                <p class="mt-2 text-xs text-slate-500">Klik untuk memperbesar.</p>
            </div>
        @endif

        @role('super-admin|admin|finance')
            @unless ($deposit->status->isFinal())
                <div class="{{ $card }}">
                    <h3 class="mb-4 font-semibold">Verifikasi</h3>

                    <form method="POST" action="{{ route('admin.deposits.approve', $deposit) }}" class="mb-4 space-y-2"
                          onsubmit="return confirm('Setujui deposit dan tambahkan {{ $rupiah($deposit->amount) }} ke saldo pengguna?')">
                        @csrf
                        <input name="note" maxlength="255" placeholder="Catatan (opsional)"
                               class="w-full rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
                        <button class="w-full rounded-lg bg-emerald-600 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                            Setujui &amp; Tambah Saldo
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.deposits.reject', $deposit) }}" class="space-y-2">
                        @csrf
                        <input name="reason" required maxlength="255" placeholder="Alasan penolakan"
                               class="w-full rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
                        <button class="w-full rounded-lg bg-rose-600 py-2 text-sm font-medium text-white hover:bg-rose-700">
                            Tolak
                        </button>
                    </form>
                </div>
            @endunless
        @endrole
    </div>
</div>

@endsection
