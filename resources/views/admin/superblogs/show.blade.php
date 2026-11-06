@extends('layouts.admin')

@section('title', $superblog->name)

@section('content')
    @include('admin.superblogs._section-tabs', ['activeTab' => 'overview'])

    <div class="mt-8 flex flex-wrap items-center gap-3">
        <h1 class="text-3xl font-semibold tracking-tight">{{ $superblog->name }}</h1>
        <x-admin.status-pill :live="$superblog->status === 'published'" />
    </div>

    @if ($superblog->description)
        <p class="mt-3 max-w-3xl text-studio-muted">{{ $superblog->description }}</p>
    @endif

    <section class="mt-8 grid gap-4 sm:grid-cols-2" aria-label="Superblog information">
        <div class="rounded-xl border border-studio-line bg-studio-panel p-5">
            <h2 class="text-sm font-semibold text-studio-muted">Webbadress</h2>
            <p class="mt-2 break-all">{{ $superblog->domain ?: $superblog->slug }}</p>
        </div>
        <div class="rounded-xl border border-studio-line bg-studio-panel p-5">
            <h2 class="text-sm font-semibold text-studio-muted">Artiklar</h2>
            <p class="mt-2">{{ $superblog->articles_count }} totalt · {{ $superblog->published_articles_count }} publicerade</p>
            <a href="{{ route('admin.superblogs.articles.index', $superblog) }}" class="mt-2 inline-block text-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Visa artiklar</a>
        </div>
    </section>

    <a href="{{ route('admin.superblogs.rss-sources.index', $superblog) }}" class="mt-6 inline-block text-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Hantera Flöden / RSS</a>
@endsection
