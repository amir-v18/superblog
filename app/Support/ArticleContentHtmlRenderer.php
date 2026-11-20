<?php

namespace App\Support;

use App\Models\Media;

/** Builds semantic HTML from a validated document; text is escaped and never treated as browser HTML. */
class ArticleContentHtmlRenderer
{
    /** @param array{version: int, blocks: list<array<string, mixed>>} $document */
    public function render(array $document, ?Media $media = null, ?string $imageUrl = null): string
    {
        return implode('', array_map(
            fn (array $block): string => $this->renderBlock($block, $media, $imageUrl),
            $document['blocks'],
        ));
    }

    /** @param array{version: int, blocks: list<array<string, mixed>>} $document */
    public function renderEditor(array $document, ?Media $media, ?string $imageUrl): string
    {
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
            $html .= '<div class="article-editor-block-controls" aria-label="Block controls">'
                .'<button type="button" data-block-move="up" aria-label="Move block up" title="Move up">↑</button>'
                .'<button type="button" data-block-move="down" aria-label="Move block down" title="Move down">↓</button>'
                .'<button type="button" data-block-remove aria-label="Remove block" title="Remove block">×</button>'
                .'</div>';

            if (in_array($block['type'], ['introduction', 'paragraph', 'heading'], true)) {
                $tag = $block['type'] === 'heading' ? 'h'.(int) $block['level'] : 'p';
                $placeholder = $block['type'] === 'introduction' ? 'Write the introduction…' : ($block['type'] === 'heading' ? 'Section heading…' : 'Write a paragraph…');
                $html .= '<'.$tag.' data-editor-text data-placeholder="'.$placeholder.'" contenteditable="true" spellcheck="true">'.$this->renderRuns($block['runs']).'</'.$tag.'>';
            } elseif ($block['type'] === 'list') {
                $tag = $block['style'] === 'numbered' ? 'ol' : 'ul';
                $html .= '<'.$tag.' data-editor-list>';
                foreach ($block['items'] as $item) {
                    $html .= '<li data-list-item-id="'.$this->escape((string) $item['id']).'" data-placeholder="List item…" contenteditable="true" spellcheck="true">'.$this->renderRuns($item['runs']).'</li>';
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
    private function renderBlock(array $block, ?Media $media, ?string $imageUrl): string
    {
        if (in_array($block['type'], ['introduction', 'paragraph'], true)) {
            return '<p>'.$this->renderRuns($block['runs']).'</p>';
        }

        if ($block['type'] === 'heading') {
            $tag = 'h'.(int) $block['level'];

            return '<'.$tag.'>'.$this->renderRuns($block['runs']).'</'.$tag.'>';
        }

        if ($block['type'] === 'list') {
            $tag = $block['style'] === 'numbered' ? 'ol' : 'ul';
            $items = array_map(fn (array $item): string => '<li>'.$this->renderRuns($item['runs']).'</li>', $block['items']);

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
    private function renderRuns(array $runs): string
    {
        $html = '';

        foreach ($runs as $run) {
            $text = $this->escape((string) $run['text']);
            foreach (($run['marks'] ?? []) as $mark) {
                $tag = $mark === 'bold' ? 'strong' : 'em';
                $text = '<'.$tag.'>'.$text.'</'.$tag.'>';
            }
            if (isset($run['href'])) {
                $text = '<a href="'.$this->escape((string) $run['href']).'">'.$text.'</a>';
            }
            if (($run['size'] ?? 'normal') !== 'normal') {
                $text = '<span data-text-size="'.$this->escape((string) $run['size']).'">'.$text.'</span>';
            }
            $html .= $text;
        }

        return $html;
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
