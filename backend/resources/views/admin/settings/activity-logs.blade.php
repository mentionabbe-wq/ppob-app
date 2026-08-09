@extends('admin.layouts.app')
@section('title', 'Log Aktivitas')

@section('content')

<form method="GET" class="mb-4 flex flex-wrap gap-3 rounded-2xl bg-white p-4 shadow-sm dark:bg-slate-800">
    <input name="action" value="{{ $filters['action'] ?? '' }}" placeholder="Filter aksi (mis. deposit.approved)"
           class="flex-1 rounded-lg border-slate-300 px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600">
    <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm text-white hover:bg-brand-700">Cari</button>
    <a href="{{ route('admin.settings.logs') }}" class="rounded-lg bg-slate-100 px-4 py-2 text-sm dark:bg-slate-700">Reset</a>
</form>

<div class="overflow-x-auto rounded-2xl bg-white shadow-sm dark:bg-slate-800">
    <table class="w-full min-w-[800px] text-sm">
        <thead class="border-b border-slate-200 text-left text-slate-500 dark:border-slate-700">
            <tr><th class="p-4">Waktu</th><th class="p-4">Aktor</th><th class="p-4">Aksi</th><th class="p-4">Objek</th><th class="p-4">IP</th><th class="p-4">Detail</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse ($logs as $log)
                <tr>
                    <td class="p-4 whitespace-nowrap text-xs text-slate-500">{{ $log->created_at->format('d/m/y H:i:s') }}</td>
                    <td class="p-4">{{ $log->user?->name ?? 'Sistem' }}</td>
                    <td class="p-4 font-mono text-xs">{{ $log->action }}</td>
                    <td class="p-4 text-xs text-slate-500">{{ class_basename((string) $log->subject_type) }} #{{ $log->subject_id }}</td>
                    <td class="p-4 font-mono text-xs">{{ $log->ip_address }}</td>
                    <td class="p-4">
                        @if ($log->properties)
                            <details>
                                <summary class="cursor-pointer text-xs text-brand-600">Lihat</summary>
                                <pre class="mt-1 max-w-md overflow-x-auto rounded bg-slate-900 p-2 text-xs text-slate-100">{{ json_encode($log->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </details>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-8 text-center text-slate-500">Belum ada aktivitas tercatat.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $logs->links() }}</div>

@endsection
