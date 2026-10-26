<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Superblog;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('viewAny', Superblog::class);

        // Each group paginates on its own page parameter so one list can be browsed without resetting the other.
        return view('admin.dashboard', [
            'published' => $this->superblogs($request)->where('status', 'published')
                ->paginate(20, pageName: 'publicerade')->withQueryString(),
            // Everything that is not published counts as under construction, so an unexpected status never hides a Superblog.
            'drafts' => $this->superblogs($request)->where('status', '!=', 'published')
                ->paginate(20, pageName: 'utkast')->withQueryString(),
            // Only needed by the create modal, so users who cannot create Superblogs skip the query.
            'templates' => Gate::allows('create', Superblog::class)
                ? Template::where('is_active', true)->orderBy('name')->get()
                : collect(),
        ]);
    }

    private function superblogs(Request $request)
    {
        // withCount adds the counts as subqueries in the same query, avoiding one query per card.
        return Superblog::accessibleTo($request->user())
            ->withCount([
                'rssSources',
                'articles as published_articles_count' => fn ($query) => $query->where('status', 'published'),
            ])
            ->orderBy('name')
            ->orderBy('id');
    }
}
