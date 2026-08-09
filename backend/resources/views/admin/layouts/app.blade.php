<!DOCTYPE html>
<html lang="id" class="h-full" x-data="{ dark: localStorage.theme === 'dark' }" :class="dark && 'dark'">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ config('app.name') }} Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { colors: { brand: {
                50:'#eff6ff',100:'#dbeafe',200:'#bfdbfe',300:'#93c5fd',400:'#60a5fa',
                500:'#3b82f6',600:'#2563eb',700:'#1d4ed8',800:'#1e40af',900:'#1e3a8a'
            } } } }
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>
<body class="h-full bg-slate-50 text-slate-800 dark:bg-slate-900 dark:text-slate-100">
<div class="flex min-h-full" x-data="{ sidebar: false }">

    {{-- Sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full bg-white shadow-lg transition-transform dark:bg-slate-800 lg:translate-x-0"
           :class="sidebar && 'translate-x-0'">
        <div class="flex h-16 items-center gap-2 border-b border-slate-200 px-6 dark:border-slate-700">
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-600 font-bold text-white">P</div>
            <span class="text-lg font-semibold">{{ config('app.name') }}</span>
        </div>

        <nav class="space-y-1 p-4 text-sm">
            @php
                $menu = [
                    ['route' => 'admin.dashboard',          'label' => 'Dashboard',  'icon' => '▦'],
                    ['route' => 'admin.transactions.index', 'label' => 'Transaksi',  'icon' => '⇄'],
                    ['route' => 'admin.deposits.index',     'label' => 'Deposit',    'icon' => '＋'],
                    ['route' => 'admin.products.index',     'label' => 'Produk',     'icon' => '▤'],
                    ['route' => 'admin.users.index',        'label' => 'Pengguna',   'icon' => '☺'],
                    ['route' => 'admin.reports.index',      'label' => 'Laporan',    'icon' => '◫'],
                    ['route' => 'admin.settings.index',     'label' => 'Pengaturan', 'icon' => '⚙'],
                ];
            @endphp

            @foreach ($menu as $item)
                @continue(! Route::has($item['route']))
                @php $active = request()->routeIs(str_replace('.index', '', $item['route']).'*'); @endphp
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2.5 transition
                          {{ $active
                              ? 'bg-brand-600 text-white'
                              : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700' }}">
                    <span class="w-5 text-center">{{ $item['icon'] }}</span>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col lg:pl-64">
        {{-- Topbar --}}
        <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200 bg-white/80 px-4 backdrop-blur dark:border-slate-700 dark:bg-slate-800/80 lg:px-8">
            <div class="flex items-center gap-3">
                <button class="lg:hidden" @click="sidebar = !sidebar" aria-label="Menu">☰</button>
                <h1 class="text-lg font-semibold">@yield('title', 'Dashboard')</h1>
            </div>

            <div class="flex items-center gap-4">
                <button @click="dark = !dark; localStorage.theme = dark ? 'dark' : 'light'"
                        class="rounded-lg px-2 py-1 hover:bg-slate-100 dark:hover:bg-slate-700"
                        aria-label="Ganti tema">
                    <span x-show="!dark">🌙</span><span x-show="dark">☀️</span>
                </button>

                <div class="text-right text-sm">
                    <div class="font-medium">{{ auth()->user()->name }}</div>
                    <div class="text-xs text-slate-500">{{ auth()->user()->getRoleNames()->join(', ') }}</div>
                </div>

                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button class="rounded-lg bg-slate-100 px-3 py-1.5 text-sm hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600">
                        Keluar
                    </button>
                </form>
            </div>
        </header>

        <main class="flex-1 p-4 lg:p-8">
            @if (session('success'))
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-200">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-200">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
