<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Superblog;
use Illuminate\View\View;

class PublicArticleController extends Controller
{
    public function show(Superblog $superblog, Article $article): View
    {
        $article = $superblog->articles()
            ->whereKey($article->getKey())
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->with(['articleLinks', 'rssItem'])
            ->firstOrFail();

        return view('templates.news.article', [
            'article' => $this->articleViewData($article),
            'theme' => $superblog->settings['theme'] ?? null,
            'layout' => $superblog->template?->layout ?? 'layouts.public',
        ]);
    }

    private function articleViewData(Article $article): array
    {
        $links = [];

        foreach ($article->articleLinks as $link) {
            $type = $link->type === 'service' ? 'product' : $link->type;

            if (! in_array($type, ['brand', 'product'], true)
                || ! $this->isHttpUrl($link->url)
                || isset($links[$type])) {
                continue;
            }

            $links[$type] = [
                'url' => $link->url,
                'anchor_text' => $link->anchor_text,
            ];
        }

        $sources = [];
        $rssItem = $article->rssItem;

        if ($rssItem && $this->isHttpUrl($rssItem->url)) {
            $sources[] = ['name' => $rssItem->title, 'url' => $rssItem->url];
        }

        return [
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'author' => $rssItem?->author,
            'published_at' => $article->published_at,
            'source_type' => $article->source_type,
            // The current workflow does not record AI processing, so RSS provenance alone is not proof of AI use.
            'is_ai_processed' => false,
            'is_locked' => $article->is_locked,
            'image' => null,
            'body' => $this->contentBlocks($article->content),
            'links' => $links,
            'sources' => $sources,
        ];
    }

    private function contentBlocks(string $content): array
    {
        $content = preg_replace('#<\s*(script|style)\b[^>]*>.*?</\s*\1\s*>#is', '', $content) ?? $content;
        $content = preg_replace('#<\s*br\s*/?\s*>|<\s*/\s*(?:p|div|h[1-6]|li)\s*>#i', "\n\n", $content) ?? $content;
        $plainText = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $paragraphs = preg_split('/\R{2,}/u', trim($plainText), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_map(fn (string $text): array => ['type' => 'p', 'text' => $text], $paragraphs);
    }

    private function isHttpUrl(string $url): bool
    {
        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
