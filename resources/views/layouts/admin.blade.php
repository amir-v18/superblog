<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') · Pressverket</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased lg:flex">
    {{-- Stacks above the content on small screens and becomes a fixed-height sidebar from lg, so no JS toggle is needed. --}}
    <aside class="flex flex-col bg-slate-950 text-slate-300 lg:sticky lg:top-0 lg:h-screen lg:w-64 lg:shrink-0">
        <div class="px-6 py-5">
            <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-3 font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white">
                <span aria-hidden="true" class="flex size-8 items-center justify-center rounded-md bg-white text-sm font-bold text-slate-950">P</span>
                <span class="text-lg tracking-tight">Pressverket</span>
            </a>
        </div>

        @auth
            <nav aria-label="Huvudmeny" class="px-3 lg:flex-1">
                <ul class="flex flex-wrap gap-1 lg:flex-col">
                    <li>
                        <a href="{{ route('admin.dashboard') }}"
                           @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif
                           class="block rounded-md px-3 py-2 text-sm font-medium hover:bg-slate-800 hover:text-white aria-[current=page]:bg-slate-800 aria-[current=page]:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                            Översikt
                        </a>
                    </li>
                    @can('create', App\Models\Superblog::class)
                        <li>
                            <a href="{{ route('admin.superblogs.create') }}"
                               @if (request()->routeIs('admin.superblogs.create')) aria-current="page" @endif
                               class="block rounded-md px-3 py-2 text-sm font-medium hover:bg-slate-800 hover:text-white aria-[current=page]:bg-slate-800 aria-[current=page]:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                                + Nytt magasin
                            </a>
                        </li>
                    @endcan
                </ul>
            </nav>

            <div class="mt-4 flex items-center justify-between gap-4 border-t border-slate-800 px-6 py-4 lg:mt-0">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-white">{{ auth()->user()->name }}</p>
                    <p class="truncate text-xs text-slate-400">{{ auth()->user()->email }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-md border border-slate-700 px-3 py-1.5 text-xs font-medium hover:bg-slate-800 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">Logga ut</button>
                </form>
            </div>
        @endauth
    </aside>

    <main class="mx-auto w-full max-w-5xl px-6 py-12 lg:min-w-0">
        @if (session('status'))
            <div role="status" class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-emerald-900">{{ session('status') }}</div>
        @endif
        @yield('content')
    </main>
</body>
</html>
