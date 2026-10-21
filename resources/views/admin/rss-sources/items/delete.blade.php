@extends('layouts.admin')

@section('title', 'Delete RSS item')

@section('content')
    <a href="{{ route('admin.superblogs.rss-sources.items.index', [$superblog, $rssSource]) }}" class="text-sm underline underline-offset-4">Back to downloaded items</a>
    <h1 class="mt-4 text-3xl font-semibold tracking-tight">Delete this RSS item?</h1>
    <p class="mt-2 text-slate-600">{{ $rssSource->name }} · {{ $superblog->name }}</p>
    <div class="mt-6 rounded-xl border border-red-200 bg-white p-6">
        <p><strong>{{ $rssItem->title }}</strong></p>
        <p class="mt-2">This permanently deletes the downloaded item. Any related article remains, with its source reference cleared.</p>
        <form method="POST" action="{{ route('admin.superblogs.rss-sources.items.destroy', [$superblog, $rssSource, $rssItem]) }}" class="mt-6 flex items-center gap-4">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-lg bg-red-700 px-4 py-2 font-medium text-white hover:bg-red-800">Yes, delete item</button>
            <a href="{{ route('admin.superblogs.rss-sources.items.index', [$superblog, $rssSource]) }}" class="text-sm underline underline-offset-4">Cancel</a>
        </form>
    </div>
@endsection
