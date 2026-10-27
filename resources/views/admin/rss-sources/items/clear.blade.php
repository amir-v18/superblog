@extends('layouts.admin')

@section('title', 'Clear RSS items')

@section('content')
    <a href="{{ route('admin.superblogs.rss-sources.items.index', [$superblog, $rssSource]) }}" class="text-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Back to downloaded items</a>
    <h1 class="mt-4 text-3xl font-semibold tracking-tight">Clear all downloaded items?</h1>
    <p class="mt-2 text-studio-muted">{{ $rssSource->name }} · {{ $superblog->name }}</p>
    <div class="mt-6 rounded-xl border border-studio-bad/40 bg-studio-panel p-6">
        <p>This permanently deletes every RSS item stored for this source. The RSS source and any related articles remain; article references to these items are cleared.</p>
        <form method="POST" action="{{ route('admin.superblogs.rss-sources.items.clear.destroy', [$superblog, $rssSource]) }}" class="mt-6 flex items-center gap-4">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-lg bg-studio-bad px-4 py-2 font-medium text-studio-side hover:bg-studio-bad/85 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Yes, permanently clear items</button>
            <a href="{{ route('admin.superblogs.rss-sources.items.index', [$superblog, $rssSource]) }}" class="text-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Cancel</a>
        </form>
    </div>
@endsection
