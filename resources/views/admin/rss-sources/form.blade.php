@extends('layouts.admin')

@section('title', $rssSource->exists ? 'Edit RSS source' : 'Add RSS source')

@section('content')
    <a href="{{ route('admin.superblogs.rss-sources.index', $superblog) }}" class="text-sm underline underline-offset-4">Back to RSS sources</a>
    <h1 class="mt-4 text-3xl font-semibold tracking-tight">{{ $rssSource->exists ? 'Edit RSS source' : 'Add RSS source' }}</h1>
    <p class="mt-2 text-slate-600">{{ $superblog->name }}</p>

    @if ($errors->any())
        <div role="alert" class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-red-900">
            <p class="font-medium">Please correct the following:</p>
            <ul class="mt-2 list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $rssSource->exists ? route('admin.superblogs.rss-sources.update', [$superblog, $rssSource]) : route('admin.superblogs.rss-sources.store', $superblog) }}" class="mt-6 space-y-5 rounded-xl border border-slate-200 bg-white p-6">
        @csrf
        @if ($rssSource->exists)
            @method('PUT')
        @endif
        <div>
            <label for="name" class="block text-sm font-medium">Source name</label>
            <input id="name" name="name" value="{{ old('name', $rssSource->name) }}" required maxlength="255" class="mt-2 w-full rounded-lg border border-slate-300 p-3">
        </div>
        <div>
            <label for="url" class="block text-sm font-medium">Feed URL</label>
            <input id="url" name="url" type="url" value="{{ old('url', $rssSource->url) }}" required maxlength="65535" placeholder="https://example.com/feed.xml" class="mt-2 w-full rounded-lg border border-slate-300 p-3">
        </div>
        <div>
            <input type="hidden" name="is_active" value="0">
            <label class="flex items-center gap-3 text-sm font-medium">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $rssSource->exists ? $rssSource->is_active : true)) class="rounded border-slate-300">
                Enabled
            </label>
        </div>
        <div class="flex items-center gap-4">
            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">Save RSS source</button>
            <a href="{{ route('admin.superblogs.rss-sources.index', $superblog) }}" class="text-sm underline underline-offset-4">Cancel</a>
        </div>
    </form>
@endsection
