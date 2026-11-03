@extends('layouts.admin')

@section('title', ($article->status === 'draft' ? 'Edit draft' : 'Edit article').' · '.$article->title)

@section('content')
    @if ($article->rssItem)
        <a href="{{ route('admin.superblogs.rss-sources.items.index', [$superblog, $article->rssItem->rssSource]) }}" class="text-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Back to downloaded items</a>
    @else
        <a href="{{ route('admin.dashboard') }}" class="text-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Back to dashboard</a>
    @endif
    <h1 class="mt-4 text-3xl font-semibold tracking-tight">{{ $article->status === 'draft' ? 'Edit draft article' : 'Edit article' }}</h1>
    <p class="mt-2 text-studio-muted">{{ $superblog->name }}</p>

    @if ($errors->any())
        <x-admin.errors class="mt-6" />
    @endif

    <section class="mt-6 rounded-xl border border-studio-line bg-studio-panel p-5" aria-label="Article publishing">
        <p class="text-sm text-studio-muted">Status: <span class="font-semibold text-studio-ink">{{ ucfirst($article->status) }}</span></p>
        @if ($article->status === 'published' && $article->published_at)
            <p class="mt-1 text-sm text-studio-muted">Published {{ $article->published_at->format('M j, Y H:i') }}</p>
        @endif
        @if ($article->status === 'draft')
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <form method="POST" action="{{ route('admin.superblogs.articles.publish', [$superblog, $article]) }}">
                    @csrf
                    <button type="submit" class="rounded-lg bg-studio-brand px-4 py-2 text-sm font-medium text-studio-side hover:bg-[#ff9068] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Publish now</button>
                </form>
            </div>
        @endif
    </section>

    @if ($article->rssItem)
        <section class="mt-6 rounded-xl border border-studio-line bg-studio-panel p-6" aria-labelledby="original-source-heading">
            <h2 id="original-source-heading" class="text-lg font-semibold">Original RSS source (read only)</h2>
            <p class="mt-3 font-medium">{{ $article->rssItem->title }}</p>
            @if (in_array(strtolower((string) parse_url($article->rssItem->url, PHP_URL_SCHEME)), ['http', 'https'], true))
                <p class="mt-1 break-all text-sm"><a href="{{ $article->rssItem->url }}" target="_blank" rel="noopener noreferrer" class="underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">{{ $article->rssItem->url }}</a></p>
            @endif
            <div class="mt-4 whitespace-pre-wrap break-words text-sm text-studio-muted">{{ $article->rssItem->content ?: $article->rssItem->summary ?: 'No original feed text was provided.' }}</div>
        </section>
    @endif

    @if (in_array($article->status, ['draft', 'published'], true))
        <form method="POST" action="{{ route('admin.superblogs.articles.update', [$superblog, $article]) }}" class="mt-6 space-y-5 rounded-xl border border-studio-line bg-studio-panel p-6">
            @csrf
            @method('PUT')
            <div>
                <label for="title" class="block text-sm font-medium">Title</label>
                <input id="title" name="title" value="{{ old('title', $article->title) }}" required maxlength="255" class="mt-2 w-full rounded-lg border border-studio-line bg-studio-bg p-3 focus-visible:outline-2 focus-visible:outline-studio-brand">
            </div>
            <div>
                <label for="slug" class="block text-sm font-medium">Slug</label>
                <input id="slug" name="slug" value="{{ old('slug', $article->slug) }}" required maxlength="255" aria-describedby="slug-help" class="mt-2 w-full rounded-lg border border-studio-line bg-studio-bg p-3 focus-visible:outline-2 focus-visible:outline-studio-brand">
                <p id="slug-help" class="mt-1 text-sm text-studio-muted">Use lowercase words separated by hyphens. Must be unique within this Superblog.</p>
            </div>
            <div>
                <label for="excerpt" class="block text-sm font-medium">Excerpt (optional)</label>
                <textarea id="excerpt" name="excerpt" rows="3" class="mt-2 w-full rounded-lg border border-studio-line bg-studio-bg p-3 focus-visible:outline-2 focus-visible:outline-studio-brand">{{ old('excerpt', $article->excerpt) }}</textarea>
            </div>
            <div>
                <label for="content" class="block text-sm font-medium">Article content</label>
                <textarea id="content" name="content" rows="18" required class="mt-2 w-full rounded-lg border border-studio-line bg-studio-bg p-3 focus-visible:outline-2 focus-visible:outline-studio-brand">{{ old('content', $article->content) }}</textarea>
            </div>
            <button type="submit" class="rounded-lg bg-studio-brand px-4 py-2 text-sm font-medium text-studio-side hover:bg-[#ff9068] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">{{ $article->status === 'draft' ? 'Save draft' : 'Save changes' }}</button>
        </form>
    @else
        <section class="mt-6 space-y-4 rounded-xl border border-studio-line bg-studio-panel p-6" aria-label="Article content (read only)">
            <h2 class="text-xl font-semibold">{{ $article->title }}</h2>
            <p class="text-sm text-studio-muted">/{{ $article->slug }}</p>
            @if ($article->excerpt)
                <p class="text-studio-muted">{{ $article->excerpt }}</p>
            @endif
            <div class="whitespace-pre-wrap">{{ $article->content }}</div>
        </section>
    @endif
@endsection
