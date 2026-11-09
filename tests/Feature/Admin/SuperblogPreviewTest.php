<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\CreatesSuperblogs;
use Tests\TestCase;

class SuperblogPreviewTest extends TestCase
{
    use CreatesSuperblogs, RefreshDatabase;

    public function test_users_without_access_cannot_see_the_preview(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $staff = $this->makeUser(approved: true);
        $superblog = $this->makeSuperblog($admin);

        $this->actingAs($staff)->get(route('admin.superblogs.preview', $superblog))
            ->assertForbidden();
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $superblog = $this->makeSuperblog($this->makeUser(approved: true, superAdmin: true));

        $this->get(route('admin.superblogs.preview', $superblog))
            ->assertRedirect(route('login'));
    }

    public function test_admin_sees_the_news_home_template_without_indexing(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin);

        $this->actingAs($admin)->get(route('admin.superblogs.preview', $superblog))
            ->assertOk()
            ->assertViewIs('templates.news.home')
            ->assertSee('<span data-live-name>Dagens Budbärare</span>', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_the_templates_site_from_the_registry_is_shown(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin);
        $superblog->template->update(['slug' => 'classic']);
        // A folder that does not exist: the error names the view, which shows the registry was read.
        config(['templates.list.classic.site' => 'templates.registry-check']);

        $this->withoutExceptionHandling();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('View [templates.registry-check.home] not found.');

        $this->actingAs($admin)->get(route('admin.superblogs.preview', $superblog));
    }

    public function test_a_template_missing_from_the_registry_gets_the_default_templates_site(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        // makeSuperblog's template has the slug "news", which is not in the registry.
        $superblog = $this->makeSuperblog($admin);
        config(['templates.list.modern.site' => 'templates.news']);

        $this->actingAs($admin)->get(route('admin.superblogs.preview', $superblog))
            ->assertOk()
            ->assertViewIs('templates.news.home');

        $superblog->template->update(['slug' => 'tech']);

        $this->get(route('admin.superblogs.preview', $superblog))
            ->assertOk()
            ->assertViewIs('templates.news.home');
    }

    public function test_classic_gets_its_own_front_page_and_modern_keeps_its_own(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin);
        $superblog->template->update(['slug' => 'classic']);

        // What the page itself contains (one H1, the name in two places, nothing made up) is tested in
        // ClassicFrontPageTest; this test covers which view the preview picks and how it is kept out of search.
        $this->actingAs($admin)->get(route('admin.superblogs.preview', $superblog))
            ->assertOk()
            ->assertViewIs('templates.classic.home')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            // The builder's script finds the preview by this id, to reload it when an article is deleted.
            ->assertSee('data-admin-preview-superblog-id="'.$superblog->getKey().'"', false)
            ->assertSee('<span data-live-name>Dagens Budbärare</span>', false);

        $superblog->template->update(['slug' => 'modern']);

