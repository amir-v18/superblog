<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Superblog;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArticleDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_article_deletion_cascades_its_records_but_keeps_shared_media_file(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $article = $this->makeArticle($superblog, 'Manual article to delete');
        $otherArticle = $this->makeArticle($superblog, 'Other article uses same file');
        Storage::fake('public');
        Storage::disk('public')->put('articles/shared.jpg', 'image bytes');

        $articleLink = $article->articleLinks()->create([
            'type' => 'external',
            'url' => 'https://example.test/source',
            'anchor_text' => 'Original source',
        ]);
        $articleMedia = $article->media()->create([
            'path' => 'articles/shared.jpg',
            'filename' => 'shared.jpg',
            'mime_type' => 'image/jpeg',
        ]);
        $otherArticle->media()->create([
            'path' => 'articles/shared.jpg',
            'filename' => 'shared.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $this->actingAs($staff)
            ->delete(route('admin.superblogs.articles.destroy', [$superblog, $article, 'flik' => 'innehall']))
            ->assertRedirect(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall', 'manual_page' => 1, 'rss_page' => 1]))
            ->assertSessionHas('status', 'Artikeln har tagits bort permanent.');

        $this->assertDatabaseMissing('articles', ['id' => $article->id]);
        $this->assertDatabaseMissing('article_links', ['id' => $articleLink->id]);
        $this->assertDatabaseMissing('media', ['id' => $articleMedia->id]);
        $this->assertDatabaseHas('media', ['article_id' => $otherArticle->id, 'path' => 'articles/shared.jpg']);
        Storage::disk('public')->assertExists('articles/shared.jpg');
    }

    public function test_deleting_an_rss_article_preserves_its_original_item_and_source(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $source = $superblog->rssSources()->create([
            'name' => 'Local feed',
            'url' => 'https://feed.example.test/rss.xml',
            'is_active' => true,
        ]);
        $item = $source->rssItems()->create([
            'title' => 'Original RSS title',
            'url' => 'https://news.example.test/story',
            'content' => 'Preserved original source text.',
        ]);
        $article = $this->makeArticle($superblog, 'RSS article to delete', rssItemId: $item->id);

        $this->actingAs($staff)
            ->delete(route('admin.superblogs.articles.destroy', [$superblog, $article, 'flik' => 'innehall']))
            ->assertRedirect();

        $this->assertDatabaseMissing('articles', ['id' => $article->id]);
        $this->assertDatabaseHas('rss_items', ['id' => $item->id, 'content' => 'Preserved original source text.']);
        $this->assertDatabaseHas('rss_sources', ['id' => $source->id, 'superblog_id' => $superblog->id]);
    }

    public function test_unassigned_user_cannot_delete_an_article(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $article = $this->makeArticle($superblog, 'Protected article');
        $unassigned = $this->makeApprovedUser();

        $this->actingAs($unassigned)
            ->delete(route('admin.superblogs.articles.destroy', [$superblog, $article]))
            ->assertForbidden();

        $this->assertDatabaseHas('articles', ['id' => $article->id]);
    }

    public function test_user_cannot_delete_an_article_through_another_superblogs_url(): void
    {
        [$staff, $authorizedSuperblog] = $this->makeAssignedSuperblog();
        [, $otherSuperblog] = $this->makeAssignedSuperblog();
        $article = $this->makeArticle($otherSuperblog, 'Article in another Superblog');

        $this->actingAs($staff)
            ->delete(route('admin.superblogs.articles.destroy', [$authorizedSuperblog, $article]))
            ->assertNotFound();

        $this->assertDatabaseHas('articles', ['id' => $article->id, 'superblog_id' => $otherSuperblog->id]);
    }

    public function test_deleting_a_published_article_makes_its_public_url_return_404(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $superblog->update(['status' => 'published', 'domain' => 'article-delete.example.test']);
        $article = $this->makeArticle($superblog, 'Published article to delete', status: 'published', selected: true, publishedAt: now());
        $url = route('public.superblogs.articles.show', [$superblog, $article]);

        $this->actingAs($staff)->delete(route('admin.superblogs.articles.destroy', [$superblog, $article]))
            ->assertRedirect();
        $this->get($url)->assertNotFound();
    }

    public function test_deleting_an_article_refreshes_the_open_preview_content(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $article = $this->makeArticle($superblog, 'Preview must drop this deleted article', selected: true);

        $this->actingAs($staff)
            ->get(route('admin.superblogs.preview', $superblog))
            ->assertOk()
            ->assertSee('data-admin-preview-superblog-id="'.$superblog->getKey().'"', false)
            ->assertSee($article->title);

        $this->deleteJson(route('admin.superblogs.articles.destroy', [$superblog, $article, 'flik' => 'innehall']))
            ->assertOk()
            ->assertJsonPath('superblogId', $superblog->getKey());

        $this->get(route('admin.superblogs.preview', $superblog))
            ->assertOk()
            ->assertDontSee($article->title);
    }

    public function test_unchecking_an_article_preserves_it_in_the_database(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $article = $this->makeArticle($superblog, 'Unchecked but retained', selected: true);

        $this->actingAs($staff)
            ->put(route('admin.superblogs.articles.selection.update', [$superblog, 'flik' => 'innehall']), [
                'visible_article_ids' => [$article->id],
                'selected_article_ids' => [],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'is_selected_for_publication' => false,
        ]);
    }

    public function test_ajax_delete_refreshes_only_the_article_section_and_clamps_its_page(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        foreach (range(1, 6) as $number) {
            $manualArticles[] = $this->makeArticle($superblog, "Manual {$number}");
        }
        foreach (range(1, 6) as $number) {
            $rssArticles[] = $this->makeArticle($superblog, "RSS {$number}", sourceType: 'rss', selected: $number === 1);
        }
        $articleToDelete = $manualArticles[5];

        $response = $this->actingAs($staff)
            ->delete(route('admin.superblogs.articles.destroy', [
                $superblog,
                $articleToDelete,
                'flik' => 'innehall',
                'manual_page' => 2,
                'rss_page' => 2,
            ]), [], [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertOk()
            ->assertJson([
                'message' => 'Artikeln har tagits bort permanent.',
                'sectionKey' => 'manual',
                'currentPage' => 1,
            ]);

        $this->assertDatabaseMissing('articles', ['id' => $articleToDelete->id]);
        $this->assertDatabaseHas('articles', ['id' => $rssArticles[0]->id, 'is_selected_for_publication' => true]);

        $manualSection = $this->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall', 'manual_page' => 1, 'rss_page' => 2]), [
            'X-Pagination-Section' => 'manual',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();
        $this->assertStringContainsString('data-article-section="manual"', $manualSection->getContent());
        $this->assertStringNotContainsString('data-article-section="rss"', $manualSection->getContent());

        $rssSection = $this->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall', 'manual_page' => 1, 'rss_page' => 2]), [
            'X-Pagination-Section' => 'rss',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();
        $this->assertStringContainsString('data-article-section="rss"', $rssSection->getContent());
        $this->assertStringContainsString('checked', $rssSection->getContent());
        $this->assertSame('manual', $response->json('sectionKey'));
    }

    private function makeAssignedSuperblog(): array
    {
        static $number = 0;
        $number++;
        $owner = $this->makeApprovedUser(superAdmin: true);
        $staff = $this->makeApprovedUser();
        $template = Template::create([
            'name' => "Article deletion template {$number}",
            'slug' => "article-deletion-template-{$number}",
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);
        $superblog = $owner->superblogs()->create([
            'template_id' => $template->id,
            'name' => "Article deletion blog {$number}",
            'slug' => "article-deletion-blog-{$number}",
            'domain' => "article-deletion-{$number}.example.test",
        ]);
        $superblog->staff()->attach($staff->id);

        return [$staff, $superblog];
    }

    private function makeArticle(
        Superblog $superblog,
        string $title,
        string $sourceType = 'manual',
        string $status = 'draft',
        bool $selected = false,
        mixed $publishedAt = null,
        ?int $rssItemId = null,
    ): Article {
        static $number = 0;
        $number++;

        return $superblog->articles()->create([
            'rss_item_id' => $rssItemId,
            'title' => $title,
            'slug' => 'article-'.$number,
            'content' => 'Article content',
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
