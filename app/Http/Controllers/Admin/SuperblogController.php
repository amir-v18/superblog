<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveSuperblogRequest;
use App\Models\Article;
use App\Models\Superblog;
use App\Models\Template;
use App\Support\SuperblogPublishingReadiness;
use App\Support\Theme;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SuperblogController extends Controller
{
    public function create(): View
    {
        Gate::authorize('create', Superblog::class);

        return view('admin.superblogs.form', [
            'superblog' => new Superblog,
            'templates' => $this->availableTemplates(),
        ]);
    }

    public function store(SaveSuperblogRequest $request): RedirectResponse
    {
        try {
            $superblog = DB::transaction(function () use ($request) {
                // A new magazine starts in its template's own theme from config/templates.php, so a newspaper
                // template opens looking like a newspaper. A template missing there gets the general default.
                $theme = Theme::forTemplate(Template::find($request->validated('template_id'))) ?? Theme::DEFAULT;

                $superblog = $request->user()->superblogs()->create([
                    ...$request->validated(),
                    'settings' => ['theme' => ['name' => $theme]],
                ]);
                // Ownership alone does not grant access: the policy uses staff assignments.
                $superblog->staff()->attach($request->user()->getKey());

                return $superblog;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $this->duplicateError();
        }

        return to_route('admin.superblogs.edit', $superblog)->with('status', 'Superblog created.');
    }

    public function edit(Superblog $superblog, SuperblogPublishingReadiness $publishingReadiness): View
    {
        Gate::authorize('view', $superblog);
        Gate::authorize('update', $superblog);

        $manualArticles = null;
        $rssArticles = null;

        if (request()->query('flik') === 'innehall') {
            $manualArticles = $superblog->articles()
                ->where('source_type', 'manual')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->paginate(5, ['*'], 'manual_page')
                ->withQueryString();
            $rssArticles = $superblog->articles()
                ->where('source_type', 'rss')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->paginate(5, ['*'], 'rss_page')
                ->withQueryString();

            $paginationSection = request()->header('X-Pagination-Section');
            if (request()->ajax() && in_array($paginationSection, ['manual', 'rss'], true)) {
                $articles = $paginationSection === 'manual' ? $manualArticles : $rssArticles;
                $group = [
                    'key' => $paginationSection,
                    'label' => $paginationSection === 'manual' ? 'Manuella artiklar' : 'RSS-artiklar',
                    'articles' => $articles,
                ];

                return view('admin.superblogs._article-section', compact('group', 'superblog'));
            }
        }
        $publicationSummary = null;
        $publicationErrors = [];

        if (request()->query('flik') === 'publicera') {
            $articles = Article::query()->where('superblog_id', $superblog->getKey());
            $publicationSummary = [
                'selected' => (clone $articles)->where('is_selected_for_publication', true)->count(),
                'to_publish' => (clone $articles)->where('is_selected_for_publication', true)->where('status', 'draft')->count(),
                'to_withdraw' => (clone $articles)->where('is_selected_for_publication', false)->where('status', 'published')->count(),
            ];
            $publicationErrors = $publishingReadiness->errorsFor($superblog);
        }

        return view('admin.superblogs.edit', [
            'superblog' => $superblog,
            'templates' => $this->availableTemplates($superblog),
            // withCount adds rss_items_count with one query, instead of one count per feed in the view.
            'rssSources' => $superblog->rssSources()->withCount('rssItems')->orderBy('name')->get(),
            // Same rule as the dashboard: only published articles count, drafts do not.
            'publishedArticlesCount' => $superblog->articles()->where('status', 'published')->count(),
            'manualArticles' => $manualArticles,
            'rssArticles' => $rssArticles,
            'publicationSummary' => $publicationSummary,
            'publicationErrors' => $publicationErrors,
        ]);
    }

    public function show(Superblog $superblog): RedirectResponse
    {
        Gate::authorize('view', $superblog);

        return to_route('admin.superblogs.edit', $superblog);
    }

    public function update(SaveSuperblogRequest $request, Superblog $superblog): RedirectResponse
    {
        try {
            $superblog->update($request->validated());
        } catch (UniqueConstraintViolationException $exception) {
            $this->duplicateError();
        }

        return to_route('admin.superblogs.edit', $superblog)->with('status', 'Superblog updated.');
    }

    public function unpublish(Superblog $superblog): RedirectResponse
    {
        Gate::authorize('update', $superblog);

        $wasPublished = DB::transaction(function () use ($superblog): bool {
            $lockedSuperblog = Superblog::query()->lockForUpdate()->findOrFail($superblog->getKey());
            $wasPublished = $lockedSuperblog->status === 'published';

            if ($wasPublished) {
                // Taking a site offline must not alter the publication history of its articles.
                $lockedSuperblog->update(['status' => 'draft']);
            }

            return $wasPublished;
        });

        $message = $wasPublished
            ? 'Superblocket är avpublicerat. Artiklar och publiceringsdatum är oförändrade.'
            : 'Superblocket var redan avpublicerat.';

        return to_route('admin.superblogs.edit', [$superblog, 'flik' => 'publicera'])->with('status', $message);
    }

    public function confirmDeletion(Superblog $superblog): View
    {
        Gate::authorize('view', $superblog);
        Gate::authorize('delete', $superblog);

        return view('admin.superblogs.delete', compact('superblog'));
    }

    public function destroy(Superblog $superblog): RedirectResponse
    {
        Gate::authorize('delete', $superblog);

        DB::transaction(function () use ($superblog): void {
            $lockedSuperblog = Superblog::query()->lockForUpdate()->findOrFail($superblog->getKey());

            $rssItemsAreUsedElsewhere = Article::query()
                ->where('superblog_id', '!=', $lockedSuperblog->getKey())
                ->whereHas('rssItem.rssSource', fn ($query) => $query->where('superblog_id', $lockedSuperblog->getKey()))
                ->exists();

            if ($rssItemsAreUsedElsewhere) {
                throw ValidationException::withMessages([
                    'superblog' => 'This Superblog has RSS items linked to articles in another Superblog. Repair those links before deleting it.',
                ]);
            }

            // Remove articles before their RSS sources so a source cascade cannot reach an article in another Superblog.
            $lockedSuperblog->articles()->delete();
            $lockedSuperblog->rssSources()->delete();
            $lockedSuperblog->delete();
        });

        return to_route('admin.dashboard')->with('status', 'Superblog deleted.');
    }

    private function duplicateError(): never
    {
        // Unique database constraints also protect against concurrent form submissions.
        throw ValidationException::withMessages(['slug' => 'The slug or domain is already in use. Please choose another.']);
    }

    private function availableTemplates(?Superblog $superblog = null)
    {
        return Template::query()
            ->where(function ($query) use ($superblog): void {
                $query->where('is_active', true);

                if ($superblog?->exists) {
                    $query->orWhereKey($superblog->template_id);
                }
            })
            ->orderBy('name')
            ->get();
    }
}
