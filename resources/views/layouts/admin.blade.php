<!DOCTYPE html>
{{-- Fixed to Swedish: the admin text is Swedish, while APP_LOCALE stays English so validation messages do not change.
     The mode attribute is only written for the light choice (cookie set by StudioModeController); without it the
     tag is exactly as it was without this feature, and the default look applies. Only this layout ever writes it. --}}
<html lang="sv"@if (request()->cookie(App\Http\Controllers\StudioModeController::COOKIE) === 'light') data-studio-mode="light"@endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Admin and login pages must never show up in search results. --}}
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Admin') · Pressverket</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Only the logo font of the newspaper theme, for template and magazine thumbnails. Named, so the admin does not start loading other fonts. --}}
    @fonts(['unifrakturmaguntia'])
</head>
{{-- color-scheme makes native controls (radios, scrollbars, dialogs) dark too. --}}
<body class="min-h-screen bg-studio-bg font-sans text-studio-ink antialiased accent-studio-brand [color-scheme:dark] lg:flex">
    @php
        // A route with {superblog} (builder, RSS pages) marks that magazine as active in the menu.
        $currentSuperblog = request()->route('superblog');
        $currentSuperblogId = $currentSuperblog instanceof App\Models\Superblog ? $currentSuperblog->getKey() : $currentSuperblog;
        $navItemClasses = 'flex items-center gap-2.5 rounded-[7px] px-2.5 py-2.25 text-sm text-studio-side-nav hover:bg-studio-contrast/7 aria-[current=page]:bg-studio-contrast/12 aria-[current=page]:font-semibold aria-[current=page]:text-studio-contrast studio-focus-ring studio-light:aria-[current=page]:bg-studio-brand/8';
        $navLabelClasses = 'px-2.5 pt-4 pb-1.5 text-[10px] tracking-[.14em] text-studio-side-label';
    @endphp

    {{-- Hidden until focused, so keyboard users can skip the magazine list in the sidebar. --}}
    <a href="#main" class="sr-only rounded-lg bg-studio-brand px-4 py-2 text-sm font-semibold text-studio-on-brand focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50">Hoppa till innehållet</a>

    {{-- Stacks above the content on small screens and becomes a fixed-height sidebar from lg, so no JS toggle is needed. --}}
    <aside class="flex flex-col gap-5.5 border-studio-contrast/6 bg-studio-side px-3.5 py-5.5 text-studio-side-ink lg:sticky lg:top-0 lg:h-screen lg:w-60 lg:shrink-0 lg:border-r studio-light:border-studio-line studio-light:max-lg:border-b">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.75 px-2 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-studio-brand">
            <span aria-hidden="true" class="text-[26px] text-studio-brand-text">▙</span>
            <span>
                <b class="block text-base tracking-tight">Pressverket</b>
                <small class="text-[11px] text-studio-side-muted">Magasinstudio</small>
            </span>
        </a>

        @auth
            <nav aria-label="Huvudmeny" class="flex flex-col gap-0.5 overflow-auto lg:flex-1">
                <a href="{{ route('admin.dashboard') }}" @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif class="{{ $navItemClasses }}">Översikt</a>

                {{-- The section only appears when this user has live Superblogs to open. --}}
                @if ($sidebarPublished->isNotEmpty())
                    <a href="{{ route('admin.superblogs.published') }}" @if (request()->routeIs('admin.superblogs.published')) aria-current="page" @endif class="{{ $navItemClasses }}">Publicerade</a>
                    @foreach ($sidebarPublished as $superblog)
                        @php
                            $themeName = App\Support\Theme::nameFor($superblog);
                        @endphp
                        <a href="{{ route('admin.superblogs.edit', $superblog) }}" @if ($superblog->getKey() == $currentSuperblogId) aria-current="page" @endif class="{{ $navItemClasses }} pl-6">
                            {{-- data-theme scopes the theme's CSS variables to the dot, so bg-primary is the magazine's own color. --}}
                            @if ($themeName)
                                <i data-theme="{{ $themeName }}" aria-hidden="true" class="size-2.5 shrink-0 rounded-[3px] bg-primary"></i>
                            @else
                                <i aria-hidden="true" class="size-2.5 shrink-0 rounded-[3px] bg-studio-side-mark"></i>
                            @endif
                            <span class="truncate">{{ $superblog->name }}</span>
                        </a>
                    @endforeach
                @endif

                <p class="{{ $navLabelClasses }}">UNDER UPPBYGGNAD</p>
                @foreach ($sidebarDrafts as $superblog)
                    @php
                        $themeName = App\Support\Theme::name($superblog->settings['theme'] ?? null);
                    @endphp
                    <a href="{{ route('admin.superblogs.edit', $superblog) }}" @if ($superblog->getKey() == $currentSuperblogId) aria-current="page" @endif class="{{ $navItemClasses }}">
                        @if ($themeName)
                            <i data-theme="{{ $themeName }}" aria-hidden="true" class="size-2.5 shrink-0 rounded-[3px] bg-primary"></i>
                        @else
                            <i aria-hidden="true" class="size-2.5 shrink-0 rounded-[3px] bg-studio-side-mark"></i>
                        @endif
                        <span class="truncate">{{ $superblog->name }}</span>
                    </a>
                @endforeach

                @can('create', App\Models\Superblog::class)
                    {{-- Opens the modal at the end of this layout; without JS it is a normal link to the fallback page. --}}
                    <a href="{{ route('admin.superblogs.create') }}" data-open-dialog="create-superblog"
                       @if (request()->routeIs('admin.superblogs.create')) aria-current="page" @endif
                       class="{{ $navItemClasses }} mt-2 justify-center border border-dashed border-studio-side-mark text-studio-side-ink">
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
            <div class="flex flex-col gap-2.5 border-t border-studio-contrast/12 px-2 pt-2.5">
                <div class="flex min-w-0 items-center gap-2.5">
                    <span aria-hidden="true" class="grid size-8 shrink-0 place-items-center rounded-full bg-studio-brand text-xs font-bold text-studio-on-brand">{{ $initials }}</span>
                    <div class="min-w-0">
                        <b class="block truncate text-[13px]">{{ auth()->user()->name }}</b>
                        <small class="text-[11px] text-studio-side-muted">{{ auth()->user()->is_super_admin ? 'Administratör' : 'Redaktör' }}</small>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg border border-studio-contrast/18 px-3 py-2.25 text-[13px] font-semibold text-studio-ink transition hover:border-studio-brand hover:bg-studio-brand/13 hover:text-studio-contrast studio-focus-ring">
                        <span aria-hidden="true">⎋</span> Logga ut
                    </button>
                </form>
            </div>
        @endauth
    </aside>

    {{-- Mode switch in the top right corner of every page, the builder included, at every width. It takes no room,
         so nothing else moves; 8 px from the page edge and 28 px tall, it ends 4 px above the opening row of the
         dashboard. --}}
    <x-admin.mode-toggle class="absolute top-2 right-4 z-10" />

    {{-- Pages can replace the width and padding via @section('main-class'); the default keeps the old layout. --}}
    <main id="main" class="@yield('main-class', 'mx-auto w-full max-w-5xl px-6 py-12') lg:min-w-0">
        @if (session('status'))
            <div role="status" class="mb-6 rounded-lg border border-studio-ok/30 bg-studio-live p-4 text-studio-ok">{{ session('status') }}</div>
        @endif
        @yield('content')
    </main>

    {{-- On every admin page, so the sidebar link opens the same modal everywhere instead of the fallback page. --}}
    @can('create', App\Models\Superblog::class)
        <x-admin.create-superblog-modal />
    @endcan
</body>
</html>
