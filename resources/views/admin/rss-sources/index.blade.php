@extends('layouts.admin')

@section('title', 'RSS sources · '.$superblog->name)

@section('content')
    <a href="{{ route('admin.dashboard') }}" class="text-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Back to dashboard</a>
    @if (session('status'))
        <div role="status" class="mt-4 rounded-lg border border-studio-ok/30 bg-studio-live p-4 text-studio-ok">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div role="alert" class="mt-4 rounded-lg border border-studio-bad/40 bg-[#43261c] p-4 text-studio-bad">{{ session('error') }}</div>
    @endif
    <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">RSS sources</h1>
            <p class="mt-2 text-studio-muted">{{ $superblog->name }}</p>
        </div>
        <a href="{{ route('admin.superblogs.rss-sources.create', $superblog) }}" class="rounded-lg bg-studio-brand px-4 py-2 text-sm font-medium text-studio-side hover:bg-[#ff9068] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Add RSS source</a>
    </div>

    @if ($errors->any())
        <div role="alert" class="mt-6 rounded-lg border border-studio-bad/40 bg-[#43261c] p-4 text-studio-bad">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <section class="mt-8 rounded-xl border border-studio-line bg-studio-panel p-6" aria-labelledby="rss-sources-heading">
        <h2 id="rss-sources-heading" class="text-lg font-semibold">Feeds for this Superblog</h2>
        <ul class="mt-4 divide-y divide-studio-line">
            @forelse ($rssSources as $rssSource)
                <li class="flex flex-wrap items-center justify-between gap-4 py-4">
                    <div class="min-w-0">
                        <h3 class="font-medium">{{ $rssSource->name }}</h3>
                        <p class="mt-1 break-all text-sm text-studio-muted">{{ $rssSource->url }}</p>
                        <p class="mt-1 text-sm text-studio-muted">{{ $rssSource->is_active ? 'Enabled' : 'Disabled' }}</p>
                    </div>
                    <div class="flex gap-4 text-sm font-medium">
                        <a href="{{ route('admin.superblogs.rss-sources.items.index', [$superblog, $rssSource]) }}" class="underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Downloaded items</a>
                        @can('update', $superblog)
                            <form method="POST" action="{{ route('admin.superblogs.rss-sources.fetch', [$superblog, $rssSource]) }}">
                                @csrf
                                <button type="submit" class="underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Fetch now</button>
                            </form>
                        <a href="{{ route('admin.superblogs.rss-sources.edit', [$superblog, $rssSource]) }}" class="underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Edit</a>
                        <a href="{{ route('admin.superblogs.rss-sources.delete', [$superblog, $rssSource]) }}" class="text-studio-bad underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Delete</a>
                        @endcan
                    </div>
                </li>
            @empty
                <li class="py-4 text-sm text-studio-muted">No RSS sources have been added to this Superblog.</li>
            @endforelse
        </ul>
        <div class="mt-4">{{ $rssSources->links() }}</div>
    </section>
@endsection
