<?php

namespace App\Http\Controllers;

use App\Enums\ArticleContentFormat;
use App\Models\Article;
use App\Models\Superblog;
use App\Support\ArticleContentDocument;
use App\Support\ArticleContentHtmlRenderer;
use App\Support\ArticleHtmlSanitizer;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicArticleController extends Controller
{
    public function show(
        Superblog $superblog,
        Article $article,
        ArticleHtmlSanitizer $htmlSanitizer,
        ArticleContentDocument $documents,
        ArticleContentHtmlRenderer $renderer,
    ): View {
        abort_unless($superblog->status === 'published', 404);

        $article = $superblog->articles()
            ->whereKey($article->getKey())
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('is_selected_for_publication', true)
            ->with(['articleLinks', 'rssItem', 'image'])
            ->firstOrFail();

        $articleViewData = $this->articleViewData($article, $htmlSanitizer, $documents, $renderer);

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

    private function articleViewData(
        Article $article,
        ArticleHtmlSanitizer $htmlSanitizer,
        ArticleContentDocument $documents,
        ArticleContentHtmlRenderer $renderer,
    ): array {
        $links = [];

        foreach ($article->articleLinks as $link) {
            if ($link->type !== 'brand'
                || ! $this->isHttpUrl($link->url)
                || isset($links['brand'])) {
                continue;
            }

            $links['brand'] = [
                'url' => $link->url,
                'anchor_text' => $link->anchor_text,
            ];
        }

        $sources = [];
        $rssItem = $article->rssItem;

        if ($rssItem && $this->isHttpUrl($rssItem->url)) {
            $sources[] = ['name' => $rssItem->title, 'url' => $rssItem->url];
        }

        $imageUrl = $article->image
            ? route('public.superblogs.articles.image', [$article->superblog, $article])
            : null;
        $image = $article->image ? [
            'src' => $imageUrl,
            'alt' => (string) $article->image->alt_text,
        ] : null;

        if ($article->content_format === ArticleContentFormat::BlocksV1) {
            try {
                $document = $documents->readFrom($article);
            } catch (ValidationException) {
                abort(404);
            }

            foreach ($document['blocks'] as $block) {
                if ($block['type'] === 'image'
                    && (! $article->image || (int) $block['media_id'] !== (int) $article->image->getKey())) {
                    abort(404);
                }
            }

            $publicDocument = $article->is_locked
                ? ['version' => $document['version'], 'blocks' => [$document['blocks'][0]]]
                : $document;
            $bodyHtml = $renderer->render($publicDocument, $article->image, $imageUrl, $article->articleLinks);
            $descriptionText = $renderer->introductionText($document);
            $imageInBody = ! $article->is_locked && collect($document['blocks'])->contains(fn (array $block): bool => $block['type'] === 'image');
        } else {
            // Sanitize legacy records again at the public boundary before the view renders HTML.
            $safeContent = $htmlSanitizer->sanitize($article->content);
            $bodyHtml = $article->is_locked ? $htmlSanitizer->firstParagraphHtml($safeContent) : $safeContent;
            $bodyHtml = $renderer->qualifySponsoredLinks($bodyHtml, $article->articleLinks);
            $descriptionText = $htmlSanitizer->firstParagraphText($safeContent);
            $imageInBody = false;
        }

        return [
            'title' => $article->title,
            'slug' => $article->slug,
            'description' => Str::limit($descriptionText, 160, '…', preserveWords: true),
            'author' => $rssItem?->author,
            'created_at' => $article->created_at,
            'published_at' => $article->published_at,
            'content_modified_at' => $article->content_modified_at,
            'source_type' => $article->source_type,
            // The current workflow does not record AI processing, so RSS provenance alone is not proof of AI use.
            'is_ai_processed' => false,
            'is_locked' => $article->is_locked,
            'image' => $image,
            'image_in_body' => $imageInBody,
            'body_html' => $bodyHtml,
            'links' => $links,
            'sources' => $sources,
        ];
    }

    private function isHttpUrl(string $url): bool
    {
        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
