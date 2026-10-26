@props(['superblog'])

@php
    $isLive = $superblog->status === 'published';
    $canEdit = auth()->user()->can('update', $superblog);
    // settings is already loaded with the row; Theme::name() returns null for a missing or unknown theme.
    $themeName = \App\Support\Theme::name($superblog->settings['theme'] ?? null);
    // The same classes on both tags, so a card without edit access looks identical but is not clickable.
    $cardClasses = 'block overflow-hidden rounded-xl border border-slate-200 bg-white';
@endphp

<div class="flex h-full flex-col">
    @if ($canEdit)
        <a href="{{ route('admin.superblogs.edit', $superblog) }}" class="{{ $cardClasses }} flex-1 transition hover:border-slate-300 hover:shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900">
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
                <div class="h-2 bg-slate-200"></div>
                <div class="flex h-32 flex-col justify-between bg-slate-100 p-4 text-slate-700">
                    <p class="truncate text-2xl font-semibold">{{ $superblog->name }}</p>
                    <div class="grid h-8 grid-cols-[2fr_1fr_1fr] gap-2">
                        <span class="rounded-md bg-slate-300"></span>
                        <span class="rounded-md bg-slate-200"></span>
                        <span class="rounded-md bg-slate-200"></span>
                    </div>
                </div>
            </div>
        @endif

        <div class="p-5">
            <div class="flex items-start justify-between gap-3">
                <h3 class="font-semibold">{{ $superblog->name }}</h3>
                @if ($isLive)
                    <span class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-800"><span aria-hidden="true">●</span> Live</span>
                @else
                    <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">Utkast</span>
                @endif
            </div>
            <p class="mt-1 break-all text-sm text-slate-600">{{ $superblog->domain ?: $superblog->slug }}</p>

            <dl class="mt-4 flex gap-6 text-sm">
                <div>
                    <dt class="text-slate-500">Flöden</dt>
                    <dd class="font-medium">{{ $superblog->rss_sources_count }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Publicerade</dt>
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
            <a href="{{ route('admin.superblogs.rss-sources.index', $superblog) }}" class="underline underline-offset-4" aria-label="RSS-flöden för {{ $superblog->name }}">RSS-flöden</a>
        @endcan
        @can('delete', $superblog)
            <a href="{{ route('admin.superblogs.delete', $superblog) }}" class="text-red-700 underline underline-offset-4" aria-label="Ta bort {{ $superblog->name }}">Ta bort</a>
        @endcan
    </div>
</div>
