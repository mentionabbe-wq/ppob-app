@extends('admin.layouts.app')
@section('title', 'Ubah Produk')

@php $rupiah = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.'); @endphp

@section('content')

<form method="POST" action="{{ route('admin.products.update', $product) }}"
      class="max-w-3xl space-y-5 rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
    @csrf
    @method('PUT')

    <div class="rounded-lg bg-slate-50 p-4 text-sm dark:bg-slate-700/50">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div><div class="text-xs text-slate-500">SKU</div><div class="font-mono">{{ $product->sku }}</div></div>
            <div><div class="text-xs text-slate-500">Provider</div><div>{{ $product->provider?->name }}</div></div>
            <div><div class="text-xs text-slate-500">Harga Modal</div><div>{{ $rupiah($product->base_price) }}</div></div>
            <div><div class="text-xs text-slate-500">Harga Jual</div><div class="font-semibold">{{ $rupiah($product->sell_price) }}</div></div>
        </div>
        <p class="mt-3 text-xs text-slate-500">
            Harga modal &amp; ketersediaan mengikuti provider dan diperbarui otomatis saat sinkronisasi.
            Harga jual dihitung ulang dari margin di bawah.
        </p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label class="mb-1 block text-sm font-medium">Nama Produk</label>
            <input name="name" value="{{ old('name', $product->name) }}" required maxlength="120"
                   class="w-full rounded-lg border-slate-300 px-3 py-2 dark:bg-slate-700 dark:border-slate-600">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Kategori</label>
            <select name="category_id" class="w-full rounded-lg border-slate-300 px-3 py-2 dark:bg-slate-700 dark:border-slate-600">
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected($product->category_id === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Tipe Margin</label>
            <select name="margin_type" class="w-full rounded-lg border-slate-300 px-3 py-2 dark:bg-slate-700 dark:border-slate-600">
                <option value="fixed" @selected($product->margin_type === 'fixed')>Tetap (Rp)</option>
                <option value="percent" @selected($product->margin_type === 'percent')>Persen (%)</option>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Nilai Margin</label>
            <input name="margin_value" type="number" step="0.01" min="0" required
                   value="{{ old('margin_value', $product->margin_value) }}"
                   class="w-full rounded-lg border-slate-300 px-3 py-2 dark:bg-slate-700 dark:border-slate-600">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Biaya Admin (produk pascabayar)</label>
            <input name="admin_fee" type="number" step="0.01" min="0" required
                   value="{{ old('admin_fee', $product->admin_fee) }}"
                   class="w-full rounded-lg border-slate-300 px-3 py-2 dark:bg-slate-700 dark:border-slate-600">
        </div>
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium">Deskripsi</label>
        <textarea name="description" rows="3" maxlength="1000"
                  class="w-full rounded-lg border-slate-300 px-3 py-2 dark:bg-slate-700 dark:border-slate-600">{{ old('description', $product->description) }}</textarea>
    </div>

    <div class="flex gap-6">
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked($product->is_active) class="rounded border-slate-300">
            Aktif dijual
        </label>
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="is_featured" value="0">
            <input type="checkbox" name="is_featured" value="1" @checked($product->is_featured) class="rounded border-slate-300">
            Tampilkan sebagai unggulan
        </label>
    </div>

    <div class="flex gap-2">
        <button class="rounded-lg bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700">Simpan</button>
        <a href="{{ route('admin.products.index') }}" class="rounded-lg bg-slate-100 px-5 py-2.5 dark:bg-slate-700">Batal</a>
    </div>
</form>

@endsection
