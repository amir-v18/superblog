<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\User;
use App\Support\DemoArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PublicArticleTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_article_is_rendered_from_its_database_content(): void
    {
        [$superblog, $article] = $this->makeArticle('published', Carbon::parse('2026-11-02 14:30:00'));

        $this->get(route('public.superblogs.articles.show', [$superblog, $article]))
            ->assertOk()
            ->assertSee('Database article title')
            ->assertSee('A real article excerpt')
            ->assertSee('Real paragraph one.')
            ->assertSee('Real paragraph two & more.')
            ->assertSee('2 november 2026')
            ->assertSee('Database article title', false)
            ->assertDontSee('alert("not rendered")')
            ->assertDontSee('Publish now')
            ->assertDontSee('Fetch now');
    }

    public function test_draft_article_is_not_publicly_accessible(): void
    {
        [$superblog, $article] = $this->makeArticle('draft', Carbon::parse('2026-11-02 14:30:00'));

        $this->get(route('public.superblogs.articles.show', [$superblog, $article]))
            ->assertNotFound();
    }

    public function test_published_article_without_publication_time_is_not_accessible(): void
    {
        [$superblog, $article] = $this->makeArticle('published', null);

        $this->get(route('public.superblogs.articles.show', [$superblog, $article]))
            ->assertNotFound();
    }

    public function test_article_cannot_be_opened_through_another_superblogs_url(): void
    {
        [$firstSuperblog, $firstArticle] = $this->makeArticle('published', Carbon::parse('2026-11-02 14:30:00'));
        [$secondSuperblog, $article] = $this->makeArticle('published', Carbon::parse('2026-11-02 14:30:00'), 'second-blog');
        $firstArticle->update(['slug' => 'another-article']);

        $this->get(route('public.superblogs.articles.show', [$firstSuperblog, $article]))
            ->assertNotFound();

        $this->get(route('public.superblogs.articles.show', [$secondSuperblog, $article]))
            ->assertOk();
    }

    public function test_demo_article_and_template_preview_views_still_render(): void
    {
        $articleHtml = view('templates.news.article', [
            'article' => DemoArticle::make(),
            'theme' => 'bondebladet',
        ])->render();
        $previewHtml = view('demo.layout')->render();

        $this->assertStringContainsString('Så påverkar höstregnen skörden i år', $articleHtml);
        $this->assertStringContainsString('Så fungerar den publika layouten', $previewHtml);
    }

    private function makeArticle(string $status, ?Carbon $publishedAt, string $blogSlug = 'public-blog'): array
    {
        $owner = User::factory()->create();
        $template = Template::create([
            'name' => "Template {$blogSlug}",
            'slug' => "template-{$blogSlug}",
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);
        $superblog = $owner->superblogs()->create([
            'template_id' => $template->getKey(),
            'name' => "Public {$blogSlug}",
            'slug' => $blogSlug,
            'settings' => ['theme' => 'bondebladet'],
        ]);
        $source = $superblog->rssSources()->create([
            'name' => 'Example feed',
            'url' => 'https://feeds.example.test/rss.xml',
            'is_active' => true,
        ]);
        $item = $source->rssItems()->create([
            'title' => 'Original source title',
            'url' => 'https://news.example.test/original-story',
            'author' => 'RSS author',
            'content' => 'Original RSS source text.',
        ]);
        $article = $superblog->articles()->create([
            'rss_item_id' => $item->getKey(),
            'title' => 'Database article title',
            'slug' => 'database-article',
            'content' => '<p>Real paragraph one.</p><p>Real paragraph two &amp; more.</p><script>alert("not rendered")</script>',
            'excerpt' => 'A real article excerpt',
            'source_type' => 'rss',
            'status' => $status,
            'published_at' => $publishedAt,
        ]);

        return [$superblog, $article];
    }
}
