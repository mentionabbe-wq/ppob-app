@extends('admin.layouts.app')
@section('title', 'Pengguna: '.$user->name)

@php
    $rupiah = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
    $card = 'rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800';
@endphp

@section('content')

<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

    {{-- Profil & peran --}}
    <div class="{{ $card }} lg:col-span-2">
        <h3 class="mb-4 font-semibold">Data Pengguna</h3>

        @role('super-admin|admin')
            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-4">
                @csrf @method('PUT')

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Nama</label>
                        <input name="name" value="{{ old('name', $user->name) }}" required
                               class="w-full rounded-lg border-slate-300 px-3 py-2 dark:bg-slate-700 dark:border-slate-600">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Email</label>
                        <input name="email" type="email" value="{{ old('email', $user->email) }}" required
                               class="w-full rounded-lg border-slate-300 px-3 py-2 dark:bg-slate-700 dark:border-slate-600">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Nomor HP</label>
                        <input name="phone" value="{{ old('phone', $user->phone) }}"
                               class="w-full rounded-lg border-slate-300 px-3 py-2 dark:bg-slate-700 dark:border-slate-600">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Status</label>
                        <select name="status" class="w-full rounded-lg border-slate-300 px-3 py-2 dark:bg-slate-700 dark:border-slate-600">
                            @foreach (['active' => 'Aktif', 'inactive' => 'Nonaktif', 'suspended' => 'Diblokir'] as $value => $label)
                                <option value="{{ $value }}" @selected($user->status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium">Peran</label>
                    <div class="flex flex-wrap gap-4">
                        @foreach (\Spatie\Permission\Models\Role::orderBy('name')->get() as $role)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="roles[]" value="{{ $role->name }}"
                                       @checked($user->hasRole($role->name)) class="rounded border-slate-300">
                                {{ $role->name }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <button class="rounded-lg bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700">Simpan</button>
            </form>
        @else
            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div><dt class="text-xs text-slate-500">Email</dt><dd>{{ $user->email }}</dd></div>
                <div><dt class="text-xs text-slate-500">Nomor HP</dt><dd>{{ $user->phone ?? '—' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Status</dt><dd>{{ $user->status }}</dd></div>
                <div><dt class="text-xs text-slate-500">Peran</dt><dd>{{ $user->getRoleNames()->join(', ') }}</dd></div>
            </dl>
        @endrole
    </div>

    {{-- Saldo --}}
    <div class="space-y-4">
        <div class="{{ $card }}">
            <div class="text-sm text-slate-500">Saldo Saat Ini</div>
            <div class="mt-1 text-3xl font-semibold">{{ $rupiah($user->wallet?->balance ?? 0) }}</div>

            @role('super-admin|finance')
                <form method="POST" action="{{ route('admin.users.balance', $user) }}" class="mt-4 space-y-2"
                      onsubmit="return confirm('Sesuaikan saldo pengguna ini?')">
                    @csrf
                    <input name="amount" type="number" step="0.01" required placeholder="Nominal (negatif untuk mengurangi)"
                           class="w-full rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
                    <input name="reason" required minlength="5" maxlength="255" placeholder="Alasan penyesuaian"
                           class="w-full rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
                    <button class="w-full rounded-lg bg-amber-600 py-2 text-sm font-medium text-white hover:bg-amber-700">
                        Sesuaikan Saldo
                    </button>
                </form>
                <p class="mt-2 text-xs text-slate-500">Setiap penyesuaian tercatat di ledger dan audit log.</p>
            @endrole
        </div>

        <div class="{{ $card }}">
            <h3 class="mb-3 font-semibold">Rekening Terdaftar</h3>
            <ul class="space-y-2 text-sm">
                @forelse ($user->bankAccounts as $account)
                    <li>
                        <div class="font-medium">{{ $account->bank_name }} — {{ $account->account_number }}</div>
                        <div class="text-xs text-slate-500">{{ $account->account_name }}</div>
                    </li>
                @empty
                    <li class="text-slate-500">Belum ada rekening.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>

{{-- Riwayat --}}
<div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
    <div class="{{ $card }}">
        <h3 class="mb-4 font-semibold">Transaksi Terakhir</h3>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse ($transactions as $transaction)
                    <tr>
                        <td class="py-2">
                            <a href="{{ route('admin.transactions.show', $transaction) }}" class="text-brand-600 hover:underline">
                                {{ $transaction->invoice_no }}
                            </a>
                            <div class="text-xs text-slate-500">{{ $transaction->product_name }}</div>
                        </td>
                        <td class="py-2 text-right">{{ $rupiah($transaction->total_paid) }}</td>
                        <td class="py-2 pl-3 text-right">@include('admin.partials.status-badge', ['status' => $transaction->status])</td>
                    </tr>
                @empty
                    <tr><td class="py-4 text-center text-slate-500">Belum ada transaksi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="{{ $card }}">
        <h3 class="mb-4 font-semibold">Mutasi Saldo</h3>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse ($mutations as $mutation)
                    <tr>
                        <td class="py-2">
                            <div>{{ $mutation->type->label() }}</div>
                            <div class="text-xs text-slate-500">{{ $mutation->description }}</div>
                        </td>
                        <td class="py-2 text-right {{ (float) $mutation->amount < 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                            {{ $rupiah($mutation->amount) }}
                        </td>
                        <td class="py-2 pl-3 text-right text-xs text-slate-500">{{ $rupiah($mutation->balance_after) }}</td>
                    </tr>
                @empty
                    <tr><td class="py-4 text-center text-slate-500">Belum ada mutasi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
