@extends('admin.layouts.app')
@section('title', 'Banner')

@section('content')

<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

    <form method="POST" action="{{ route('admin.settings.banners.store') }}" enctype="multipart/form-data"
          class="space-y-3 rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
        @csrf
        <h3 class="font-semibold">Tambah Banner</h3>

        <input name="title" required maxlength="120" placeholder="Judul banner"
               class="w-full rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">

        <input name="image" type="file" accept="image/*" required
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">

        <select name="action_type" class="w-full rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
            <option value="none">Tanpa aksi</option>
            <option value="url">Buka URL</option>
            <option value="category">Buka kategori</option>
            <option value="product">Buka produk</option>
            <option value="promo">Buka promo</option>
        </select>

        <input name="action_value" maxlength="191" placeholder="URL / slug kategori / ID produk"
               class="w-full rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">

        <div class="grid grid-cols-2 gap-3">
            <input name="start_date" type="date" class="rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
            <input name="end_date" type="date" class="rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
        </div>

        <input name="sort_order" type="number" min="0" value="0" placeholder="Urutan"
               class="w-full rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">

        <button class="w-full rounded-lg bg-brand-600 py-2.5 text-sm font-medium text-white hover:bg-brand-700">Simpan Banner</button>
    </form>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:col-span-2">
        @forelse ($banners as $banner)
            <div class="overflow-hidden rounded-2xl bg-white shadow-sm dark:bg-slate-800">
                <img src="{{ Storage::disk('public')->url($banner->image_path) }}" alt="{{ $banner->title }}"
                     class="h-36 w-full object-cover">
                <div class="p-4">
                    <div class="font-medium">{{ $banner->title }}</div>
                    <div class="text-xs text-slate-500">
                        {{ $banner->action_type }}{{ $banner->action_value ? ': '.$banner->action_value : '' }}
                    </div>
                    <div class="mt-1 text-xs text-slate-500">
                        {{ $banner->start_date?->format('d/m/Y') ?? '—' }} s/d {{ $banner->end_date?->format('d/m/Y') ?? '—' }}
                    </div>

                    <form method="POST" action="{{ route('admin.settings.banners.destroy', $banner) }}" class="mt-3"
                          onsubmit="return confirm('Hapus banner ini?')">
                        @csrf @method('DELETE')
                        <button class="text-sm text-rose-600 hover:underline">Hapus</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="text-slate-500">Belum ada banner.</p>
        @endforelse
    </div>
</div>

@endsection
