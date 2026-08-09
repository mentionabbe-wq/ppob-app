@extends('admin.layouts.app')
@section('title', 'Produk')

@php $rupiah = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.'); @endphp

@section('content')

<div class="mb-4 flex flex-wrap items-center gap-2">
    <form method="POST" action="{{ route('admin.products.sync') }}">
        @csrf
        <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm text-white hover:bg-brand-700">
            Sinkronkan Katalog Provider
        </button>
    </form>

    <details class="relative">
        <summary class="cursor-pointer rounded-lg bg-slate-100 px-4 py-2 text-sm dark:bg-slate-700">Ubah Margin Massal</summary>
        <form method="POST" action="{{ route('admin.products.bulk-margin') }}"
              class="absolute z-20 mt-2 w-80 space-y-2 rounded-xl bg-white p-4 shadow-xl dark:bg-slate-800">
            @csrf
            <select name="category_id" class="w-full rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
                <option value="">Semua kategori</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
            <input name="brand" placeholder="Brand (opsional)" class="w-full rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
            <select name="margin_type" class="w-full rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
                <option value="fixed">Margin tetap (Rp)</option>
                <option value="percent">Margin persen (%)</option>
            </select>
            <input name="margin_value" type="number" step="0.01" min="0" required placeholder="Nilai margin"
                   class="w-full rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
            <button class="w-full rounded-lg bg-emerald-600 py-2 text-sm text-white hover:bg-emerald-700">Terapkan</button>
        </form>
    </details>
</div>

<form method="GET" class="mb-4 grid grid-cols-1 gap-3 rounded-2xl bg-white p-4 shadow-sm dark:bg-slate-800 sm:grid-cols-2 lg:grid-cols-5">
    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nama / SKU / brand"
           class="rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 lg:col-span-2">
    <select name="category_id" class="rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
        <option value="">Semua kategori</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected((int) ($filters['category_id'] ?? 0) === $category->id)>{{ $category->name }}</option>
        @endforeach
    </select>
    <select name="provider_id" class="rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
        <option value="">Semua provider</option>
        @foreach ($providers as $provider)
            <option value="{{ $provider->id }}" @selected((int) ($filters['provider_id'] ?? 0) === $provider->id)>{{ $provider->name }}</option>
        @endforeach
    </select>
    <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm text-white hover:bg-brand-700">Cari</button>
</form>

<div class="overflow-x-auto rounded-2xl bg-white shadow-sm dark:bg-slate-800">
    <table class="w-full min-w-[900px] text-sm">
        <thead class="border-b border-slate-200 text-left text-slate-500 dark:border-slate-700">
            <tr>
                <th class="p-4">Produk</th><th class="p-4">Kategori</th><th class="p-4">Provider</th>
                <th class="p-4 text-right">Modal</th><th class="p-4 text-right">Margin</th>
                <th class="p-4 text-right">Jual</th><th class="p-4 text-right">Laba</th>
                <th class="p-4">Status</th><th class="p-4"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse ($products as $product)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                    <td class="p-4">
                        <div class="font-medium">{{ $product->name }}</div>
                        <div class="font-mono text-xs text-slate-500">{{ $product->sku }} · {{ $product->brand }}</div>
                    </td>
                    <td class="p-4">{{ $product->category?->name }}</td>
                    <td class="p-4">{{ $product->provider?->name }}</td>
                    <td class="p-4 text-right">{{ $rupiah($product->base_price) }}</td>
                    <td class="p-4 text-right">
                        {{ $product->margin_type === 'percent'
                            ? rtrim(rtrim(number_format((float) $product->margin_value, 2), '0'), ',').'%'
                            : $rupiah($product->margin_value) }}
                    </td>
                    <td class="p-4 text-right font-medium">{{ $rupiah($product->sell_price) }}</td>
                    <td class="p-4 text-right text-emerald-600">{{ $rupiah($product->profit()) }}</td>
                    <td class="p-4">
                        @if (! $product->is_active)
                            <span class="rounded-full bg-slate-200 px-2.5 py-1 text-xs dark:bg-slate-600">Nonaktif</span>
                        @elseif (! $product->is_available)
                            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs text-amber-700">Kosong</span>
                        @else
                            <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs text-emerald-700">Tersedia</span>
                        @endif
                    </td>
                    <td class="p-4 whitespace-nowrap">
                        <a href="{{ route('admin.products.edit', $product) }}" class="text-brand-600 hover:underline">Ubah</a>
                        <form method="POST" action="{{ route('admin.products.toggle', $product) }}" class="ml-2 inline">
                            @csrf
                            <button class="text-slate-500 hover:underline">{{ $product->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="p-8 text-center text-slate-500">Belum ada produk. Jalankan sinkronisasi provider.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $products->links() }}</div>

@endsection
