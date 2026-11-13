@props(['superblog'])

@php
    $isLive = $superblog->status === 'published';
    $canOpen = auth()->user()->can('view', $superblog);
    $canManageSettings = auth()->user()->can('update', $superblog);
    $builderUrl = route('admin.superblogs.edit', $canManageSettings ? $superblog : [$superblog, 'flik' => 'innehall']);
    // settings is already loaded with the row; Theme::nameFor() returns null for a missing or unknown theme.
    $themeName = \App\Support\Theme::nameFor($superblog);
    // withMax returns a raw string, not a cast date. Swedish only here, since the app locale is English.
    $lastFetchedAt = $superblog->rss_sources_max_last_fetched_at;
    $lastFetched = $lastFetchedAt
        ? ucfirst(\Illuminate\Support\Carbon::parse($lastFetchedAt)->locale('sv')->diffForHumans())
        : 'Aldrig';
    // The same classes on both tags, so inaccessible cards look identical but are not clickable.
    $cardClasses = 'block overflow-hidden rounded-xl border border-studio-line bg-studio-panel studio-light:studio-raised';
@endphp

<div class="flex h-full flex-col">
    @if ($canOpen)
        <a href="{{ $builderUrl }}" class="{{ $cardClasses }} flex-1 transition hover:-translate-y-0.5 hover:shadow-[0_10px_28px_var(--color-studio-card-shadow)] studio-focus-ring studio-light:hover:shadow-[0_10px_28px_var(--color-studio-card-shadow)]">
    @else
        <div class="{{ $cardClasses }} flex-1">
    @endif
        {{-- Decorative thumbnail in the saved theme, or the grey one until a valid theme is chosen. --}}
        <x-admin.theme-thumb :theme="$themeName" :name="$superblog->name" />

        <div class="flex flex-col gap-1.5 px-4 pt-3.5 pb-4">
            {{-- A word too long for the card is broken rather than pushing the status out of it. --}}
            <div class="flex items-center justify-between gap-3">
                <h3 class="min-w-0 text-[17px] font-semibold wrap-anywhere">{{ $superblog->name }}</h3>
                <x-admin.status-pill :live="$isLive" class="shrink-0" />
            </div>
            {{-- Live cards show where they are published, drafts show which template they are built on. --}}
            <p class="break-all text-xs text-studio-muted">{{ $isLive ? ($superblog->domain ?: $superblog->slug) : $superblog->template?->name }}</p>

            <div class="mt-1.5 flex flex-wrap gap-x-3.5 gap-y-1 text-xs text-studio-muted">
                <span><b class="text-studio-ink">{{ $superblog->rss_sources_count }}</b> flöden</span>
                <span><b class="text-studio-ink">{{ $superblog->published_articles_count }}</b> publicerade</span>
                <span>Senast hämtat: {{ $lastFetched }}</span>
            </div>
        </div>
    @if ($canOpen)
        </a>
    @else
        </div>
    @endif

    <div class="mt-2 flex gap-4 text-sm">
        {{-- RSS source changes are admin-only; assigned editors get a read-only path to source items. --}}
        @if ($canManageSettings)
            <a href="{{ route('admin.superblogs.rss-sources.index', $superblog) }}" class="underline underline-offset-4 studio-focus-ring" aria-label="RSS-flöden för {{ $superblog->name }}">RSS-flöden</a>
        @elseif (auth()->user()->can('manageArticles', $superblog))
            <a href="{{ route('admin.superblogs.rss-sources.index', $superblog) }}" class="underline underline-offset-4 studio-focus-ring" aria-label="RSS-innehåll för {{ $superblog->name }}">RSS-innehåll</a>
        @endif
        @can('delete', $superblog)
            <a href="{{ route('admin.superblogs.delete', $superblog) }}" class="text-studio-bad underline underline-offset-4 studio-focus-ring" aria-label="Ta bort {{ $superblog->name }}">Ta bort</a>
        @endcan
    </div>
</div>
