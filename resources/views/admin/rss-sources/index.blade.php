@extends('layouts.admin')

@section('title', 'RSS sources · '.$superblog->name)

@section('content')
    <a href="{{ route('admin.dashboard') }}" class="text-sm underline underline-offset-4">Back to dashboard</a>
    @if (session('status'))
        <div role="status" class="mt-4 rounded-lg border border-green-200 bg-green-50 p-4 text-green-900">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div role="alert" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-red-900">{{ session('error') }}</div>
    @endif
    <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">RSS sources</h1>
            <p class="mt-2 text-slate-600">{{ $superblog->name }}</p>
        </div>
        <a href="{{ route('admin.superblogs.rss-sources.create', $superblog) }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Add RSS source</a>
    </div>

    @if ($errors->any())
        <div role="alert" class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-red-900">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <section class="mt-8 rounded-xl border border-slate-200 bg-white p-6" aria-labelledby="rss-sources-heading">
        <h2 id="rss-sources-heading" class="text-lg font-semibold">Feeds for this Superblog</h2>
        <ul class="mt-4 divide-y divide-slate-200">
            @forelse ($rssSources as $rssSource)
                <li class="flex flex-wrap items-center justify-between gap-4 py-4">
                    <div class="min-w-0">
                        <h3 class="font-medium">{{ $rssSource->name }}</h3>
                        <p class="mt-1 break-all text-sm text-slate-600">{{ $rssSource->url }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $rssSource->is_active ? 'Enabled' : 'Disabled' }}</p>
                    </div>
                    <div class="flex gap-4 text-sm font-medium">
                        <a href="{{ route('admin.superblogs.rss-sources.items.index', [$superblog, $rssSource]) }}" class="underline underline-offset-4">Downloaded items</a>
                        @can('update', $superblog)
                            <form method="POST" action="{{ route('admin.superblogs.rss-sources.fetch', [$superblog, $rssSource]) }}">
                                @csrf
                                <button type="submit" class="underline underline-offset-4">Fetch now</button>
                            </form>
                        <a href="{{ route('admin.superblogs.rss-sources.edit', [$superblog, $rssSource]) }}" class="underline underline-offset-4">Edit</a>
                        <a href="{{ route('admin.superblogs.rss-sources.delete', [$superblog, $rssSource]) }}" class="text-red-700 underline underline-offset-4">Delete</a>
                        @endcan
                    </div>
                </li>
            @empty
                <li class="py-4 text-sm text-slate-600">No RSS sources have been added to this Superblog.</li>
            @endforelse
        </ul>
        <div class="mt-4">{{ $rssSources->links() }}</div>
    </section>
@endsection
