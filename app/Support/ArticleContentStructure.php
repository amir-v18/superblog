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
                return 'Artikeltexten kunde inte läsas. Kontrollera hur innehållet är strukturerat.';
            }

            $body = $document->getElementsByTagName('body')->item(0);
            $container = $body?->getElementsByTagName('div')->item(0);
            $blocks = [];

            if (! $container instanceof DOMElement) {
                return 'Börja artikeln med ett inledande stycke.';
            }

            foreach ($container->childNodes as $child) {
                if ($child->nodeType === XML_TEXT_NODE && trim($child->textContent) === '') {
                    continue;
                }

                if (! $child instanceof DOMElement) {
                    return 'Använd stycken, rubriker och listor som separata innehållsblock.';
                }

                $blocks[] = $child;
            }

            if (! isset($blocks[0]) || strtolower($blocks[0]->tagName) !== 'p' || trim($blocks[0]->textContent) === '') {
                return 'Börja artikeln med ett vanligt inledande stycke.';
            }

            $hasMainHeading = false;

            foreach ($blocks as $block) {
                $tag = strtolower($block->tagName);

                if (! in_array($tag, ['p', 'h2', 'h3', 'ul', 'ol'], true)) {
                    return 'Använd bara stycken, H2- och H3-rubriker samt punktlistor eller numrerade listor.';
                }

                if (in_array($tag, ['h2', 'h3'], true) && trim($block->textContent) === '') {
                    return 'Rubriker måste innehålla text.';
                }

                if ($tag === 'h2') {
                    $hasMainHeading = true;
                }

                if ($tag === 'h3' && ! $hasMainHeading) {
                    return 'Lägg till en H2-huvudrubrik innan du använder en H3-underrubrik.';
                }
            }

            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }
    }
}
