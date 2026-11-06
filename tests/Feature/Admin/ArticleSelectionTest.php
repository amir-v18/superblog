<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Superblog;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ArticleSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_builder_contents_lists_rss_manual_draft_and_published_articles_only_for_selected_superblog(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $rssSource = $superblog->rssSources()->create([
            'name' => 'Local news',
            'url' => 'https://news.example/feed.xml',
            'is_active' => true,
        ]);
        $rssItem = $rssSource->rssItems()->create([
            'title' => 'RSS story',
            'url' => 'https://news.example/story',
            'content' => 'Original feed body',
        ]);
        $rssDraft = $this->makeArticle($superblog, 'RSS draft', sourceType: 'rss', rssItemId: $rssItem->id);
        $manualDraft = $this->makeArticle($superblog, 'Manual draft');
        $published = $this->makeArticle($superblog, 'Published story', status: 'published', selected: true, publishedAt: now());
        [, $otherSuperblog] = $this->makeAssignedSuperblog($staff, 'Other Superblog');
        $otherArticle = $this->makeArticle($otherSuperblog, 'Other blog article');

        $this->actingAs($staff)
            ->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall']))
            ->assertOk()
            ->assertSee('RSS draft')
            ->assertSee('Manual draft')
            ->assertSee('Published story')
            ->assertDontSee('Other blog article')
            ->assertSee('Manuella artiklar')
            ->assertSee('RSS-artiklar')
            ->assertSee(route('admin.superblogs.articles.edit', [$superblog, $rssDraft]), false)
            ->assertSee(route('admin.superblogs.articles.edit', [$superblog, $manualDraft]), false)
            ->assertSee('RSS')
            ->assertSee('Manuell')
            ->assertDontSee('<span>Draft</span>', false)
            ->assertDontSee('<span>Published</span>', false);

        $this->assertDatabaseHas('articles', ['id' => $rssDraft->id, 'rss_item_id' => $rssItem->id]);
        $this->assertDatabaseHas('articles', ['id' => $otherArticle->id, 'superblog_id' => $otherSuperblog->id]);
    }

    public function test_published_articles_are_selected_and_new_drafts_are_not_selected_by_default(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $published = $this->makeArticle($superblog, 'Selected published', status: 'published', selected: true, publishedAt: now());
        $draft = $this->makeArticle($superblog, 'Unselected draft');

        $this->assertTrue($draft->fresh()->is_selected_for_publication === false);

        $this->actingAs($staff)
            ->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall']))
            ->assertOk()
            ->assertSee('name="selected_article_ids[]" value="'.$published->id.'" checked', false)
            ->assertSee('name="selected_article_ids[]" value="'.$draft->id.'"', false)
            ->assertDontSee('name="selected_article_ids[]" value="'.$draft->id.'" checked', false);
    }

    public function test_migration_selects_existing_published_articles_and_leaves_drafts_unselected(): void
    {
        [, $superblog] = $this->makeAssignedSuperblog();
        $published = $this->makeArticle($superblog, 'Legacy published', status: 'published', publishedAt: now());
        $draft = $this->makeArticle($superblog, 'Legacy draft');

        Schema::table('articles', fn ($table) => $table->dropColumn('is_selected_for_publication'));

        $migration = require base_path('database/migrations/2026_11_04_000001_add_publication_selection_to_articles.php');
        $migration->up();

        $this->assertDatabaseHas('articles', ['id' => $published->id, 'is_selected_for_publication' => true]);
        $this->assertDatabaseHas('articles', ['id' => $draft->id, 'is_selected_for_publication' => false]);
    }

    public function test_saving_selection_on_an_offline_superblog_does_not_change_article_publication(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $publishedAt = now()->subDay()->startOfSecond();
        $published = $this->makeArticle($superblog, 'Published article', status: 'published', selected: true, publishedAt: $publishedAt);
        $draft = $this->makeArticle($superblog, 'Draft article');

        $this->actingAs($staff)
            ->put(route('admin.superblogs.articles.selection.update', [$superblog, 'flik' => 'innehall']), [
                'visible_article_ids' => [$published->id, $draft->id],
                'selected_article_ids' => [$draft->id],
            ])
            ->assertRedirect(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall', 'manual_page' => 1, 'rss_page' => 1]))
            ->assertSessionHas('status', 'Article selection saved for the current page. The Superblog is offline, so article publication was not changed.');

        $published->refresh();
        $draft->refresh();

        $this->assertFalse($published->is_selected_for_publication);
        $this->assertTrue($draft->is_selected_for_publication);
        $this->assertSame('published', $published->status);
        $this->assertTrue($publishedAt->equalTo($published->published_at));
        $this->assertSame('draft', $draft->status);
        $this->assertNull($draft->published_at);
    }

    public function test_saving_selection_on_a_live_superblog_publishes_and_withdraws_only_current_page_articles(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $superblog->update(['status' => 'published']);

        $offPageDate = now()->subDays(5)->startOfSecond();
        $offPageArticle = $this->makeArticle($superblog, 'Other page published', status: 'published', selected: true, publishedAt: $offPageDate);

        for ($index = 0; $index < 16; $index++) {
            $this->makeArticle($superblog, 'Earlier filler '.$index);
        }

        $publishedDate = now()->subDays(3)->startOfSecond();
        $keepPublished = $this->makeArticle($superblog, 'Keep published', status: 'published', selected: true, publishedAt: $publishedDate);
        $withdrawPublished = $this->makeArticle($superblog, 'Withdraw published', status: 'published', selected: true, publishedAt: now()->subDay());
        $publishDraft = $this->makeArticle($superblog, 'Publish draft');
        $leaveDraft = $this->makeArticle($superblog, 'Leave draft');

        $page = $this->actingAs($staff)
            ->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall', 'manual_page' => 1]))
            ->assertOk()
            ->assertSee('manual_page=2', false)
            ->viewData('manualArticles');
        $visibleIds = $page->getCollection()->modelKeys();

        $this->assertContains($keepPublished->getKey(), $visibleIds);
        $this->assertContains($withdrawPublished->getKey(), $visibleIds);
        $this->assertContains($publishDraft->getKey(), $visibleIds);
        $this->assertContains($leaveDraft->getKey(), $visibleIds);
        $this->assertNotContains($offPageArticle->getKey(), $visibleIds);

        $this->put(route('admin.superblogs.articles.selection.update', [$superblog, 'flik' => 'innehall', 'manual_page' => 1, 'rss_page' => 1]), [
            'visible_article_ids' => $visibleIds,
            'selected_article_ids' => [$keepPublished->getKey(), $publishDraft->getKey()],
        ])->assertRedirect(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall', 'manual_page' => 1, 'rss_page' => 1]))
            ->assertSessionHas('status', 'Article selection saved and applied to publication for the current page.');

        $this->assertSame('published', $keepPublished->fresh()->status);
        $this->assertTrue($publishedDate->equalTo($keepPublished->fresh()->published_at));
        $this->assertTrue($keepPublished->fresh()->is_selected_for_publication);

        $this->assertSame('published', $publishDraft->fresh()->status);
        $this->assertNotNull($publishDraft->fresh()->published_at);
        $this->assertTrue($publishDraft->fresh()->is_selected_for_publication);

        $this->assertSame('draft', $withdrawPublished->fresh()->status);
        $this->assertNull($withdrawPublished->fresh()->published_at);
        $this->assertFalse($withdrawPublished->fresh()->is_selected_for_publication);
        $this->assertSame('draft', $leaveDraft->fresh()->status);
        $this->assertNull($leaveDraft->fresh()->published_at);
        $this->assertFalse($leaveDraft->fresh()->is_selected_for_publication);

        $this->assertSame('published', $offPageArticle->fresh()->status);
        $this->assertTrue($offPageArticle->fresh()->is_selected_for_publication);
        $this->assertTrue($offPageDate->equalTo($offPageArticle->fresh()->published_at));

        $pageTwoResponse = $this->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall', 'manual_page' => 2, 'rss_page' => 1]))
            ->assertOk();
        $pageTwo = $pageTwoResponse->viewData('manualArticles');
        $this->assertSame(2, $pageTwo->currentPage());
        $pageTwoResponse->assertSee('action="'.e(route('admin.superblogs.articles.selection.update', [$superblog, 'flik' => 'innehall', 'manual_page' => 2, 'rss_page' => 1])).'"', false);
    }

    public function test_manual_and_rss_sections_keep_independent_pagination_and_selections(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $manualArticles = [];
        $rssArticles = [];

        for ($index = 1; $index <= 11; $index++) {
            $manualArticles[] = $this->makeArticle($superblog, 'Manual pagination '.$index);
        }

        for ($index = 1; $index <= 6; $index++) {
            $rssArticles[] = $this->makeArticle($superblog, 'RSS pagination '.$index, sourceType: 'rss', selected: true);
        }

        $response = $this->actingAs($staff)
            ->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall']))
            ->assertOk()
            ->assertSee('manual_page=2', false)
            ->assertSee('rss_page=2', false);

        $this->assertSame(1, $response->viewData('manualArticles')->currentPage());
        $this->assertSame(1, $response->viewData('rssArticles')->currentPage());
        $this->assertCount(5, $response->viewData('manualArticles'));
        $this->assertCount(5, $response->viewData('rssArticles'));
        $this->assertSame(11, $response->viewData('manualArticles')->total());
        $this->assertSame(6, $response->viewData('rssArticles')->total());

        $html = $response->getContent();
        $manualListEnd = strpos($html, '</ul>', strpos($html, 'id="manual-articles-heading"'));
        $manualPagination = strpos($html, 'manual_page=2', $manualListEnd);
        $manualSave = strpos($html, 'Spara urval', $manualPagination);
        $rssListEnd = strpos($html, '</ul>', strpos($html, 'id="rss-articles-heading"'));
        $rssPagination = strpos($html, 'rss_page=2', $rssListEnd);
        $rssSave = strpos($html, 'Spara urval', $rssPagination);

        $this->assertNotFalse($manualListEnd);
        $this->assertNotFalse($manualPagination);
        $this->assertNotFalse($manualSave);
        $this->assertLessThan($manualSave, $manualPagination);
        $this->assertNotFalse($rssListEnd);
        $this->assertNotFalse($rssPagination);
        $this->assertNotFalse($rssSave);
        $this->assertLessThan($rssSave, $rssPagination);

        $pageTwoResponse = $this->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall', 'manual_page' => 2, 'rss_page' => 1]))
            ->assertOk();
        $manualPageTwo = $pageTwoResponse->viewData('manualArticles');
        $this->assertSame(2, $manualPageTwo->currentPage());
        $this->assertSame(1, $pageTwoResponse->viewData('rssArticles')->currentPage());
        $visibleManualIds = $manualPageTwo->getCollection()->modelKeys();
        $this->assertCount(5, $visibleManualIds);
        $visibleRssIds = $pageTwoResponse->viewData('rssArticles')->getCollection()->modelKeys();
        $this->assertCount(5, $visibleRssIds);

        $this->put(route('admin.superblogs.articles.selection.update', [$superblog, 'flik' => 'innehall', 'manual_page' => 2, 'rss_page' => 1]), [
            'visible_article_ids' => $visibleManualIds,
            'selected_article_ids' => [$manualArticles[5]->getKey()],
        ])->assertRedirect(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall', 'manual_page' => 2, 'rss_page' => 1]));

        $this->assertFalse($manualArticles[0]->fresh()->is_selected_for_publication);
        $this->assertTrue($manualArticles[5]->fresh()->is_selected_for_publication);
        foreach (array_slice($manualArticles, 1, 4) as $article) {
            $this->assertFalse($article->fresh()->is_selected_for_publication);
        }
        foreach (array_slice($manualArticles, 6) as $article) {
            $this->assertFalse($article->fresh()->is_selected_for_publication);
        }

        foreach ($rssArticles as $article) {
            $this->assertTrue($article->fresh()->is_selected_for_publication);
        }

        $pageTwoResponse = $this->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall', 'manual_page' => 2, 'rss_page' => 1]))
            ->assertOk();
        $this->assertSame(2, $pageTwoResponse->viewData('manualArticles')->currentPage());
        $this->assertSame(1, $pageTwoResponse->viewData('rssArticles')->currentPage());

        $rssPageTwoResponse = $this->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall', 'manual_page' => 1, 'rss_page' => 2]))
            ->assertOk();
        $this->assertSame(1, $rssPageTwoResponse->viewData('manualArticles')->currentPage());
        $this->assertSame(2, $rssPageTwoResponse->viewData('rssArticles')->currentPage());
        $this->assertCount(1, $rssPageTwoResponse->viewData('rssArticles'));
    }

    public function test_contents_pagination_is_compact_and_shows_all_pages_only_when_there_are_seven_or_fewer(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();

        for ($index = 1; $index <= 40; $index++) {
            $this->makeArticle($superblog, 'Manual pagination UI '.$index);
        }

        for ($index = 1; $index <= 35; $index++) {
            $this->makeArticle($superblog, 'RSS pagination UI '.$index, sourceType: 'rss');
        }

        $response = $this->actingAs($staff)
            ->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall']))
            ->assertOk()
            ->assertDontSee('Showing')
            ->assertSee('w-full min-w-0 max-w-full overflow-hidden', false)
            ->assertSee('min-h-8 min-w-8 shrink-0', false)
            ->assertSee('px-2 py-1.5 text-sm', false)
            ->assertSee('border-2 border-studio-brand bg-studio-brand', false);

        preg_match_all('/<nav role="navigation" aria-label="Pagination Navigation".*?<\/nav>/s', $response->getContent(), $matches);
        $this->assertCount(2, $matches[0]);

        $manualPages = $response->viewData('manualArticles');
        $rssPages = $response->viewData('rssArticles');
        $manualNavigation = $matches[0][0];
        $rssNavigation = $matches[0][1];

        $this->assertStringContainsString('aria-current="page"', $manualNavigation);
        foreach ([2, 3, 4, 8] as $page) {
            $this->assertStringContainsString('href="'.e($manualPages->url($page)).'" data-pagination-section="manual"', $manualNavigation);
        }
        $this->assertStringContainsString('…', $manualNavigation);
        $this->assertStringNotContainsString('href="'.e($manualPages->url(5)).'" data-pagination-section="manual"', $manualNavigation);
        $this->assertStringNotContainsString('#manual-articles-heading', $manualNavigation);

        $this->assertStringContainsString('aria-current="page"', $rssNavigation);
        foreach (range(2, 7) as $page) {
            $this->assertStringContainsString('href="'.e($rssPages->url($page)).'" data-pagination-section="rss"', $rssNavigation);
        }
        $this->assertStringNotContainsString('#rss-articles-heading', $rssNavigation);
        $this->assertStringNotContainsString('…', $rssNavigation);
    }

    public function test_compact_pagination_keeps_the_current_page_visible_at_the_beginning_middle_and_end(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();

        for ($index = 1; $index <= 40; $index++) {
            $this->makeArticle($superblog, 'Pagination window '.$index);
        }

        $this->actingAs($staff);

        foreach ([
            1 => ['links' => [2, 3, 4, 8], 'ellipses' => 1],
            5 => ['links' => [1, 4, 6, 8], 'ellipses' => 2],
            6 => ['links' => [1, 7, 8], 'ellipses' => 1],
            8 => ['links' => [1, 6, 7], 'ellipses' => 1],
        ] as $page => $expected) {
            $response = $this->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall', 'manual_page' => $page, 'rss_page' => 1]))
                ->assertOk();

            preg_match('/<nav role="navigation" aria-label="Pagination Navigation".*?<\/nav>/s', $response->getContent(), $match);
            $this->assertNotEmpty($match);

            $navigation = $match[0];
            $paginator = $response->viewData('manualArticles');
            $this->assertSame($page, $paginator->currentPage());
            $this->assertMatchesRegularExpression('/<span aria-current="page"[^>]*>'.$page.'<\/span>/', $navigation);
            $this->assertSame($expected['ellipses'], substr_count($navigation, '…'));

            foreach ($expected['links'] as $linkedPage) {
                $this->assertStringContainsString('href="'.e($paginator->url($linkedPage)).'" data-pagination-section="manual"', $navigation);
            }
        }
    }

    public function test_ajax_pagination_returns_only_the_requested_article_section(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();

        for ($index = 1; $index <= 6; $index++) {
            $this->makeArticle($superblog, 'Manual partial '.$index);
            $this->makeArticle($superblog, 'RSS partial '.$index, sourceType: 'rss');
        }

        $this->actingAs($staff)
            ->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall', 'manual_page' => 2, 'rss_page' => 1]), [
                'X-Requested-With' => 'XMLHttpRequest',
                'X-Pagination-Section' => 'manual',
            ])
            ->assertOk()
            ->assertSee('id="manual-articles-section"', false)
            ->assertSee('Manual partial 1')
            ->assertDontSee('id="rss-articles-section"', false)
            ->assertDontSee('RSS partial');

        $this->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall', 'manual_page' => 1, 'rss_page' => 2]), [
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Pagination-Section' => 'rss',
        ])
            ->assertOk()
            ->assertSee('id="rss-articles-section"', false)
            ->assertSee('RSS partial 1')
            ->assertDontSee('id="manual-articles-section"', false)
            ->assertDontSee('Manual partial');
    }

    public function test_articles_from_another_superblog_cannot_be_selected_or_modified(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $otherOwner = $this->makeApprovedUser(superAdmin: true);
        $otherSuperblog = $this->makeSuperblog($otherOwner, 'Other Superblog');
        $otherArticle = $this->makeArticle($otherSuperblog, 'Private draft');
        $ownArticle = $this->makeArticle($superblog, 'Own draft');

        $this->actingAs($staff)
            ->from(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall']))
            ->put(route('admin.superblogs.articles.selection.update', $superblog), [
                'visible_article_ids' => [$ownArticle->id, $otherArticle->id],
                'selected_article_ids' => [$otherArticle->id],
            ])
            ->assertRedirect(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall']))
            ->assertSessionHasErrors('visible_article_ids.1');

        $this->assertFalse($ownArticle->fresh()->is_selected_for_publication);
        $this->assertFalse($otherArticle->fresh()->is_selected_for_publication);
        $this->assertSame('draft', $otherArticle->fresh()->status);
    }

    public function test_unassigned_user_cannot_save_article_selection(): void
    {
        $owner = $this->makeApprovedUser(superAdmin: true);
        $superblog = $this->makeSuperblog($owner, 'Private Superblog');
        $article = $this->makeArticle($superblog, 'Private draft');
        $unassigned = $this->makeApprovedUser();

        $this->actingAs($unassigned)
            ->put(route('admin.superblogs.articles.selection.update', $superblog), [
                'visible_article_ids' => [$article->id],
                'selected_article_ids' => [$article->id],
            ])
            ->assertForbidden();

        $this->assertFalse($article->fresh()->is_selected_for_publication);
    }

    public function test_global_articles_overview_remains_unchanged(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $article = $this->makeArticle($superblog, 'Central article');

        $this->actingAs($staff)
            ->get(route('admin.articles.index'))
            ->assertOk()
            ->assertSee('Central article')
            ->assertDontSee('selected_article_ids[]', false)
            ->assertSee(route('admin.superblogs.articles.edit', [$superblog, $article]), false);
    }

    private function makeAssignedSuperblog(?User $staff = null, string $name = 'Selection Superblog'): array
    {
        $owner = $this->makeApprovedUser(superAdmin: true);
        $staff ??= $this->makeApprovedUser();
        $superblog = $this->makeSuperblog($owner, $name);
        $superblog->staff()->attach($staff->getKey());

        return [$staff, $superblog];
    }

    private function makeSuperblog(User $owner, string $name): Superblog
    {
        $template = Template::create([
            'name' => 'Selection template '.$name,
            'slug' => str($name)->slug().'-template',
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);

        return $owner->superblogs()->create([
            'template_id' => $template->id,
            'name' => $name,
            'slug' => str($name)->slug(),
        ]);
    }

    private function makeArticle(
        Superblog $superblog,
        string $title,
        string $status = 'draft',
        string $sourceType = 'manual',
        ?int $rssItemId = null,
        bool $selected = false,
        mixed $publishedAt = null,
    ): Article {
        return $superblog->articles()->create([
            'rss_item_id' => $rssItemId,
            'title' => $title,
            'slug' => str($title)->slug(),
            'content' => 'Article body',
            'source_type' => $sourceType,
            'status' => $status,
            'is_selected_for_publication' => $selected,
            'published_at' => $publishedAt,
        ]);
    }

    private function makeApprovedUser(bool $superAdmin = false): User
    {
        $user = User::factory()->create();
        $user->approval_status = 'approved';
        $user->is_super_admin = $superAdmin;
        $user->save();

        return $user;
    }
}
