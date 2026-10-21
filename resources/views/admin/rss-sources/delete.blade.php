@extends('layouts.admin')

@section('title', 'Delete RSS source')

@section('content')
    <a href="{{ route('admin.superblogs.rss-sources.index', $superblog) }}" class="text-sm underline underline-offset-4">Back to RSS sources</a>
    <h1 class="mt-4 text-3xl font-semibold tracking-tight">Delete {{ $rssSource->name }}?</h1>
    <p class="mt-2 text-slate-600">{{ $superblog->name }}</p>
    <div class="mt-6 rounded-xl border border-red-200 bg-white p-6">
        <p>Deleting this source also deletes its stored RSS items. Articles linked to those items remain, but their RSS item references are cleared.</p>
        <form method="POST" action="{{ route('admin.superblogs.rss-sources.destroy', [$superblog, $rssSource]) }}" class="mt-6 flex items-center gap-4">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-lg bg-red-700 px-4 py-2 font-medium text-white hover:bg-red-800">Yes, delete RSS source</button>
            <a href="{{ route('admin.superblogs.rss-sources.index', $superblog) }}" class="text-sm underline underline-offset-4">Cancel</a>
        </form>
    </div>
@endsection
