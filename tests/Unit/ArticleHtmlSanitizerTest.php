<?php

namespace Tests\Unit;

use App\Support\ArticleHtmlSanitizer;
use PHPUnit\Framework\TestCase;

class ArticleHtmlSanitizerTest extends TestCase
{
    public function test_it_keeps_only_the_approved_semantic_formatting_and_text_sizes(): void
    {
        $sanitizer = new ArticleHtmlSanitizer;

        $safe = $sanitizer->sanitize(<<<'HTML'
            <p>Text <strong>bold</strong> <em>italic</em> <a href="https://example.com/story">link</a>
            <span data-text-size="small">small</span>
            <span data-text-size="normal">normal</span>
            <span data-text-size="large">large</span></p>
            <h2>Heading 2</h2><h3>Heading 3</h3>
            <ol><li>First</li></ol><ul><li>Second</li></ul>
            HTML);

        $this->assertStringContainsString('<strong>bold</strong>', $safe);
        $this->assertStringContainsString('<em>italic</em>', $safe);
        $this->assertStringContainsString('<a href="https://example.com/story">link</a>', $safe);
        $this->assertStringContainsString('<span data-text-size="small">small</span>', $safe);
        $this->assertStringContainsString('<span data-text-size="normal">normal</span>', $safe);
        $this->assertStringContainsString('<span data-text-size="large">large</span>', $safe);
        $this->assertStringContainsString('<h2>Heading 2</h2>', $safe);
        $this->assertStringContainsString('<h3>Heading 3</h3>', $safe);
        $this->assertStringContainsString('<ol><li>First</li></ol>', $safe);
        $this->assertStringContainsString('<ul><li>Second</li></ul>', $safe);
        $this->assertSame($safe, $sanitizer->sanitize($safe));
    }

    public function test_it_removes_scripts_unsafe_links_and_unsupported_attributes(): void
    {
        $sanitizer = new ArticleHtmlSanitizer;

        $safe = $sanitizer->sanitize(<<<'HTML'
            <p onclick="alert(1)" style="color:red">Keep this
            <script>alert("script removed")</script>
            <a href="javascript:alert(1)" onclick="alert(2)">unsafe link</a>
            <span data-text-size="huge" style="font-size:999px" class="arbitrary">safe text</span>
            <iframe src="https://example.com">frame removed</iframe></p>
            HTML);

        $this->assertStringContainsString('Keep this', $safe);
        $this->assertStringContainsString('unsafe link', $safe);
        $this->assertStringContainsString('safe text', $safe);
        $this->assertStringNotContainsString('<script', $safe);
        $this->assertStringNotContainsString('script removed', $safe);
        $this->assertStringNotContainsString('<iframe', $safe);
        $this->assertStringNotContainsString('frame removed', $safe);
        $this->assertStringNotContainsString('javascript:', $safe);
        $this->assertStringNotContainsString('onclick', $safe);
        $this->assertStringNotContainsString('style=', $safe);
        $this->assertStringNotContainsString('class=', $safe);
        $this->assertStringNotContainsString('data-text-size="huge"', $safe);
    }

    public function test_plain_text_is_stored_as_safe_paragraphs(): void
    {
        $sanitizer = new ArticleHtmlSanitizer;

        $this->assertSame('<p>First paragraph.</p><p>Second &amp; final paragraph.</p>', $sanitizer->sanitize("First paragraph.\n\nSecond & final paragraph."));
    }

    public function test_body_h1_is_removed_because_the_article_title_supplies_the_only_h1(): void
    {
        $sanitizer = new ArticleHtmlSanitizer;

        $this->assertSame('<p>Introduction.</p>', $sanitizer->sanitize('<h1>Not a body heading</h1><p>Introduction.</p>'));
    }

    public function test_it_extracts_only_sanitized_first_paragraph_content(): void
    {
        $sanitizer = new ArticleHtmlSanitizer;
        $safe = $sanitizer->sanitize('<h2>Heading</h2><p>First <strong>paragraph</strong> &amp; text.</p><p>Second paragraph.</p>');

        $this->assertSame('First paragraph & text.', $sanitizer->firstParagraphText($safe));
        $this->assertSame('<p>First <strong>paragraph</strong> &amp; text.</p>', $sanitizer->firstParagraphHtml($safe));
    }
}
