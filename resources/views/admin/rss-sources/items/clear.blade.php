@extends('layouts.admin')

@section('title', 'Clear RSS items')

@section('content')
    <a href="{{ route('admin.superblogs.rss-sources.items.index', [$superblog, $rssSource]) }}" class="text-sm underline underline-offset-4">Back to downloaded items</a>
    <h1 class="mt-4 text-3xl font-semibold tracking-tight">Clear all downloaded items?</h1>
    <p class="mt-2 text-slate-600">{{ $rssSource->name }} · {{ $superblog->name }}</p>
    <div class="mt-6 rounded-xl border border-red-200 bg-white p-6">
        <p>This permanently deletes every RSS item stored for this source. The RSS source and any related articles remain; article references to these items are cleared.</p>
        <form method="POST" action="{{ route('admin.superblogs.rss-sources.items.clear.destroy', [$superblog, $rssSource]) }}" class="mt-6 flex items-center gap-4">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-lg bg-red-700 px-4 py-2 font-medium text-white hover:bg-red-800">Yes, permanently clear items</button>
            <a href="{{ route('admin.superblogs.rss-sources.items.index', [$superblog, $rssSource]) }}" class="text-sm underline underline-offset-4">Cancel</a>
        </form>
    </div>
@endsection
