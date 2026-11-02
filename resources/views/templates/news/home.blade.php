@use('App\Support\Theme')
@php
    // $theme: a theme name or an array of overrides with a base 'name', as in layouts.public.
    $themeName = Theme::name($theme);
    $themeStyle = Theme::cssVariables($theme);
    $sidePadding = 'px-10 max-[760px]:px-[18px]';
@endphp
<!DOCTYPE html>
{{-- Front page after Figma's SiteModern. Its own document, because layouts.public has a generic header that is not in Figma. --}}
<html lang="sv" @if ($themeName) data-theme="{{ $themeName }}" @endif @if ($themeStyle) style="{{ $themeStyle }}" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Only used as an admin preview for now, so it must never show up in search results. --}}
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $superblog->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-paper font-body leading-[1.4] text-ink antialiased">
    {{-- Hidden by decision: section menu, "Logga in"/"Prenumerera", tagline, ads and newsletter. --}}
    <header>
        {{-- Figma hides the date below 760 px, and the login links are not shown, so the whole bar goes away there. --}}
        <div class="flex justify-between border-b border-ink/14 py-2.5 text-[11px] tracking-[.1em] uppercase max-[760px]:hidden {{ $sidePadding }}">
            <time datetime="{{ now()->toDateString() }}">{{ now()->locale('sv')->isoFormat('dddd D MMMM YYYY') }}</time>
        </div>
        {{-- The magazine name is the page's single H1 until articles exist. The dot is decoration, so screen readers skip it. --}}
        <h1 class="pt-[26px] pb-[22px] text-center font-display text-[64px] leading-none font-semibold tracking-[-.045em] max-[760px]:pt-[18px] max-[760px]:pb-[14px] max-[760px]:text-[42px]">{{ $superblog->name }}<span aria-hidden="true" class="text-primary italic">.</span></h1>
    </header>

    {{-- Lead story, cards and "Mest läst" placeholders are added in step 4. --}}
    <main class="flex-1"></main>

    <footer class="grid gap-2 border-t border-ink/15 pt-7 pb-[34px] text-[13px] {{ $sidePadding }}">
        <p class="pb-2.5 font-display text-[34px] leading-none font-semibold tracking-[-.045em]">{{ $superblog->name }}<span aria-hidden="true" class="text-primary italic">.</span></p>
        <small class="text-ink/60">&copy; {{ now()->year }} {{ $superblog->name }}. Alla rättigheter förbehållna.</small>
    </footer>
</body>
</html>
