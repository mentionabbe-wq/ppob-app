<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk Admin — {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex min-h-screen items-center justify-center bg-gradient-to-br from-blue-600 to-blue-800 p-4">

<div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-2xl">
    <div class="mb-8 text-center">
        <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-600 text-2xl font-bold text-white">P</div>
        <h1 class="text-2xl font-semibold text-slate-800">Panel Admin</h1>
        <p class="text-sm text-slate-500">{{ config('app.name') }}</p>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="mb-1 block text-sm font-medium text-slate-700">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                   class="w-full rounded-lg border border-slate-300 px-4 py-2.5 focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium text-slate-700">Kata Sandi</label>
            <input id="password" name="password" type="password" required
                   class="w-full rounded-lg border border-slate-300 px-4 py-2.5 focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" value="1" class="rounded border-slate-300">
            Ingat saya
        </label>

        <button type="submit"
                class="w-full rounded-lg bg-blue-600 py-2.5 font-medium text-white transition hover:bg-blue-700">
            Masuk
        </button>
    </form>
</div>

</body>
</html>
