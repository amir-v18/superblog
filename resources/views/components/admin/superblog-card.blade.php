@props(['superblog'])

@php
    $isLive = $superblog->status === 'published';
    $canEdit = auth()->user()->can('update', $superblog);
    // settings is already loaded with the row; Theme::name() returns null for a missing or unknown theme.
    $themeName = \App\Support\Theme::name($superblog->settings['theme'] ?? null);
    // withMax returns a raw string, not a cast date. Swedish only here, since the app locale is English.
    $lastFetchedAt = $superblog->rss_sources_max_last_fetched_at;
    $lastFetched = $lastFetchedAt
        ? ucfirst(\Illuminate\Support\Carbon::parse($lastFetchedAt)->locale('sv')->diffForHumans())
        : 'Aldrig';
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

        {{-- Figma's .mag-info: padding 14/16/16, 6px between rows. --}}
        <div class="flex flex-col gap-1.5 px-4 pt-3.5 pb-4">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-[17px] font-semibold">{{ $superblog->name }}</h3>
                <x-admin.status-pill :live="$isLive" class="shrink-0" />
            </div>
            {{-- Figma: live cards show where they are published, drafts show which template they are built on. --}}
            <p class="break-all text-xs text-studio-muted">{{ $isLive ? ($superblog->domain ?: $superblog->slug) : $superblog->template?->name }}</p>

            <div class="mt-1.5 flex flex-wrap gap-x-3.5 gap-y-1 text-xs text-studio-muted">
                <span><b class="text-studio-ink">{{ $superblog->rss_sources_count }}</b> flöden</span>
                <span><b class="text-studio-ink">{{ $superblog->published_articles_count }}</b> publicerade</span>
                <span>Senast hämtat: {{ $lastFetched }}</span>
            </div>
        </div>
    @if ($canEdit)
        </a>
    @else
        </div>
    @endif

    <div class="mt-2 flex gap-4 text-sm">
        {{-- Reuses $canEdit: the same update check, without running the policy query again. --}}
        @if ($canEdit)
            <a href="{{ route('admin.superblogs.rss-sources.index', $superblog) }}" class="underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand" aria-label="RSS-flöden för {{ $superblog->name }}">RSS-flöden</a>
        @endif
        @can('delete', $superblog)
            <a href="{{ route('admin.superblogs.delete', $superblog) }}" class="text-studio-bad underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand" aria-label="Ta bort {{ $superblog->name }}">Ta bort</a>
        @endcan
    </div>
</div>
