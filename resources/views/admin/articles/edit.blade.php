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

    @if ($documentError)
        <div class="mt-6 rounded-xl border border-studio-warn-line bg-studio-warn-bg p-5 text-sm text-studio-warn-ink" role="alert">
            {{ $documentError }} The original article is preserved. Contact an administrator before editing its body.
        </div>
    @endif

    <section class="mt-6 rounded-xl border border-studio-line bg-studio-panel p-5" aria-label="Article publishing">
        <p class="text-sm text-studio-muted">Status: <span class="font-semibold text-studio-ink">{{ ucfirst($article->status) }}</span></p>
        @if ($article->status === 'published' && $article->published_at)
            <p class="mt-1 text-sm text-studio-muted">Published {{ $article->published_at->format('M j, Y H:i') }}</p>
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

    @if (in_array($article->status, ['draft', 'published'], true) && ! $documentError)
        <form method="POST" enctype="multipart/form-data" action="{{ $isCreating ? route('admin.superblogs.articles.store', $superblog) : route('admin.superblogs.articles.update', [$superblog, $article]) }}" class="mt-6 space-y-5" data-article-editor data-editor-mode="{{ $legacyFallback ? 'legacy' : 'structured' }}" data-original-image-id="{{ $image?->getKey() ?? '' }}">
            @csrf
            @unless ($isCreating)
                @method('PUT')
            @endunless

            <input type="hidden" name="title" value="{{ old('title', $article->title) }}" data-article-title-field>
            @if ($editorDocument)
                <textarea name="document" hidden data-article-document-field>{{ json_encode($editorDocument, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</textarea>
            @else
                <textarea name="content" hidden data-article-content-field>{{ $editorHtml }}</textarea>
            @endif
            <input type="hidden" name="remove_image" value="0" data-remove-image-field>

            <div class="rounded-xl border border-studio-line bg-studio-panel p-4 sm:p-6">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-studio-line pb-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[.12em] text-studio-muted">Article editor</p>
                        <p class="mt-1 text-sm text-studio-muted">Click in the article and write. Your title is the page’s only main heading.</p>
                    </div>
                    <span class="rounded-full border border-studio-line px-3 py-1 text-xs text-studio-muted">{{ $article->source_type === 'rss' ? 'RSS article' : 'Manual article' }}</span>
                </div>

                <div class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-studio-line bg-studio-bg p-2" role="toolbar" aria-label="Article formatting">
                    <button type="button" data-article-command="bold" class="article-editor-tool" aria-label="Bold"><strong>B</strong></button>
                    <button type="button" data-article-command="italic" class="article-editor-tool" aria-label="Italic"><em>I</em></button>
                    <button type="button" data-article-insert-link="normal" class="article-editor-tool" disabled>Insert link</button>
                    <button type="button" data-article-insert-link="product" class="article-editor-tool" disabled>Insert product link</button>
                    <label class="flex items-center gap-2 rounded-lg border border-studio-line px-3 py-2 text-sm" for="article-text-size">Text size
                        <select id="article-text-size" data-article-text-size class="rounded border border-studio-line bg-studio-panel px-2 py-1">
                            <option value="small">Small</option>
                            <option value="normal" selected>Normal</option>
                            <option value="large">Large</option>
                        </select>
                    </label>
                    <span class="mx-1 h-7 border-l border-studio-line" aria-hidden="true"></span>
                    <button type="button" data-article-add-block="paragraph" class="article-editor-tool">Paragraph</button>
                    <button type="button" data-article-add-block="h2" class="article-editor-tool">Heading</button>
                    <button type="button" data-article-add-block="h3" class="article-editor-tool" disabled>Subheading</button>
                    <button type="button" data-article-add-block="bullet" class="article-editor-tool">Bulleted list</button>
                    <button type="button" data-article-add-block="numbered" class="article-editor-tool">Numbered list</button>
                </div>

                <p data-article-link-notice class="mb-4 rounded-lg border border-studio-warn-line bg-studio-warn-bg px-3 py-2 text-sm text-studio-warn-ink" role="status" aria-live="polite" hidden></p>
                <p class="mb-4 text-xs text-studio-muted">Use headings to organize the story. A subsection is available after a main heading.</p>

                <div class="mx-auto max-w-3xl rounded-lg bg-paper p-5 font-body text-ink shadow-sm sm:p-10">
                    <article>
                        <header class="border-b border-line pb-6">
                            <p class="text-xs uppercase tracking-[.12em] text-muted">{{ $superblog->name }}</p>
                            <div class="mt-3 flex items-start gap-2">
                                <h1 class="min-h-12 flex-1 font-display text-4xl font-semibold tracking-tight outline-none sm:text-5xl" data-editor-title data-placeholder="Article title" contenteditable="true" aria-required="true" spellcheck="true">{{ old('title', $article->title) }}</h1>
                                <span data-required-marker="title" class="mt-2 text-lg font-semibold text-studio-bad" aria-hidden="true">*</span>
                            </div>
                            <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-muted">
                                <span>{{ $article->source_type === 'rss' ? 'RSS source' : 'Manual article' }}</span>
                                <span>{{ $article->status === 'published' ? 'Published' : 'Draft' }}</span>
                            </div>
                        </header>

                        @if ($legacyFallback && $image)
                            <figure class="mt-8" data-legacy-image-preview>
                                <img src="{{ $imageUrl }}" alt="{{ $image->alt_text ?? '' }}" class="mx-auto h-auto max-h-[32rem] max-w-full rounded-md">
                            </figure>
                        @endif

                        <div class="article-body article-editor-body mt-8 space-y-4 leading-7" data-editor-body>
                            @if ($legacyFallback)
                                <p class="text-xs text-studio-muted">Article body <span class="font-semibold text-studio-bad" aria-hidden="true">*</span></p>
                            @endif
                            @if ($editorDocument)
                                {!! $editorBodyHtml !!}
                            @else
                                {!! $editorHtml !!}
                            @endif
                        </div>

                        <aside data-editor-backlinks-preview class="mt-10 rounded-md border border-line p-5" aria-labelledby="editor-sources-heading" @if (! filled($brandLink?->url)) hidden @endif>
                            <h2 id="editor-sources-heading" class="font-display text-lg font-semibold">Källor</h2>
                            <ul class="mt-3 space-y-2 text-sm">
                                <li data-editor-brand-row @if (! filled($brandLink?->url)) hidden @endif><span class="text-muted">Varumärkets webbplats:</span> <a data-editor-brand-preview href="{{ $brandLink->url ?? '' }}" rel="sponsored" class="font-medium text-primary underline underline-offset-4 hover:text-deep">{{ $brandLink->anchor_text ?? '' }}</a></li>
                            </ul>
                        </aside>
                    </article>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-studio-muted">
                    <span data-save-state role="status" aria-live="polite">All changes saved</span>
                    @if ($legacyFallback)
                        <span class="text-xs">This older article stays in its safe HTML format because its structure cannot be converted without losing content.</span>
                    @endif
                </div>
            </div>

            <section class="rounded-xl border border-studio-line bg-studio-panel p-5" aria-labelledby="article-image-heading">
                <h2 id="article-image-heading" class="text-sm font-semibold">Article image</h2>
                <p class="mt-1 text-sm text-studio-muted">One optional image is stored privately and shown only through an authorized or published article route.</p>
                @php($imageIsDecorative = old('image_decorative', $image && $image->alt_text === ''))
                <div class="mt-4 grid gap-4 sm:grid-cols-2 sm:items-start">
                    <label class="block text-sm font-medium" for="article-image">Upload or replace image
                        <input id="article-image" name="image" type="file" accept="image/jpeg,image/png,image/webp" data-article-image-input class="mt-2 block h-11 w-full rounded-lg border border-studio-line bg-studio-bg p-1 text-sm text-studio-ink file:mr-3 file:rounded-md file:border-0 file:bg-studio-pill file:px-3 file:py-1 file:text-sm file:font-medium file:text-studio-ink">
                    </label>
                    <label class="block text-sm font-medium" for="image-alt-text">Alt text <span data-image-alt-required-marker @if (! $image || $imageIsDecorative) hidden @endif class="font-semibold text-studio-bad" aria-hidden="true">*</span>
                        <input id="image-alt-text" name="image_alt_text" value="{{ old('image_alt_text', $image?->alt_text ?? '') }}" maxlength="255" data-image-alt-text class="mt-2 h-11 w-full rounded-lg border border-studio-line bg-studio-bg px-3 text-studio-ink focus-visible:outline-2 focus-visible:outline-studio-brand">
                        <span class="mt-1 block text-xs font-normal text-studio-muted">Required unless the image is marked decorative.</span>
                    </label>
                </div>
                <label class="mt-3 flex items-center gap-2 text-sm text-studio-muted">
                    <input type="checkbox" name="image_decorative" value="1" @checked(old('image_decorative', $image && $image->alt_text === '')) data-image-decorative class="rounded border-studio-line text-studio-brand focus:ring-studio-brand">
                    Decorative image (no alt text)
                </label>
                @if ($image)
                    <button type="button" data-remove-image class="mt-3 rounded-lg border border-studio-line px-3 py-2 text-sm font-medium text-studio-bad hover:bg-studio-bad-bg">Remove image</button>
                @endif
            </section>

            <section class="rounded-xl border border-studio-line bg-studio-panel p-5" aria-labelledby="editor-backlinks-heading">
                <h2 id="editor-backlinks-heading" class="text-sm font-semibold">Brand backlink</h2>
                <p class="mt-1 text-sm text-studio-muted">The brand link appears under “Källor”. Product or service links are added directly to selected article text with the Insert link button.</p>
                <div class="mt-4 max-w-xl space-y-3">
                    <input type="hidden" name="links[product][url]" value="{{ old('links.product.url', $productLink->url ?? '') }}" data-editor-link-url="product">
                    <input type="hidden" name="links[product][anchor_text]" value="{{ old('links.product.anchor_text', $productLink->anchor_text ?? '') }}" data-editor-link-anchor="product">
                    <label class="block text-sm font-medium" for="brand-url">Brand website URL <span data-required-marker="brand-url" hidden class="font-semibold text-studio-bad" aria-hidden="true">*</span>
                        <input id="brand-url" name="links[brand][url]" type="url" inputmode="url" maxlength="2048" value="{{ old('links.brand.url', $brandLink->url ?? '') }}" data-editor-link-url="brand" class="mt-1 h-11 w-full rounded-lg border border-studio-line bg-studio-bg px-3 text-studio-ink focus-visible:outline-2 focus-visible:outline-studio-brand">
                    </label>
                    <label class="block text-sm font-medium" for="brand-anchor">Brand link text <span data-required-marker="brand-anchor" hidden class="font-semibold text-studio-bad" aria-hidden="true">*</span>
                        <input id="brand-anchor" name="links[brand][anchor_text]" maxlength="255" value="{{ old('links.brand.anchor_text', $brandLink->anchor_text ?? '') }}" data-editor-link-anchor="brand" class="mt-1 h-11 w-full rounded-lg border border-studio-line bg-studio-bg px-3 text-studio-ink focus-visible:outline-2 focus-visible:outline-studio-brand">
                    </label>
                </div>
            </section>

            <section class="rounded-xl border border-studio-line bg-studio-panel p-5">
                <label for="slug" class="block text-sm font-medium">Article URL slug <span data-required-marker="slug" class="font-semibold text-studio-bad" aria-hidden="true">*</span></label>
                <input id="slug" name="slug" value="{{ old('slug', $article->slug) }}" required maxlength="255" aria-describedby="slug-help" class="mt-2 w-full rounded-lg border border-studio-line bg-studio-bg p-3 focus-visible:outline-2 focus-visible:outline-studio-brand studio-light:border-studio-field-line">
                <p id="slug-help" class="mt-1 text-sm text-studio-muted">Use lowercase words separated by hyphens. Must be unique within this Superblog.</p>
            </section>

            <div class="sticky bottom-0 z-20 -mx-4 mt-6 flex flex-wrap justify-end gap-3 border-t border-studio-line bg-studio-bg px-4 py-3 shadow-lg sm:-mx-6 sm:px-6">
                @if ($article->status === 'draft')
                    <button type="submit" data-save-article class="rounded-lg border border-studio-line px-5 py-3 text-sm font-semibold text-studio-ink hover:bg-studio-panel focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Save draft</button>
                @endif
                <button type="submit" name="publish_after_save" value="1" data-save-publish-article class="rounded-lg bg-studio-brand px-5 py-3 text-sm font-semibold text-studio-on-brand hover:bg-studio-brand-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Save and publish</button>
            </div>
        </form>
    @else
        <section class="mt-6 space-y-4 rounded-xl border border-studio-line bg-studio-panel p-6" aria-label="Article content (read only)">
            <h2 class="text-xl font-semibold">{{ $article->title }}</h2>
            <p class="text-sm text-studio-muted">/{{ $article->slug }}</p>
            <div class="whitespace-pre-wrap">{{ $article->content }}</div>
        </section>
    @endif
@endsection
