<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerAction;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class ArticleHtmlSanitizer
{
    private readonly HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig)
            ->defaultAction(HtmlSanitizerAction::Block)
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('h2')
            ->allowElement('h3')
            ->allowElement('strong')
            ->allowElement('em')
            ->allowElement('a', ['href'])
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('span', ['data-text-size'])
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowRelativeLinks()
            ->withAttributeSanitizer(new ArticleTextSizeAttributeSanitizer)
            // Do not silently truncate long articles; HTTP form and RSS fetch limits guard new input.
            ->withMaxInputLength(-1);

        // The article title field supplies the single H1; body-provided H1 elements are discarded.
        foreach (['h1', 'script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template'] as $unsafeElement) {
            $config = $config->dropElement($unsafeElement);
        }

        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function sanitize(string $content): string
    {
        if (! $this->containsMarkup($content)) {
            $content = $this->plainTextParagraphs($content);
        }

        $safeContent = trim($this->wrapRootTextNodes($this->sanitizer->sanitize($content)));

        if ($safeContent !== '' && ! preg_match('/<(?:p|h2|h3|ul|ol)\b/i', $safeContent)) {
            $safeContent = '<p>'.$safeContent.'</p>';
        }

        return $safeContent;
    }

    public function hasText(string $safeContent): bool
    {
        return $this->textContent($safeContent) !== '';
    }

    public function firstParagraphHtml(string $safeContent): string
    {
        $paragraph = $this->firstParagraph($safeContent);

        if (! $paragraph) {
            return '';
        }

        $html = '';

        foreach ($paragraph->childNodes as $child) {
            $html .= $paragraph->ownerDocument?->saveHTML($child) ?: '';
        }

        return '<p>'.$html.'</p>';
    }

    public function firstParagraphText(string $safeContent): string
    {
        $paragraph = $this->firstParagraph($safeContent);

        if (! $paragraph) {
            return '';
        }

        $text = str_replace(["\u{00A0}", "\u{202F}"], ' ', $paragraph->textContent);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    private function containsMarkup(string $content): bool
    {
        return preg_match('/<\s*\/?\s*[a-z][a-z0-9:-]*(?:\s[^>]*)?>/i', $content) === 1;
    }

    private function plainTextParagraphs(string $content): string
    {
        $paragraphs = preg_split('/(?:\r\n|\r|\n){2,}/u', trim($content), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode('', array_map(function (string $paragraph): string {
            $paragraph = preg_replace('/\R/u', ' ', trim($paragraph)) ?? trim($paragraph);
            $escaped = htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

            return '<p>'.$escaped.'</p>';
        }, $paragraphs));
    }

    private function textContent(string $safeContent): string
    {
        $container = $this->documentContainer($safeContent);

        return trim(preg_replace('/\s+/u', ' ', $container?->textContent ?? '') ?? '');
    }

    private function wrapRootTextNodes(string $safeContent): string
    {
        $container = $this->documentContainer($safeContent);

        if (! $container) {
            return $safeContent;
        }

        $document = $container->ownerDocument;
        $textNodes = [];

        foreach ($container->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE && trim($child->textContent) !== '') {
                $textNodes[] = $child;
            }
        }

        foreach ($textNodes as $textNode) {
            $paragraph = $document->createElement('p');
            $paragraph->appendChild($document->createTextNode($textNode->textContent));
            $container->replaceChild($paragraph, $textNode);
        }

        $html = '';
        foreach ($container->childNodes as $child) {
            $html .= $document->saveHTML($child) ?: '';
        }

        return $html;
    }

    private function firstParagraph(string $safeContent): ?DOMElement
    {
        return $this->documentContainer($safeContent)?->getElementsByTagName('p')->item(0);
    }

    private function documentContainer(string $safeContent): ?DOMElement
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previousErrorMode = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML(
                '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div>'.$safeContent.'</div></body></html>',
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
            );

            if (! $loaded) {
                return null;
            }

            $body = $document->getElementsByTagName('body')->item(0);
            $container = $body?->getElementsByTagName('div')->item(0);

            return $container instanceof DOMElement ? $container : null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }
    }
}
