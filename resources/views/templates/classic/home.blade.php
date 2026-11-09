@use('App\Support\Theme')
@php
    // $theme: a theme name or an array of overrides with a base 'name', as in layouts.public.
    $themeName = Theme::name($theme);
    $themeStyle = Theme::cssVariables($theme);
    // Articles arrive newest first and fill the page in reading order: the lead story, two news items on the left,
    // five telegrams on the right and the row of four. A slot without an article keeps its stand-in.
    $lead = $articles->first();
    $news = $articles->slice(1, 2)->values();
    $telegrams = $articles->slice(3, 5)->values();
    // From the ninth article on, rows of four. The first row always has four slots; later rows only hold the
    // articles that do not fit, without stand-ins, so no selected article is ever left off the page.
    $rows = $articles->slice(8)->values()->chunk(4)->map->values()->whenEmpty(fn ($rows) => $rows->push(collect()));
    // Stand-ins for empty slots: striped picture areas and grey text lines, so no titles or texts are ever made up.
    // There are no article pictures yet either, so the plates stay striped next to real headlines.
    $stripes = 'grid place-items-center border-[1.5px] border-dashed border-ink/30 bg-ink/10 [background-image:repeating-linear-gradient(45deg,transparent_0_9px,color-mix(in_srgb,var(--ink)_6%,transparent)_9px_18px)]';
    $stripeLabel = 'text-[11px] font-semibold tracking-[.12em] text-ink/50 uppercase';
    $textLine = 'h-[11px] rounded-[3px] bg-ink/14';
    $headLine = 'h-[26px] rounded-[3px] bg-ink/14';
    $byline = 'font-caps text-[11px] tracking-[.12em] text-muted uppercase';
@endphp
<!DOCTYPE html>
{{-- Front page of a newspaper-style magazine: a centred sheet of paper on a desk-coloured background. --}}
<html lang="sv" @if ($themeName) data-theme="{{ $themeName }}" @endif @if ($themeStyle) style="{{ $themeStyle }}" @endif>
<head>
    @include('templates.partials.head')
