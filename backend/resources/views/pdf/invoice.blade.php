<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $transaction->invoice_no }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; margin: 0; padding: 24px; }
        .header { border-bottom: 2px solid #2563eb; padding-bottom: 12px; margin-bottom: 16px; }
        .brand { font-size: 18px; font-weight: bold; color: #2563eb; }
        .muted { color: #64748b; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        .info td { padding: 3px 0; vertical-align: top; }
        .info td:first-child { color: #64748b; width: 40%; }
        .items th { background: #f1f5f9; text-align: left; padding: 8px; font-size: 10px; }
        .items td { padding: 8px; border-bottom: 1px solid #e2e8f0; }
        .total { font-size: 14px; font-weight: bold; color: #2563eb; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 10px; font-weight: bold; }
        .sn { background: #f8fafc; border: 1px dashed #94a3b8; padding: 10px; margin-top: 12px; font-family: monospace; font-size: 12px; text-align: center; }
        .footer { margin-top: 24px; text-align: center; color: #94a3b8; font-size: 9px; border-top: 1px solid #e2e8f0; padding-top: 10px; }
    </style>
</head>
<body>

@php
    $rupiah = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
    $badgeColor = match ($transaction->status->color()) {
        'emerald' => ['#dcfce7', '#15803d'],
        'amber' => ['#fef3c7', '#b45309'],
        'rose' => ['#ffe4e6', '#be123c'],
        default => ['#e0f2fe', '#0369a1'],
    };
@endphp

<div class="header">
    <table>
        <tr>
            <td>
                <div class="brand">{{ config('app.name') }}</div>
                <div class="muted">Bukti Transaksi Pembelian</div>
            </td>
            <td style="text-align: right">
                <div style="font-weight: bold">{{ $transaction->invoice_no }}</div>
                <div class="muted">{{ $transaction->created_at->format('d F Y, H:i') }} WIB</div>
                <div style="margin-top: 6px">
                    <span class="badge" style="background: {{ $badgeColor[0] }}; color: {{ $badgeColor[1] }}">
                        {{ strtoupper($transaction->status->label()) }}
                    </span>
                </div>
            </td>
        </tr>
    </table>
</div>

<table>
    <tr>
        <td style="width: 50%; vertical-align: top">
            <div style="font-weight: bold; margin-bottom: 6px">Pelanggan</div>
            <table class="info">
                <tr><td>Nama</td><td>{{ $transaction->user->name }}</td></tr>
                <tr><td>Email</td><td>{{ $transaction->user->email }}</td></tr>
            </table>
        </td>
        <td style="width: 50%; vertical-align: top">
            <div style="font-weight: bold; margin-bottom: 6px">Tujuan</div>
            <table class="info">
                <tr><td>Nomor</td><td>{{ $transaction->customer_no }}</td></tr>
                @if ($transaction->customer_name)
                    <tr><td>Atas nama</td><td>{{ $transaction->customer_name }}</td></tr>
                @endif
                <tr><td>Kategori</td><td>{{ $transaction->product?->category?->name }}</td></tr>
            </table>
        </td>
    </tr>
</table>

<table class="items" style="margin-top: 16px">
    <thead>
        <tr><th>Deskripsi</th><th style="text-align: right">Jumlah</th></tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ $transaction->product_name }}</td>
            <td style="text-align: right">{{ $rupiah($transaction->sell_price) }}</td>
        </tr>
        @if ((float) $transaction->admin_fee > 0)
            <tr><td>Biaya admin</td><td style="text-align: right">{{ $rupiah($transaction->admin_fee) }}</td></tr>
        @endif
        @if ((float) $transaction->discount > 0)
            <tr><td>Diskon promo</td><td style="text-align: right; color: #059669">-{{ $rupiah($transaction->discount) }}</td></tr>
        @endif
        <tr>
            <td class="total">TOTAL DIBAYAR</td>
            <td class="total" style="text-align: right">{{ $rupiah($transaction->total_paid) }}</td>
        </tr>
    </tbody>
</table>

@if ($transaction->serial_number)
    <div class="sn">
        <div class="muted" style="margin-bottom: 4px">SERIAL NUMBER / TOKEN</div>
        <strong>{{ $transaction->serial_number }}</strong>
    </div>
@endif

<div class="footer">
    Dokumen ini dicetak otomatis dari sistem dan sah tanpa tanda tangan.<br>
    Dicetak pada {{ now()->format('d/m/Y H:i') }} WIB · {{ config('app.url') }}
</div>

</body>
</html>
