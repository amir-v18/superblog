@extends($layout ?? 'layouts.public')

@section('title', $article['title'])
{{-- A null value here would open a long section that is never closed, so it must always be a string. --}}
@section('meta_description', $article['description'] ?? '')
@section('og_type', 'article')

@section('content')
    @php
        $isLocked = (bool) $article['is_locked'];
        $image = $article['image'] ?? null;
        $hasImage = is_array($image) && ! empty($image['src']);
        $author = $article['author'] ?? null;

        // Locked articles only get the first paragraph. The rest is never sent to the browser,
        // so it cannot be read from the page source.
        $blocks = $isLocked
            ? collect($article['body'])->where('type', 'p')->take(1)->all()
            : $article['body'];

        // RSS articles always go through AI transformation (RSS → AI → Article); there is no "ai" source type.
        $isAiProcessed = $article['is_ai_processed'] ?? $article['source_type'] === 'rss';

        // No hasPart/cssSelector for locked articles: the locked text is never in the HTML,
        // so there is no element for Google to point at.
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => $article['title'],
            'datePublished' => $article['published_at']->toIso8601String(),
            'isAccessibleForFree' => ! $isLocked,
        ];

        if (! empty($article['description'])) {
            $jsonLd['description'] = $article['description'];
        }

        if ($hasImage && isset($image['width'], $image['height'])) {
            $jsonLd['image'] = [[
                '@type' => 'ImageObject',
                'url' => $image['src'],
                'width' => $image['width'],
                'height' => $image['height'],
            ]];
        }

        if ($author) {
            $jsonLd['author'] = [['@type' => 'Person', 'name' => $author]];
        }
    @endphp

    @push('head')
        @if ($hasImage)
            <meta property="og:image" content="{{ $image['src'] }}">
            @if (isset($image['width'], $image['height']))
                <meta property="og:image:width" content="{{ $image['width'] }}">
                <meta property="og:image:height" content="{{ $image['height'] }}">
            @endif
            @if (! empty($image['alt']))
                <meta property="og:image:alt" content="{{ $image['alt'] }}">
            @endif
        @endif
        <meta property="article:published_time" content="{{ $article['published_at']->toIso8601String() }}">
        <meta name="twitter:card" content="{{ $hasImage ? 'summary_large_image' : 'summary' }}">
        {{-- Raw output is required for valid JSON. JSON_HEX_TAG encodes < and > as </>, so no value can close the script tag. --}}
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush

    <div class="rounded-lg bg-paper p-6 font-body text-ink sm:p-10">
        <article>
            <header>
                <h1 class="font-display text-4xl font-semibold tracking-tight">{{ $article['title'] }}</h1>

                <div class="mt-6 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-muted">
                    @if ($author)
                        <span>Av <span class="font-medium text-ink">{{ $author }}</span></span>
                    @endif
                    <time datetime="{{ $article['published_at']->toIso8601String() }}">
                        {{ $article['published_at']->locale('sv')->isoFormat('D MMMM YYYY') }}
                    </time>
                    @if ($isAiProcessed)
                        <x-ai-label />
                    @endif
                </div>
            </header>

            @if ($hasImage)
                <figure class="mt-8">
                    <img src="{{ $image['src'] }}"
                         alt="{{ $image['alt'] ?? '' }}"
                         @if (isset($image['width'])) width="{{ $image['width'] }}" @endif
                         @if (isset($image['height'])) height="{{ $image['height'] }}" @endif
                         class="h-auto w-full rounded-md">
                    @if (! empty($image['caption']))
                        <figcaption class="mt-2 text-sm text-muted">{{ $image['caption'] }}</figcaption>
                    @endif
                </figure>
            @endif

            <div class="relative mt-8">
                @foreach ($blocks as $block)
                    @switch($block['type'])
                        @case('h2')
                            <h2 class="mt-10 font-display text-2xl font-semibold">{{ $block['text'] }}</h2>
                            @break
                        @case('h3')
                            <h3 class="mt-8 font-display text-xl font-semibold">{{ $block['text'] }}</h3>
                            @break
                        @case('p')
                            <p class="mt-4 leading-7 first:mt-0">{{ $block['text'] }}</p>
                            @break
                    @endswitch
                @endforeach

                @if ($isLocked)
                    <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 bottom-0 h-16 bg-linear-to-b from-transparent to-paper"></div>
                @endif
            </div>

            @if ($isLocked)
                <div class="paywall mt-6 rounded-md border border-line bg-pale p-6 text-center">
                    <p class="font-display text-xl font-semibold">Logga in för att läsa resten</p>
                    <a href="{{ route('login') }}" class="mt-4 inline-block rounded-md bg-primary px-5 py-2 text-sm font-medium text-white hover:bg-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                        Logga in
                    </a>
                </div>
            @endif

            {{-- Brand and product links sit outside the body so they are present on locked articles too. --}}
            @if (! empty($article['links']['brand']) || ! empty($article['links']['product']))
                <p class="mt-10 border-t border-line pt-6">
                    Läs mer:
                    @if (! empty($article['links']['brand']))
                        <x-product-link :href="$article['links']['brand']['url']">{{ $article['links']['brand']['anchor_text'] }}</x-product-link>
                    @endif
                    @if (! empty($article['links']['brand']) && ! empty($article['links']['product']))
                        ·
                    @endif
                    @if (! empty($article['links']['product']))
                        <x-product-link :href="$article['links']['product']['url']">{{ $article['links']['product']['anchor_text'] }}</x-product-link>
                    @endif
                </p>
            @endif

            <x-source-box :sources="$article['sources']" class="mt-8" />
        </article>
    </div>
@endsection
