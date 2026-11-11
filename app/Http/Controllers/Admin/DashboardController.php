<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Superblog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('viewAny', Superblog::class);

        $isAdmin = $request->user()->is_super_admin === true;
        $dashboard = ['isAdmin' => $isAdmin];

        if ($isAdmin) {
            // Admins retain separate lists and independent pagination for live and draft sites.
            $dashboard += [
                'published' => $this->superblogs($request)->where('status', 'published')
                    ->paginate(20, pageName: 'publicerade')->withQueryString(),
                // Everything not live remains visible in the admin's under-construction list.
                'drafts' => $this->superblogs($request)->where('status', '!=', 'published')
                    ->paginate(20, pageName: 'utkast')->withQueryString(),
            ];
        } else {
            // Editors use one list so assigned sites are not split by publication state.
            $dashboard['superblogs'] = $this->superblogs($request)->paginate(20)->withQueryString();
        }

        return view('admin.dashboard', $dashboard);
    }

    public function published(Request $request): View
    {
        Gate::authorize('viewAny', Superblog::class);

        return view('admin.superblogs.published', [
            'superblogs' => $this->superblogs($request)
                ->where('status', 'published')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    private function superblogs(Request $request)
    {
        // withCount/withMax add subqueries to the same query and with() loads all templates at once, avoiding one query per card.
        return Superblog::accessibleTo($request->user())
            ->with('template')
            ->withCount([
                // Only feeds that are switched on are counted.
                'rssSources' => fn ($query) => $query->where('is_active', true),
                'articles as published_articles_count' => fn ($query) => $query->where('status', 'published'),
            ])
            // Across all feeds: a fetch that happened is still true after a feed is switched off.
            ->withMax('rssSources', 'last_fetched_at')
            ->orderBy('name')
            ->orderBy('id');
    }
}
