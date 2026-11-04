@props(['superblog'])

@php
    $isLive = $superblog->status === 'published';
    $canEdit = auth()->user()->can('update', $superblog);
    // settings is already loaded with the row; Theme::nameFor() returns null for a missing or unknown theme.
    $themeName = \App\Support\Theme::nameFor($superblog);
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
        <a href="{{ route('admin.superblogs.edit', $superblog) }}" class="{{ $cardClasses }} flex-1 transition hover:-translate-y-0.5 hover:shadow-[0_10px_28px_#0006] studio-focus-ring">
    @else
        <div class="{{ $cardClasses }} flex-1">
    @endif
        {{-- Decorative thumbnail in the saved theme, or the grey one until a valid theme is chosen. --}}
        <x-admin.theme-thumb :theme="$themeName" :name="$superblog->name" />

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
            <a href="{{ route('admin.superblogs.rss-sources.index', $superblog) }}" class="underline underline-offset-4 studio-focus-ring" aria-label="RSS-flöden för {{ $superblog->name }}">RSS-flöden</a>
        @endif
        @can('delete', $superblog)
            <a href="{{ route('admin.superblogs.delete', $superblog) }}" class="text-studio-bad underline underline-offset-4 studio-focus-ring" aria-label="Ta bort {{ $superblog->name }}">Ta bort</a>
        @endcan
    </div>
</div>
