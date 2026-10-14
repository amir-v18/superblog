<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Superblog')</title>
    <meta name="description" content="@yield('meta_description', '')">
    <link rel="canonical" href="@yield('canonical', url()->current())">

    {{-- Open Graph reuses the same sections so each page only defines its SEO values once. --}}
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="@yield('title', 'Superblog')">
    <meta property="og:description" content="@yield('meta_description', '')">
    <meta property="og:url" content="@yield('canonical', url()->current())">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-white font-sans text-slate-900 antialiased">
    <header class="border-b border-slate-200">
        <nav aria-label="Huvudnavigering" class="mx-auto flex max-w-3xl flex-wrap items-center justify-between gap-4 px-6 py-4">
            <a href="#" class="text-lg font-semibold focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-slate-900">Superblog</a>
            <ul class="flex items-center gap-6 text-sm font-medium">
                <li><a href="#" class="hover:underline hover:underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-slate-900">Nyheter</a></li>
                <li><a href="#" class="hover:underline hover:underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-slate-900">Guider</a></li>
                <li><a href="#" class="hover:underline hover:underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-slate-900">Om oss</a></li>
            </ul>
        </nav>
    </header>

    {{-- No <h1> here: each page renders exactly one H1 itself (SEO rule). --}}
    <main class="mx-auto w-full max-w-3xl flex-1 px-6 py-12">
        @yield('content')
    </main>

    <footer class="border-t border-slate-200">
        <div class="mx-auto max-w-3xl px-6 py-6 text-sm text-slate-600">
            &copy; {{ date('Y') }} Superblog
        </div>
    </footer>
</body>
</html>
