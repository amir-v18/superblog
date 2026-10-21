<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveRssSourceRequest;
use App\Models\RssItem;
use App\Models\RssSource;
use App\Models\Superblog;
use App\Services\RssFeedFetcher;
use App\Support\RssFeedFetchException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RssSourceController extends Controller
{
    public function index(Superblog $superblog): View
    {
        Gate::authorize('view', $superblog);

        return view('admin.rss-sources.index', [
            'superblog' => $superblog,
            'rssSources' => $superblog->rssSources()->orderBy('name')->paginate(20),
        ]);
    }

    public function create(Superblog $superblog): View
    {
        Gate::authorize('update', $superblog);

        return view('admin.rss-sources.form', [
            'superblog' => $superblog,
            'rssSource' => new RssSource(['is_active' => true]),
        ]);
    }

    public function store(SaveRssSourceRequest $request, Superblog $superblog): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($superblog, $validated): void {
            $lockedSuperblog = Superblog::whereKey($superblog->getKey())->lockForUpdate()->firstOrFail();
            $this->ensureUrlIsUnique($lockedSuperblog, $validated['url']);
            $lockedSuperblog->rssSources()->create($validated);
        });

        return to_route('admin.superblogs.rss-sources.index', $superblog)
            ->with('status', 'RSS source created.');
    }

    public function edit(Superblog $superblog, RssSource $rssSource): View
    {
        Gate::authorize('update', $superblog);
        $rssSource = $this->sourceForSuperblog($superblog, $rssSource);

        return view('admin.rss-sources.form', compact('superblog', 'rssSource'));
    }

    public function update(SaveRssSourceRequest $request, Superblog $superblog, RssSource $rssSource): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($superblog, $rssSource, $validated): void {
            $lockedSuperblog = Superblog::whereKey($superblog->getKey())->lockForUpdate()->firstOrFail();
            $lockedSource = $this->sourceForSuperblog($lockedSuperblog, $rssSource);
            $this->ensureUrlIsUnique($lockedSuperblog, $validated['url'], $lockedSource->getKey());
            $lockedSource->update($validated);
        });

        return to_route('admin.superblogs.rss-sources.index', $superblog)
            ->with('status', 'RSS source updated.');
    }

    public function confirmDeletion(Superblog $superblog, RssSource $rssSource): View
    {
        Gate::authorize('update', $superblog);
        $rssSource = $this->sourceForSuperblog($superblog, $rssSource);

        return view('admin.rss-sources.delete', compact('superblog', 'rssSource'));
    }

    public function destroy(Superblog $superblog, RssSource $rssSource): RedirectResponse
    {
        Gate::authorize('update', $superblog);
        $this->sourceForSuperblog($superblog, $rssSource)->delete();

        return to_route('admin.superblogs.rss-sources.index', $superblog)
            ->with('status', 'RSS source deleted.');
    }

    public function fetch(Superblog $superblog, RssSource $rssSource, RssFeedFetcher $fetcher): RedirectResponse
    {
        Gate::authorize('update', $superblog);
        $rssSource = $this->sourceForSuperblog($superblog, $rssSource);

        try {
            $imported = $fetcher->fetch($rssSource);
        } catch (RssFeedFetchException $exception) {
            return to_route('admin.superblogs.rss-sources.index', $superblog)
                ->with('error', $exception->getMessage());
        }

        return to_route('admin.superblogs.rss-sources.index', $superblog)
            ->with('status', "Fetch completed. {$imported} new ".str('item')->plural($imported).' imported.');
    }

    public function items(Superblog $superblog, RssSource $rssSource): View
    {
        Gate::authorize('view', $superblog);
        $rssSource = $this->sourceForSuperblog($superblog, $rssSource);

        return view('admin.rss-sources.items.index', [
            'superblog' => $superblog,
            'rssSource' => $rssSource,
            'rssItems' => $rssSource->rssItems()->latest('published_at')->latest('id')->paginate(20),
        ]);
    }

    public function confirmItemDeletion(Superblog $superblog, RssSource $rssSource, RssItem $rssItem): View
    {
        Gate::authorize('update', $superblog);
        $rssSource = $this->sourceForSuperblog($superblog, $rssSource);
        $rssItem = $this->itemForSource($rssSource, $rssItem);

        return view('admin.rss-sources.items.delete', compact('superblog', 'rssSource', 'rssItem'));
    }

    public function destroyItem(Superblog $superblog, RssSource $rssSource, RssItem $rssItem): RedirectResponse
    {
        Gate::authorize('update', $superblog);
        $rssSource = $this->sourceForSuperblog($superblog, $rssSource);
        $this->itemForSource($rssSource, $rssItem)->delete();

        return to_route('admin.superblogs.rss-sources.items.index', [$superblog, $rssSource])
            ->with('status', 'RSS item deleted. Any related article remains, with its source reference cleared.');
    }

    public function confirmClearItems(Superblog $superblog, RssSource $rssSource): View
    {
        Gate::authorize('update', $superblog);
        $rssSource = $this->sourceForSuperblog($superblog, $rssSource);

        return view('admin.rss-sources.items.clear', compact('superblog', 'rssSource'));
    }

    public function clearItems(Superblog $superblog, RssSource $rssSource): RedirectResponse
    {
        Gate::authorize('update', $superblog);
        $rssSource = $this->sourceForSuperblog($superblog, $rssSource);
        $deleted = $rssSource->rssItems()->delete();

        return to_route('admin.superblogs.rss-sources.items.index', [$superblog, $rssSource])
            ->with('status', "Deleted {$deleted} RSS ".str('item')->plural($deleted).'. Related articles remain.');
    }

    private function sourceForSuperblog(Superblog $superblog, RssSource $rssSource): RssSource
    {
        return $superblog->rssSources()->whereKey($rssSource->getKey())->firstOrFail();
    }

    private function itemForSource(RssSource $rssSource, RssItem $rssItem): RssItem
    {
        return $rssSource->rssItems()->whereKey($rssItem->getKey())->firstOrFail();
    }

    private function ensureUrlIsUnique(Superblog $superblog, string $url, ?int $ignoreSourceId = null): void
    {
        $query = $superblog->rssSources()->where('url', $url);

        if ($ignoreSourceId !== null) {
            $query->whereKeyNot($ignoreSourceId);
        }

        // The schema stores URLs as TEXT, so serialize writes per Superblog instead of indexing that column.
        if ($query->exists()) {
            throw ValidationException::withMessages(['url' => 'This feed URL is already added to this Superblog.']);
        }
    }
}
