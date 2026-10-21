@extends('layouts.admin')

@section('title', 'Downloaded RSS items · '.$rssSource->name)

@section('content')
    <a href="{{ route('admin.superblogs.rss-sources.index', $superblog) }}" class="text-sm underline underline-offset-4">Back to RSS sources</a>
    @if (session('status'))
        <div role="status" class="mt-4 rounded-lg border border-green-200 bg-green-50 p-4 text-green-900">{{ session('status') }}</div>
    @endif
    <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">Downloaded items</h1>
            <p class="mt-2 text-slate-600">{{ $rssSource->name }} · {{ $superblog->name }}</p>
        </div>
        @can('update', $superblog)
            <div class="flex flex-wrap items-center gap-4">
                <form method="POST" action="{{ route('admin.superblogs.rss-sources.fetch', [$superblog, $rssSource]) }}">
                    @csrf
                    <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Fetch now</button>
                </form>
                <a href="{{ route('admin.superblogs.rss-sources.items.clear', [$superblog, $rssSource]) }}" class="text-sm text-red-700 underline underline-offset-4">Clear all items</a>
            </div>
        @endcan
    </div>

    <section class="mt-8 rounded-xl border border-slate-200 bg-white p-6" aria-label="Downloaded RSS items">
        @forelse ($rssItems as $rssItem)
            <article class="border-b border-slate-200 py-5 last:border-b-0">
                <h2 class="text-lg font-semibold">{{ $rssItem->title }}</h2>
                <p class="mt-1 text-sm text-slate-600">
                    @if ($rssItem->published_at)
                        Published {{ $rssItem->published_at->format('M j, Y H:i') }}
                    @else
                        Publication date not provided
                    @endif
                </p>
                @if ($rssItem->url !== '')
                    @if (in_array(strtolower((string) parse_url($rssItem->url, PHP_URL_SCHEME)), ['http', 'https'], true))
                        <p class="mt-2 break-all text-sm"><a href="{{ $rssItem->url }}" target="_blank" rel="noopener noreferrer" class="underline underline-offset-4">{{ $rssItem->url }}</a></p>
                    @else
                        <p class="mt-2 break-all text-sm text-slate-600">{{ $rssItem->url }}</p>
                    @endif
                @endif
                @if ($rssItem->summary || $rssItem->content)
                    <p class="mt-3 whitespace-pre-line text-sm text-slate-700">{{ \Illuminate\Support\Str::limit(strip_tags($rssItem->summary ?: $rssItem->content), 500) }}</p>
                @endif
                @can('update', $superblog)
                    <a href="{{ route('admin.superblogs.rss-sources.items.delete', [$superblog, $rssSource, $rssItem]) }}" class="mt-3 inline-block text-sm text-red-700 underline underline-offset-4">Delete item</a>
                @endcan
            </article>
        @empty
            <p class="py-4 text-sm text-slate-600">No items have been downloaded from this feed yet. Use Fetch now to check for items.</p>
        @endforelse
        <div class="mt-4">{{ $rssItems->links() }}</div>
    </section>
@endsection
