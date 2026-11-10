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
            ->assertSee('Real paragraph one.')
            ->assertSee('Real paragraph two & more.')
            ->assertSee('2 november 2026')
            ->assertSee('Database article title', false)
            ->assertDontSee('alert("not rendered")')
            ->assertDontSee('Publish now')
            ->assertDontSee('Fetch now');
    }

    public function test_article_without_excerpt_uses_its_first_paragraph_as_meta_description(): void
    {
        [$superblog, $article] = $this->makeArticle('published', Carbon::parse('2026-11-02 14:30:00'));
        $article->update([
            'content' => "<p>First   paragraph&nbsp;with a\nline break &amp; more.</p><p>Second paragraph.</p>",
        ]);

        $description = 'First paragraph with a line break &amp; more.';

        $this->get(route('public.superblogs.articles.show', [$superblog, $article]))
            ->assertOk()
            ->assertSee('<meta name="description" content="'.$description.'">', false)
            ->assertSee('<meta property="og:description" content="'.$description.'">', false)
            ->assertSee('"description":"First paragraph with a line break', false)
            ->assertSee('Second paragraph.');
    }

    public function test_article_without_paragraph_text_renders_with_an_empty_meta_description(): void
    {
        [$superblog, $article] = $this->makeArticle('published', Carbon::parse('2026-11-02 14:30:00'));
        $article->update(['content' => '<p> </p><script>alert("not rendered")</script>']);

        $this->get(route('public.superblogs.articles.show', [$superblog, $article]))
            ->assertOk()
            ->assertSee('Database article title')
            ->assertSee('<meta name="description" content="">', false)
            ->assertDontSee('"description":', false);
    }

    public function test_locked_article_meta_description_never_contains_locked_text(): void
    {
        [$superblog, $article] = $this->makeArticle('published', Carbon::parse('2026-11-02 14:30:00'));
        $article->update([
            'is_locked' => true,
            'content' => '<p>'.str_repeat('Open words ', 30).'</p><p>Locked secret paragraph.</p>',
        ]);

        $response = $this->get(route('public.superblogs.articles.show', [$superblog, $article]))
            ->assertOk()
            ->assertDontSee('Locked secret paragraph.');

        preg_match('/<meta name="description" content="([^"]*)">/', $response->getContent(), $match);
        $this->assertStringStartsWith('Open words Open words', $match[1]);
        $this->assertStringEndsWith('…', $match[1]);
        $this->assertLessThanOrEqual(161, mb_strlen($match[1]));
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

    public function test_published_article_is_not_accessible_when_its_superblog_is_unpublished(): void
    {
        [$superblog, $article] = $this->makeArticle('published', Carbon::parse('2026-11-02 14:30:00'));
        $superblog->update(['status' => 'draft']);

        $this->get(route('public.superblogs.articles.show', [$superblog, $article]))
            ->assertNotFound();
    }

    public function test_unselected_article_is_not_publicly_accessible_even_if_its_status_is_published(): void
    {
        [$superblog, $article] = $this->makeArticle('published', Carbon::parse('2026-11-02 14:30:00'));
        $article->update(['is_selected_for_publication' => false]);

        $this->get(route('public.superblogs.articles.show', [$superblog, $article]))
            ->assertNotFound();
    }

    public function test_public_urls_follow_published_and_withdrawn_article_states(): void
    {
        [$superblog, $published] = $this->makeArticle('draft', null);
        $owner = $superblog->user;
        $owner->approval_status = 'approved';
        $owner->is_super_admin = true;
        $owner->save();
        $published->update(['is_selected_for_publication' => true]);
        $withdrawn = $superblog->articles()->create([
            'title' => 'Withdrawn article',
            'slug' => 'withdrawn-article',
            'content' => 'Previously public content',
            'source_type' => 'manual',
            'status' => 'published',
            'published_at' => now(),
            'is_selected_for_publication' => false,
        ]);

        $this->actingAs($owner)
            ->post(route('admin.superblogs.articles.publish-selected', $superblog))
            ->assertRedirect();

        $this->get(route('public.superblogs.articles.show', [$superblog, $published]))
            ->assertOk()
            ->assertSee('Database article title');
        $this->get(route('public.superblogs.articles.show', [$superblog, $withdrawn]))
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
            'status' => 'published',
            'domain' => "{$blogSlug}.example.test",
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
            'source_type' => 'rss',
            'status' => $status,
            'is_selected_for_publication' => $status === 'published',
            'published_at' => $publishedAt,
        ]);

        return [$superblog, $article];
    }
}
