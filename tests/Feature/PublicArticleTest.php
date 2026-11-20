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
        $createdAt = Carbon::parse('2026-11-01 10:00:00');
        $article->forceFill(['created_at' => $createdAt])->save();

        $this->get(route('public.superblogs.articles.show', [$superblog, $article]))
            ->assertOk()
            ->assertSee('Database article title')
            ->assertSee('Real paragraph one.')
            ->assertSee('Real paragraph two & more.')
            ->assertSee('Källor')
            ->assertDontSee('Källor och länkar')
            ->assertSee('Original source title')
            ->assertSee('Skapad')
            ->assertSee('1 nov 2026')
            ->assertDontSee('Senast ändrad')
            ->assertSee('Database article title', false)
            ->assertDontSee('alert("not rendered")')
            ->assertDontSee('Publish now')
            ->assertDontSee('Fetch now');
    }

    public function test_article_byline_uses_the_saved_editor_name_and_escapes_it(): void
    {
        [$superblog, $article] = $this->makeArticle('published', Carbon::parse('2026-11-02 14:30:00'));
        $article->update(['author_name' => '<Editor & writer>']);

        $this->get(route('public.superblogs.articles.show', [$superblog, $article]))
            ->assertOk()
            ->assertSee('Av')
            ->assertSee('&lt;Editor &amp; writer&gt;', false)
            ->assertDontSee('<Editor & writer>', false);
    }

    public function test_public_rendering_preserves_approved_formatting_and_sanitizes_legacy_content(): void
    {
        [$superblog, $article] = $this->makeArticle('published', Carbon::parse('2026-11-02 14:30:00'));
        $article->update([
            'content' => '<p onclick="alert(1)">First <strong>bold</strong> <em>italic</em> <a href="https://example.test/story">safe link</a> <a href="javascript:alert(2)">unsafe link</a> <span data-text-size="large" style="color:red">large</span><span data-text-size="huge">unknown size</span></p><h2>A heading</h2><ol><li>List item</li></ol><script>alert("script must not render")</script>',
        ]);

        $this->get(route('public.superblogs.articles.show', [$superblog, $article]))
            ->assertOk()
            ->assertSee('<strong>bold</strong>', false)
            ->assertSee('<em>italic</em>', false)
            ->assertSee('<a href="https://example.test/story">safe link</a>', false)
            ->assertSee('<span data-text-size="large">large</span>', false)
            ->assertSee('<h2>A heading</h2>', false)
            ->assertSee('<ol><li>List item</li></ol>', false)
            ->assertSee('unsafe link')
            ->assertSee('unknown size')
            ->assertDontSee('onclick')
            ->assertDontSee('javascript:')
            ->assertDontSee('style=')
            ->assertDontSee('data-text-size="huge"')
            ->assertDontSee('script must not render');
    }

    public function test_content_modified_date_is_rendered_only_when_available(): void
    {
        [$superblog, $article] = $this->makeArticle('published', Carbon::parse('2026-11-02 14:30:00'));
        $createdAt = Carbon::parse('2026-11-02 08:00:00');
        $article->forceFill(['created_at' => $createdAt])->save();
        $modifiedAt = Carbon::parse('2026-11-04 09:15:00');
        $article->forceFill(['content_modified_at' => $modifiedAt])->save();

        $this->get(route('public.superblogs.articles.show', [$superblog, $article]))
            ->assertOk()
            ->assertSee('Skapad')
            ->assertSee('2 nov 2026')
            ->assertSee('Senast ändrad')
            ->assertSee('4 nov 2026')
            ->assertSee('<meta property="article:modified_time" content="'.$modifiedAt->toIso8601String().'">', false)
            ->assertSee('"datePublished":"'.$article->published_at->toIso8601String().'"', false)
            ->assertSee('"dateModified":"'.$modifiedAt->toIso8601String().'"', false);

        $article->forceFill(['content_modified_at' => null])->save();

        $this->get(route('public.superblogs.articles.show', [$superblog, $article]))
            ->assertOk()
            ->assertDontSee('Senast ändrad')
            ->assertDontSee('article:modified_time');
    }

    public function test_same_day_content_edit_shows_modified_date(): void
    {
        [$superblog, $article] = $this->makeArticle('published', Carbon::parse('2026-11-02 14:30:00'));
        $createdAt = Carbon::parse('2026-11-02 08:00:00');
        $modifiedAt = Carbon::parse('2026-11-02 16:00:00');
        $article->forceFill([
            'created_at' => $createdAt,
            'content_modified_at' => $modifiedAt,
        ])->save();

        $response = $this->get(route('public.superblogs.articles.show', [$superblog, $article]))
            ->assertOk()
            ->assertSee('Skapad')
            ->assertSee('Senast ändrad')
            ->assertSee('2 nov 2026')
            ->assertSee('"dateModified":"'.$modifiedAt->toIso8601String().'"', false);

        $this->assertSame(2, substr_count($response->getContent(), '2 nov 2026'));
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
