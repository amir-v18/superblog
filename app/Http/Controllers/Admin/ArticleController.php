<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ArticleContentFormat;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveArticleRequest;
use App\Http\Requests\Admin\SaveArticleSelectionRequest;
use App\Models\Article;
use App\Models\ArticleLink;
use App\Models\RssItem;
use App\Models\RssSource;
use App\Models\Superblog;
use App\Support\ArticleContentStructure;
use App\Support\ArticleHtmlSanitizer;
use App\Support\SuperblogPublishingReadiness;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

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

    public function create(Superblog $superblog, ArticleHtmlSanitizer $htmlSanitizer): View
    {
        Gate::authorize('manageArticles', $superblog);

        $article = new Article([
            'status' => 'draft',
            'source_type' => 'manual',
            'is_selected_for_publication' => true,
        ]);

        return view('admin.articles.edit', [
            'superblog' => $superblog,
            'article' => $article,
            'isCreating' => true,
            'editorHtml' => $htmlSanitizer->sanitize((string) old('content', '')),
            'brandLink' => null,
            'productLink' => null,
        ]);
    }

    public function store(
        SaveArticleRequest $request,
        Superblog $superblog,
        ArticleHtmlSanitizer $htmlSanitizer,
        ArticleContentStructure $contentStructure,
    ): RedirectResponse {
        $validated = $request->validated();
        $validated['content'] = $htmlSanitizer->sanitize($validated['content']);
        $this->ensureSanitizedContentHasText($validated['content'], $htmlSanitizer);
        $this->ensureValidContentStructure($validated['content'], $contentStructure);
        $links = $validated['links'] ?? [];
        unset($validated['links']);

        try {
            $article = DB::transaction(function () use ($validated, $links, $superblog): Article {
                $lockedSuperblog = Superblog::query()->lockForUpdate()->findOrFail($superblog->getKey());

                $article = $lockedSuperblog->articles()->create([
                    ...$validated,
                    'rss_item_id' => null,
                    'content_format' => ArticleContentFormat::LegacyHtml,
                    'source_type' => 'manual',
                    'status' => 'draft',
                    'is_selected_for_publication' => true,
                    'published_at' => null,
                ]);

                $this->syncArticleLinks($article, $links);

                return $article;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['slug' => 'This slug is already used by another article in this Superblog.']);
        }

        return to_route('admin.superblogs.articles.edit', [$superblog, $article])
            ->with('status', 'Draft article created.');
    }

    public function updateSelection(
        SaveArticleSelectionRequest $request,
        Superblog $superblog,
        ArticleHtmlSanitizer $htmlSanitizer,
        ArticleContentStructure $contentStructure,
    ): RedirectResponse {
        $visibleIds = array_map('intval', $request->validated('visible_article_ids'));
        $selectedIds = array_map('intval', $request->validated('selected_article_ids', []));

        $isPublished = DB::transaction(function () use ($superblog, $visibleIds, $selectedIds, $htmlSanitizer, $contentStructure): bool {
            $lockedSuperblog = Superblog::query()->lockForUpdate()->findOrFail($superblog->getKey());

            if ($lockedSuperblog->status === 'published' && $selectedIds !== []) {
                $draftsToPublish = $lockedSuperblog->articles()
                    ->whereIn('id', $visibleIds)
                    ->whereIn('id', $selectedIds)
                    ->where('status', 'draft')
                    ->get();

                foreach ($draftsToPublish as $article) {
                    $this->ensureArticleCanBePublished($article, $htmlSanitizer, $contentStructure);
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
    ): RedirectResponse {
        Gate::authorize('manageArticles', $superblog);
        $canPublishSuperblog = Gate::allows('update', $superblog);
        $publishedAt = now();

        [$published, $withdrawn, $wasAlreadyPublished] = DB::transaction(function () use ($superblog, $publishedAt, $publishingReadiness, $canPublishSuperblog, $htmlSanitizer, $contentStructure): array {
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
                $this->ensureArticleCanBePublished($article, $htmlSanitizer, $contentStructure);
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

    public function edit(Superblog $superblog, Article $article, ArticleHtmlSanitizer $htmlSanitizer): View
    {
        Gate::authorize('manageArticles', $superblog);
        $article = $this->articleForSuperblog($superblog, $article)->load('articleLinks');

        $productLink = $article->articleLinks->first(fn (ArticleLink $link): bool => in_array($link->type, ['product', 'service'], true));

        return view('admin.articles.edit', [
            'superblog' => $superblog,
            'article' => $article,
            'isCreating' => false,
            'editorHtml' => $htmlSanitizer->sanitize((string) old('content', $article->content)),
            'brandLink' => old('links.brand') ? (object) old('links.brand') : $article->articleLinks->firstWhere('type', 'brand'),
            'productLink' => old('links.product') ? (object) old('links.product') : $productLink,
        ]);
    }

    public function destroy(Request $request, Superblog $superblog, Article $article): RedirectResponse|JsonResponse
    {
        Gate::authorize('manageArticles', $superblog);

        [$sectionKey, $currentPage] = DB::transaction(function () use ($request, $superblog, $article): array {
            $lockedSuperblog = Superblog::query()->lockForUpdate()->findOrFail($superblog->getKey());
            $lockedArticle = $this->articleForSuperblog($lockedSuperblog, $article, lock: true);
            $sectionKey = match ($lockedArticle->source_type) {
                'manual' => 'manual',
                'rss' => 'rss',
                default => abort(404),
            };

            // Foreign-key cascades remove this article's child rows, but leave its RSS source item and files alone.
            $lockedArticle->delete();

            $pageName = $sectionKey.'_page';
            $lastPage = max(1, (int) ceil($lockedSuperblog->articles()->where('source_type', $sectionKey)->count() / 5));
            $requestedPage = max(1, (int) $request->query($pageName, 1));

            return [$sectionKey, min($requestedPage, $lastPage)];
        });

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
    ): RedirectResponse {
        Gate::authorize('manageArticles', $superblog);

        DB::transaction(function () use ($superblog, $article, $htmlSanitizer, $contentStructure): void {
            $article = $this->articleForSuperblog($superblog, $article, lock: true);

            if ($article->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Only draft articles can be published.']);
            }

            $this->ensureArticleCanBePublished($article, $htmlSanitizer, $contentStructure);

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
        ArticleHtmlSanitizer $htmlSanitizer,
        ArticleContentStructure $contentStructure,
    ): RedirectResponse {
        $validated = $request->validated();
        $validated['content'] = $htmlSanitizer->sanitize($validated['content']);
        $this->ensureSanitizedContentHasText($validated['content'], $htmlSanitizer);
        $this->ensureValidContentStructure($validated['content'], $contentStructure);
        $links = $validated['links'] ?? null;
        unset($validated['links']);

        $article = DB::transaction(function () use ($validated, $links, $superblog, $article, $htmlSanitizer): Article {
            $article = $this->articleForSuperblog($superblog, $article, lock: true);
            abort_unless(in_array($article->status, ['draft', 'published'], true), 404);

            $wasPublished = $article->status === 'published';
            $previousContent = $htmlSanitizer->sanitize($article->content);
            $article->fill($validated);
            $linksChanged = $links !== null && $this->syncArticleLinks($article, $links);

            if ($wasPublished && ($article->isDirty('title') || $previousContent !== $validated['content'] || $linksChanged)) {
                $article->content_modified_at = now();
            }

            try {
                $article->save();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['slug' => 'This slug is already used by another article in this Superblog.']);
            }

            return $article;
        });

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
    ): void {
        if ($article->content_format !== ArticleContentFormat::LegacyHtml) {
            throw ValidationException::withMessages([
                'content' => 'Structured articles cannot be published until structured public rendering is enabled.',
            ]);
        }

        $safeContent = $htmlSanitizer->sanitize($article->content);
        $this->ensureSanitizedContentHasText($safeContent, $htmlSanitizer);
        $this->ensureValidContentStructure($safeContent, $contentStructure);
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
