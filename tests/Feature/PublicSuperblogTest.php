<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Superblog;
use App\Models\Template;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSuperblogTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_superblog_homepage_renders_public_articles_and_sidebar(): void
    {
        $superblog = $this->makeSuperblog('published', 'public-home');
        $olderManual = $this->makeArticle($superblog, 'Older manual article', 'manual', 'published', true, now()->subDays(2));
        $newerRss = $this->makeArticle($superblog, 'Newer RSS article', 'rss', 'published', true, now()->subDay());
        $newestManual = $this->makeArticle($superblog, 'Newest manual article', 'manual', 'published', true, now());
        $this->makeArticle($superblog, 'Draft article must not appear', 'manual', 'draft', true, now()->addDay());
        $this->makeArticle($superblog, 'Unselected article must not appear', 'manual', 'published', false, now()->addDay());
        $this->makeArticle($superblog, 'Undated article must not appear', 'manual', 'published', true, null);

        $response = $this->get(route('public.superblogs.show', $superblog))
            ->assertOk()
            ->assertSee($newestManual->title)
            ->assertSee($newerRss->title)
            ->assertSee($olderManual->title)
            ->assertSee(route('public.superblogs.articles.show', [$superblog, $newestManual]), false)
            ->assertDontSee('Draft article must not appear')
            ->assertDontSee('Unselected article must not appear')
            ->assertDontSee('Undated article must not appear');

        $html = $response->getContent();
        preg_match('/<section aria-label="Förstasidan".*?<\/section>/s', $html, $timelineMatch);
        $this->assertNotEmpty($timelineMatch);
        $rssPosition = strpos($timelineMatch[0], $newerRss->title);
        $olderPosition = strpos($timelineMatch[0], $olderManual->title);
        $this->assertNotFalse($rssPosition);
        $this->assertLessThan($olderPosition, $rssPosition);

        preg_match('/<aside aria-labelledby="latest-sidebar-heading".*?<\/aside>/s', $html, $match);
        $this->assertNotEmpty($match);
        $sidebar = $match[0];
        $newestSidebarPosition = strpos($sidebar, $newestManual->title);
        $olderSidebarPosition = strpos($sidebar, $olderManual->title);
        $this->assertNotFalse($newestSidebarPosition);
        $this->assertLessThan($olderSidebarPosition, $newestSidebarPosition);
        $this->assertStringNotContainsString($newerRss->title, $sidebar);
    }

    public function test_unpublished_superblog_homepage_returns_not_found(): void
    {
        $superblog = $this->makeSuperblog('draft', 'offline-home');

        $this->get(route('public.superblogs.show', $superblog))
            ->assertNotFound();
    }

    public function test_empty_one_article_and_multiple_article_homepages_keep_the_same_card_layout(): void
    {
        $empty = $this->makeSuperblog('published', 'empty-layout');
        $oneArticle = $this->makeSuperblog('published', 'one-article-layout');
        $this->makeArticle($oneArticle, 'Single homepage story', 'rss', 'published', true, now());

        $severalArticles = $this->makeSuperblog('published', 'several-articles-layout');
        foreach (range(1, 4) as $number) {
            $this->makeArticle($severalArticles, "Homepage story {$number}", 'rss', 'published', true, now()->subMinutes($number));
        }

        $emptyHtml = $this->get(route('public.superblogs.show', $empty))->assertOk()->getContent();
        $oneArticleHtml = $this->get(route('public.superblogs.show', $oneArticle))->assertOk()->getContent();
        $severalArticlesHtml = $this->get(route('public.superblogs.show', $severalArticles))->assertOk()->getContent();

        foreach ([$emptyHtml, $oneArticleHtml, $severalArticlesHtml] as $html) {
            $this->assertStringContainsString('grid-cols-[2fr_1fr_.9fr]', $html);
            $this->assertStringContainsString('max-[760px]:grid-cols-1', $html);
            $this->assertStringContainsString('min-h-[470px]', $html);
            $this->assertSame(9, substr_count($html, '<article'));
            $this->assertSame(8, substr_count($html, 'aspect-16/10'));
        }

        $this->assertStringContainsString('Single homepage story', $oneArticleHtml);
        $this->assertStringContainsString('Homepage story 2', $severalArticlesHtml);
        $this->assertStringContainsString('Homepage story 4', $severalArticlesHtml);
        $this->assertStringNotContainsString('Här visas din första artikel', $oneArticleHtml);
    }

    public function test_published_homepage_and_admin_preview_render_identical_public_content(): void
    {
        $admin = User::factory()->create(['approval_status' => 'approved', 'is_super_admin' => true]);
        $superblog = $this->makeSuperblog('published', 'matching-home', $admin);
        $this->makeArticle($superblog, 'Manual home story', 'manual', 'published', true, now());
        $this->makeArticle($superblog, 'RSS home story', 'rss', 'published', true, now()->subDay());

        $publicHtml = $this->get(route('public.superblogs.show', $superblog))
            ->assertOk()
            ->getContent();
        $previewHtml = $this->actingAs($admin)
            ->get(route('admin.superblogs.preview', $superblog))
            ->assertOk()
            ->getContent();

        preg_match('/<main.*?<\/main>/s', $publicHtml, $publicMain);
        preg_match('/<main.*?<\/main>/s', $previewHtml, $previewMain);
        $this->assertNotEmpty($publicMain);
        $this->assertSame($publicMain[0], $previewMain[0]);
    }

    public function test_selected_drafts_remain_visible_in_offline_preview_but_not_publicly(): void
    {
        $admin = User::factory()->create(['approval_status' => 'approved', 'is_super_admin' => true]);
        $superblog = $this->makeSuperblog('draft', 'offline-preview', $admin);
        $draft = $this->makeArticle($superblog, 'Selected draft preview story', 'manual', 'draft', true, null);

        $this->actingAs($admin)
            ->get(route('admin.superblogs.preview', $superblog))
            ->assertOk()
            ->assertSee($draft->title);

        $this->get(route('public.superblogs.show', $superblog))
            ->assertNotFound();
    }

    private function makeSuperblog(string $status, string $slug, ?User $owner = null): Superblog
    {
        $owner ??= User::factory()->create();
        $template = Template::create([
            'name' => "Template {$slug}",
            'slug' => "template-{$slug}",
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);

        return $owner->superblogs()->create([
            'template_id' => $template->getKey(),
            'name' => "Public {$slug}",
            'slug' => $slug,
            'status' => $status,
            'settings' => ['theme' => 'bondebladet'],
        ]);
    }

    private function makeArticle(
        Superblog $superblog,
        string $title,
        string $sourceType,
        string $status,
        bool $selected,
        ?DateTimeInterface $publishedAt,
    ): Article {
        static $number = 0;
        $number++;
        $slug = 'article-'.$number;

        return $superblog->articles()->create([
            'title' => $title,
            'slug' => $slug,
            'content' => "Content for {$title}",
            'source_type' => $sourceType,
            'status' => $status,
            'is_selected_for_publication' => $selected,
            'published_at' => $publishedAt,
        ]);
    }
}
