<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') · Superblog</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <nav aria-label="Main navigation" class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-6 py-4">
            <a href="{{ route('admin.dashboard') }}" class="text-lg font-semibold focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-slate-900">Superblog</a>
            @auth
                <div class="flex items-center gap-6">
                    <a href="{{ route('admin.dashboard') }}" class="text-sm font-medium underline underline-offset-4" @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif>Dashboard</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900">Log out</button>
                    </form>
                </div>
            @endauth
        </nav>
    </header>
    <main class="mx-auto max-w-5xl px-6 py-12">
        @if (session('status'))
            <div role="status" class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-emerald-900">{{ session('status') }}</div>
        @endif
        @yield('content')
    </main>
</body>
</html>
