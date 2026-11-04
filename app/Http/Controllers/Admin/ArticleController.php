<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveArticleRequest;
use App\Models\Article;
use App\Models\RssItem;
use App\Models\RssSource;
use App\Models\Superblog;
use Illuminate\Database\UniqueConstraintViolationException;
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

    public function superblogIndex(Superblog $superblog): View
    {
        Gate::authorize('view', $superblog);

        $articles = $superblog->articles()
            ->with('superblog')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.articles.index', compact('articles', 'superblog'));
    }

    public function createFromRssItem(Superblog $superblog, RssSource $rssSource, RssItem $rssItem): RedirectResponse
    {
        Gate::authorize('update', $superblog);

        try {
            [$article, $wasExisting] = DB::transaction(function () use ($superblog, $rssSource, $rssItem): array {
                // Lock the blog to serialize slug allocation; the unique RSS-item index remains the final race guard.
                $lockedSuperblog = Superblog::query()->lockForUpdate()->findOrFail($superblog->getKey());
                $lockedSource = $lockedSuperblog->rssSources()->whereKey($rssSource->getKey())->firstOrFail();
                $lockedItem = $lockedSource->rssItems()->whereKey($rssItem->getKey())->lockForUpdate()->firstOrFail();

                $existing = $lockedItem->article()->first();

                if ($existing) {
                    abort_unless((int) $existing->superblog_id === (int) $lockedSuperblog->getKey(), 404);

                    return [$existing, true];
                }

                $sourceContent = $lockedItem->content ?: $lockedItem->summary ?: '';

                $article = $lockedSuperblog->articles()->create([
                    'rss_item_id' => $lockedItem->getKey(),
                    'title' => $lockedItem->title,
                    'slug' => $this->uniqueSlug($lockedSuperblog, $lockedItem),
                    'content' => $sourceContent,
                    'excerpt' => $lockedItem->summary,
                    'source_type' => 'rss',
                    'status' => 'draft',
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

    public function edit(Superblog $superblog, Article $article): View
    {
        Gate::authorize('update', $superblog);
        $article = $this->articleForSuperblog($superblog, $article);

        return view('admin.articles.edit', compact('superblog', 'article'));
    }

    public function publish(Superblog $superblog, Article $article): RedirectResponse
    {
        Gate::authorize('update', $superblog);

        DB::transaction(function () use ($superblog, $article): void {
            $article = $this->articleForSuperblog($superblog, $article, lock: true);

            if ($article->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Only draft articles can be published.']);
            }

            $article->update([
                'status' => 'published',
                'published_at' => now(),
            ]);
        });

        return to_route('admin.superblogs.articles.edit', [$superblog, $article])
            ->with('status', 'Article published.');
    }

    public function update(SaveArticleRequest $request, Superblog $superblog, Article $article): RedirectResponse
    {
        $article = DB::transaction(function () use ($request, $superblog, $article): Article {
            $article = $this->articleForSuperblog($superblog, $article, lock: true);
            abort_unless(in_array($article->status, ['draft', 'published'], true), 404);

            try {
                $article->update($request->validated());
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['slug' => 'This slug is already used by another article in this Superblog.']);
            }

            return $article;
        });

        return to_route('admin.superblogs.articles.edit', [$superblog, $article])->with('status', 'Article saved.');
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
