@extends('admin.layouts.app')
@section('title', 'Pengaturan')

@php $card = 'rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800'; @endphp

@section('content')

<div class="mb-4 flex flex-wrap gap-2">
    <a href="{{ route('admin.settings.banners') }}" class="rounded-lg bg-slate-100 px-4 py-2 text-sm dark:bg-slate-700">Banner</a>
    <a href="{{ route('admin.settings.promos') }}" class="rounded-lg bg-slate-100 px-4 py-2 text-sm dark:bg-slate-700">Promo &amp; Voucher</a>
    <a href="{{ route('admin.settings.logs') }}" class="rounded-lg bg-slate-100 px-4 py-2 text-sm dark:bg-slate-700">Log Aktivitas</a>
</div>

<div class="grid grid-cols-1 gap-4 xl:grid-cols-2">

    {{-- Pengaturan umum --}}
    <form method="POST" action="{{ route('admin.settings.update') }}" class="{{ $card }}">
        @csrf @method('PUT')
        <h3 class="mb-4 font-semibold">Pengaturan Aplikasi</h3>

        @foreach ($settings as $group => $items)
            <div class="mb-6">
                <h4 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ str($group)->headline() }}</h4>
                <div class="space-y-3">
                    @foreach ($items as $setting)
                        <div>
                            <label class="mb-1 block text-sm">{{ $setting->label ?? str($setting->key)->headline() }}</label>
                            @if ($setting->type === 'bool')
                                <select name="settings[{{ $setting->key }}]"
                                        class="w-full rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
                                    <option value="1" @selected($setting->castValue())>Ya</option>
                                    <option value="0" @selected(! $setting->castValue())>Tidak</option>
                                </select>
                            @else
                                <input name="settings[{{ $setting->key }}]" value="{{ $setting->value }}"
                                       class="w-full rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <button class="rounded-lg bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700">Simpan Pengaturan</button>
    </form>

    {{-- Provider --}}
    <div class="space-y-4">
        @foreach ($providers as $provider)
            <form method="POST" action="{{ route('admin.settings.providers.update', $provider) }}" class="{{ $card }}">
                @csrf @method('PUT')

                <div class="mb-4 flex items-center justify-between">
                    <h3 class="font-semibold">{{ $provider->name }}</h3>
                    <span class="rounded-full px-2.5 py-1 text-xs {{ $provider->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' }}">
                        {{ $provider->is_active ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-sm">Base URL</label>
                        <input name="base_url" value="{{ $provider->base_url }}" required
                               class="w-full rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
                    </div>

                    @foreach ($provider->code === 'digiflazz'
                        ? ['username' => 'Username', 'api_key' => 'API Key', 'webhook_secret' => 'Webhook Secret']
                        : ['api_id' => 'API ID', 'api_key' => 'API Key', 'webhook_secret' => 'Webhook Secret'] as $key => $label)
                        <div>
                            <label class="mb-1 block text-sm">{{ $label }}</label>
                            <input name="credentials[{{ $key }}]" type="password" autocomplete="off"
                                   placeholder="{{ filled($provider->credential($key)) ? '•••••• (tersimpan)' : 'Belum diatur' }}"
                                   class="w-full rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
                        </div>
                    @endforeach

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-sm">Prioritas</label>
                            <input name="priority" type="number" min="1" max="999" value="{{ $provider->priority }}"
                                   class="w-full rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
                        </div>
                        <label class="flex items-end gap-2 pb-2 text-sm">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" @checked($provider->is_active) class="rounded border-slate-300">
                            Aktifkan provider
                        </label>
                    </div>
                </div>

                <p class="mt-3 text-xs text-slate-500">
                    Kredensial disimpan terenkripsi. Kosongkan bila tidak ingin mengubah.
                </p>

                <button class="mt-4 rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-700">
                    Simpan
                </button>
            </form>
        @endforeach
    </div>
</div>

@endsection
