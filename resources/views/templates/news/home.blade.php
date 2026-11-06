@use('App\Support\Theme')
@php
    // $theme: a theme name or an array of overrides with a base 'name', as in layouts.public.
    $themeName = Theme::name($theme);
    $themeStyle = Theme::cssVariables($theme);
    $sidePadding = 'px-10 max-[760px]:px-[18px]';
    // Figma's placeholder image (ph-img), used by the hero and passed to x-placeholder-card. It stands in for articles that do not exist yet; no titles or texts are made up.
    $stripes = 'grid place-items-center bg-ink/10 [background-image:repeating-linear-gradient(45deg,transparent_0_9px,color-mix(in_srgb,var(--ink)_6%,transparent)_9px_18px)]';
    $stripeLabel = 'text-[11px] font-semibold tracking-[.12em] text-ink/50 uppercase';
    $eyebrow = 'text-[10px] font-bold tracking-[.16em] text-primary uppercase';
    $featuredArticle = $articles->first();
    $middleArticles = $articles->slice(1, 2)->values();
    $latestArticles = $articles->slice(3, 6)->values();
    $remainingArticles = $articles->slice(9)->values();
    $latestPlaceholderCount = max(0, 6 - $latestArticles->count());
@endphp
<!DOCTYPE html>
{{-- Front page after Figma's SiteModern. Its own document, because layouts.public has a generic header that is not in Figma. --}}
<html lang="sv" @if ($themeName) data-theme="{{ $themeName }}" @endif @if ($themeStyle) style="{{ $themeStyle }}" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if ($isPreview)
    <meta name="robots" content="noindex, nofollow">
    @endif
    <title>{{ $superblog->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body @if ($isPreview) data-admin-preview-superblog-id="{{ $superblog->getKey() }}" @endif class="flex min-h-screen flex-col bg-paper font-body leading-[1.4] text-ink antialiased">
    {{-- Hidden by decision: section menu, "Logga in"/"Prenumerera", tagline, ads and newsletter. --}}
    <header>
        {{-- Figma hides the date below 760 px, and the login links are not shown, so the whole bar goes away there. --}}
        <div class="flex justify-between border-b border-ink/14 py-2.5 text-[11px] tracking-[.1em] uppercase max-[760px]:hidden {{ $sidePadding }}">
            <time datetime="{{ now()->toDateString() }}">{{ now()->locale('sv')->isoFormat('dddd D MMMM YYYY') }}</time>
        </div>
        {{-- The magazine name is the page's single H1 until articles exist. The dot is decoration, so screen readers skip it. --}}
        <h1 class="pt-[26px] pb-[22px] text-center font-display text-[64px] leading-none font-semibold tracking-[-.045em] max-[760px]:pt-[18px] max-[760px]:pb-[14px] max-[760px]:text-[42px]"><span data-live-name>{{ $superblog->name }}</span><span aria-hidden="true" class="text-primary italic">.</span></h1>
    </header>

    <main class="flex-1 pb-6">
        <section aria-label="Förstasidan" class="pt-[26px] pb-2.5 {{ $sidePadding }}">
            <div class="grid grid-cols-[2fr_1fr_.9fr] gap-6 max-[760px]:grid-cols-1">
                <article class="relative min-h-[470px] overflow-hidden rounded-md border-[1.5px] border-dashed border-ink/30 max-[760px]:min-h-[340px]">
                    <div class="absolute inset-0 {{ $stripes }}"><span class="{{ $stripeLabel }}">Huvudbild</span></div>
                    @if ($featuredArticle)
                        <div class="absolute right-5 bottom-5 left-5 flex flex-col gap-2 rounded-md bg-paper/92 p-4">
                            <p class="{{ $eyebrow }}">Senaste artikeln</p>
                            <h2 class="line-clamp-3 font-display text-3xl leading-tight font-semibold tracking-tight">
                                <a href="{{ route('public.superblogs.articles.show', [$superblog, $featuredArticle]) }}" target="_blank" rel="noopener" class="hover:underline">{{ $featuredArticle->title }}</a>
                            </h2>
                            @if ($featuredArticle->excerpt)
                                <p class="line-clamp-2 text-sm text-ink/70">{{ $featuredArticle->excerpt }}</p>
                            @endif
                            @if ($featuredArticle->published_at)
                                <time class="text-xs text-ink/60" datetime="{{ $featuredArticle->published_at->toIso8601String() }}">{{ $featuredArticle->published_at->locale('sv')->isoFormat('D MMMM YYYY') }}</time>
                            @endif
                        </div>
                    @else
                        <div class="absolute right-5 bottom-5 left-5 flex flex-col gap-2 rounded-md bg-paper/92 p-4">
                            <div class="my-2.5 h-[26px] rounded-[3px] bg-ink/14"></div>
                            <div class="my-2.5 h-[26px] w-[60%] rounded-[3px] bg-ink/14"></div>
                            <span class="text-[11px] opacity-72">Här visas din första artikel</span>
                        </div>
                    @endif
                </article>

                <div class="flex flex-col gap-5">
                    @foreach (range(0, 1) as $slot)
                        <x-placeholder-card :stripes="$stripes" :stripe-label="$stripeLabel" :article="$middleArticles->get($slot)" :superblog="$superblog" />
                    @endforeach
                </div>

                <aside aria-labelledby="latest-sidebar-heading" class="border-l border-ink/15 pl-5 max-[760px]:border-l-0 max-[760px]:pl-0">
                    <h2 id="latest-sidebar-heading" class="mb-1 border-b-3 border-primary pb-2 {{ $eyebrow }}">Mest lästa artiklar just nu</h2>
                    @forelse ($manualArticles as $article)
                        <a href="{{ route('public.superblogs.articles.show', [$superblog, $article]) }}" target="_blank" rel="noopener" class="block border-b border-ink/12 py-3 font-medium hover:underline">{{ $article->title }}</a>
                    @empty
                        @if ($articles->isEmpty())
                            @foreach (range(1, 5) as $rank)
                                <div class="grid grid-cols-[22px_1fr] items-center gap-2 border-b border-ink/12 py-[11px]">
                                    <span class="font-display text-[22px] leading-none font-medium text-primary opacity-40">{{ $rank }}</span>
                                    <div>
                                        <div class="my-[3px] h-[11px] w-[92%] rounded-[3px] bg-ink/14"></div>
                                        <div class="my-[3px] h-[11px] w-[70%] rounded-[3px] bg-ink/14"></div>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <p class="py-3 text-sm text-ink/60">Manuella artiklar visas här när de publiceras.</p>
                        @endif
                    @endforelse
                </aside>
            </div>
        </section>

        <section aria-labelledby="latest-heading" class="pt-[26px] pb-2.5 {{ $sidePadding }}">
            <p class="{{ $eyebrow }}">Det senaste</p>
            <h2 id="latest-heading" class="mt-1.5 mb-[18px] font-display text-[30px] leading-[1.1] font-semibold tracking-[-.02em]">Nytt från redaktionen</h2>
            <div class="grid grid-cols-3 gap-x-6 gap-y-[26px] max-[760px]:grid-cols-1">
                @foreach ($latestArticles as $article)
                    <x-placeholder-card :stripes="$stripes" :stripe-label="$stripeLabel" :article="$article" :superblog="$superblog" />
                @endforeach
                @for ($slot = 0; $slot < $latestPlaceholderCount; $slot++)
                    <x-placeholder-card :stripes="$stripes" :stripe-label="$stripeLabel" />
                @endfor
            </div>
            @if ($remainingArticles->isNotEmpty())
                <div class="mt-8 grid grid-cols-3 gap-x-6 gap-y-[26px] max-[760px]:grid-cols-1" aria-label="Fler artiklar">
                    @foreach ($remainingArticles as $article)
                        <x-placeholder-card :stripes="$stripes" :stripe-label="$stripeLabel" :article="$article" :superblog="$superblog" />
                    @endforeach
                </div>
            @endif
        </section>
    </main>

    <footer class="grid gap-2 border-t border-ink/15 pt-7 pb-[34px] text-[13px] {{ $sidePadding }}">
        <p class="pb-2.5 font-display text-[34px] leading-none font-semibold tracking-[-.045em]"><span data-live-name>{{ $superblog->name }}</span><span aria-hidden="true" class="text-primary italic">.</span></p>
        <small class="text-ink/60">&copy; {{ now()->year }} <span data-live-name>{{ $superblog->name }}</span>. Alla rättigheter förbehållna.</small>
    </footer>
</body>
</html>
