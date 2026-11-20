<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Converts only the supported legacy HTML subset; unsupported markup stays in legacy mode. */
class LegacyArticleDocumentConverter
{
    private const ALLOWED_ELEMENTS = ['p', 'h1', 'h2', 'h3', 'ul', 'ol', 'li', 'strong', 'b', 'em', 'i', 'a', 'span'];

    public function __construct(
        private readonly ArticleHtmlSanitizer $sanitizer,
        private readonly ArticleContentDocument $documents,
    ) {}

    /** @return array{version: int, blocks: list<array<string, mixed>>}|null */
    public function convert(string $html): ?array
    {
        $source = $this->load($html);
        if (! $source || ! $this->containsSupportedMarkup($source)) {
            return null;
        }

        // A legacy body H1 cannot remain an H1 because the article title owns that level.
        // Convert its text to a normal paragraph before sanitizing so the old words are preserved.
        $html = preg_replace('/<\s*(\/?)\s*h1\b/i', '<$1p', $html) ?? $html;
        $safeHtml = $this->sanitizer->sanitize($html);
        $container = $this->load($safeHtml);
        if (! $container) {
            return null;
        }

        $blocks = [];
        foreach ($container->childNodes as $node) {
            if ($node instanceof DOMText && trim($node->textContent) === '') {
                continue;
            }

            if (! $node instanceof DOMElement) {
                return null;
            }

            $tag = strtolower($node->tagName);
            $id = (string) Str::uuid();

            if (in_array($tag, ['p', 'h2', 'h3'], true)) {
                $runs = $this->runs($node);
                $blocks[] = match ($tag) {
                    'p' => [
                        'id' => $id,
                        'type' => $blocks === [] ? 'introduction' : 'paragraph',
                        'runs' => $runs,
                    ],
                    default => ['id' => $id, 'type' => 'heading', 'level' => (int) substr($tag, 1), 'runs' => $runs],
                };

                continue;
            }

            if (in_array($tag, ['ul', 'ol'], true)) {
                $items = [];
                foreach ($node->childNodes as $child) {
                    if ($child instanceof DOMText && trim($child->textContent) === '') {
                        continue;
                    }

                    if (! $child instanceof DOMElement || strtolower($child->tagName) !== 'li') {
                        return null;
                    }

                    foreach ($child->childNodes as $itemChild) {
                        if ($itemChild instanceof DOMElement && in_array(strtolower($itemChild->tagName), ['ul', 'ol'], true)) {
                            return null;
                        }
                    }

                    $items[] = ['id' => (string) Str::uuid(), 'runs' => $this->runs($child)];
                }

                if ($items === []) {
                    return null;
                }

                $blocks[] = ['id' => $id, 'type' => 'list', 'style' => $tag === 'ul' ? 'bullet' : 'numbered', 'items' => $items];

                continue;
            }

            return null;
        }

        try {
            return $this->documents->validate(['version' => ArticleContentDocument::VERSION, 'blocks' => $blocks]);
        } catch (ValidationException) {
            return null;
        }
    }

    private function load(string $html): ?DOMElement
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previousErrorMode = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML(
                '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="article-root">'.$html.'</div></body></html>',
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
            );

            if (! $loaded) {
                return null;
            }

            $root = $document->getElementById('article-root');

            return $root instanceof DOMElement ? $root : null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }
    }

    private function containsSupportedMarkup(DOMElement $root): bool
    {
        $walk = function (DOMNode $node) use (&$walk): bool {
            foreach ($node->childNodes as $child) {
                if (! $child instanceof DOMElement) {
                    continue;
                }

                $tag = strtolower($child->tagName);
                if (! in_array($tag, self::ALLOWED_ELEMENTS, true)) {
                    return false;
                }

                foreach ($child->attributes as $attribute) {
                    $name = strtolower($attribute->name);
                    $allowed = ($tag === 'a' && $name === 'href')
                        || ($tag === 'span' && $name === 'data-text-size');
                    if (! $allowed) {
                        return false;
                    }
                }

                if ($tag === 'span' && ! in_array($child->getAttribute('data-text-size'), ['small', 'normal', 'large'], true)) {
                    return false;
                }

                if (! $walk($child)) {
                    return false;
                }
            }

            return true;
        };

        return $walk($root);
    }

    /** @return list<array{text: string, marks: list<string>, size: string, href?: string}> */
    private function runs(DOMNode $node, array $marks = [], string $size = 'normal', ?string $href = null): array
    {
        $runs = [];

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                if ($child->textContent !== '') {
                    $run = ['text' => $child->textContent, 'marks' => $marks, 'size' => $size];
                    if ($href !== null) {
                        $run['href'] = $href;
                    }
                    $runs[] = $run;
                }

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);
            $nextMarks = $marks;
            if (in_array($tag, ['strong', 'b'], true)) {
                $nextMarks[] = 'bold';
            } elseif (in_array($tag, ['em', 'i'], true)) {
                $nextMarks[] = 'italic';
            }

            $runs = [...$runs, ...$this->runs(
                $child,
                array_values(array_unique($nextMarks)),
                $tag === 'span' ? $child->getAttribute('data-text-size') : $size,
                $tag === 'a' ? $child->getAttribute('href') : $href,
            )];
        }

        return $runs;
    }
}
