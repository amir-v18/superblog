<?php

namespace App\Providers;

use App\Models\Superblog;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('access-admin', fn (User $user): bool => $user->canAccessAdmin());
        Gate::define('manage-staff', fn (User $user): bool => $user->is_super_admin === true && $user->approval_status === 'approved');

        // The sidebar lists the user's magazines on every admin page, so the layout fetches them itself.
        View::composer('layouts.admin', function ($view): void {
            $user = auth()->user();

            // Guests and unapproved accounts (login, pending, revoked) get an empty menu without a query.
            $superblogs = $user?->canAccessAdmin()
                ? Superblog::accessibleTo($user)->orderBy('name')->orderBy('id')->get(['id', 'name', 'status', 'settings'])
                : collect();

            // Same rule as the dashboard: everything that is not published counts as under construction.
            [$published, $drafts] = $superblogs->partition(fn (Superblog $superblog): bool => $superblog->status === 'published');

            $view->with([
                'sidebarPublished' => $published,
                'sidebarDrafts' => $drafts,
            ]);
        });
    }
}
