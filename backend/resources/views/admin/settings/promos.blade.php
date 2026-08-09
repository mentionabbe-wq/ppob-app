@extends('admin.layouts.app')
@section('title', 'Promo & Voucher')

@php $rupiah = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.'); @endphp

@section('content')

<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

    <form method="POST" action="{{ route('admin.settings.promos.store') }}"
          class="space-y-3 rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
        @csrf
        <h3 class="font-semibold">Buat Promo</h3>

        <input name="code" required maxlength="40" placeholder="Kode promo (mis. HEMAT10)"
               class="w-full rounded-lg border-slate-300 px-3 py-2 text-sm uppercase dark:bg-slate-700 dark:border-slate-600">
        <input name="title" required maxlength="120" placeholder="Judul promo"
               class="w-full rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
        <textarea name="description" rows="2" maxlength="1000" placeholder="Deskripsi"
                  class="w-full rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600"></textarea>

        <div class="grid grid-cols-2 gap-3">
            <select name="discount_type" class="rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
                <option value="fixed">Potongan tetap</option>
                <option value="percent">Potongan persen</option>
            </select>
            <input name="discount_value" type="number" step="0.01" min="0" required placeholder="Nilai"
                   class="rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
            <input name="max_discount" type="number" step="0.01" min="0" placeholder="Maks potongan"
                   class="rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
            <input name="min_transaction" type="number" step="0.01" min="0" value="0" required placeholder="Min transaksi"
                   class="rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
            <input name="quota" type="number" min="1" placeholder="Kuota (kosong = tanpa batas)"
                   class="rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
            <input name="per_user_limit" type="number" min="1" value="1" required placeholder="Batas per user"
                   class="rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
            <input name="start_date" type="date" required class="rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
            <input name="end_date" type="date" required class="rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
        </div>

        <button class="w-full rounded-lg bg-brand-600 py-2.5 text-sm font-medium text-white hover:bg-brand-700">Simpan Promo</button>
    </form>

    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm dark:bg-slate-800 lg:col-span-2">
        <table class="w-full text-sm">
            <thead class="border-b border-slate-200 text-left text-slate-500 dark:border-slate-700">
                <tr>
                    <th class="p-4">Kode</th><th class="p-4">Judul</th><th class="p-4">Potongan</th>
                    <th class="p-4 text-right">Terpakai</th><th class="p-4">Periode</th><th class="p-4">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse ($promos as $promo)
                    <tr>
                        <td class="p-4 font-mono font-medium">{{ $promo->code }}</td>
                        <td class="p-4">{{ $promo->title }}</td>
                        <td class="p-4">
                            {{ $promo->discount_type === 'percent'
                                ? rtrim(rtrim(number_format((float) $promo->discount_value, 2), '0'), ',').'%'
                                : $rupiah($promo->discount_value) }}
                        </td>
                        <td class="p-4 text-right">{{ $promo->used }}{{ $promo->quota ? '/'.$promo->quota : '' }}</td>
                        <td class="p-4 text-xs">{{ $promo->start_date->format('d/m/y') }} – {{ $promo->end_date->format('d/m/y') }}</td>
                        <td class="p-4">
                            <span class="rounded-full px-2.5 py-1 text-xs {{ $promo->is_active && $promo->end_date->isFuture() ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' }}">
                                {{ $promo->is_active && $promo->end_date->isFuture() ? 'Aktif' : 'Berakhir' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-8 text-center text-slate-500">Belum ada promo.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $promos->links() }}</div>
    </div>
</div>

@endsection
