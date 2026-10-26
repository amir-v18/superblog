<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') · Pressverket</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- color-scheme makes native controls (radios, scrollbars, dialogs) dark too. --}}
<body class="min-h-screen bg-studio-bg font-sans text-studio-ink antialiased accent-studio-brand [color-scheme:dark] lg:flex">
    @php
        // A route with {superblog} (builder, RSS pages) marks that magazine as active in the menu.
        $currentSuperblog = request()->route('superblog');
        $currentSuperblogId = $currentSuperblog instanceof App\Models\Superblog ? $currentSuperblog->getKey() : $currentSuperblog;
        $navItemClasses = 'flex items-center gap-2.5 rounded-[7px] px-2.5 py-2.25 text-sm text-[#c9c8c2] hover:bg-white/7 aria-[current=page]:bg-white/12 aria-[current=page]:font-semibold aria-[current=page]:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand';
        $navLabelClasses = 'px-2.5 pt-4 pb-1.5 text-[10px] tracking-[.14em] text-[#7d7f87]';
    @endphp

    {{-- Stacks above the content on small screens and becomes a fixed-height sidebar from lg, so no JS toggle is needed. --}}
    <aside class="flex flex-col gap-5.5 border-white/6 bg-studio-side px-3.5 py-5.5 text-[#e9e7e1] lg:sticky lg:top-0 lg:h-screen lg:w-60 lg:shrink-0 lg:border-r">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.75 px-2 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-studio-brand">
            <span aria-hidden="true" class="text-[26px] text-studio-brand">▙</span>
            <span>
                <b class="block text-base tracking-tight">Pressverket</b>
                <small class="text-[11px] text-[#9a9ca4]">Magasinstudio</small>
            </span>
        </a>

        @auth
            <nav aria-label="Huvudmeny" class="flex flex-col gap-0.5 overflow-auto lg:flex-1">
                <a href="{{ route('admin.dashboard') }}" @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif class="{{ $navItemClasses }}">Översikt</a>

                {{-- accessibleTo() follows the same rule as the update policy, so every listed magazine may be opened. --}}
                @foreach (['PUBLICERADE' => $sidebarPublished, 'UNDER UPPBYGGNAD' => $sidebarDrafts] as $label => $superblogs)
                    <p class="{{ $navLabelClasses }}">{{ $label }}</p>
                    @if ($label === 'PUBLICERADE' && $superblogs->isEmpty())
                        <small class="px-2.5 text-sm text-[#999]">Inga ännu</small>
                    @endif
                    @foreach ($superblogs as $superblog)
                        @php
                            $themeName = App\Support\Theme::name($superblog->settings['theme'] ?? null);
                        @endphp
                        <a href="{{ route('admin.superblogs.edit', $superblog) }}" @if ($superblog->getKey() == $currentSuperblogId) aria-current="page" @endif class="{{ $navItemClasses }}">
                            {{-- data-theme scopes the theme's CSS variables to the dot, so bg-primary is the magazine's own color. --}}
                            @if ($themeName)
                                <i data-theme="{{ $themeName }}" aria-hidden="true" class="size-2.5 shrink-0 rounded-[3px] bg-primary"></i>
                            @else
                                <i aria-hidden="true" class="size-2.5 shrink-0 rounded-[3px] bg-[#5b5d66]"></i>
                            @endif
                            <span class="truncate">{{ $superblog->name }}</span>
                        </a>
                    @endforeach
                @endforeach

                @can('create', App\Models\Superblog::class)
                    {{-- Opens the modal on pages that include it; elsewhere, or without JS, it is a normal link. --}}
                    <a href="{{ route('admin.superblogs.create') }}" data-open-dialog="create-superblog"
                       @if (request()->routeIs('admin.superblogs.create')) aria-current="page" @endif
                       class="{{ $navItemClasses }} mt-2 justify-center border border-dashed border-[#5b5d66] text-[#e9e7e1]">
                        + Nytt magasin
                    </a>
                @endcan
            </nav>

            @php
                // Initials from the first two words; mb_ functions keep Å, Ä and Ö intact.
                $initials = collect(preg_split('/\s+/u', trim(auth()->user()->name)))
                    ->filter()->take(2)
                    ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
                    ->implode('');
            @endphp
            <div class="flex flex-col gap-2.5 border-t border-white/12 px-2 pt-2.5">
                <div class="flex min-w-0 items-center gap-2.5">
                    <span aria-hidden="true" class="grid size-8 shrink-0 place-items-center rounded-full bg-studio-brand text-xs font-bold text-studio-side">{{ $initials }}</span>
                    <div class="min-w-0">
                        <b class="block truncate text-[13px]">{{ auth()->user()->name }}</b>
                        <small class="text-[11px] text-[#9a9ca4]">{{ auth()->user()->is_super_admin ? 'Administratör' : 'Redaktör' }}</small>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg border border-white/18 px-3 py-2.25 text-[13px] font-semibold text-studio-ink transition hover:border-studio-brand hover:bg-studio-brand/13 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">
                        <span aria-hidden="true">⎋</span> Logga ut
                    </button>
                </form>
            </div>
        @endauth
    </aside>

    {{-- Pages can replace the width and padding via @section('main-class'); the default keeps the old layout. --}}
    <main class="@yield('main-class', 'mx-auto w-full max-w-5xl px-6 py-12') lg:min-w-0">
        @if (session('status'))
            <div role="status" class="mb-6 rounded-lg border border-studio-ok/30 bg-studio-live p-4 text-studio-ok">{{ session('status') }}</div>
        @endif
        @yield('content')
    </main>
</body>
</html>
