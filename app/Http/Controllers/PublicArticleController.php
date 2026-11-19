<?php

namespace App\Http\Controllers;

use App\Enums\ArticleContentFormat;
use App\Models\Article;
use App\Models\Superblog;
use App\Support\ArticleHtmlSanitizer;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicArticleController extends Controller
{
    public function show(Superblog $superblog, Article $article, ArticleHtmlSanitizer $htmlSanitizer): View
    {
        abort_unless($superblog->status === 'published', 404);

        $article = $superblog->articles()
            ->whereKey($article->getKey())
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('is_selected_for_publication', true)
            ->with(['articleLinks', 'rssItem'])
            ->firstOrFail();

        // The structured-document renderer is a later phase; never expose its JSON as article body text.
        abort_unless($article->content_format === ArticleContentFormat::LegacyHtml, 404);

        $articleViewData = $this->articleViewData($article, $htmlSanitizer);

        $template = $superblog->template;
        $templates = config('templates.list', []);
        $articleView = $templates[(string) $template?->seed_key]['article_view']
            ?? $templates[config('templates.default')]['article_view']
            ?? 'templates.news.article';

        return view($articleView, [
            'article' => $articleViewData,
            'theme' => $superblog->settings['theme'] ?? null,
            'layout' => $template?->layout ?? 'layouts.public',
        ]);
    }

    private function articleViewData(Article $article, ArticleHtmlSanitizer $htmlSanitizer): array
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

        // Sanitize legacy records again at the public boundary before the view renders HTML.
        $safeContent = $htmlSanitizer->sanitize($article->content);

        return [
            'title' => $article->title,
            'slug' => $article->slug,
            'description' => $this->metaDescription($safeContent, $htmlSanitizer),
            'author' => $rssItem?->author,
            'created_at' => $article->created_at,
            'published_at' => $article->published_at,
            'content_modified_at' => $article->content_modified_at,
            'source_type' => $article->source_type,
            // The current workflow does not record AI processing, so RSS provenance alone is not proof of AI use.
            'is_ai_processed' => false,
            'is_locked' => $article->is_locked,
            'image' => null,
            'body_html' => $article->is_locked
                ? $htmlSanitizer->firstParagraphHtml($safeContent)
                : $safeContent,
            'links' => $links,
            'sources' => $sources,
        ];
    }

    private function metaDescription(string $safeContent, ArticleHtmlSanitizer $htmlSanitizer): string
    {
        $text = $htmlSanitizer->firstParagraphText($safeContent);

        return Str::limit($text, 160, '…', preserveWords: true);
    }

    private function isHttpUrl(string $url): bool
    {
        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
