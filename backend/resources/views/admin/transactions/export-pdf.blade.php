<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Transaksi</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1e293b; }
        h1 { font-size: 14px; margin: 0 0 2px; }
        .muted { color: #64748b; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #1e40af; color: #fff; padding: 6px; text-align: left; }
        td { padding: 5px 6px; border-bottom: 1px solid #e2e8f0; }
        .right { text-align: right; }
        .summary { margin-top: 8px; background: #f1f5f9; padding: 8px; }
        .summary span { margin-right: 18px; }
    </style>
</head>
<body>

@php $rupiah = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.'); @endphp

<h1>Laporan Transaksi — {{ config('app.name') }}</h1>
<div class="muted">
    Periode: {{ $filters['from'] ?? 'awal' }} s/d {{ $filters['to'] ?? 'sekarang' }}
    · Dicetak {{ now()->format('d/m/Y H:i') }}
</div>

<div class="summary">
    <span><strong>Total:</strong> {{ number_format($summary['total_count']) }}</span>
    <span><strong>Sukses:</strong> {{ number_format($summary['success_count']) }}</span>
    <span><strong>Gagal:</strong> {{ number_format($summary['failed_count']) }}</span>
    <span><strong>Omzet:</strong> {{ $rupiah($summary['omzet']) }}</span>
    <span><strong>Laba:</strong> {{ $rupiah($summary['profit']) }}</span>
</div>

<table>
    <thead>
        <tr>
            <th>Invoice</th><th>Tanggal</th><th>Pengguna</th><th>Produk</th><th>Tujuan</th>
            <th class="right">Modal</th><th class="right">Bayar</th><th class="right">Laba</th><th>Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($transactions as $transaction)
            <tr>
                <td>{{ $transaction->invoice_no }}</td>
                <td>{{ $transaction->created_at->format('d/m/y H:i') }}</td>
                <td>{{ $transaction->user?->name }}</td>
                <td>{{ $transaction->product_name }}</td>
                <td>{{ $transaction->customer_no }}</td>
                <td class="right">{{ $rupiah($transaction->base_price) }}</td>
                <td class="right">{{ $rupiah($transaction->total_paid) }}</td>
                <td class="right">{{ $rupiah($transaction->profit) }}</td>
                <td>{{ $transaction->status->label() }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

</body>
</html>
