<?php

namespace App\Support;

use DOMDocument;
use DOMElement;

class ArticleContentStructure
{
    public function error(string $sanitizedHtml): ?string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previousErrorMode = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML(
                '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div>'.$sanitizedHtml.'</div></body></html>',
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
            );

            if (! $loaded) {
                return 'The article body could not be read. Please review its structure.';
            }

            $body = $document->getElementsByTagName('body')->item(0);
            $container = $body?->getElementsByTagName('div')->item(0);
            $blocks = [];

            if (! $container instanceof DOMElement) {
                return 'Start the article with an introduction paragraph.';
            }

            foreach ($container->childNodes as $child) {
                if ($child->nodeType === XML_TEXT_NODE && trim($child->textContent) === '') {
                    continue;
                }

                if (! $child instanceof DOMElement) {
                    return 'Use paragraphs, headings, and lists as separate article blocks.';
                }

                $blocks[] = $child;
            }

            if (! isset($blocks[0]) || strtolower($blocks[0]->tagName) !== 'p' || trim($blocks[0]->textContent) === '') {
                return 'Start the article with a normal introduction paragraph.';
            }

            $hasMainHeading = false;

            foreach ($blocks as $block) {
                $tag = strtolower($block->tagName);

                if (! in_array($tag, ['p', 'h2', 'h3', 'ul', 'ol'], true)) {
                    return 'Use only paragraphs, H2/H3 headings, and ordered or unordered lists.';
                }

                if (in_array($tag, ['h2', 'h3'], true) && trim($block->textContent) === '') {
                    return 'Headings must contain text.';
                }

                if ($tag === 'h2') {
                    $hasMainHeading = true;
                }

                if ($tag === 'h3' && ! $hasMainHeading) {
                    return 'Add an H2 main section before using an H3 heading.';
                }
            }

            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }
    }
}
