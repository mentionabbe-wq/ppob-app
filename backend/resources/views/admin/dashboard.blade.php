@extends('admin.layouts.app')
@section('title', 'Dashboard')

@php
    $card = 'rounded-2xl bg-white p-5 shadow-sm dark:bg-slate-800';
    $rupiah = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
@endphp

@section('content')

{{-- Kartu ringkasan --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="{{ $card }}">
        <div class="text-sm text-slate-500">Total Pengguna</div>
        <div class="mt-1 text-2xl font-semibold">{{ number_format($userStats['total']) }}</div>
        <div class="mt-1 text-xs text-emerald-600">+{{ $userStats['new_today'] }} hari ini</div>
    </div>

    <div class="{{ $card }}">
        <div class="text-sm text-slate-500">Total Transaksi</div>
        <div class="mt-1 text-2xl font-semibold">{{ number_format($summary['total_count']) }}</div>
        <div class="mt-1 text-xs text-slate-500">
            {{ number_format($summary['success_count']) }} sukses ·
            {{ number_format($summary['pending_count']) }} pending
        </div>
    </div>

    <div class="{{ $card }}">
        <div class="text-sm text-slate-500">Omzet</div>
        <div class="mt-1 text-2xl font-semibold">{{ $rupiah($summary['omzet']) }}</div>
        <div class="mt-1 text-xs text-slate-500">Hari ini {{ $rupiah($today['omzet']) }}</div>
    </div>

    <div class="{{ $card }}">
        <div class="text-sm text-slate-500">Keuntungan</div>
        <div class="mt-1 text-2xl font-semibold text-emerald-600">{{ $rupiah($summary['profit']) }}</div>
        <div class="mt-1 text-xs text-slate-500">Hari ini {{ $rupiah($today['profit']) }}</div>
    </div>
</div>

<div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div class="{{ $card }}">
        <div class="text-sm text-slate-500">Deposit Disetujui</div>
        <div class="mt-1 text-xl font-semibold">{{ $rupiah($depositStats['approved_amount']) }}</div>
    </div>
    <div class="{{ $card }}">
        <div class="text-sm text-slate-500">Deposit Menunggu</div>
        <div class="mt-1 text-xl font-semibold text-amber-600">{{ number_format($depositStats['pending_count']) }}</div>
        <a href="{{ route('admin.deposits.index', ['status' => 'waiting_payment']) }}"
           class="text-xs text-brand-600 hover:underline">Tinjau sekarang →</a>
    </div>
    <div class="{{ $card }}">
        <div class="text-sm text-slate-500">Total Saldo Pengguna</div>
        <div class="mt-1 text-xl font-semibold">{{ $rupiah($totalUserBalance) }}</div>
    </div>
</div>

{{-- Grafik --}}
<div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
    <div class="{{ $card }}">
        <h2 class="mb-4 font-semibold">Transaksi 30 Hari Terakhir</h2>
        <canvas id="dailyChart" height="120"></canvas>
    </div>
    <div class="{{ $card }}">
        <h2 class="mb-4 font-semibold">Omzet & Laba 12 Bulan</h2>
        <canvas id="monthlyChart" height="120"></canvas>
    </div>
</div>

{{-- Tabel ringkas --}}
<div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
    <div class="{{ $card }} xl:col-span-2">
        <h2 class="mb-4 font-semibold">Produk Terlaris</h2>
        <table class="w-full text-sm">
            <thead class="text-left text-slate-500">
                <tr><th class="pb-2">Produk</th><th class="pb-2 text-right">Transaksi</th><th class="pb-2 text-right">Omzet</th><th class="pb-2 text-right">Laba</th></tr>
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
        <h2 class="mb-4 font-semibold">Saldo Provider</h2>
        <ul class="space-y-3 text-sm">
            @forelse ($providerBalances as $provider)
                <li class="flex items-center justify-between">
                    <div>
                        <div class="font-medium">{{ $provider['name'] }}</div>
                        <div class="text-xs text-slate-500">
                            {{ $provider['balance_synced_at'] ? \Illuminate\Support\Carbon::parse($provider['balance_synced_at'])->diffForHumans() : 'Belum disinkronkan' }}
                        </div>
                    </div>
                    <span class="font-semibold {{ $provider['balance'] < 100000 ? 'text-rose-600' : '' }}">
                        {{ $rupiah($provider['balance']) }}
                    </span>
                </li>
            @empty
                <li class="text-slate-500">Belum ada provider aktif.</li>
            @endforelse
        </ul>

        <h2 class="mb-3 mt-6 font-semibold">Pengguna Teraktif</h2>
        <ul class="space-y-2 text-sm">
            @foreach ($activeUsers as $user)
                <li class="flex justify-between">
                    <span class="truncate">{{ $user['name'] }}</span>
                    <span class="text-slate-500">{{ number_format($user['trx_count']) }}x</span>
                </li>
            @endforeach
        </ul>
    </div>
</div>

@push('scripts')
<script>
    const daily = @json($dailySeries);
    const monthly = @json($monthlySeries);
    const gridColor = 'rgba(148,163,184,.2)';

    new Chart(document.getElementById('dailyChart'), {
        type: 'line',
        data: {
            labels: daily.map(d => d.date),
            datasets: [
                { label: 'Transaksi', data: daily.map(d => d.trx_count), borderColor: '#2563eb',
                  backgroundColor: 'rgba(37,99,235,.1)', fill: true, tension: .35 },
            ],
        },
        options: { responsive: true, plugins: { legend: { display: false } },
                   scales: { y: { beginAtZero: true, grid: { color: gridColor } }, x: { grid: { display: false } } } },
    });

    new Chart(document.getElementById('monthlyChart'), {
        type: 'bar',
        data: {
            labels: monthly.map(d => d.month),
            datasets: [
                { label: 'Omzet', data: monthly.map(d => d.omzet), backgroundColor: '#3b82f6' },
                { label: 'Laba', data: monthly.map(d => d.profit), backgroundColor: '#10b981' },
            ],
        },
        options: { responsive: true, scales: { y: { beginAtZero: true, grid: { color: gridColor } }, x: { grid: { display: false } } } },
    });
</script>
@endpush

@endsection
