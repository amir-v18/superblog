@extends('layouts.admin')

@section('title', 'Delete RSS source')

@section('content')
    <a href="{{ route('admin.superblogs.rss-sources.index', $superblog) }}" class="text-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Back to RSS sources</a>
    <h1 class="mt-4 text-3xl font-semibold tracking-tight">Delete {{ $rssSource->name }}?</h1>
    <p class="mt-2 text-studio-muted">{{ $superblog->name }}</p>
    <div class="mt-6 rounded-xl border border-studio-bad/40 bg-studio-panel p-6">
        <p>Deleting this source also deletes its stored RSS items. Articles linked to those items remain, but their RSS item references are cleared.</p>
        <form method="POST" action="{{ route('admin.superblogs.rss-sources.destroy', [$superblog, $rssSource]) }}" class="mt-6 flex items-center gap-4">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-lg bg-studio-bad px-4 py-2 font-medium text-studio-side hover:bg-studio-bad/85 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Yes, delete RSS source</button>
            <a href="{{ route('admin.superblogs.rss-sources.index', $superblog) }}" class="text-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Cancel</a>
        </form>
    </div>
@endsection