        $this->get(route('admin.superblogs.preview', $superblog))
            ->assertOk()
            ->assertViewIs('templates.news.home');
    }

    public function test_missing_or_unknown_theme_falls_back_to_the_first_theme(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin);

        $this->actingAs($admin)->get(route('admin.superblogs.preview', $superblog))
            ->assertSee('data-theme="bondebladet"', false);

        $superblog->update(['settings' => ['theme' => ['name' => 'okant']]]);

        $this->get(route('admin.superblogs.preview', $superblog))
            ->assertSee('data-theme="bondebladet"', false);
    }

    public function test_without_a_saved_theme_the_templates_theme_is_used(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin);
        $superblog->template->update(['slug' => 'classic']);

        $this->actingAs($admin)->get(route('admin.superblogs.preview', $superblog))
            ->assertSee('data-theme="budbararen"', false)
            ->assertDontSee('data-theme="bondebladet"', false);

        // A saved theme still wins over the template's theme.
        $superblog->update(['settings' => ['theme' => ['name' => 'techenspraket']]]);

        $this->get(route('admin.superblogs.preview', $superblog))
            ->assertSee('data-theme="techenspraket"', false)
            ->assertDontSee('data-theme="budbararen"', false);
    }

    public function test_saved_theme_is_used_in_the_preview(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin);
        $superblog->update(['settings' => ['theme' => ['name' => 'techenspraket']]]);

        $this->actingAs($admin)->get(route('admin.superblogs.preview', $superblog))
            ->assertSee('data-theme="techenspraket"', false)
            ->assertDontSee('data-theme="bondebladet"', false);
    }

    public function test_published_superblog_preview_uses_live_articles_and_excludes_drafts(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin);
        $superblog->update(['status' => 'published']);
        $published = $superblog->articles()->create([
            'title' => 'Published home preview article',
            'slug' => 'published-home-preview-article',
            'content' => 'Live article content.',
            'excerpt' => 'Live article summary.',
            'source_type' => 'manual',
            'status' => 'published',
            'is_selected_for_publication' => true,
            'published_at' => now(),
        ]);
        $superblog->articles()->create([
            'title' => 'Draft must stay hidden',
            'slug' => 'draft-must-stay-hidden',
            'content' => 'Draft content.',
            'source_type' => 'manual',
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.superblogs.preview', $superblog))
            ->assertOk()
            ->assertSee('Published home preview article')
            ->assertDontSee('Live article summary.')
            ->assertSee(route('public.superblogs.articles.show', [$superblog, $published]), false)
            ->assertDontSee('Draft must stay hidden')
            ->assertDontSee('Här visas din första artikel');
    }

    public function test_offline_preview_shows_checked_articles_after_saving_selection(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin);
        $article = $superblog->articles()->create([
            'title' => 'Offline preview article',
            'slug' => 'offline-preview-article',
            'content' => 'This remains a private preview.',
            'source_type' => 'manual',
            'status' => 'draft',
            'is_selected_for_publication' => false,
            'published_at' => null,
        ]);
        $unselectedArticle = $superblog->articles()->create([
            'title' => 'Unchecked offline preview article',
            'slug' => 'unchecked-offline-preview-article',
            'content' => 'This must not appear in the preview.',
            'source_type' => 'manual',
            'status' => 'draft',
            'is_selected_for_publication' => true,
            'published_at' => null,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.superblogs.articles.selection.update', $superblog), [
                'visible_article_ids' => [$article->getKey(), $unselectedArticle->getKey()],
                'selected_article_ids' => [$article->getKey()],
            ])
            ->assertRedirect();

        $this->assertSame('draft', $article->fresh()->status);
        $this->assertNull($article->fresh()->published_at);
        $this->assertFalse($unselectedArticle->fresh()->is_selected_for_publication);
        $this->assertTrue($article->fresh()->is_selected_for_publication);

        $this->get(route('admin.superblogs.preview', $superblog))
            ->assertOk()
            ->assertSee('Offline preview article')
            ->assertSee(route('public.superblogs.articles.show', [$superblog, $article]), false)
            ->assertDontSee($unselectedArticle->title)
            ->assertDontSee(route('public.superblogs.articles.show', [$superblog, $unselectedArticle]), false);
    }

    public function test_preview_timeline_keeps_manual_and_rss_articles_in_chronological_order(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin);
        $superblog->update(['status' => 'published']);
        $olderManual = $superblog->articles()->create([
            'title' => 'Older manual article',
            'slug' => 'older-manual-article',
            'content' => 'Manual article body.',
            'source_type' => 'manual',
            'status' => 'published',
            'is_selected_for_publication' => true,
            'published_at' => now()->subDays(2),
        ]);
        $newerRss = $superblog->articles()->create([
            'title' => 'Newest RSS article',
            'slug' => 'newest-rss-article',
            'content' => 'RSS article body.',
            'source_type' => 'rss',
            'status' => 'published',
            'is_selected_for_publication' => true,
            'published_at' => now()->subDay(),
        ]);

        $html = $this->actingAs($admin)
            ->get(route('admin.superblogs.preview', $superblog))
            ->assertOk()
            ->getContent();

        $newerPosition = strpos($html, $newerRss->title);
        $olderPosition = strpos($html, $olderManual->title);

        $this->assertNotFalse($newerPosition);
        $this->assertNotFalse($olderPosition);
        $this->assertLessThan($olderPosition, $newerPosition);
    }

    public function test_most_read_section_shows_only_published_selected_manual_articles_in_newest_first_order(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin);
        $superblog->update(['status' => 'published']);

        $olderManual = $superblog->articles()->create([
            'title' => 'Older manual sidebar article',
            'slug' => 'older-manual-sidebar-article',
            'content' => 'Older manual content.',
            'source_type' => 'manual',
            'status' => 'published',
            'is_selected_for_publication' => true,
            'published_at' => now()->subDays(2),
        ]);
        $newerManual = $superblog->articles()->create([
            'title' => 'Newer manual sidebar article',
            'slug' => 'newer-manual-sidebar-article',
            'content' => 'Newer manual content.',
            'source_type' => 'manual',
            'status' => 'published',
            'is_selected_for_publication' => true,
            'published_at' => now()->subDay(),
        ]);
        $rssArticle = $superblog->articles()->create([
            'title' => 'RSS sidebar article must be excluded',
            'slug' => 'rss-sidebar-article-must-be-excluded',
            'content' => 'RSS content.',
            'source_type' => 'rss',
            'status' => 'published',
            'is_selected_for_publication' => true,
            'published_at' => now(),
        ]);
        $unselectedManual = $superblog->articles()->create([
            'title' => 'Unselected manual sidebar article must be excluded',
            'slug' => 'unselected-manual-sidebar-article-must-be-excluded',
            'content' => 'Unselected content.',
            'source_type' => 'manual',
            'status' => 'published',
            'is_selected_for_publication' => false,
            'published_at' => now(),
        ]);
        $undatedManual = $superblog->articles()->create([
            'title' => 'Undated manual sidebar article must be excluded',
            'slug' => 'undated-manual-sidebar-article-must-be-excluded',
            'content' => 'Undated content.',
            'source_type' => 'manual',
            'status' => 'published',
            'is_selected_for_publication' => true,
            'published_at' => null,
        ]);
        $draftManual = $superblog->articles()->create([
            'title' => 'Draft manual sidebar article must be excluded',
            'slug' => 'draft-manual-sidebar-article-must-be-excluded',
            'content' => 'Draft content.',
            'source_type' => 'manual',
            'status' => 'draft',
            'is_selected_for_publication' => true,
            'published_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.superblogs.preview', $superblog))
            ->assertOk()
            ->assertSee('Mest lästa artiklar just nu');

        preg_match('/<aside aria-labelledby="latest-sidebar-heading".*?<\/aside>/s', $response->getContent(), $match);
        $this->assertNotEmpty($match);
        $sidebar = $match[0];

        $newerPosition = strpos($sidebar, $newerManual->title);
        $olderPosition = strpos($sidebar, $olderManual->title);
        $this->assertNotFalse($newerPosition);
        $this->assertNotFalse($olderPosition);
        $this->assertLessThan($olderPosition, $newerPosition);
        $this->assertStringContainsString(route('public.superblogs.articles.show', [$superblog, $newerManual]), $sidebar);

        foreach ([$rssArticle, $unselectedManual, $undatedManual, $draftManual] as $excludedArticle) {
            $this->assertStringNotContainsString($excludedArticle->title, $sidebar);
        }
    }
}
