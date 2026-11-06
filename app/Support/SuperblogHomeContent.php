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
                ->limit(7)
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

    private function themeFor(Superblog $superblog): string|array
    {
        $theme = $superblog->settings['theme'] ?? null;

        return Theme::name($theme) === null ? Theme::NAMES[0] : $theme;
    }
}
