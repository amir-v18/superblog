@use('App\Support\Theme')
@php
    // $theme: a theme name or an array of overrides with a base 'name', as in layouts.public.
    $themeName = Theme::name($theme);
    $themeStyle = Theme::cssVariables($theme);
    $sidePadding = 'px-10 max-[760px]:px-[18px]';
    // Figma's placeholders (ph-img, ph-line). They stand in for articles that do not exist yet; no titles or texts are made up.
    $stripes = 'grid place-items-center bg-ink/10 [background-image:repeating-linear-gradient(45deg,transparent_0_9px,color-mix(in_srgb,var(--ink)_6%,transparent)_9px_18px)]';
    $stripeLabel = 'text-[11px] font-semibold tracking-[.12em] text-ink/50 uppercase';
    $line = 'my-[7px] h-[11px] rounded-[3px] bg-ink/14';
    $eyebrow = 'text-[10px] font-bold tracking-[.16em] text-primary uppercase';
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

    {{-- Figma's section labels above each card are left out, since there are no sections yet. --}}
    <main class="flex-1 pb-6">
        <section aria-label="Förstasidan" class="pt-[26px] pb-2.5 {{ $sidePadding }}">
            <div class="grid grid-cols-[2fr_1fr_.9fr] gap-6 max-[760px]:grid-cols-1">
                <article class="relative min-h-[470px] overflow-hidden rounded-md border-[1.5px] border-dashed border-ink/30 max-[760px]:min-h-[340px]">
                    <div class="absolute inset-0 {{ $stripes }}"><span class="{{ $stripeLabel }}">Huvudbild</span></div>
                    <div class="absolute right-5 bottom-5 left-5 flex flex-col gap-2 rounded-md bg-paper/92 p-4">
                        <div class="my-2.5 h-[26px] rounded-[3px] bg-ink/14"></div>
                        <div class="my-2.5 h-[26px] w-[60%] rounded-[3px] bg-ink/14"></div>
                        <span class="text-[11px] opacity-72">Här visas din första artikel</span>
                    </div>
                </article>

                <div class="flex flex-col gap-5">
                    @foreach (range(1, 2) as $card)
                        <article class="flex flex-col gap-[7px]">
                            <div class="aspect-[16/10] rounded border-[1.5px] border-dashed border-ink/30 {{ $stripes }}"><span class="{{ $stripeLabel }}">Bild</span></div>
                            <div class="{{ $line }} w-[92%]"></div>
                            <div class="{{ $line }} w-[70%]"></div>
                            <span class="text-[11px] opacity-45">Tid · Författare</span>
                        </article>
                    @endforeach
                </div>

                <aside aria-labelledby="most-read-heading" class="border-l border-ink/15 pl-5 max-[760px]:border-l-0 max-[760px]:pl-0">
                    <h2 id="most-read-heading" class="mb-1 border-b-3 border-primary pb-2 {{ $eyebrow }}">Mest läst</h2>
                    @foreach (range(1, 5) as $rank)
                        <div class="grid grid-cols-[22px_1fr] items-center gap-2 border-b border-ink/12 py-[11px]">
                            <span class="font-display text-[22px] leading-none font-medium text-primary opacity-40">{{ $rank }}</span>
                            <div>
                                <div class="my-[3px] h-[11px] w-[92%] rounded-[3px] bg-ink/14"></div>
                                <div class="my-[3px] h-[11px] w-[70%] rounded-[3px] bg-ink/14"></div>
                            </div>
                        </div>
                    @endforeach
                </aside>
            </div>
        </section>

        <section aria-labelledby="latest-heading" class="pt-[26px] pb-2.5 {{ $sidePadding }}">
            <p class="{{ $eyebrow }}">Det senaste</p>
            <h2 id="latest-heading" class="mt-1.5 mb-[18px] font-display text-[30px] leading-[1.1] font-semibold tracking-[-.02em]">Nytt från redaktionen</h2>
            <div class="grid grid-cols-3 gap-x-6 gap-y-[26px] max-[760px]:grid-cols-1">
                @foreach (range(1, 6) as $card)
                    <article class="flex flex-col gap-[7px]">
                        <div class="aspect-[16/10] rounded border-[1.5px] border-dashed border-ink/30 {{ $stripes }}"><span class="{{ $stripeLabel }}">Bild</span></div>
                        <div class="{{ $line }} w-[92%]"></div>
                        <div class="{{ $line }} w-[70%]"></div>
                        <span class="text-[11px] opacity-45">Tid · Författare</span>
                    </article>
                @endforeach
            </div>
        </section>
    </main>

    <footer class="grid gap-2 border-t border-ink/15 pt-7 pb-[34px] text-[13px] {{ $sidePadding }}">
        <p class="pb-2.5 font-display text-[34px] leading-none font-semibold tracking-[-.045em]">{{ $superblog->name }}<span aria-hidden="true" class="text-primary italic">.</span></p>
        <small class="text-ink/60">&copy; {{ now()->year }} {{ $superblog->name }}. Alla rättigheter förbehållna.</small>
    </footer>
</body>
</html>
