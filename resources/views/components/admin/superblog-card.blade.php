@props(['superblog'])

@php
    $isLive = $superblog->status === 'published';
    $canEdit = auth()->user()->can('update', $superblog);
    // settings is already loaded with the row; Theme::name() returns null for a missing or unknown theme.
    $themeName = \App\Support\Theme::name($superblog->settings['theme'] ?? null);
    // The same classes on both tags, so a card without edit access looks identical but is not clickable.
    $cardClasses = 'block overflow-hidden rounded-xl border border-studio-line bg-studio-panel';
@endphp

<div class="flex h-full flex-col">
    @if ($canEdit)
        {{-- Figma's .mag-card:hover: lifts 2px with a dark shadow. --}}
        <a href="{{ route('admin.superblogs.edit', $superblog) }}" class="{{ $cardClasses }} flex-1 transition hover:-translate-y-0.5 hover:shadow-[0_10px_28px_#0006] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">
    @else
        <div class="{{ $cardClasses }} flex-1">
    @endif
        {{-- Decorative thumbnail: the name is repeated in the info below, which is what screen readers get. --}}
        @if ($themeName)
            {{-- data-theme scopes the theme's CSS variables to the thumbnail; no color values are written into a style attribute. --}}
            <div data-theme="{{ $themeName }}" aria-hidden="true">
                <div class="h-2 bg-primary"></div>
                <div class="flex h-32 flex-col justify-between bg-paper p-4 text-ink">
                    <p class="truncate font-display text-2xl font-semibold">{{ $superblog->name }}</p>
                    <div class="grid h-8 grid-cols-[2fr_1fr_1fr] gap-2">
                        <span class="rounded-md bg-primary/90"></span>
                        <span class="rounded-md bg-deep/70"></span>
                        <span class="rounded-md bg-pale"></span>
                    </div>
                </div>
            </div>
        @else
            {{-- Neutral thumbnail until a valid theme is chosen; same shape so cards stay the same height. --}}
            <div aria-hidden="true">
                <div class="h-2 bg-studio-line"></div>
                <div class="flex h-32 flex-col justify-between bg-studio-bg p-4 text-studio-muted">
                    <p class="truncate text-2xl font-semibold">{{ $superblog->name }}</p>
                    <div class="grid h-8 grid-cols-[2fr_1fr_1fr] gap-2">
                        <span class="rounded-md bg-studio-pill"></span>
                        <span class="rounded-md bg-studio-line"></span>
                        <span class="rounded-md bg-studio-line"></span>
                    </div>
                </div>
            </div>
        @endif

        <div class="p-5">
            <div class="flex items-start justify-between gap-3">
                <h3 class="font-semibold">{{ $superblog->name }}</h3>
                @if ($isLive)
                    <span class="shrink-0 rounded-full bg-studio-live px-2.5 py-0.5 text-xs font-medium text-studio-ok"><span aria-hidden="true">●</span> Live</span>
                @else
                    <span class="shrink-0 rounded-full bg-studio-pill px-2.5 py-0.5 text-xs font-medium text-studio-muted">Utkast</span>
                @endif
            </div>
            <p class="mt-1 break-all text-sm text-studio-muted">{{ $superblog->domain ?: $superblog->slug }}</p>

            <dl class="mt-4 flex gap-6 text-sm">
                <div>
                    <dt class="text-studio-muted">Flöden</dt>
                    <dd class="font-medium">{{ $superblog->rss_sources_count }}</dd>
                </div>
                <div>
                    <dt class="text-studio-muted">Publicerade</dt>
                    <dd class="font-medium">{{ $superblog->published_articles_count }}</dd>
                </div>
            </dl>
        </div>
    @if ($canEdit)
        </a>
    @else
        </div>
    @endif

    <div class="mt-2 flex gap-4 text-sm">
        @can('update', $superblog)
            <a href="{{ route('admin.superblogs.rss-sources.index', $superblog) }}" class="underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand" aria-label="RSS-flöden för {{ $superblog->name }}">RSS-flöden</a>
        @endcan
        @can('delete', $superblog)
            <a href="{{ route('admin.superblogs.delete', $superblog) }}" class="text-studio-bad underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand" aria-label="Ta bort {{ $superblog->name }}">Ta bort</a>
        @endcan
    </div>
</div>
