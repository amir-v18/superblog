<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\RssItem;
use App\Models\RssSource;
use App\Models\Superblog;
use App\Models\Template;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RssArticleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_one_draft_from_an_rss_item_without_changing_original_content(): void
    {
        [$staff, $superblog, $source, $item] = $this->makeAssignedSourceAndItem();

        $response = $this->actingAs($staff)
            ->post(route('admin.superblogs.rss-sources.items.article.store', [$superblog, $source, $item]));

        $article = Article::query()->where('rss_item_id', $item->getKey())->firstOrFail();
        $response->assertRedirect(route('admin.superblogs.articles.edit', [$superblog, $article]));

        $this->assertSame($superblog->getKey(), $article->superblog_id);
        $this->assertSame($item->getKey(), $article->rss_item_id);
        $this->assertSame('rss', $article->source_type);
        $this->assertSame('draft', $article->status);
        $this->assertTrue($article->is_selected_for_publication);
        $this->assertSame('Original feed content', $article->content);
        $this->assertSame('Original feed content', $item->fresh()->content);
        $this->assertSame('A source summary', $item->fresh()->summary);

        $this->post(route('admin.superblogs.rss-sources.items.article.store', [$superblog, $source, $item]))
            ->assertRedirect(route('admin.superblogs.articles.edit', [$superblog, $article]))
            ->assertSessionHas('status', 'The draft for this RSS item is already created.');

        $this->assertDatabaseCount('articles', 1);
    }

    public function test_item_page_offers_create_then_edit_for_its_article(): void
    {
        [$staff, $superblog, $source, $item] = $this->makeAssignedSourceAndItem();

        $this->actingAs($staff)
            ->get(route('admin.superblogs.rss-sources.items.index', [$superblog, $source]))
            ->assertOk()
            ->assertSee('Create article');

        $article = $superblog->articles()->create([
            'rss_item_id' => $item->getKey(),
            'title' => 'A draft',
            'slug' => 'a-draft',
            'content' => 'Draft body',
            'source_type' => 'rss',
        ]);

        $this->get(route('admin.superblogs.rss-sources.items.index', [$superblog, $source]))
            ->assertOk()
            ->assertSee('Edit draft article')
            ->assertSee(route('admin.superblogs.articles.edit', [$superblog, $article]), false)
            ->assertDontSee('Create article');
    }

    public function test_central_article_page_lists_created_articles_with_editor_link_and_empty_state(): void
    {
        [$staff, $superblog, $source, $item] = $this->makeAssignedSourceAndItem();

        $this->actingAs($staff)
            ->get(route('admin.articles.index'))
            ->assertOk()
            ->assertSee('No articles yet.');

        $this->post(route('admin.superblogs.rss-sources.items.article.store', [$superblog, $source, $item]))
            ->assertRedirect();
        $article = Article::query()->where('rss_item_id', $item->getKey())->firstOrFail();

        $this->get(route('admin.articles.index'))
            ->assertOk()
            ->assertSee($article->title)
            ->assertSee($superblog->name)
            ->assertSee('Draft')
            ->assertSee('RSS')
            ->assertSee(route('admin.superblogs.articles.edit', [$superblog, $article]), false);
    }

    public function test_central_article_page_only_lists_articles_for_accessible_superblogs(): void
    {
        $owner = $this->makeUser();
        $staff = $this->makeUser();
        $assignedBlog = $this->makeSuperblog($owner, 'assigned-blog');
        $unassignedBlog = $this->makeSuperblog($owner, 'unassigned-blog');
        $assignedBlog->staff()->attach($staff->getKey());
        $assignedArticle = $assignedBlog->articles()->create([
            'title' => 'Assigned article',
            'slug' => 'assigned-article',
            'content' => 'Body',
            'source_type' => 'manual',
        ]);
        $unassignedArticle = $unassignedBlog->articles()->create([
            'title' => 'Unassigned private article',
            'slug' => 'unassigned-private-article',
            'content' => 'Private body',
            'source_type' => 'manual',
        ]);

        $this->actingAs($staff)
            ->get(route('admin.articles.index'))
            ->assertOk()
            ->assertSee($assignedArticle->title)
            ->assertDontSee($unassignedArticle->title)
            ->assertDontSee($unassignedBlog->name);
    }

    public function test_generated_slug_is_unique_within_its_superblog(): void
    {
        [$staff, $superblog, $source, $item] = $this->makeAssignedSourceAndItem();
        $superblog->articles()->create([
            'title' => 'Existing use of slug',
            'slug' => 'a-source-story',
            'content' => 'Manual draft',
            'source_type' => 'manual',
        ]);

        $this->actingAs($staff)
            ->post(route('admin.superblogs.rss-sources.items.article.store', [$superblog, $source, $item]))
            ->assertRedirect();

        $article = Article::query()->where('rss_item_id', $item->getKey())->firstOrFail();
        $this->assertSame('a-source-story-2', $article->slug);
        $this->assertDatabaseCount('articles', 2);
    }

    public function test_unassigned_users_cannot_create_or_edit_articles(): void
    {
        [$owner, $superblog, $source, $item] = $this->makeAssignedSourceAndItem();
        $unassigned = $this->makeUser();
        $article = $superblog->articles()->create([
            'rss_item_id' => $item->getKey(),
            'title' => 'Private draft',
            'slug' => 'private-draft',
            'content' => 'Draft body',
            'source_type' => 'rss',
        ]);

        $this->actingAs($unassigned)
            ->post(route('admin.superblogs.rss-sources.items.article.store', [$superblog, $source, $item]))
            ->assertForbidden();
        $this->get(route('admin.superblogs.articles.edit', [$superblog, $article]))
            ->assertForbidden();
        $this->put(route('admin.superblogs.articles.update', [$superblog, $article]), [
            'title' => 'Changed',
            'slug' => 'changed',
            'content' => 'Changed body',
        ])->assertForbidden();

        $this->assertSame('Private draft', $article->fresh()->title);
    }

    public function test_rss_items_and_articles_cannot_be_crossed_between_superblogs(): void
    {
        $admin = $this->makeUser(superAdmin: true);
        $firstBlog = $this->makeSuperblog($admin, 'first-blog');
        $secondBlog = $this->makeSuperblog($admin, 'second-blog');
        $source = $this->makeSource($secondBlog);
        $item = $source->rssItems()->create(['title' => 'Other blog item', 'url' => 'https://news.example/other']);
        $article = $secondBlog->articles()->create([
            'rss_item_id' => $item->getKey(),
            'title' => 'Other blog draft',
            'slug' => 'other-blog-draft',
            'content' => 'Draft body',
            'source_type' => 'rss',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.superblogs.rss-sources.items.article.store', [$firstBlog, $source, $item]))
            ->assertNotFound();
        $this->get(route('admin.superblogs.articles.edit', [$firstBlog, $article]))
            ->assertNotFound();

        $this->assertDatabaseCount('articles', 1);
    }

    public function test_article_linked_to_an_item_from_another_superblog_cannot_be_opened_or_saved(): void
    {
        $admin = $this->makeUser(superAdmin: true);
        $articleBlog = $this->makeSuperblog($admin, 'article-blog');
        $sourceBlog = $this->makeSuperblog($admin, 'source-blog');
        $source = $this->makeSource($sourceBlog);
        $item = $source->rssItems()->create(['title' => 'Other blog source', 'url' => 'https://news.example/other-blog']);
        $article = $articleBlog->articles()->create([
            'rss_item_id' => $item->getKey(),
            'title' => 'Mismatched draft',
            'slug' => 'mismatched-draft',
            'content' => 'Draft body',
            'source_type' => 'rss',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.superblogs.articles.edit', [$articleBlog, $article]))
            ->assertNotFound();
        $this->put(route('admin.superblogs.articles.update', [$articleBlog, $article]), [
            'title' => 'Changed title',
            'slug' => 'changed-title',
            'content' => 'Changed body',
        ])->assertNotFound();

        $this->assertSame('Mismatched draft', $article->fresh()->title);
    }

    public function test_staff_can_edit_draft_fields_but_not_source_or_publication_state(): void
    {
        [$staff, $superblog, $source, $item] = $this->makeAssignedSourceAndItem();
        $article = $this->createDraft($staff, $superblog, $source, $item);

        $this->actingAs($staff)
            ->get(route('admin.superblogs.articles.edit', [$superblog, $article]))
            ->assertOk()
            ->assertSee('Original RSS source (read only)')
            ->assertSee('Original feed content');

        $this->put(route('admin.superblogs.articles.update', [$superblog, $article]), [
            'title' => 'Edited title',
            'slug' => 'edited-title',
            'content' => 'Edited article body',
            'excerpt' => 'Edited summary',
            'status' => 'published',
            'source_type' => 'manual',
            'rss_item_id' => null,
            'published_at' => now()->toDateTimeString(),
        ])->assertRedirect(route('admin.superblogs.articles.edit', [$superblog, $article]));

        $article->refresh();
        $this->assertSame('Edited title', $article->title);
        $this->assertSame('edited-title', $article->slug);
        $this->assertSame('Edited article body', $article->content);
        $this->assertSame('Edited summary', $article->excerpt);
        $this->assertSame('draft', $article->status);
        $this->assertSame('rss', $article->source_type);
        $this->assertSame($item->getKey(), $article->rss_item_id);
        $this->assertNull($article->published_at);
        $this->assertSame('Original feed content', $item->fresh()->content);
    }

    public function test_assigned_staff_can_publish_a_draft_directly_and_sets_published_at(): void
    {
        [$staff, $superblog, $source, $item] = $this->makeAssignedSourceAndItem();
        $article = $this->createDraft($staff, $superblog, $source, $item);

        $this->actingAs($staff)
            ->post(route('admin.superblogs.articles.publish', [$superblog, $article]))
            ->assertRedirect(route('admin.superblogs.articles.edit', [$superblog, $article]))
            ->assertSessionHas('status', 'Article published.');

        $article->refresh();
        $this->assertSame('published', $article->status);
        $this->assertNotNull($article->published_at);
        $this->assertSame('Original feed content', $item->fresh()->content);
    }

    public function test_super_admin_can_publish_an_article(): void
    {
        $superAdmin = $this->makeUser(superAdmin: true);
        $owner = $this->makeUser();
        $superblog = $this->makeSuperblog($owner, 'admin-publish-blog');
        $article = $superblog->articles()->create([
            'title' => 'Admin draft',
            'slug' => 'admin-draft',
            'content' => 'Article body',
            'source_type' => 'manual',
        ]);

        $this->actingAs($superAdmin)
            ->post(route('admin.superblogs.articles.publish', [$superblog, $article]))
            ->assertRedirect();

        $this->assertSame('published', $article->fresh()->status);
        $publishedAt = $article->fresh()->published_at;
        $this->put(route('admin.superblogs.articles.update', [$superblog, $article]), [
            'title' => 'Updated by super admin',
            'slug' => 'updated-by-super-admin',
            'content' => 'Updated article body',
        ])->assertRedirect();

        $this->assertSame('Updated by super admin', $article->fresh()->title);
        $this->assertSame('published', $article->fresh()->status);
        $this->assertTrue($publishedAt->equalTo($article->fresh()->published_at));
    }

    public function test_staff_cannot_publish_an_article_for_an_unassigned_superblog(): void
    {
        $owner = $this->makeUser();
        $staff = $this->makeUser();
        $superblog = $this->makeSuperblog($owner, 'unassigned-publish-blog');
        $article = $superblog->articles()->create([
            'title' => 'Private draft',
            'slug' => 'private-draft',
            'content' => 'Article body',
            'source_type' => 'manual',
        ]);

        $this->actingAs($staff)
            ->post(route('admin.superblogs.articles.publish', [$superblog, $article]))
            ->assertForbidden();

        $this->assertSame('draft', $article->fresh()->status);
        $this->assertNull($article->fresh()->published_at);
    }

    public function test_published_article_remains_editable_without_changing_status_or_publication_time(): void
    {
        [$staff, $superblog, $source, $item] = $this->makeAssignedSourceAndItem();
        $article = $this->createDraft($staff, $superblog, $source, $item);

        $this->actingAs($staff)
            ->post(route('admin.superblogs.articles.publish', [$superblog, $article]))
            ->assertRedirect(route('admin.superblogs.articles.edit', [$superblog, $article]));
        $publishedAt = $article->fresh()->published_at;

        $this->get(route('admin.superblogs.articles.edit', [$superblog, $article]))
            ->assertOk()
            ->assertSee('Edit article')
            ->assertSee('Save changes')
            ->assertDontSee('Publish now');

        $this->put(route('admin.superblogs.articles.update', [$superblog, $article]), [
            'title' => 'Updated published title',
            'slug' => 'updated-published-title',
            'content' => 'Updated published body',
            'excerpt' => 'Updated published excerpt',
            'status' => 'draft',
            'published_at' => null,
        ])->assertRedirect(route('admin.superblogs.articles.edit', [$superblog, $article]));

        $article->refresh();
        $this->assertSame('Updated published title', $article->title);
        $this->assertSame('Updated published body', $article->content);
        $this->assertSame('published', $article->status);
        $this->assertTrue($publishedAt->equalTo($article->published_at));

        $this->post(route('admin.superblogs.articles.publish', [$superblog, $article]))
            ->assertSessionHasErrors('status');
        $this->assertSame('published', $article->fresh()->status);
        $this->assertTrue($publishedAt->equalTo($article->fresh()->published_at));
    }

    public function test_article_validation_rejects_missing_fields_and_duplicate_blog_slugs(): void
    {
        [$staff, $superblog, $source, $item] = $this->makeAssignedSourceAndItem();
        $article = $this->createDraft($staff, $superblog, $source, $item);
        $other = $superblog->articles()->create([
            'title' => 'Another article',
            'slug' => 'taken-slug',
            'content' => 'Body',
            'source_type' => 'manual',
        ]);

        $this->actingAs($staff)
            ->from(route('admin.superblogs.articles.edit', [$superblog, $article]))
            ->put(route('admin.superblogs.articles.update', [$superblog, $article]), [
                'title' => '',
                'slug' => 'taken-slug',
                'content' => '',
                'excerpt' => str_repeat('x', 65536),
            ])
            ->assertRedirect(route('admin.superblogs.articles.edit', [$superblog, $article]))
            ->assertSessionHasErrors(['title', 'slug', 'content', 'excerpt']);

        $this->assertDatabaseHas('articles', ['id' => $other->getKey(), 'slug' => 'taken-slug']);
        $this->assertSame('A source story', $article->fresh()->title);
    }

    public function test_database_allows_multiple_manual_articles_with_null_rss_item_but_rejects_duplicate_source_link(): void
    {
        [$staff, $superblog, $source, $item] = $this->makeAssignedSourceAndItem();
        $article = $this->createDraft($staff, $superblog, $source, $item);

        try {
            $superblog->articles()->create([
                'rss_item_id' => $item->getKey(),
                'title' => 'Duplicate source article',
                'slug' => 'duplicate-source-article',
                'content' => 'Another body',
                'source_type' => 'rss',
            ]);
            $this->fail('The database should enforce one article per RSS item.');
        } catch (QueryException) {
            $this->assertDatabaseCount('articles', 1);
        }

        $manualOne = $superblog->articles()->create([
            'title' => 'Manual one',
            'slug' => 'manual-one',
            'content' => 'Manual content one',
            'source_type' => 'manual',
        ]);
        $manualTwo = $superblog->articles()->create([
            'title' => 'Manual two',
            'slug' => 'manual-two',
            'content' => 'Manual content two',
            'source_type' => 'manual',
        ]);

        $this->assertNull($manualOne->rss_item_id);
        $this->assertNull($manualTwo->rss_item_id);
        $this->assertDatabaseHas('articles', ['id' => $article->getKey(), 'rss_item_id' => $item->getKey()]);
    }

    private function makeAssignedSourceAndItem(): array
    {
        $owner = $this->makeUser();
        $staff = $this->makeUser();
        $superblog = $this->makeSuperblog($owner, 'article-workflow-blog');
        $superblog->staff()->attach($staff->getKey());
        $source = $this->makeSource($superblog);
        $item = $source->rssItems()->create([
            'title' => 'A source story',
            'url' => 'https://news.example/story',
            'content' => 'Original feed content',
            'summary' => 'A source summary',
        ]);

        return [$staff, $superblog, $source, $item];
    }

    private function createDraft(User $staff, Superblog $superblog, RssSource $source, RssItem $item): Article
    {
        $this->actingAs($staff)
            ->post(route('admin.superblogs.rss-sources.items.article.store', [$superblog, $source, $item]))
            ->assertRedirect();

        return Article::query()->where('rss_item_id', $item->getKey())->firstOrFail();
    }

    private function makeUser(bool $superAdmin = false): User
    {
        $user = User::factory()->create();
        $user->approval_status = 'approved';
        $user->is_super_admin = $superAdmin;
        $user->has_all_superblogs_access = false;
        $user->save();

        return $user;
    }

    private function makeSuperblog(User $owner, string $slug): Superblog
    {
        $template = Template::create([
            'name' => "Template {$slug}",
            'slug' => "template-{$slug}",
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);

        return $owner->superblogs()->create([
            'template_id' => $template->getKey(),
            'name' => str($slug)->replace('-', ' ')->title()->toString(),
            'slug' => $slug,
        ]);
    }

    private function makeSource(Superblog $superblog): RssSource
    {
        return $superblog->rssSources()->create([
            'name' => 'Source',
            'url' => 'https://feeds.example.com/feed.xml',
            'is_active' => true,
        ]);
    }
}