</head>
<body @if ($isPreview ?? false) data-admin-preview-superblog-id="{{ $superblog->getKey() }}" @endif class="min-h-screen bg-desk px-5 pt-[22px] pb-[60px] font-body text-ink antialiased max-[640px]:p-0">
    {{-- Left out for now, since there is no data for them: weather and price boxes, volume number and founding
         year, tagline, section menu, the strip of short news, ads, small ads and publisher details. --}}
    {{-- Two kinds of breakpoints. The sheet cannot measure itself, so its own spacing and the desk around it follow
         the screen width; on phones the sheet is as wide as the screen, so both agree there. Everything inside
         follows the width of the sheet, at 1000 and 640 px. --}}
    <div class="classic-paper @container mx-auto max-w-[1280px] border px-[clamp(16px,3vw,38px)] pt-[26px] pb-[34px] max-[640px]:px-3.5 max-[640px]:pt-4 max-[640px]:pb-[26px]">
        <header>
            {{-- The name is the page's single H1 until articles exist, as on the Modern front page. --}}
            <h1 class="px-2 pt-1 pb-2 text-center font-logo text-[clamp(46px,8.2cqw,112px)] leading-none font-normal tracking-[-.01em] whitespace-nowrap @max-[640px]:text-[clamp(44px,15cqw,64px)]"><span data-live-name>{{ $superblog->name }}</span></h1>
            <p class="border-t-4 border-b border-double border-line px-1 py-1.5 text-center font-caps text-xs tracking-[.14em] uppercase @max-[640px]:text-[10px]">
                <time datetime="{{ now()->toDateString() }}">{{ now()->locale('sv')->isoFormat('dddd[en den] D MMMM YYYY') }}</time>
            </p>
        </header>

        <main>
            {{-- Narrower sheets put the lead story first: across the full width with the two side columns below it,
                 and on phones one column under the other. --}}
            <div class="grid grid-cols-[1fr_2.3fr_1fr] pt-[18px] pb-2 @max-[1000px]:grid-cols-2 @max-[640px]:flex @max-[640px]:flex-col">
                <section aria-labelledby="news-heading" class="min-w-0 pr-5 @max-[1000px]:border-r @max-[1000px]:border-line @max-[640px]:mb-3.5 @max-[640px]:border-r-0 @max-[640px]:pr-0">
                    <x-classic.ornament id="news-heading">Dagens nyheter</x-classic.ornament>
                    @foreach (range(0, 1) as $slot)
                        @php($story = $news->get($slot))
                        <article class="mb-3.5 border-b border-line pb-3.5 last:border-b-0">
                            @if ($story)
                                <h3 class="mb-1.5 font-display text-[21px] leading-[1.12] font-bold text-balance">
                                    <a href="{{ route('public.superblogs.articles.show', [$superblog, $story]) }}" target="_blank" rel="noopener" class="hover:text-primary">{{ $story->title }}</a>
                                </h3>
                                @if ($story->excerpt)
                                    <p class="mb-1.5 text-justify text-sm leading-[1.45] hyphens-auto">{{ $story->excerpt }}</p>
                                @endif
                                <x-classic.date :article="$story" :class="$byline" />
                            @else
                                <div class="my-[7px] w-[92%] {{ $textLine }}"></div>
                                <div class="my-[7px] w-[70%] {{ $textLine }}"></div>
                                <div class="my-[7px] w-[48%] {{ $textLine }}"></div>
                                <p class="opacity-45 {{ $byline }}">Av redaktionen</p>
                            @endif
                        </article>
                    @endforeach
                </section>

                {{-- The magazine name stays the page's single H1, as on the Modern front page, so the lead story is an H2. --}}
                <section aria-label="Huvudartikel" class="min-w-0 border-x border-line px-5 @max-[1000px]:order-first @max-[1000px]:col-span-full @max-[1000px]:mb-3.5 @max-[1000px]:border-x-0 @max-[1000px]:border-b @max-[1000px]:px-0 @max-[1000px]:pb-3.5 @max-[640px]:border-b-0 @max-[640px]:pb-0">
                    @if ($lead)
                        <h2 class="mt-1 text-center font-display text-[clamp(34px,4.4cqw,62px)] leading-[1.02] font-black tracking-[-.01em] text-balance @max-[640px]:text-[34px]">
                            <a href="{{ route('public.superblogs.articles.show', [$superblog, $lead]) }}" target="_blank" rel="noopener" class="hover:text-primary">{{ $lead->title }}</a>
                        </h2>
                        @if ($lead->excerpt)
                            <p class="mx-auto mt-2.5 mb-2 max-w-[36em] text-center font-display text-[clamp(16px,1.5cqw,21px)] leading-[1.35] text-muted italic">{{ $lead->excerpt }}</p>
                        @endif
                        <p class="mt-2 mb-3.5 border-y border-line py-[5px] text-center {{ $byline }}"><x-classic.date :article="$lead" /></p>
                    @else
                        <div class="my-2.5 {{ $headLine }}"></div>
                        <div class="mx-auto my-2 w-[70%] {{ $headLine }}"></div>
                        <p class="mt-2 mb-3.5 border-y border-line py-[5px] text-center {{ $byline }}">Här visas din första artikel</p>
                    @endif
                    <div class="classic-plate mb-3">
                        <div class="aspect-[16/9] {{ $stripes }}"><span class="{{ $stripeLabel }}">Huvudbild</span></div>
                    </div>
                    {{-- Stand-in body text only while there is no lead story. A real one shows no body on the front page. --}}
                    @unless ($lead)
                        <div class="my-[7px] w-[92%] {{ $textLine }}"></div>
                        <div class="my-[7px] w-[70%] {{ $textLine }}"></div>
                        <div class="my-[7px] w-[48%] {{ $textLine }}"></div>
                        <div class="my-[7px] w-[26%] {{ $textLine }}"></div>
                    @endunless
                </section>

                <section aria-labelledby="telegram-heading" class="min-w-0 pl-5 @max-[640px]:mb-3.5 @max-[640px]:pl-0">
                    <x-classic.ornament id="telegram-heading">Senaste telegram</x-classic.ornament>
                    {{-- The time of day only, as on a telegram. A stand-in time is plain text rather than a time element. --}}
                    <ol class="mb-3.5">
                        @foreach (range(0, 4) as $slot)
                            @php($telegram = $telegrams->get($slot))
                            <li class="grid grid-cols-[46px_1fr] gap-2 border-b border-dotted border-line py-[7px] text-sm leading-[1.3]">
                                @if ($telegram)
                                    <x-classic.date :article="$telegram" format="HH.mm" class="pt-0.5 font-caps text-xs tracking-[.06em] text-primary" />
                                    <a href="{{ route('public.superblogs.articles.show', [$superblog, $telegram]) }}" target="_blank" rel="noopener" class="min-w-0 hover:underline">{{ $telegram->title }}</a>
                                @else
                                    <span class="pt-0.5 font-caps text-xs tracking-[.06em] text-primary opacity-40">00.00</span>
                                    <div class="my-1 w-[92%] {{ $textLine }}"></div>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </section>
            </div>

            <section aria-labelledby="more-heading" class="mt-[18px] border-t-4 border-double border-line pt-3">
                <x-classic.ornament id="more-heading">Ur dagens tidning</x-classic.ornament>
                {{-- Four in a row, two by two on narrower sheets (the third one then starts a new row without a rule
                     on its left), and one under the other on phones with a rule below each. Every set of four is laid
                     out on its own, so the first and third in each set get those same rules. --}}
                @foreach ($rows as $index => $row)
                    <div class="grid grid-cols-4 @max-[1000px]:grid-cols-2 @max-[1000px]:gap-y-4 @max-[640px]:grid-cols-1 {{ $index > 0 ? 'mt-4' : '' }}">
                        @foreach ($index === 0 ? range(0, 3) : $row->keys() as $slot)
                            @php($story = $row->get($slot))
                            <article class="min-w-0 border-l border-line px-4 first:border-l-0 first:pl-0 last:pr-0 @max-[1000px]:nth-3:border-l-0 @max-[1000px]:nth-3:pl-0 @max-[640px]:border-b @max-[640px]:border-l-0 @max-[640px]:px-0 @max-[640px]:pb-3">
                                <div class="classic-plate mb-3">
                                    <div class="aspect-[16/9] {{ $stripes }}"><span class="{{ $stripeLabel }}">Bild</span></div>
                                </div>
                                @if ($story)
                                    <h3 class="mb-1.5 font-display text-[19px] leading-[1.12] font-bold text-balance">
                                        <a href="{{ route('public.superblogs.articles.show', [$superblog, $story]) }}" target="_blank" rel="noopener" class="hover:text-primary">{{ $story->title }}</a>
                                    </h3>
                                    <x-classic.date :article="$story" :class="$byline" />
                                @else
                                    <div class="my-[7px] w-[92%] {{ $textLine }}"></div>
                                    <div class="my-[7px] w-[70%] {{ $textLine }}"></div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endforeach
            </section>
        </main>

        <footer class="mt-[22px] border-t-4 border-double border-line pt-2.5">
            <x-classic.ornament decorative>❖ ❖ ❖</x-classic.ornament>
            <p class="mt-3.5 text-center font-caps text-xs tracking-[.16em] text-muted uppercase">&copy; {{ now()->year }} <span data-live-name>{{ $superblog->name }}</span></p>
        </footer>
    </div>
</body>
</html>
