@extends('admin.layouts.app')
@section('title', 'Deposit')

@php $rupiah = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.'); @endphp

@section('content')

<div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div class="rounded-2xl bg-white p-4 shadow-sm dark:bg-slate-800">
        <div class="text-xs text-slate-500">Total Pengajuan</div>
        <div class="text-xl font-semibold">{{ number_format($summary['total_count']) }}</div>
    </div>
    <div class="rounded-2xl bg-white p-4 shadow-sm dark:bg-slate-800">
        <div class="text-xs text-slate-500">Menunggu Verifikasi</div>
        <div class="text-xl font-semibold text-amber-600">{{ number_format($summary['pending_count']) }}</div>
    </div>
    <div class="rounded-2xl bg-white p-4 shadow-sm dark:bg-slate-800">
        <div class="text-xs text-slate-500">Nominal Disetujui</div>
        <div class="text-xl font-semibold text-emerald-600">{{ $rupiah($summary['approved_amount']) }}</div>
    </div>
</div>

<form method="GET" class="mb-4 grid grid-cols-1 gap-3 rounded-2xl bg-white p-4 shadow-sm dark:bg-slate-800 sm:grid-cols-2 lg:grid-cols-5">
    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Kode / nama / email"
           class="rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 lg:col-span-2">
    <select name="status" class="rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
        <option value="">Semua Status</option>
        @foreach (\App\Enums\DepositStatus::cases() as $status)
            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
        @endforeach
    </select>
    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
    <div class="flex gap-2 lg:col-span-5">
        <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm text-white hover:bg-brand-700">Terapkan</button>
        <a href="{{ route('admin.deposits.index') }}" class="rounded-lg bg-slate-100 px-4 py-2 text-sm dark:bg-slate-700">Reset</a>
    </div>
</form>

<div class="overflow-x-auto rounded-2xl bg-white shadow-sm dark:bg-slate-800">
    <table class="w-full min-w-[800px] text-sm">
        <thead class="border-b border-slate-200 text-left text-slate-500 dark:border-slate-700">
            <tr>
                <th class="p-4">Kode</th><th class="p-4">Pengguna</th><th class="p-4">Metode</th>
                <th class="p-4 text-right">Nominal</th><th class="p-4 text-right">Total Transfer</th>
                <th class="p-4">Status</th><th class="p-4">Waktu</th><th class="p-4"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse ($deposits as $deposit)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                    <td class="p-4 font-medium">{{ $deposit->code }}</td>
                    <td class="p-4">
                        {{ $deposit->user?->name }}
                        <div class="text-xs text-slate-500">{{ $deposit->user?->email }}</div>
                    </td>
                    <td class="p-4">
                        {{ str($deposit->method)->headline() }}
                        <div class="text-xs uppercase text-slate-500">{{ $deposit->channel }}</div>
                    </td>
                    <td class="p-4 text-right">{{ $rupiah($deposit->amount) }}</td>
                    <td class="p-4 text-right font-medium">{{ $rupiah($deposit->total_amount) }}</td>
                    <td class="p-4">@include('admin.partials.status-badge', ['status' => $deposit->status])</td>
                    <td class="p-4 text-xs text-slate-500">{{ $deposit->created_at->format('d/m/y H:i') }}</td>
                    <td class="p-4">
                        <a href="{{ route('admin.deposits.show', $deposit) }}" class="text-brand-600 hover:underline">Tinjau</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="p-8 text-center text-slate-500">Tidak ada deposit.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $deposits->links() }}</div>

@endsection
