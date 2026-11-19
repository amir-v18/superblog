@extends('layouts.admin')

@section('title', ($isCreating ? 'New article' : ($article->status === 'draft' ? 'Edit draft' : 'Edit article')).($article->title ? ' · '.$article->title : ''))

@section('content')
    @if ($article->rssItem)
        <a href="{{ route('admin.superblogs.rss-sources.items.index', [$superblog, $article->rssItem->rssSource]) }}" class="text-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Back to downloaded items</a>
    @elseif ($isCreating)
        <a href="{{ route('admin.superblogs.edit', [$superblog, 'flik' => 'skriv-artikel']) }}" class="text-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Back to Skriv artikel</a>
    @else
        <a href="{{ route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall']) }}" class="text-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Back to Innehåll</a>
    @endif
    <h2 class="mt-4 text-3xl font-semibold tracking-tight">{{ $isCreating ? 'New manual article' : ($article->status === 'draft' ? 'Edit draft article' : 'Edit article') }}</h2>
    <p class="mt-2 text-studio-muted">{{ $superblog->name }}</p>

    @if ($errors->any())
        <x-admin.errors class="mt-6" />
    @endif

    <section class="mt-6 rounded-xl border border-studio-line bg-studio-panel p-5" aria-label="Article publishing">
        <p class="text-sm text-studio-muted">Status: <span class="font-semibold text-studio-ink">{{ ucfirst($article->status) }}</span></p>
        @if ($article->status === 'published' && $article->published_at)
            <p class="mt-1 text-sm text-studio-muted">Published {{ $article->published_at->format('M j, Y H:i') }}</p>
        @endif
        @if ($article->status === 'draft' && $article->exists)
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <form method="POST" action="{{ route('admin.superblogs.articles.publish', [$superblog, $article]) }}">
                    @csrf
                    <button type="submit" class="rounded-lg bg-studio-brand px-4 py-2 text-sm font-medium text-studio-on-brand hover:bg-studio-brand-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Publish now</button>
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
        <form method="POST" action="{{ $isCreating ? route('admin.superblogs.articles.store', $superblog) : route('admin.superblogs.articles.update', [$superblog, $article]) }}" class="mt-6 space-y-5" data-article-editor>
            @csrf
            @unless ($isCreating)
                @method('PUT')
            @endunless

            <textarea name="title" hidden data-article-title-field>{{ old('title', $article->title) }}</textarea>
            <textarea name="content" hidden data-article-content-field>{{ $editorHtml }}</textarea>

            <div class="rounded-xl border border-studio-line bg-studio-panel p-5 sm:p-8">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-studio-line pb-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[.12em] text-studio-muted">Live article preview</p>
                        <p class="mt-1 text-sm text-studio-muted">Double-click the title or a content block to edit it in place.</p>
                    </div>
                    <span class="rounded-full border border-studio-line px-3 py-1 text-xs text-studio-muted">{{ $article->source_type === 'rss' ? 'RSS article' : 'Manual article' }}</span>
                </div>

                <div class="mx-auto max-w-3xl rounded-lg bg-paper p-5 font-body text-ink sm:p-10">
                    <article>
                        <header class="border-b border-line pb-6">
                            <p class="text-xs uppercase tracking-[.12em] text-muted">{{ $superblog->name }}</p>
                            <h1 class="mt-3 font-display text-4xl font-semibold tracking-tight outline-none sm:text-5xl" data-editor-title data-editable-block data-placeholder="Article title">{{ old('title', $article->title) }}</h1>
                            <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-muted">
                                <span>{{ $article->source_type === 'rss' ? 'RSS source' : 'Manual article' }}</span>
                                <span>{{ $article->status === 'published' ? 'Published' : 'Draft' }}</span>
                            </div>
                        </header>

                        <div class="article-body mt-8 space-y-4 leading-7" data-editor-body>
                            @if (trim($editorHtml) === '')
                                <p data-editable-block data-placeholder="Introduction paragraph"></p>
                            @else
                                {!! $editorHtml !!}
                            @endif
                        </div>

                        @php($hasBacklinks = filled($brandLink?->url) || filled($productLink?->url))
                        <p class="mt-10 border-t border-line pt-6" data-editor-backlinks-preview @if (! $hasBacklinks) hidden @endif>
                            Läs mer:
                            <a data-editor-brand-preview @if (! filled($brandLink?->url)) hidden @endif href="{{ $brandLink->url ?? '' }}" rel="sponsored" class="font-medium text-primary underline underline-offset-4 hover:text-deep">{{ $brandLink->anchor_text ?? '' }}</a>
                            <span data-editor-link-separator @if (! (filled($brandLink?->url) && filled($productLink?->url))) hidden @endif> · </span>
                            <a data-editor-product-preview @if (! filled($productLink?->url)) hidden @endif href="{{ $productLink->url ?? '' }}" rel="sponsored" class="font-medium text-primary underline underline-offset-4 hover:text-deep">{{ $productLink->anchor_text ?? '' }}</a>
                        </p>
                    </article>
                </div>
            </div>

            <section class="rounded-xl border border-studio-line bg-studio-panel p-5" aria-labelledby="editor-backlinks-heading">
                <h2 id="editor-backlinks-heading" class="text-sm font-semibold">Brand and product/service links</h2>
                <p class="mt-1 text-sm text-studio-muted">These links appear in the preview and the article’s dedicated “Läs mer” section.</p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <fieldset class="space-y-3 rounded-lg border border-studio-line p-4">
                        <legend class="px-1 text-sm font-semibold">Brand backlink</legend>
                        <label class="block text-sm font-medium" for="brand-url">URL</label>
                        <input id="brand-url" name="links[brand][url]" type="url" inputmode="url" maxlength="2048" value="{{ old('links.brand.url', $brandLink->url ?? '') }}" data-editor-link-url="brand" class="w-full rounded-lg border border-studio-line bg-studio-bg p-2.5 text-studio-ink focus-visible:outline-2 focus-visible:outline-studio-brand">
                        <label class="block text-sm font-medium" for="brand-anchor">Link text</label>
                        <input id="brand-anchor" name="links[brand][anchor_text]" maxlength="255" value="{{ old('links.brand.anchor_text', $brandLink->anchor_text ?? '') }}" data-editor-link-anchor="brand" class="w-full rounded-lg border border-studio-line bg-studio-bg p-2.5 text-studio-ink focus-visible:outline-2 focus-visible:outline-studio-brand">
                    </fieldset>

                    <fieldset class="space-y-3 rounded-lg border border-studio-line p-4">
                        <legend class="px-1 text-sm font-semibold">Product or service backlink</legend>
                        <label class="block text-sm font-medium" for="product-url">URL</label>
                        <input id="product-url" name="links[product][url]" type="url" inputmode="url" maxlength="2048" value="{{ old('links.product.url', $productLink->url ?? '') }}" data-editor-link-url="product" class="w-full rounded-lg border border-studio-line bg-studio-bg p-2.5 text-sm text-studio-ink focus-visible:outline-2 focus-visible:outline-studio-brand">
                        <label class="block text-sm font-medium" for="product-anchor">Link text</label>
                        <input id="product-anchor" name="links[product][anchor_text]" maxlength="255" value="{{ old('links.product.anchor_text', $productLink->anchor_text ?? '') }}" data-editor-link-anchor="product" class="w-full rounded-lg border border-studio-line bg-studio-bg p-2.5 text-sm text-studio-ink focus-visible:outline-2 focus-visible:outline-studio-brand">
                    </fieldset>
                </div>
            </section>

            <section class="rounded-xl border border-studio-line bg-studio-panel p-5" aria-labelledby="article-tools-heading">
                <h2 id="article-tools-heading" class="text-sm font-semibold">Article tools</h2>
                <div class="mt-3 flex flex-wrap items-center gap-2" role="toolbar" aria-label="Article formatting and blocks">
                    <button type="button" data-article-command="bold" class="article-editor-tool"><strong>B</strong><span class="sr-only">Bold</span></button>
                    <button type="button" data-article-command="italic" class="article-editor-tool"><em>I</em><span class="sr-only">Italic</span></button>
                    <button type="button" data-article-command="createLink" class="article-editor-tool">Link</button>
                    <label class="flex items-center gap-2 rounded-lg border border-studio-line px-3 py-2 text-sm" for="article-text-size">Text size
                        <select id="article-text-size" data-article-text-size class="rounded border border-studio-line bg-studio-panel px-2 py-1">
                            <option value="small">Small</option>
                            <option value="normal" selected>Normal</option>
                            <option value="large">Large</option>
                        </select>
                    </label>
                    <span class="mx-1 h-7 border-l border-studio-line" aria-hidden="true"></span>
                    <button type="button" data-article-add-block="p" class="article-editor-tool">Paragraph</button>
                    <button type="button" data-article-add-block="h2" class="article-editor-tool">H2 section</button>
                    <button type="button" data-article-add-block="h3" class="article-editor-tool">H3 subsection</button>
                    <button type="button" data-article-add-block="ul" class="article-editor-tool">Bulleted list</button>
                    <button type="button" data-article-add-block="ol" class="article-editor-tool">Numbered list</button>
                </div>
                <p class="mt-2 text-xs text-studio-muted">Select text for bold, italic, link or size. The article title is the only H1; use H2 for main sections and H3 beneath an H2.</p>
            </section>

            <section class="rounded-xl border border-studio-line bg-studio-panel p-5">
                <label for="slug" class="block text-sm font-medium">Article URL slug</label>
                <input id="slug" name="slug" value="{{ old('slug', $article->slug) }}" required maxlength="255" aria-describedby="slug-help" class="mt-2 w-full rounded-lg border border-studio-line bg-studio-bg p-3 focus-visible:outline-2 focus-visible:outline-studio-brand studio-light:border-studio-field-line">
                <p id="slug-help" class="mt-1 text-sm text-studio-muted">Use lowercase words separated by hyphens. Must be unique within this Superblog.</p>
            </section>

            <button type="submit" class="rounded-lg bg-studio-brand px-4 py-2 text-sm font-medium text-studio-on-brand hover:bg-studio-brand-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">{{ $article->status === 'draft' ? 'Save draft' : 'Save changes' }}</button>
        </form>
    @else
        <section class="mt-6 space-y-4 rounded-xl border border-studio-line bg-studio-panel p-6" aria-label="Article content (read only)">
            <h2 class="text-xl font-semibold">{{ $article->title }}</h2>
            <p class="text-sm text-studio-muted">/{{ $article->slug }}</p>
            <div class="whitespace-pre-wrap">{{ $article->content }}</div>
        </section>
    @endif
@endsection
