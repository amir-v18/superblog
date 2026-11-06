<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('Live article summary.')
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
}
