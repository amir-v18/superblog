@use('App\Support\Theme')
@php
    // $theme: a theme name or an array of overrides with a base 'name', as in layouts.public.
    $themeName = Theme::name($theme);
    $themeStyle = Theme::cssVariables($theme);
    // Stand-ins for articles that do not exist yet: striped picture areas and grey text lines. No titles or texts are made up.
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
                    @foreach (range(1, 2) as $story)
                        <article class="mb-3.5 border-b border-line pb-3.5 last:border-b-0">
                            <div class="my-[7px] w-[92%] {{ $textLine }}"></div>
                            <div class="my-[7px] w-[70%] {{ $textLine }}"></div>
                            <div class="my-[7px] w-[48%] {{ $textLine }}"></div>
                            <p class="opacity-45 {{ $byline }}">Av redaktionen</p>
                        </article>
                    @endforeach
                </section>

                <section aria-label="Huvudartikel" class="min-w-0 border-x border-line px-5 @max-[1000px]:order-first @max-[1000px]:col-span-full @max-[1000px]:mb-3.5 @max-[1000px]:border-x-0 @max-[1000px]:border-b @max-[1000px]:px-0 @max-[1000px]:pb-3.5 @max-[640px]:border-b-0 @max-[640px]:pb-0">
                    <div class="my-2.5 {{ $headLine }}"></div>
                    <div class="mx-auto my-2 w-[70%] {{ $headLine }}"></div>
                    <p class="mt-2 mb-3.5 border-y border-line py-[5px] text-center {{ $byline }}">Här visas din första artikel</p>
                    <div class="classic-plate mb-3">
                        <div class="aspect-[16/9] {{ $stripes }}"><span class="{{ $stripeLabel }}">Huvudbild</span></div>
                    </div>
                    <div class="my-[7px] w-[92%] {{ $textLine }}"></div>
                    <div class="my-[7px] w-[70%] {{ $textLine }}"></div>
                    <div class="my-[7px] w-[48%] {{ $textLine }}"></div>
                    <div class="my-[7px] w-[26%] {{ $textLine }}"></div>
                </section>

                <section aria-labelledby="telegram-heading" class="min-w-0 pl-5 @max-[640px]:mb-3.5 @max-[640px]:pl-0">
                    <x-classic.ornament id="telegram-heading">Senaste telegram</x-classic.ornament>
                    {{-- A placeholder time, not a real one, so it is plain text rather than a time element. --}}
                    <ol class="mb-3.5">
                        @foreach (range(1, 5) as $telegram)
                            <li class="grid grid-cols-[46px_1fr] gap-2 border-b border-dotted border-line py-[7px]">
                                <span class="pt-0.5 font-caps text-xs tracking-[.06em] text-primary opacity-40">00.00</span>
                                <div class="my-1 w-[92%] {{ $textLine }}"></div>
                            </li>
                        @endforeach
                    </ol>
                </section>
            </div>

            <section aria-labelledby="more-heading" class="mt-[18px] border-t-4 border-double border-line pt-3">
                <x-classic.ornament id="more-heading">Ur dagens tidning</x-classic.ornament>
                {{-- Four in a row, two by two on narrower sheets (the third one then starts a new row without a rule
                     on its left), and one under the other on phones with a rule below each. --}}
                <div class="grid grid-cols-4 @max-[1000px]:grid-cols-2 @max-[1000px]:gap-y-4 @max-[640px]:grid-cols-1">
                    @foreach (range(1, 4) as $story)
                        <article class="min-w-0 border-l border-line px-4 first:border-l-0 first:pl-0 last:pr-0 @max-[1000px]:nth-3:border-l-0 @max-[1000px]:nth-3:pl-0 @max-[640px]:border-b @max-[640px]:border-l-0 @max-[640px]:px-0 @max-[640px]:pb-3">
                            <div class="classic-plate mb-3">
                                <div class="aspect-[16/9] {{ $stripes }}"><span class="{{ $stripeLabel }}">Bild</span></div>
                            </div>
                            <div class="my-[7px] w-[92%] {{ $textLine }}"></div>
                            <div class="my-[7px] w-[70%] {{ $textLine }}"></div>
                        </article>
                    @endforeach
                </div>
            </section>
        </main>

        <footer class="mt-[22px] border-t-4 border-double border-line pt-2.5">
            <x-classic.ornament decorative>❖ ❖ ❖</x-classic.ornament>
            <p class="mt-3.5 text-center font-caps text-xs tracking-[.16em] text-muted uppercase">&copy; {{ now()->year }} <span data-live-name>{{ $superblog->name }}</span></p>
        </footer>
    </div>
</body>
</html>
