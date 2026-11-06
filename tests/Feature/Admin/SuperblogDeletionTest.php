<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSuperblogs;
use Tests\TestCase;

class SuperblogDeletionTest extends TestCase
{
    use CreatesSuperblogs, RefreshDatabase;

    public function test_authorized_user_can_confirm_and_delete_a_superblog_and_its_dependent_records(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin);
        $assignedStaff = $this->makeUser(approved: true);
        $superblog->staff()->attach($assignedStaff);
        $source = $superblog->rssSources()->create([
            'name' => 'Local feed',
            'url' => 'https://feed.example.test/rss.xml',
            'is_active' => true,
        ]);
        $item = $source->rssItems()->create([
            'title' => 'Original RSS item',
            'url' => 'https://news.example.test/item',
            'content' => 'Original source content.',
        ]);
        $article = $superblog->articles()->create([
            'rss_item_id' => $item->id,
            'title' => 'Article to cascade',
            'slug' => 'article-to-cascade',
            'content' => 'Article content.',
            'source_type' => 'rss',
            'status' => 'draft',
        ]);
        $link = $article->articleLinks()->create([
            'type' => 'external',
            'url' => 'https://source.example.test',
            'anchor_text' => 'Source',
        ]);

        $otherSuperblog = $admin->superblogs()->create([
            'template_id' => $superblog->template_id,
            'name' => 'Other blog',
            'slug' => 'other-blog',
        ]);
        $otherArticle = $otherSuperblog->articles()->create([
            'title' => 'Other blog article',
            'slug' => 'other-blog-article',
            'content' => 'Other article content.',
            'source_type' => 'manual',
            'status' => 'draft',
        ]);
        Storage::fake('public');
        Storage::disk('public')->put('shared/image.jpg', 'shared image');
        $media = $article->media()->create([
            'path' => 'shared/image.jpg',
            'filename' => 'image.jpg',
            'mime_type' => 'image/jpeg',
        ]);
        $otherMedia = $otherArticle->media()->create([
            'path' => 'shared/image.jpg',
            'filename' => 'image.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.superblogs.edit', $superblog))
            ->assertOk()
            ->assertSee(route('admin.superblogs.delete', $superblog), false);

        $this->get(route('admin.superblogs.delete', $superblog))
            ->assertOk()
            ->assertSee($superblog->name)
            ->assertSee('kan inte ångras');

        $this->delete(route('admin.superblogs.destroy', $superblog))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('status', 'Superblog deleted.');

        $this->assertDatabaseMissing('superblogs', ['id' => $superblog->id]);
        $this->assertDatabaseMissing('superblog_user', ['superblog_id' => $superblog->id]);
        $this->assertDatabaseMissing('rss_sources', ['id' => $source->id]);
        $this->assertDatabaseMissing('rss_items', ['id' => $item->id]);
        $this->assertDatabaseMissing('articles', ['id' => $article->id]);
        $this->assertDatabaseMissing('article_links', ['id' => $link->id]);
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        $this->assertDatabaseHas('superblogs', ['id' => $otherSuperblog->id]);
        $this->assertDatabaseHas('articles', ['id' => $otherArticle->id]);
        $this->assertDatabaseHas('media', ['id' => $otherMedia->id]);
        Storage::disk('public')->assertExists('shared/image.jpg');
        $this->get('/superblogs/'.$superblog->slug)->assertNotFound();
        $this->get(route('public.superblogs.articles.show', [$superblog, $article]))->assertNotFound();
    }

    public function test_user_without_superblog_access_cannot_open_or_submit_deletion(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin);
        $staff = $this->makeUser(approved: true);

        $this->actingAs($staff)
            ->get(route('admin.superblogs.delete', $superblog))
            ->assertForbidden();

        $this->delete(route('admin.superblogs.destroy', $superblog))
            ->assertForbidden();

        $this->assertDatabaseHas('superblogs', ['id' => $superblog->id]);
    }

    public function test_deletion_is_blocked_if_its_rss_item_is_referenced_by_another_superblogs_article(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin);
        $otherSuperblog = $admin->superblogs()->create([
            'template_id' => $superblog->template_id,
            'name' => 'Other blog',
            'slug' => 'other-blog-with-rss-reference',
        ]);
        $source = $superblog->rssSources()->create([
            'name' => 'Feed to preserve',
            'url' => 'https://feed.example.test/rss.xml',
            'is_active' => true,
        ]);
        $item = $source->rssItems()->create([
            'title' => 'Referenced item',
            'url' => 'https://news.example.test/item',
        ]);
        $otherArticle = $otherSuperblog->articles()->create([
            'rss_item_id' => $item->id,
            'title' => 'Other blog article',
            'slug' => 'other-blog-article',
            'content' => 'Keep this article and its source relationship.',
            'source_type' => 'rss',
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.superblogs.delete', $superblog))
            ->delete(route('admin.superblogs.destroy', $superblog))
            ->assertRedirect(route('admin.superblogs.delete', $superblog))
            ->assertSessionHasErrors('superblog');

        $this->assertDatabaseHas('superblogs', ['id' => $superblog->id]);
        $this->assertDatabaseHas('rss_items', ['id' => $item->id]);
        $this->assertDatabaseHas('articles', ['id' => $otherArticle->id, 'rss_item_id' => $item->id]);
    }
}
