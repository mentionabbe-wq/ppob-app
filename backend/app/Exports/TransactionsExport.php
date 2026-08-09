<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Transaction;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TransactionsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private readonly array $filters = []) {}

    /** FromQuery + chunk membuat ekspor ratusan ribu baris tetap hemat memori. */
    public function query()
    {
        return Transaction::query()
            ->with(['user:id,name,email', 'product:id,name,sku'])
            ->status($this->filters['status'] ?? null)
            ->between($this->filters['from'] ?? null, $this->filters['to'] ?? null)
            ->search($this->filters['search'] ?? null)
            ->when($this->filters['provider_id'] ?? null, fn ($q, $v) => $q->where('provider_id', $v))
            ->latest('id');
    }

    public function headings(): array
    {
        return [
            'Invoice', 'Tanggal', 'Nama User', 'Email', 'Produk', 'SKU',
            'Nomor Tujuan', 'Harga Modal', 'Harga Jual', 'Diskon',
            'Total Bayar', 'Keuntungan', 'Status', 'Serial Number',
        ];
    }

    /** @param  Transaction  $transaction */
    public function map($transaction): array
    {
        return [
            $transaction->invoice_no,
            $transaction->created_at->format('d/m/Y H:i'),
            $transaction->user?->name,
            $transaction->user?->email,
            $transaction->product_name,
            $transaction->product?->sku,
            $transaction->customer_no,
            (float) $transaction->base_price,
            (float) $transaction->sell_price,
            (float) $transaction->discount,
            (float) $transaction->total_paid,
            (float) $transaction->profit,
            $transaction->status->label(),
            $transaction->serial_number,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
