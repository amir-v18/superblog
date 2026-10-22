@props(['superblog'])

@php
    $isLive = $superblog->status === 'published';
    $canEdit = auth()->user()->can('update', $superblog);
    // The same classes on both tags, so a card without edit access looks identical but is not clickable.
    $cardClasses = 'block overflow-hidden rounded-xl border border-slate-200 bg-white';
@endphp

<div class="flex h-full flex-col">
    @if ($canEdit)
        <a href="{{ route('admin.superblogs.edit', $superblog) }}" class="{{ $cardClasses }} flex-1 transition hover:border-slate-300 hover:shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900">
    @else
        <div class="{{ $cardClasses }} flex-1">
    @endif
        {{-- Neutral until themes are stored per Superblog; connect this strip to the saved theme colors then. --}}
        <div aria-hidden="true" class="h-2 bg-slate-200"></div>

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
