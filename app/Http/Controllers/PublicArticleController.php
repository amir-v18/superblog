<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Superblog;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicArticleController extends Controller
{
    public function show(Superblog $superblog, Article $article): View
    {
        abort_unless($superblog->status === 'published', 404);

        $article = $superblog->articles()
            ->whereKey($article->getKey())
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('is_selected_for_publication', true)
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

        $body = $this->contentBlocks($article->content);

        return [
            'title' => $article->title,
            'slug' => $article->slug,
            'description' => $this->metaDescription($body),
            'author' => $rssItem?->author,
            'published_at' => $article->published_at,
            'source_type' => $article->source_type,
            // The current workflow does not record AI processing, so RSS provenance alone is not proof of AI use.
            'is_ai_processed' => false,
            'is_locked' => $article->is_locked,
            'image' => null,
            'body' => $body,
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

    /**
     * Built from the first paragraph only: a locked article page also shows only its first paragraph,
     * so the description can never reveal locked text. The paragraphs are already free of HTML and
     * have their entities decoded. Always returns a string, never null.
     */
    private function metaDescription(array $body): string
    {
        $firstParagraph = collect($body)->firstWhere('type', 'p')['text'] ?? '';
        $text = trim(preg_replace('/\s+/u', ' ', $firstParagraph) ?? '');

        return Str::limit($text, 160, '…', preserveWords: true);
    }

    private function isHttpUrl(string $url): bool
    {
        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
