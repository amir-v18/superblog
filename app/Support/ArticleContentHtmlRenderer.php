<?php

namespace App\Support;

use App\Models\ArticleLink;
use App\Models\Media;

/** Builds semantic HTML from a validated document; text is escaped and never treated as browser HTML. */
class ArticleContentHtmlRenderer
{
    /** Qualify legacy HTML links that match saved brand/product records. Input must already be sanitized. */
    public function qualifySponsoredLinks(string $html, iterable $articleLinks): string
    {
        $sponsoredUrls = $this->articleLinkUrls($articleLinks)['sponsored'];
        if ($sponsoredUrls === []) {
            return $html;
        }

        return preg_replace_callback('/<a\b([^>]*)>/i', function (array $matches) use ($sponsoredUrls): string {
            if (! preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/i', $matches[1], $hrefMatch)) {
                return $matches[0];
            }

            $href = html_entity_decode($hrefMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (! in_array($href, $sponsoredUrls, true)) {
                return $matches[0];
            }

            $attributes = preg_replace('/\s+rel\s*=\s*(["\']).*?\1/i', '', $matches[1]) ?? $matches[1];

            return '<a'.$attributes.' rel="sponsored">';
        }, $html) ?? $html;
    }

    /** @param array{version: int, blocks: list<array<string, mixed>>} $document */
    public function render(array $document, ?Media $media = null, ?string $imageUrl = null, iterable $articleLinks = []): string
    {
        $links = $this->articleLinkUrls($articleLinks);

        return implode('', array_map(
            fn (array $block): string => $this->renderBlock($block, $media, $imageUrl, $links),
            $document['blocks'],
        ));
    }

    /** @param array{version: int, blocks: list<array<string, mixed>>} $document */
    public function renderEditor(array $document, ?Media $media, ?string $imageUrl, iterable $articleLinks = []): string
    {
        $links = $this->articleLinkUrls($articleLinks);
        $html = '';

        foreach ($document['blocks'] as $block) {
            $id = $this->escape((string) $block['id']);
            $type = $this->escape((string) $block['type']);
            $html .= '<div class="article-editor-block group" data-editor-block data-block-id="'.$id.'" data-block-type="'.$type.'"';

            if ($block['type'] === 'heading') {
                $html .= ' data-level="'.(int) $block['level'].'"';
            }
            if ($block['type'] === 'image') {
                $html .= ' data-media-id="'.(int) $block['media_id'].'"';
            }

            $html .= '>';
            if ($block['type'] !== 'image') {
                $html .= '<div class="article-editor-block-controls" role="group" aria-label="Block controls">'
                    .'<button type="button" data-block-move="up" aria-label="Move block up" title="Move up">↑</button>'
                    .'<button type="button" data-block-move="down" aria-label="Move block down" title="Move down">↓</button>'
                    .'<button type="button" class="article-editor-block-remove" data-block-remove aria-label="Remove block" title="Remove block">X</button>'
                    .'</div>';
            }

            if ($block['type'] === 'introduction') {
                $hasIntroductionText = trim($this->runsText($block['runs'])) !== '';
                $html .= '<input type="text" required tabindex="-1" aria-label="Introduction paragraph" data-introduction-required-proxy class="article-editor-required-proxy" value="'.($hasIntroductionText ? 'present' : '').'">';
            }

            if (in_array($block['type'], ['introduction', 'paragraph', 'heading'], true)) {
                $tag = $block['type'] === 'heading' ? 'h'.(int) $block['level'] : 'p';
                $placeholder = $block['type'] === 'introduction' ? 'Write the introduction…' : ($block['type'] === 'heading' ? 'Section heading…' : 'Write a paragraph…');
                $html .= '<'.$tag.' data-editor-text data-placeholder="'.$placeholder.'" contenteditable="true" spellcheck="true">'.$this->renderRuns($block['runs'], $links, editor: true).'</'.$tag.'>';
            } elseif ($block['type'] === 'list') {
                $tag = $block['style'] === 'numbered' ? 'ol' : 'ul';
                $html .= '<'.$tag.' data-editor-list>';
                foreach ($block['items'] as $item) {
                    $html .= '<li data-list-item-id="'.$this->escape((string) $item['id']).'" data-placeholder="List item…" contenteditable="true" spellcheck="true">'.$this->renderRuns($item['runs'], $links, editor: true).'</li>';
                }
                $html .= '</'.$tag.'>';
            } elseif ($block['type'] === 'image') {
                $validMedia = $media && (int) $block['media_id'] === (int) $media->getKey();
                $html .= '<figure data-editor-image-block>';
                if ($validMedia && $imageUrl) {
                    $html .= '<img data-editor-image src="'.$this->escape($imageUrl).'" alt="'.$this->escape((string) $media->alt_text).'" class="mx-auto h-auto max-h-[32rem] max-w-full rounded-md">';
                } else {
                    $html .= '<div data-image-placeholder class="rounded-lg border border-dashed border-studio-line p-8 text-center text-sm text-studio-muted">Choose an image to see it here.</div>';
                }
                $html .= '<figcaption data-editor-caption data-placeholder="Add an optional caption…" contenteditable="true" spellcheck="true">'.$this->escape((string) $block['caption']).'</figcaption>';
                $html .= '</figure>';
            }

            $html .= '</div>';
        }

        return $html;
    }

    /** @param array{version: int, blocks: list<array<string, mixed>>} $document */
    public function introductionText(array $document): string
    {
        $introduction = $document['blocks'][0] ?? [];

        return $this->runsText($introduction['runs'] ?? []);
    }

    /** @param array<string, mixed> $block */
    private function renderBlock(array $block, ?Media $media, ?string $imageUrl, array $links): string
    {
        if (in_array($block['type'], ['introduction', 'paragraph'], true)) {
            return '<p>'.$this->renderRuns($block['runs'], $links).'</p>';
        }

        if ($block['type'] === 'heading') {
            $tag = 'h'.(int) $block['level'];

            return '<'.$tag.'>'.$this->renderRuns($block['runs'], $links).'</'.$tag.'>';
        }

        if ($block['type'] === 'list') {
            $tag = $block['style'] === 'numbered' ? 'ol' : 'ul';
            $items = array_map(fn (array $item): string => '<li>'.$this->renderRuns($item['runs'], $links).'</li>', $block['items']);

            return '<'.$tag.'>'.implode('', $items).'</'.$tag.'>';
        }

        if ($block['type'] === 'image' && $media && $imageUrl && (int) $block['media_id'] === (int) $media->getKey()) {
            $html = '<figure><img src="'.$this->escape($imageUrl).'" alt="'.$this->escape((string) $media->alt_text).'" class="h-auto max-w-full rounded-md">';
            if ($block['caption'] !== '') {
                $html .= '<figcaption>'.$this->escape((string) $block['caption']).'</figcaption>';
            }

            return $html.'</figure>';
        }

        return '';
    }

    /** @param list<array<string, mixed>> $runs */
    private function renderRuns(array $runs, array $links = ['sponsored' => [], 'product' => []], bool $editor = false): string
    {
        $html = '';

        foreach ($runs as $run) {
            $text = preg_replace('/\r\n|\r|\n/u', '<br>', $this->escape((string) $run['text'])) ?? $this->escape((string) $run['text']);
            foreach (($run['marks'] ?? []) as $mark) {
                $tag = $mark === 'bold' ? 'strong' : 'em';
                $text = '<'.$tag.'>'.$text.'</'.$tag.'>';
            }
            if (isset($run['href'])) {
                $href = (string) $run['href'];
                $attributes = ' href="'.$this->escape($href).'"';
                if (in_array($href, $links['sponsored'], true)) {
                    $attributes .= ' rel="sponsored"';
                }
                if ($editor && in_array($href, $links['product'], true)) {
                    $attributes .= ' data-article-product-link';
                }
                $text = '<a'.$attributes.'>'.$text.'</a>';
            }
            if (($run['size'] ?? 'normal') !== 'normal') {
                $text = '<span data-text-size="'.$this->escape((string) $run['size']).'">'.$text.'</span>';
            }
            $html .= $text;
        }

        return $html;
    }

    /** @return array{sponsored: list<string>, product: list<string>} */
    private function articleLinkUrls(iterable $articleLinks): array
    {
        $links = ['sponsored' => [], 'product' => []];

        foreach ($articleLinks as $link) {
            if (! $link instanceof ArticleLink || ! in_array($link->type, ['brand', 'product', 'service'], true)) {
                continue;
            }

            $url = trim((string) $link->url);
            if (! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                continue;
            }

            $links['sponsored'][] = $url;
            if (in_array($link->type, ['product', 'service'], true)) {
                $links['product'][] = $url;
            }
        }

        return $links;
    }

    /** @param list<array<string, mixed>> $runs */
    private function runsText(array $runs): string
    {
        return implode('', array_map(fn (array $run): string => (string) $run['text'], $runs));
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
