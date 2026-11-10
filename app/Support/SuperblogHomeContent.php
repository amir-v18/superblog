<?php

namespace App\Support;

use App\Models\Superblog;

class SuperblogHomeContent
{
    public function for(Superblog $superblog): array
    {
        $articles = $superblog->articles()
            ->where('is_selected_for_publication', true);

        // A published preview must match the public homepage; offline previews may still show selected drafts.
        if ($superblog->status === 'published') {
            $articles->where('status', 'published')
                ->whereNotNull('published_at');
        }

        return [
            'theme' => $this->themeFor($superblog),
            'articles' => $articles
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->get(),
            'manualArticles' => $superblog->articles()
                ->where('source_type', 'manual')
                ->where('status', 'published')
                ->where('is_selected_for_publication', true)
                ->whereNotNull('published_at')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->limit(5)
                ->get(),
        ];
    }

    /**
     * The folder of the template's site views, from config/templates.php. A template missing there (an old or
     * hand-made one) gets the default template's site, so every magazine can still be rendered.
     */
    public function siteFor(Superblog $superblog): string
    {
        // Looked up as array keys, not with dot notation, so the slug is never parsed as a config path.
        $templates = config('templates.list', []);

        return $templates[(string) $superblog->template?->slug]['site']
            ?? $templates[config('templates.default')]['site'];
    }

    private function themeFor(Superblog $superblog): string|array
    {
        $theme = $superblog->settings['theme'] ?? null;

        if (Theme::name($theme) !== null) {
            return $theme;
        }

        // Without a theme the page would have no colors or fonts. Until one is saved, the template's own theme
        // from the registry is used, and if it has none, the first theme.
        return Theme::forTemplate($superblog->template) ?? Theme::NAMES[0];
    }
}
