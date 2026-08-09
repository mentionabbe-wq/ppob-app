@extends('admin.layouts.app')
@section('title', 'Pengguna')

@php $rupiah = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.'); @endphp

@section('content')

<div class="mb-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
    @foreach ([
        'Total Pengguna' => $stats['total'],
        'Aktif' => $stats['active'],
        'Baru Hari Ini' => $stats['new_today'],
        'Baru Bulan Ini' => $stats['new_this_month'],
    ] as $label => $value)
        <div class="rounded-2xl bg-white p-4 shadow-sm dark:bg-slate-800">
            <div class="text-xs text-slate-500">{{ $label }}</div>
            <div class="text-xl font-semibold">{{ number_format($value) }}</div>
        </div>
    @endforeach
</div>

<form method="GET" class="mb-4 flex flex-wrap gap-3 rounded-2xl bg-white p-4 shadow-sm dark:bg-slate-800">
    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nama / email / HP / kode referral"
           class="flex-1 rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
    <select name="status" class="rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
        <option value="">Semua status</option>
        @foreach (['active' => 'Aktif', 'inactive' => 'Nonaktif', 'suspended' => 'Diblokir'] as $value => $label)
            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <select name="role" class="rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
        <option value="">Semua peran</option>
        @foreach ($roles as $role)
            <option value="{{ $role->name }}" @selected(($filters['role'] ?? '') === $role->name)>{{ $role->name }}</option>
        @endforeach
    </select>
    <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm text-white hover:bg-brand-700">Cari</button>
</form>

<div class="overflow-x-auto rounded-2xl bg-white shadow-sm dark:bg-slate-800">
    <table class="w-full min-w-[800px] text-sm">
        <thead class="border-b border-slate-200 text-left text-slate-500 dark:border-slate-700">
            <tr>
                <th class="p-4">Nama</th><th class="p-4">Kontak</th><th class="p-4">Peran</th>
                <th class="p-4 text-right">Saldo</th><th class="p-4">Status</th>
                <th class="p-4">Login Terakhir</th><th class="p-4"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse ($users as $user)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                    <td class="p-4">
                        <div class="font-medium">{{ $user->name }}</div>
                        <div class="font-mono text-xs text-slate-500">{{ $user->referral_code }}</div>
                    </td>
                    <td class="p-4">
                        <div>{{ $user->email }}</div>
                        <div class="text-xs text-slate-500">{{ $user->phone ?? '—' }}</div>
                    </td>
                    <td class="p-4">{{ $user->getRoleNames()->join(', ') ?: '—' }}</td>
                    <td class="p-4 text-right font-medium">{{ $rupiah($user->wallet?->balance ?? 0) }}</td>
                    <td class="p-4">
                        <span class="rounded-full px-2.5 py-1 text-xs
                            {{ match ($user->status) {
                                'active' => 'bg-emerald-100 text-emerald-700',
                                'suspended' => 'bg-rose-100 text-rose-700',
                                default => 'bg-slate-200 text-slate-700',
                            } }}">
                            {{ ['active' => 'Aktif', 'inactive' => 'Nonaktif', 'suspended' => 'Diblokir'][$user->status] }}
                        </span>
                    </td>
                    <td class="p-4 text-xs text-slate-500">{{ $user->last_login_at?->diffForHumans() ?? 'Belum pernah' }}</td>
                    <td class="p-4"><a href="{{ route('admin.users.show', $user) }}" class="text-brand-600 hover:underline">Detail</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="p-8 text-center text-slate-500">Tidak ada pengguna.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $users->links() }}</div>

@endsection
