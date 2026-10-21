<?php

namespace Tests\Feature\Admin;

use App\Models\Superblog;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RssSourceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_staff_can_list_create_edit_and_delete_rss_sources(): void
    {
        $owner = $this->makeUser();
        $staff = $this->makeUser();
        $superblog = $this->makeSuperblog($owner, 'assigned-blog');
        $otherSuperblog = $this->makeSuperblog($owner, 'other-blog');
        $superblog->staff()->attach($staff->getKey());
        $existing = $superblog->rssSources()->create([
            'name' => 'Existing feed',
            'url' => 'https://example.com/existing.xml',
            'is_active' => true,
        ]);
        $otherSuperblog->rssSources()->create([
            'name' => 'Private feed',
            'url' => 'https://example.com/private.xml',
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('admin.superblogs.rss-sources.index', $superblog))
            ->assertOk()
            ->assertSee('Existing feed')
            ->assertDontSee('Private feed');

        $this->get(route('admin.superblogs.rss-sources.create', $superblog))->assertOk();

        $this->post(route('admin.superblogs.rss-sources.store', $superblog), [
            'name' => 'New feed',
            'url' => 'https://example.com/feed.xml',
            'is_active' => '1',
            'superblog_id' => $otherSuperblog->getKey(),
        ])->assertRedirect(route('admin.superblogs.rss-sources.index', $superblog));

        $created = $superblog->rssSources()->where('name', 'New feed')->firstOrFail();
        $this->assertSame((int) $superblog->getKey(), (int) $created->superblog_id);

        $this->get(route('admin.superblogs.rss-sources.edit', [$superblog, $existing]))
            ->assertOk()
            ->assertSee('Existing feed');

        $this->put(route('admin.superblogs.rss-sources.update', [$superblog, $existing]), [
            'name' => 'Updated feed',
            'url' => 'https://example.com/updated.xml',
            'is_active' => '0',
            'superblog_id' => $otherSuperblog->getKey(),
        ])->assertRedirect(route('admin.superblogs.rss-sources.index', $superblog));

        $this->assertDatabaseHas('rss_sources', [
            'id' => $existing->getKey(),
            'superblog_id' => $superblog->getKey(),
            'name' => 'Updated feed',
            'is_active' => false,
        ]);

        $item = $created->rssItems()->create([
            'title' => 'Original item',
            'url' => 'https://example.com/article',
            'content' => 'Original feed content',
        ]);
        $article = $superblog->articles()->create([
            'rss_item_id' => $item->getKey(),
            'title' => 'Article derived from source',
            'slug' => 'article-derived-from-source',
            'content' => 'Article content',
            'source_type' => 'rss',
        ]);

        $this->get(route('admin.superblogs.rss-sources.delete', [$superblog, $created]))
            ->assertOk()
            ->assertSee('also deletes its stored RSS items')
            ->assertSee('their RSS item references are cleared');

        $this->delete(route('admin.superblogs.rss-sources.destroy', [$superblog, $created]))
            ->assertRedirect(route('admin.superblogs.rss-sources.index', $superblog));

        $this->assertDatabaseMissing('rss_sources', ['id' => $created->getKey()]);
        $this->assertDatabaseMissing('rss_items', ['id' => $item->getKey()]);
        $this->assertDatabaseHas('articles', ['id' => $article->getKey(), 'rss_item_id' => null]);
    }

    public function test_source_validation_rejects_missing_fields_invalid_urls_and_duplicates_per_superblog(): void
    {
        $owner = $this->makeUser();
        $staff = $this->makeUser();
        $superblog = $this->makeSuperblog($owner, 'validation-blog');
        $otherSuperblog = $this->makeSuperblog($owner, 'validation-other-blog');
        $superblog->staff()->attach($staff->getKey());
        $otherSuperblog->staff()->attach($staff->getKey());
        $url = 'https://example.com/feed.xml';
        $superblog->rssSources()->create([
            'name' => 'Existing feed',
            'url' => $url,
            'is_active' => true,
        ]);

        $this->actingAs($staff)->from(route('admin.superblogs.rss-sources.create', $superblog))
            ->post(route('admin.superblogs.rss-sources.store', $superblog), [
                'name' => '',
                'url' => 'not a URL',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.superblogs.rss-sources.create', $superblog))
            ->assertSessionHasErrors(['name', 'url']);

        $this->post(route('admin.superblogs.rss-sources.store', $superblog), [
            'name' => 'Duplicate feed',
            'url' => $url,
            'is_active' => '1',
        ])->assertSessionHasErrors('url');

        $this->post(route('admin.superblogs.rss-sources.store', $otherSuperblog), [
            'name' => 'Same URL on another Superblog',
            'url' => $url,
            'is_active' => '1',
        ])->assertRedirect(route('admin.superblogs.rss-sources.index', $otherSuperblog));

        $this->assertDatabaseCount('rss_sources', 2);
    }

    public function test_unassigned_staff_cannot_manage_a_superblogs_sources(): void
    {
        $owner = $this->makeUser();
        $unassigned = $this->makeUser();
        $superblog = $this->makeSuperblog($owner, 'protected-blog');
        $source = $superblog->rssSources()->create([
            'name' => 'Protected feed',
            'url' => 'https://example.com/protected.xml',
            'is_active' => true,
        ]);

        $this->actingAs($unassigned)
            ->get(route('admin.superblogs.rss-sources.index', $superblog))
            ->assertForbidden();

        $this->post(route('admin.superblogs.rss-sources.store', $superblog), [
            'name' => 'Unauthorized feed',
            'url' => 'https://example.com/unauthorized.xml',
            'is_active' => '1',
        ])->assertForbidden();

        $this->get(route('admin.superblogs.rss-sources.edit', [$superblog, $source]))->assertForbidden();
        $this->put(route('admin.superblogs.rss-sources.update', [$superblog, $source]), [
            'name' => 'Changed feed',
            'url' => 'https://example.com/changed.xml',
            'is_active' => '0',
        ])->assertForbidden();
        $this->get(route('admin.superblogs.rss-sources.delete', [$superblog, $source]))->assertForbidden();
        $this->delete(route('admin.superblogs.rss-sources.destroy', [$superblog, $source]))->assertForbidden();

        $this->assertDatabaseHas('rss_sources', [
            'id' => $source->getKey(),
            'name' => 'Protected feed',
            'url' => 'https://example.com/protected.xml',
            'is_active' => true,
        ]);
        $this->assertDatabaseCount('rss_sources', 1);
    }

    public function test_a_source_cannot_be_accessed_through_another_superblogs_route(): void
    {
        $owner = $this->makeUser();
        $staff = $this->makeUser();
        $allowed = $this->makeSuperblog($owner, 'allowed-blog');
        $notAllowed = $this->makeSuperblog($owner, 'not-allowed-blog');
        $allowed->staff()->attach($staff->getKey());
        $source = $notAllowed->rssSources()->create([
            'name' => 'Other blogs source',
            'url' => 'https://example.com/other.xml',
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('admin.superblogs.rss-sources.edit', [$allowed, $source]))
            ->assertNotFound();

        $this->put(route('admin.superblogs.rss-sources.update', [$allowed, $source]), [
            'name' => 'Tampered source',
            'url' => 'https://example.com/tampered.xml',
            'is_active' => '0',
        ])->assertNotFound();

        $this->delete(route('admin.superblogs.rss-sources.destroy', [$allowed, $source]))
            ->assertNotFound();

        $this->assertDatabaseHas('rss_sources', [
            'id' => $source->getKey(),
            'superblog_id' => $notAllowed->getKey(),
            'name' => 'Other blogs source',
        ]);
    }

    private function makeUser(): User
    {
        $user = User::factory()->create();
        $user->approval_status = 'approved';
        $user->is_super_admin = false;
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
}
