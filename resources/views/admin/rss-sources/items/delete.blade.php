@extends('layouts.admin')

@section('title', 'Delete RSS item')

@section('content')
    <a href="{{ route('admin.superblogs.rss-sources.items.index', [$superblog, $rssSource]) }}" class="text-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Back to downloaded items</a>
    <h1 class="mt-4 text-3xl font-semibold tracking-tight">Delete this RSS item?</h1>
    <p class="mt-2 text-studio-muted">{{ $rssSource->name }} · {{ $superblog->name }}</p>
    <div class="mt-6 rounded-xl border border-studio-bad/40 bg-studio-panel p-6">
        <p><strong>{{ $rssItem->title }}</strong></p>
        <p class="mt-2">This permanently deletes the downloaded item. Any related article remains, with its source reference cleared.</p>
        <form method="POST" action="{{ route('admin.superblogs.rss-sources.items.destroy', [$superblog, $rssSource, $rssItem]) }}" class="mt-6 flex items-center gap-4">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-lg bg-studio-bad px-4 py-2 font-medium text-studio-side hover:bg-studio-bad/85 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Yes, delete item</button>
            <a href="{{ route('admin.superblogs.rss-sources.items.index', [$superblog, $rssSource]) }}" class="text-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Cancel</a>
        </form>
    </div>
@endsection
