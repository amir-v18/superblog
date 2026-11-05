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
            ->assertRedirect(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall', 'page' => 1]))
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
            ->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall', 'page' => 1]))
            ->assertOk()
            ->assertSee('page=2', false)
            ->viewData('articles');
        $visibleIds = $page->getCollection()->modelKeys();

        $this->assertContains($keepPublished->getKey(), $visibleIds);
        $this->assertContains($withdrawPublished->getKey(), $visibleIds);
        $this->assertContains($publishDraft->getKey(), $visibleIds);
        $this->assertContains($leaveDraft->getKey(), $visibleIds);
        $this->assertNotContains($offPageArticle->getKey(), $visibleIds);

        $this->put(route('admin.superblogs.articles.selection.update', [$superblog, 'flik' => 'innehall', 'page' => 1]), [
            'visible_article_ids' => $visibleIds,
            'selected_article_ids' => [$keepPublished->getKey(), $publishDraft->getKey()],
        ])->assertRedirect(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall', 'page' => 1]))
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

        $pageTwoResponse = $this->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall', 'page' => 2]))
            ->assertOk();
        $pageTwo = $pageTwoResponse->viewData('articles');
        $this->assertSame(2, $pageTwo->currentPage());
        $pageTwoResponse->assertSee('action="'.e(route('admin.superblogs.articles.selection.update', [$superblog, 'flik' => 'innehall', 'page' => 2])).'"', false);
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
