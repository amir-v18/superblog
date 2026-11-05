<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Superblog;
use App\Support\Theme;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class SuperblogPreviewController extends Controller
{
    /**
     * The builder's live preview, shown in an iframe. Same access as the builder itself.
     */
    public function __invoke(Superblog $superblog): Response
    {
        Gate::authorize('view', $superblog);
        Gate::authorize('update', $superblog);

        // Without a saved theme the page would have no colors or fonts, so the first theme is shown until one is chosen.
        $theme = $superblog->settings['theme'] ?? null;
        if (Theme::name($theme) === null) {
            $theme = Theme::NAMES[0];
        }

        // The template's site views come from config/templates.php. A template missing there (an old or
        // hand-made one) gets the default template's site, so every magazine still has a preview.
        $templates = config('templates.list', []);
        $site = $templates[(string) $superblog->template?->slug]['site']
            ?? $templates[config('templates.default')]['site'];

        // The header also covers non-HTML crawlers; the meta tag in the template is a second line of defence.
        return response()
            ->view("{$site}.home", ['superblog' => $superblog, 'theme' => $theme])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
