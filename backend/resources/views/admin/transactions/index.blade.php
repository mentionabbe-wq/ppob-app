@extends('admin.layouts.app')
@section('title', 'Transaksi')

@php $rupiah = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.'); @endphp

@section('content')

<div class="mb-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
    <div class="rounded-2xl bg-white p-4 shadow-sm dark:bg-slate-800">
        <div class="text-xs text-slate-500">Transaksi</div>
        <div class="text-xl font-semibold">{{ number_format($summary['total_count']) }}</div>
    </div>
    <div class="rounded-2xl bg-white p-4 shadow-sm dark:bg-slate-800">
        <div class="text-xs text-slate-500">Sukses</div>
        <div class="text-xl font-semibold text-emerald-600">{{ number_format($summary['success_count']) }}</div>
    </div>
    <div class="rounded-2xl bg-white p-4 shadow-sm dark:bg-slate-800">
        <div class="text-xs text-slate-500">Omzet</div>
        <div class="text-xl font-semibold">{{ $rupiah($summary['omzet']) }}</div>
    </div>
    <div class="rounded-2xl bg-white p-4 shadow-sm dark:bg-slate-800">
        <div class="text-xs text-slate-500">Laba</div>
        <div class="text-xl font-semibold text-emerald-600">{{ $rupiah($summary['profit']) }}</div>
    </div>
</div>

{{-- Filter --}}
<form method="GET" class="mb-4 grid grid-cols-1 gap-3 rounded-2xl bg-white p-4 shadow-sm dark:bg-slate-800 sm:grid-cols-2 lg:grid-cols-6">
    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Invoice / nomor / SN"
           class="rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 lg:col-span-2">

    <select name="status" class="rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
        <option value="">Semua Status</option>
        @foreach (\App\Enums\TransactionStatus::cases() as $status)
            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
        @endforeach
    </select>

    <select name="provider_id" class="rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
        <option value="">Semua Provider</option>
        @foreach ($providers as $provider)
            <option value="{{ $provider->id }}" @selected((int) ($filters['provider_id'] ?? 0) === $provider->id)>{{ $provider->name }}</option>
        @endforeach
    </select>

    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"
           class="rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"
           class="rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">

    <div class="flex gap-2 lg:col-span-6">
        <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm text-white hover:bg-brand-700">Terapkan</button>
        <a href="{{ route('admin.transactions.index') }}" class="rounded-lg bg-slate-100 px-4 py-2 text-sm dark:bg-slate-700">Reset</a>
        <a href="{{ route('admin.transactions.export.excel', request()->query()) }}"
           class="ml-auto rounded-lg bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700">Export Excel</a>
        <a href="{{ route('admin.transactions.export.pdf', request()->query()) }}"
           class="rounded-lg bg-rose-600 px-4 py-2 text-sm text-white hover:bg-rose-700">Export PDF</a>
    </div>
</form>

{{-- Tabel --}}
<div class="overflow-x-auto rounded-2xl bg-white shadow-sm dark:bg-slate-800">
    <table class="w-full min-w-[900px] text-sm">
        <thead class="border-b border-slate-200 text-left text-slate-500 dark:border-slate-700">
            <tr>
                <th class="p-4">Invoice</th>
                <th class="p-4">Pengguna</th>
                <th class="p-4">Produk</th>
                <th class="p-4">Tujuan</th>
                <th class="p-4 text-right">Bayar</th>
                <th class="p-4 text-right">Laba</th>
                <th class="p-4">Status</th>
                <th class="p-4">Waktu</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse ($transactions as $transaction)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                    <td class="p-4">
                        <a href="{{ route('admin.transactions.show', $transaction) }}"
                           class="font-medium text-brand-600 hover:underline">{{ $transaction->invoice_no }}</a>
                    </td>
                    <td class="p-4">{{ $transaction->user?->name }}</td>
                    <td class="p-4">{{ $transaction->product_name }}</td>
                    <td class="p-4 font-mono text-xs">{{ $transaction->customer_no }}</td>
                    <td class="p-4 text-right">{{ $rupiah($transaction->total_paid) }}</td>
                    <td class="p-4 text-right text-emerald-600">{{ $rupiah($transaction->profit) }}</td>
                    <td class="p-4">
                        @include('admin.partials.status-badge', ['status' => $transaction->status])
                    </td>
                    <td class="p-4 text-xs text-slate-500">{{ $transaction->created_at->format('d/m/y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="p-8 text-center text-slate-500">Tidak ada transaksi.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $transactions->links() }}</div>

@endsection
