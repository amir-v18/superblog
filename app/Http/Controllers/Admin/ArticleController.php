<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ArticleContentFormat;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveArticleRequest;
use App\Http\Requests\Admin\SaveArticleSelectionRequest;
use App\Models\Article;
use App\Models\ArticleLink;
use App\Models\Media;
use App\Models\RssItem;
use App\Models\RssSource;
use App\Models\Superblog;
use App\Support\ArticleContentDocument;
use App\Support\ArticleContentHtmlRenderer;
use App\Support\ArticleContentStructure;
use App\Support\ArticleHtmlSanitizer;
use App\Support\LegacyArticleDocumentConverter;
use App\Support\SuperblogPublishingReadiness;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use JsonException;
use Throwable;

class ArticleController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Superblog::class);

        $articles = Article::query()
            ->whereHas('superblog', fn ($query) => $query->accessibleTo($request->user()))
            ->with('superblog')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.articles.index', compact('articles'));
    }

    public function superblogIndex(Superblog $superblog): RedirectResponse
    {
        Gate::authorize('view', $superblog);

        return to_route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall']);
    }

    public function create(Superblog $superblog, ArticleContentDocument $documents, ArticleContentHtmlRenderer $renderer): View
    {
        Gate::authorize('manageArticles', $superblog);

        $article = new Article([
            'status' => 'draft',
            'source_type' => 'manual',
            'is_selected_for_publication' => true,
        ]);
        $editorDocument = $this->oldDocument($documents) ?? [
            'version' => ArticleContentDocument::VERSION,
            'blocks' => [[
                'id' => (string) Str::uuid(),
                'type' => 'introduction',
                'runs' => [],
            ]],
        ];

        return view('admin.articles.edit', [
            'superblog' => $superblog,
            'article' => $article,
            'isCreating' => true,
            'editorDocument' => $editorDocument,
            'editorBodyHtml' => $renderer->renderEditor($editorDocument, null, null),
            'legacyFallback' => false,
            'editorHtml' => '',
            'image' => null,
            'imageUrl' => null,
            'documentError' => null,
            'brandLink' => null,
            'productLink' => null,
        ]);
    }

    public function store(
        SaveArticleRequest $request,
        Superblog $superblog,
        ArticleContentDocument $documents,
    ): RedirectResponse {
        $validated = $request->validated();
        $document = $this->decodeSubmittedDocument($validated['document'], $documents, $request->hasFile('image'));
        $links = $validated['links'] ?? [];
        unset($validated['links'], $validated['document'], $validated['content']);

        $newPath = null;
        try {
            $article = DB::transaction(function () use ($validated, $links, $superblog, $documents, $document, &$newPath): Article {
                $lockedSuperblog = Superblog::query()->lockForUpdate()->findOrFail($superblog->getKey());

                $article = $lockedSuperblog->articles()->create([
                    'rss_item_id' => null,
                    'title' => $validated['title'],
                    'slug' => $validated['slug'],
                    'content' => '',
                    'content_format' => ArticleContentFormat::BlocksV1,
                    'source_type' => 'manual',
                    'status' => 'draft',
                    'is_selected_for_publication' => true,
                    'published_at' => null,
                ]);

                [$document, , $newPath] = $this->syncArticleImage($article, $document, $validated);
                $this->validateImageReferences($article, $document, $documents);
                $documents->storeOn($article, $document);
                $article->save();
                $this->syncArticleLinks($article, $links);

                return $article;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $this->deleteUnreferencedMediaFile($newPath);
            if (! $superblog->articles()->where('slug', $validated['slug'])->exists()) {
                throw $exception;
            }
            throw ValidationException::withMessages(['slug' => 'This slug is already used by another article in this Superblog.']);
        } catch (Throwable $exception) {
            $this->deleteUnreferencedMediaFile($newPath);
            throw $exception;
        }

        return to_route('admin.superblogs.articles.edit', [$superblog, $article])
            ->with('status', 'Draft article created.');
    }

    public function updateSelection(
        SaveArticleSelectionRequest $request,
        Superblog $superblog,
        ArticleHtmlSanitizer $htmlSanitizer,
        ArticleContentStructure $contentStructure,
        ArticleContentDocument $documents,
    ): RedirectResponse {
        $visibleIds = array_map('intval', $request->validated('visible_article_ids'));
        $selectedIds = array_map('intval', $request->validated('selected_article_ids', []));

        $isPublished = DB::transaction(function () use ($superblog, $visibleIds, $selectedIds, $htmlSanitizer, $contentStructure, $documents): bool {
            $lockedSuperblog = Superblog::query()->lockForUpdate()->findOrFail($superblog->getKey());

            if ($lockedSuperblog->status === 'published' && $selectedIds !== []) {
                $draftsToPublish = $lockedSuperblog->articles()
                    ->whereIn('id', $visibleIds)
                    ->whereIn('id', $selectedIds)
                    ->where('status', 'draft')
                    ->get();

                foreach ($draftsToPublish as $article) {
                    $this->ensureArticleCanBePublished($article, $htmlSanitizer, $contentStructure, $documents);
                }
            }

            $visibleArticles = $lockedSuperblog->articles()->whereIn('id', $visibleIds);

            $visibleArticles->whereNotIn('id', $selectedIds)
                ->update(['is_selected_for_publication' => false]);

            if ($selectedIds !== []) {
                $lockedSuperblog->articles()
                    ->whereIn('id', $visibleIds)
                    ->whereIn('id', $selectedIds)
                    ->update(['is_selected_for_publication' => true]);
            }

            if ($lockedSuperblog->status !== 'published') {
                return false;
            }

            $articlesToPublish = $lockedSuperblog->articles()
                ->whereIn('id', $visibleIds)
                ->where('is_selected_for_publication', true)
                ->where('status', 'draft');

            $now = now();

            (clone $articlesToPublish)->whereNull('published_at')->update(['published_at' => $now]);

            (clone $articlesToPublish)->update(['status' => 'published']);

            $lockedSuperblog->articles()
                ->whereIn('id', $visibleIds)
                ->where('is_selected_for_publication', false)
                ->where(function ($query): void {
                    $query->where('status', '!=', 'draft')->orWhereNotNull('published_at');
                })
                ->update([
                    'status' => 'draft',
                ]);

            return true;
        });

        $message = $isPublished
            ? 'Article selection saved and applied to publication for the current page.'
            : 'Article selection saved for the current page. The Superblog is offline, so article publication was not changed.';

        return to_route('admin.superblogs.edit', [
            $superblog,
            'flik' => 'innehall',
            'manual_page' => $request->query('manual_page', 1),
            'rss_page' => $request->query('rss_page', 1),
        ])->with('status', $message);
    }

    public function publishSelected(
        Superblog $superblog,
        SuperblogPublishingReadiness $publishingReadiness,
        ArticleHtmlSanitizer $htmlSanitizer,
        ArticleContentStructure $contentStructure,
        ArticleContentDocument $documents,
    ): RedirectResponse {
        Gate::authorize('manageArticles', $superblog);
        $canPublishSuperblog = Gate::allows('update', $superblog);
        $publishedAt = now();

        [$published, $withdrawn, $wasAlreadyPublished] = DB::transaction(function () use ($superblog, $publishedAt, $publishingReadiness, $canPublishSuperblog, $htmlSanitizer, $contentStructure, $documents): array {
            $lockedSuperblog = Superblog::query()->lockForUpdate()->findOrFail($superblog->getKey());
            if ($canPublishSuperblog) {
                $publishingReadiness->validate($lockedSuperblog);
            }
            $wasAlreadyPublished = $lockedSuperblog->status === 'published';

            // Restrict each update to articles whose status actually needs to change; repeated requests are safe.
            $articlesToPublish = $lockedSuperblog->articles()
                ->where('is_selected_for_publication', true)
                ->where('status', 'draft');

            foreach ((clone $articlesToPublish)->get() as $article) {
                $this->ensureArticleCanBePublished($article, $htmlSanitizer, $contentStructure, $documents);
            }

            $articlesToWithdraw = $lockedSuperblog->articles()
                ->where('is_selected_for_publication', false)
                ->where('status', 'published');

            // Count matching rows before updating so the message reports transitions, not driver-specific row counts.
            $published = $articlesToPublish->count();
            $withdrawn = $articlesToWithdraw->count();

            if ($published > 0) {
                (clone $articlesToPublish)->whereNull('published_at')->update(['published_at' => $publishedAt]);

                (clone $articlesToPublish)->update(['status' => 'published']);
            }

            if ($withdrawn > 0) {
                $articlesToWithdraw->update([
                    'status' => 'draft',
                ]);
            }

            if ($canPublishSuperblog && ! $wasAlreadyPublished) {
                $lockedSuperblog->update(['status' => 'published']);
            }

            return [$published, $withdrawn, $wasAlreadyPublished];
        });

        $publishedMessage = $published === 1 ? '1 artikel publicerades' : "{$published} artiklar publicerades";
        $withdrawnMessage = $withdrawn === 1 ? '1 artikel drogs tillbaka' : "{$withdrawn} artiklar drogs tillbaka";
        $superblogMessage = ! $canPublishSuperblog
            ? 'Superbloggens status ändrades inte.'
            : ($wasAlreadyPublished ? 'Superblocket var redan publicerat.' : 'Superblocket publicerades.');

        return to_route('admin.superblogs.edit', [$superblog, 'flik' => 'publicera'])
            ->with('status', "Artikelpublicering klar: {$publishedMessage} och {$withdrawnMessage}. {$superblogMessage}");
    }

    public function createFromRssItem(Superblog $superblog, RssSource $rssSource, RssItem $rssItem, ArticleHtmlSanitizer $htmlSanitizer): RedirectResponse
    {
        Gate::authorize('manageArticles', $superblog);

        try {
            [$article, $wasExisting] = DB::transaction(function () use ($superblog, $rssSource, $rssItem, $htmlSanitizer): array {
                // Lock the blog to serialize slug allocation; the unique RSS-item index remains the final race guard.
                $lockedSuperblog = Superblog::query()->lockForUpdate()->findOrFail($superblog->getKey());
                $lockedSource = $lockedSuperblog->rssSources()->whereKey($rssSource->getKey())->firstOrFail();
                $lockedItem = $lockedSource->rssItems()->whereKey($rssItem->getKey())->lockForUpdate()->firstOrFail();

                $existing = $lockedItem->article()->first();

                if ($existing) {
                    abort_unless((int) $existing->superblog_id === (int) $lockedSuperblog->getKey(), 404);

                    return [$existing, true];
                }

                $sourceContent = $htmlSanitizer->sanitize($lockedItem->content ?: '');

                if (! $htmlSanitizer->hasText($sourceContent)) {
                    $sourceContent = $htmlSanitizer->sanitize($lockedItem->summary ?: '');
                }

                $article = $lockedSuperblog->articles()->create([
                    'rss_item_id' => $lockedItem->getKey(),
                    'content_format' => ArticleContentFormat::LegacyHtml,
                    'title' => $lockedItem->title,
                    'slug' => $this->uniqueSlug($lockedSuperblog, $lockedItem),
                    'content' => $sourceContent,
                    'source_type' => 'rss',
                    'status' => 'draft',
                    'is_selected_for_publication' => true,
                ]);

                return [$article, false];
            });
        } catch (UniqueConstraintViolationException $exception) {
            $article = $rssItem->article()->first();

            if (! $article || (int) $article->superblog_id !== (int) $superblog->getKey()) {
                throw $exception;
            }

            $wasExisting = true;
        }

        return to_route('admin.superblogs.articles.edit', [$superblog, $article])
            ->with('status', $wasExisting ? 'The draft for this RSS item is already created.' : 'Draft article created from RSS item.');
    }

    public function edit(
        Superblog $superblog,
        Article $article,
        ArticleContentDocument $documents,
        LegacyArticleDocumentConverter $legacyConverter,
        ArticleContentHtmlRenderer $renderer,
        ArticleHtmlSanitizer $htmlSanitizer,
    ): View {
        Gate::authorize('manageArticles', $superblog);
        $article = $this->articleForSuperblog($superblog, $article)->load(['articleLinks', 'image']);
        $documentError = null;
        $editorDocument = $this->oldDocument($documents);

        if ($editorDocument === null) {
            try {
                $editorDocument = $article->content_format === ArticleContentFormat::BlocksV1
                    ? $documents->readFrom($article)
                    : $legacyConverter->convert($article->content);
            } catch (ValidationException) {
                $documentError = 'This saved structured article could not be opened safely. Its stored content has not been changed.';
            }
        }

        if ($editorDocument !== null && $article->image) {
            $hasImageBlock = collect($editorDocument['blocks'])->contains(fn (array $block): bool => $block['type'] === 'image');
            if (! $hasImageBlock) {
                $editorDocument['blocks'][] = [
                    'id' => (string) Str::uuid(),
                    'type' => 'image',
                    'media_id' => (int) $article->image->getKey(),
                    'caption' => '',
                ];
            }
        }

        $productLink = $article->articleLinks->first(fn (ArticleLink $link): bool => in_array($link->type, ['product', 'service'], true));

        return view('admin.articles.edit', [
            'superblog' => $superblog,
            'article' => $article,
            'isCreating' => false,
            'editorDocument' => $editorDocument,
            'editorBodyHtml' => $editorDocument !== null
                ? $renderer->renderEditor($editorDocument, $article->image, $article->image ? route('admin.superblogs.articles.image', [$superblog, $article]) : null)
                : '',
            'legacyFallback' => $editorDocument === null && $documentError === null && $article->content_format === ArticleContentFormat::LegacyHtml,
            'editorHtml' => $htmlSanitizer->sanitize((string) old('content', $article->content)),
            'image' => $article->image,
            'imageUrl' => $article->image ? route('admin.superblogs.articles.image', [$superblog, $article]) : null,
            'documentError' => $documentError,
            'brandLink' => old('links.brand') ? (object) old('links.brand') : $article->articleLinks->firstWhere('type', 'brand'),
            'productLink' => old('links.product') ? (object) old('links.product') : $productLink,
        ]);
    }

    public function destroy(Request $request, Superblog $superblog, Article $article): RedirectResponse|JsonResponse
    {
        Gate::authorize('manageArticles', $superblog);

        [$sectionKey, $currentPage, $mediaPath] = DB::transaction(function () use ($request, $superblog, $article): array {
            $lockedSuperblog = Superblog::query()->lockForUpdate()->findOrFail($superblog->getKey());
            $lockedArticle = $this->articleForSuperblog($lockedSuperblog, $article, lock: true)->load('image');
            $sectionKey = match ($lockedArticle->source_type) {
                'manual' => 'manual',
                'rss' => 'rss',
                default => abort(404),
            };

            // Foreign-key cascades remove this article's child rows, but leave its RSS source item and files alone.
            $mediaPath = $lockedArticle->image?->path;
            $lockedArticle->delete();

            $pageName = $sectionKey.'_page';
            $lastPage = max(1, (int) ceil($lockedSuperblog->articles()->where('source_type', $sectionKey)->count() / 5));
            $requestedPage = max(1, (int) $request->query($pageName, 1));

            return [$sectionKey, min($requestedPage, $lastPage), $mediaPath];
        });

        $this->deleteUnreferencedMediaFile($mediaPath);

        $message = 'Artikeln har tagits bort permanent.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'sectionKey' => $sectionKey,
                'currentPage' => $currentPage,
                'superblogId' => $superblog->getKey(),
            ]);
        }

        return to_route('admin.superblogs.edit', [
            $superblog,
            'flik' => 'innehall',
            'manual_page' => $sectionKey === 'manual' ? $currentPage : $request->query('manual_page', 1),
            'rss_page' => $sectionKey === 'rss' ? $currentPage : $request->query('rss_page', 1),
        ])->with('status', $message);
    }

    public function publish(
        Superblog $superblog,
        Article $article,
        ArticleHtmlSanitizer $htmlSanitizer,
        ArticleContentStructure $contentStructure,
        ArticleContentDocument $documents,
    ): RedirectResponse {
        Gate::authorize('manageArticles', $superblog);

        DB::transaction(function () use ($superblog, $article, $htmlSanitizer, $contentStructure, $documents): void {
            $article = $this->articleForSuperblog($superblog, $article, lock: true);

            if ($article->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Only draft articles can be published.']);
            }

            $this->ensureArticleCanBePublished($article, $htmlSanitizer, $contentStructure, $documents);

            $now = now();
            $article->status = 'published';
            $article->published_at ??= $now;
            $article->is_selected_for_publication = true;
            $article->save();
        });

        return to_route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall'])
            ->with('status', 'Article published.');
    }

    public function update(
        SaveArticleRequest $request,
        Superblog $superblog,
        Article $article,
        ArticleContentDocument $documents,
        LegacyArticleDocumentConverter $legacyConverter,
        ArticleHtmlSanitizer $htmlSanitizer,
    ): RedirectResponse {
        $validated = $request->validated();
        $links = $validated['links'] ?? null;
        unset($validated['links'], $validated['document'], $validated['content']);

        $newPath = null;
        $oldPath = null;
        try {
            $article = DB::transaction(function () use ($request, $validated, $links, $superblog, $article, $documents, $legacyConverter, $htmlSanitizer, &$newPath, &$oldPath): Article {
                $article = $this->articleForSuperblog($superblog, $article, lock: true)->load('image');
                abort_unless(in_array($article->status, ['draft', 'published'], true), 404);

                $wasPublished = $article->status === 'published';
                $previousContent = $article->content;
                $previousFormat = $article->content_format;
                $previousTitle = $article->title;
                $previousDocumentFingerprint = null;
                if ($previousFormat === ArticleContentFormat::BlocksV1) {
                    $previousDocumentFingerprint = $this->documentFingerprint($documents->readFrom($article) ?? []);
                } else {
                    $previousDocument = $legacyConverter->convert($article->content);
                    if ($previousDocument !== null && $article->image) {
                        $previousDocument['blocks'][] = [
                            'id' => (string) Str::uuid(),
                            'type' => 'image',
                            'media_id' => (int) $article->image->getKey(),
                            'caption' => '',
                        ];
                    }
                    if ($previousDocument !== null) {
                        $previousDocumentFingerprint = $this->documentFingerprint($previousDocument);
                    }
                }
                $imageChanged = false;

                if (isset($request->validated()['document'])) {
                    $document = $this->decodeSubmittedDocument($request->validated('document'), $documents, $request->hasFile('image'));
                    [$document, $imageChanged, $newPath, $oldPath] = $this->syncArticleImage($article, $document, $request->validated());
                    $this->validateImageReferences($article, $document, $documents);
                    $documents->storeOn($article, $document);
                } else {
                    if ($article->content_format !== ArticleContentFormat::LegacyHtml) {
                        throw ValidationException::withMessages(['document' => 'This article must be saved using its structured editor content. Reload the page and try again.']);
                    }

                    $safeContent = $htmlSanitizer->sanitize((string) $request->validated('content'));
                    $this->ensureSanitizedContentHasText($safeContent, $htmlSanitizer);
                    $document = $legacyConverter->convert($safeContent);

                    if ($document !== null) {
                        if ($article->image && ! collect($document['blocks'])->contains(fn (array $block): bool => $block['type'] === 'image')) {
                            $document['blocks'][] = [
                                'id' => (string) Str::uuid(),
                                'type' => 'image',
                                'media_id' => (int) $article->image->getKey(),
                                'caption' => '',
                            ];
                        }
                        [$document, $imageChanged, $newPath, $oldPath] = $this->syncArticleImage($article, $document, $request->validated());
                        $this->validateImageReferences($article, $document, $documents);
                        $documents->storeOn($article, $document);
                    } else {
                        $article->content = $safeContent;
                        [$imageChanged, $newPath, $oldPath] = $this->syncLegacyArticleImage($article, $request->validated());
                    }
                }

                $article->fill(['title' => $validated['title'], 'slug' => $validated['slug']]);
                $linksChanged = $links !== null && $this->syncArticleLinks($article, $links);
                $contentChanged = $article->content_format === ArticleContentFormat::BlocksV1
                    ? $previousDocumentFingerprint !== $this->documentFingerprint($documents->readFrom($article) ?? [])
                    : $previousContent !== $article->content;

                if ($wasPublished && ($previousTitle !== $article->title || $contentChanged || $linksChanged || $imageChanged)) {
                    $article->content_modified_at = now();
                }

                $article->save();

                return $article;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $this->deleteUnreferencedMediaFile($newPath);
            if (! $superblog->articles()->where('slug', $validated['slug'])->where('id', '<>', $article->getKey())->exists()) {
                throw $exception;
            }
            throw ValidationException::withMessages(['slug' => 'This slug is already used by another article in this Superblog.']);
        } catch (Throwable $exception) {
            $this->deleteUnreferencedMediaFile($newPath);
            throw $exception;
        }

        $this->deleteUnreferencedMediaFile($oldPath);

        return to_route('admin.superblogs.articles.edit', [$superblog, $article])->with('status', 'Article saved.');
    }

    private function ensureSanitizedContentHasText(string $content, ArticleHtmlSanitizer $htmlSanitizer): void
    {
        if (! $htmlSanitizer->hasText($content)) {
            throw ValidationException::withMessages(['content' => 'Article content must contain readable text.']);
        }
    }

    private function ensureValidContentStructure(string $content, ArticleContentStructure $contentStructure): void
    {
        $message = $contentStructure->error($content);

        if ($message !== null) {
            throw ValidationException::withMessages(['content' => $message]);
        }
    }

    private function ensureArticleCanBePublished(
        Article $article,
        ArticleHtmlSanitizer $htmlSanitizer,
        ArticleContentStructure $contentStructure,
        ArticleContentDocument $documents,
    ): void {
        if ($article->content_format === ArticleContentFormat::BlocksV1) {
            $document = $documents->readFrom($article);
            $this->validateImageReferences($article, $document ?? [], $documents);

            return;
        }

        $safeContent = $htmlSanitizer->sanitize($article->content);
        $this->ensureSanitizedContentHasText($safeContent, $htmlSanitizer);
        $this->ensureValidContentStructure($safeContent, $contentStructure);
    }

    /** @return array{version: int, blocks: list<array<string, mixed>>} */
    private function decodeSubmittedDocument(string $json, ArticleContentDocument $documents, bool $hasUpload = false): array
    {
        try {
            $raw = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['document' => 'The article editor sent an invalid document. Please reload and try again.']);
        }

        // A new inline image has no database ID until the upload is stored in this request.
        if ($hasUpload && is_array($raw['blocks'] ?? null)) {
            foreach ($raw['blocks'] as &$block) {
                if (($block['type'] ?? null) === 'image' && ($block['media_id'] ?? null) === 0) {
                    $block['media_id'] = PHP_INT_MAX;
                }
            }
            unset($block);
        }

        return $documents->validate($raw);
    }

    /** @return array{version: int, blocks: list<array<string, mixed>>}|null */
    private function oldDocument(ArticleContentDocument $documents): ?array
    {
        $old = old('document');
        if (! is_string($old) || $old === '') {
            return null;
        }

        try {
            $raw = json_decode($old, true, flags: JSON_THROW_ON_ERROR);
            if (is_array($raw) && is_array($raw['blocks'] ?? null)) {
                // Browsers cannot restore an uploaded file after validation redirects; keep the text and
                // remove only its unsaved image placeholder so the editor can be submitted again.
                $raw['blocks'] = array_values(array_filter(
                    $raw['blocks'],
                    fn (mixed $block): bool => ! (is_array($block)
                        && ($block['type'] ?? null) === 'image'
                        && ($block['media_id'] ?? null) === 0),
                ));
            }

            return $documents->validate($raw);
        } catch (JsonException|ValidationException) {
            return null;
        }
    }

    /**
     * Upload or remove the article's single image and synchronize its block reference.
     *
     * @param  array{version: int, blocks: list<array<string, mixed>>}  $document
     * @param  array<string, mixed>  $input
     * @return array{0: array{version: int, blocks: list<array<string, mixed>>}, 1: bool, 2: ?string, 3: ?string}
     */
    private function syncArticleImage(Article $article, array $document, array $input): array
    {
        $media = $article->image()->lockForUpdate()->first();
        $oldPath = null;
        $newPath = null;
        $changed = false;
        $file = $input['image'] ?? null;
        $remove = (bool) ($input['remove_image'] ?? false);
        $imageIndexes = [];
        foreach ($document['blocks'] as $index => $block) {
            if ($block['type'] === 'image') {
                $imageIndexes[] = $index;
            }
        }

        if ($remove && ! $file) {
            if ($media) {
                $oldPath = $media->path;
                $media->delete();
                $changed = true;
            }
            $document['blocks'] = array_values(array_filter($document['blocks'], fn (array $block): bool => $block['type'] !== 'image'));

            return [$document, $changed, null, $oldPath];
        }

        if ($file) {
            $oldPath = $media?->path;
            $newPath = $file->store('articles/'.$article->getKey(), 'article_media');
            if (! is_string($newPath) || $newPath === '') {
                throw ValidationException::withMessages(['image' => 'The image could not be stored. Please try again.']);
            }

            $decorative = (bool) ($input['image_decorative'] ?? false);
            $altText = $decorative ? '' : trim((string) ($input['image_alt_text'] ?? ''));
            $attributes = [
                'path' => $newPath,
                'filename' => mb_substr(basename((string) $file->getClientOriginalName()), 0, 255),
                'mime_type' => (string) $file->getMimeType(),
                'alt_text' => $altText,
            ];

            try {
                if ($media) {
                    $media->update($attributes);
                } else {
                    $media = $article->image()->create($attributes);
                }

                if ($imageIndexes === []) {
                    $document['blocks'][] = [
                        'id' => (string) Str::uuid(),
                        'type' => 'image',
                        'media_id' => (int) $media->getKey(),
                        'caption' => '',
                    ];
                } else {
                    $document['blocks'][$imageIndexes[0]]['media_id'] = (int) $media->getKey();
                }
            } catch (Throwable $exception) {
                try {
                    Storage::disk('article_media')->delete($newPath);
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
                throw $exception;
            }
            $changed = true;

            return [$document, $changed, $newPath, $oldPath];
        }

        if ($media) {
            if ($imageIndexes === [] || (int) $document['blocks'][$imageIndexes[0]]['media_id'] !== (int) $media->getKey()) {
                throw ValidationException::withMessages(['document' => 'Use the image controls to remove or replace the current image.']);
            }

            if (array_key_exists('image_alt_text', $input)) {
                $altText = (bool) ($input['image_decorative'] ?? false) ? '' : trim((string) $input['image_alt_text']);
                if ($media->alt_text !== $altText) {
                    $media->alt_text = $altText;
                    $media->save();
                    $changed = true;
                }
            }
        } elseif ($imageIndexes !== []) {
            throw ValidationException::withMessages(['document' => 'The selected image does not belong to this article.']);
        }

        return [$document, $changed, $newPath, $oldPath];
    }

    /** @param array<string, mixed> $input
     * @return array{0: bool, 1: ?string, 2: ?string}
     */
    private function syncLegacyArticleImage(Article $article, array $input): array
    {
        $media = $article->image()->lockForUpdate()->first();
        $oldPath = null;
        $newPath = null;
        $changed = false;

        if ((bool) ($input['remove_image'] ?? false) && ! isset($input['image'])) {
            if ($media) {
                $oldPath = $media->path;
                $media->delete();
                $changed = true;
            }

            return [$changed, null, $oldPath];
        }

        if (isset($input['image'])) {
            $file = $input['image'];
            $oldPath = $media?->path;
            $newPath = $file->store('articles/'.$article->getKey(), 'article_media');
            if (! is_string($newPath) || $newPath === '') {
                throw ValidationException::withMessages(['image' => 'The image could not be stored. Please try again.']);
            }

            $attributes = [
                'path' => $newPath,
                'filename' => mb_substr(basename((string) $file->getClientOriginalName()), 0, 255),
                'mime_type' => (string) $file->getMimeType(),
                'alt_text' => (bool) ($input['image_decorative'] ?? false) ? '' : trim((string) ($input['image_alt_text'] ?? '')),
            ];
            try {
                $media ? $media->update($attributes) : $article->image()->create($attributes);
            } catch (Throwable $exception) {
                try {
                    Storage::disk('article_media')->delete($newPath);
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
                throw $exception;
            }
            $changed = true;
        } elseif ($media && array_key_exists('image_alt_text', $input)) {
            $altText = (bool) ($input['image_decorative'] ?? false) ? '' : trim((string) $input['image_alt_text']);
            if ($media->alt_text !== $altText) {
                $media->alt_text = $altText;
                $media->save();
                $changed = true;
            }
        }

        return [$changed, $newPath, $oldPath];
    }

    /** @param array{version: int, blocks: list<array<string, mixed>>} $document */
    private function validateImageReferences(Article $article, array $document, ArticleContentDocument $documents): void
    {
        foreach ($documents->validate($document)['blocks'] as $block) {
            if ($block['type'] === 'image'
                && ! $article->image()->whereKey($block['media_id'])->exists()) {
                throw ValidationException::withMessages(['document' => 'The image must belong to this article.']);
            }
        }
    }

    private function deleteUnreferencedMediaFile(?string $path): void
    {
        if ($path !== null && ! Media::query()->where('path', $path)->exists()) {
            Storage::disk('article_media')->delete($path);
        }
    }

    /**
     * Compare reader-visible document content while ignoring generated block and list-item IDs.
     * Legacy conversion creates fresh IDs on each edit, so including IDs would falsely mark an
     * unchanged article as modified the first time it is saved in the structured editor.
     *
     * @param  array{version: int, blocks: list<array<string, mixed>>}  $document
     */
    private function documentFingerprint(array $document): string
    {
        foreach ($document['blocks'] as &$block) {
            unset($block['id']);
            if (isset($block['items']) && is_array($block['items'])) {
                foreach ($block['items'] as &$item) {
                    unset($item['id']);
                }
                unset($item);
            }
        }
        unset($block);

        return json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** @param array<string, array{url?: ?string, anchor_text?: ?string}> $links */
    private function syncArticleLinks(Article $article, array $links): bool
    {
        $changed = false;

        foreach (['brand' => ['brand'], 'product' => ['product', 'service']] as $key => $types) {
            if (! array_key_exists($key, $links)) {
                continue;
            }

            $values = $links[$key] ?? [];
            $url = trim((string) ($values['url'] ?? ''));
            $anchorText = trim((string) ($values['anchor_text'] ?? ''));
            $existing = $article->articleLinks()->whereIn('type', $types)->first();

            if ($url === '' && $anchorText === '') {
                if ($existing) {
                    $existing->delete();
                    $changed = true;
                }

                continue;
            }

            $type = $key === 'brand' ? 'brand' : ($existing?->type === 'service' ? 'service' : 'product');
            if (! $existing) {
                $article->articleLinks()->create(['type' => $type, 'url' => $url, 'anchor_text' => $anchorText]);
                $changed = true;

                continue;
            }

            if ($existing->type !== $type || $existing->url !== $url || $existing->anchor_text !== $anchorText) {
                $existing->update(['type' => $type, 'url' => $url, 'anchor_text' => $anchorText]);
                $changed = true;
            }
        }

        return $changed;
    }

    private function articleForSuperblog(Superblog $superblog, Article $article, bool $lock = false): Article
    {
        $query = $superblog->articles()->with('rssItem.rssSource')->whereKey($article->getKey());

        if ($lock) {
            $query->lockForUpdate();
        }

        $article = $query->firstOrFail();

        if ($article->rss_item_id !== null) {
            abort_unless((int) $article->rssItem?->rssSource?->superblog_id === (int) $superblog->getKey(), 404);
        }

        return $article;
    }

    private function uniqueSlug(Superblog $superblog, RssItem $rssItem): string
    {
        $base = Str::slug($rssItem->title) ?: 'rss-item-'.$rssItem->getKey();
        $base = rtrim(mb_substr($base, 0, 240), '-') ?: 'rss-item-'.$rssItem->getKey();
        $slug = $base;
        $suffix = 2;

        while ($superblog->articles()->where('slug', $slug)->exists()) {
            $ending = '-'.$suffix++;
            $slug = rtrim(mb_substr($base, 0, 255 - strlen($ending)), '-').$ending;
        }

        return $slug;
    }
}
