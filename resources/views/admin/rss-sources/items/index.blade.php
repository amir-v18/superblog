@extends('layouts.admin')

@section('title', 'Downloaded RSS items · '.$rssSource->name)

@section('content')
    <a href="{{ route('admin.superblogs.rss-sources.index', $superblog) }}" class="text-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Back to RSS sources</a>
    <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">Downloaded items</h1>
            <p class="mt-2 text-studio-muted">{{ $rssSource->name }} · {{ $superblog->name }}</p>
        </div>
        @can('update', $superblog)
            <div class="flex flex-wrap items-center gap-4">
                <form method="POST" action="{{ route('admin.superblogs.rss-sources.fetch', [$superblog, $rssSource]) }}">
                    @csrf
                    <button type="submit" class="rounded-lg bg-studio-brand px-4 py-2 text-sm font-medium text-studio-side hover:bg-[#ff9068] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Fetch now</button>
                </form>
                <a href="{{ route('admin.superblogs.rss-sources.items.clear', [$superblog, $rssSource]) }}" class="text-sm text-studio-bad underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Clear all items</a>
            </div>
        @endcan
    </div>

    <section class="mt-8 rounded-xl border border-studio-line bg-studio-panel p-6" aria-label="Downloaded RSS items">
        @forelse ($rssItems as $rssItem)
            <article class="border-b border-studio-line py-5 last:border-b-0">
                <h2 class="text-lg font-semibold">{{ $rssItem->title }}</h2>
                <p class="mt-1 text-sm text-studio-muted">
                    @if ($rssItem->published_at)
                        Published {{ $rssItem->published_at->format('M j, Y H:i') }}
                    @else
                        Publication date not provided
                    @endif
                </p>
                @if ($rssItem->url !== '')
                    @if (in_array(strtolower((string) parse_url($rssItem->url, PHP_URL_SCHEME)), ['http', 'https'], true))
                        <p class="mt-2 break-all text-sm"><a href="{{ $rssItem->url }}" target="_blank" rel="noopener noreferrer" class="underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">{{ $rssItem->url }}</a></p>
                    @else
                        <p class="mt-2 break-all text-sm text-studio-muted">{{ $rssItem->url }}</p>
                    @endif
                @endif
                @if ($rssItem->summary || $rssItem->content)
                    <p class="mt-3 whitespace-pre-line text-sm text-studio-muted">{{ \Illuminate\Support\Str::limit(strip_tags($rssItem->summary ?: $rssItem->content), 500) }}</p>
                @endif
                @can('update', $superblog)
                    <a href="{{ route('admin.superblogs.rss-sources.items.delete', [$superblog, $rssSource, $rssItem]) }}" class="mt-3 inline-block text-sm text-studio-bad underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Delete item</a>
                @endcan
            </article>
        @empty
            <p class="py-4 text-sm text-studio-muted">No items have been downloaded from this feed yet. Use Fetch now to check for items.</p>
        @endforelse
        <div class="mt-4">{{ $rssItems->links() }}</div>
    </section>
@endsection
