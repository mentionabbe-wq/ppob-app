@extends('admin.layouts.app')
@section('title', 'Laporan')

@php
    $rupiah = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
    $card = 'rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800';
@endphp

@section('content')

<form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-2xl bg-white p-4 shadow-sm dark:bg-slate-800">
    <div>
        <label class="mb-1 block text-xs text-slate-500">Dari</label>
        <input type="date" name="from" value="{{ $filters['from'] }}"
               class="rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
    </div>
    <div>
        <label class="mb-1 block text-xs text-slate-500">Sampai</label>
        <input type="date" name="to" value="{{ $filters['to'] }}"
               class="rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
    </div>
    <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm text-white hover:bg-brand-700">Terapkan</button>
    <a href="{{ route('admin.reports.export', request()->query()) }}"
       class="ml-auto rounded-lg bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700">Export Excel</a>
</form>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ([
        'Transaksi Sukses' => number_format($summary['success_count']),
        'Transaksi Gagal' => number_format($summary['failed_count']),
        'Total Omzet' => $rupiah($summary['omzet']),
        'Total Keuntungan' => $rupiah($summary['profit']),
    ] as $label => $value)
        <div class="{{ $card }}">
            <div class="text-sm text-slate-500">{{ $label }}</div>
            <div class="mt-1 text-2xl font-semibold">{{ $value }}</div>
        </div>
    @endforeach
</div>

<div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
    <div class="{{ $card }}">
        <h3 class="mb-4 font-semibold">Laporan Deposit</h3>
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Total pengajuan</dt><dd>{{ number_format($depositSummary['total_count']) }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Menunggu verifikasi</dt><dd>{{ number_format($depositSummary['pending_count']) }}</dd></div>
            <div class="flex justify-between font-semibold"><dt>Nominal disetujui</dt><dd>{{ $rupiah($depositSummary['approved_amount']) }}</dd></div>
        </dl>
    </div>

    <div class="{{ $card }}">
        <h3 class="mb-4 font-semibold">Tren Bulanan</h3>
        <canvas id="reportChart" height="140"></canvas>
    </div>
</div>

<div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
    <div class="{{ $card }}">
        <h3 class="mb-4 font-semibold">Produk Terlaris</h3>
        <table class="w-full text-sm">
            <thead class="text-left text-slate-500">
                <tr><th class="pb-2">Produk</th><th class="pb-2 text-right">Qty</th><th class="pb-2 text-right">Omzet</th><th class="pb-2 text-right">Laba</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse ($bestProducts as $product)
                    <tr>
                        <td class="py-2">{{ $product['name'] }}</td>
                        <td class="py-2 text-right">{{ number_format($product['trx_count']) }}</td>
                        <td class="py-2 text-right">{{ $rupiah($product['omzet']) }}</td>
                        <td class="py-2 text-right text-emerald-600">{{ $rupiah($product['profit']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-center text-slate-500">Belum ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="{{ $card }}">
        <h3 class="mb-4 font-semibold">Pengguna Teraktif</h3>
        <table class="w-full text-sm">
            <thead class="text-left text-slate-500">
                <tr><th class="pb-2">Nama</th><th class="pb-2">Email</th><th class="pb-2 text-right">Transaksi</th><th class="pb-2 text-right">Belanja</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse ($activeUsers as $user)
                    <tr>
                        <td class="py-2">{{ $user['name'] }}</td>
                        <td class="py-2 text-xs text-slate-500">{{ $user['email'] }}</td>
                        <td class="py-2 text-right">{{ number_format($user['trx_count']) }}</td>
                        <td class="py-2 text-right">{{ $rupiah($user['omzet']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-center text-slate-500">Belum ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
    const monthly = @json($monthlySeries);
    new Chart(document.getElementById('reportChart'), {
        type: 'line',
        data: {
            labels: monthly.map(d => d.month),
            datasets: [
                { label: 'Omzet', data: monthly.map(d => d.omzet), borderColor: '#2563eb', tension: .35 },
                { label: 'Laba', data: monthly.map(d => d.profit), borderColor: '#10b981', tension: .35 },
            ],
        },
        options: { responsive: true, scales: { y: { beginAtZero: true } } },
    });
</script>
@endpush

@endsection
