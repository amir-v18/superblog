<?php

namespace Tests\Feature\Admin;

use App\Models\RssSource;
use App\Models\Superblog;
use App\Models\Template;
use App\Models\User;
use App\Support\RssFeedDnsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RssFetchingAndItemsTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetch_imports_original_content_and_repeated_fetch_does_not_duplicate_items(): void
    {
        $staff = $this->makeUser();
        $superblog = $this->makeSuperblog($staff, 'fetch-blog');
        $superblog->staff()->attach($staff->getKey());
        $source = $this->makeSource($superblog);
        $this->allowPublicFeedHost();
        Http::fake(['*' => Http::response($this->feed(), 200)]);

        $this->actingAs($staff)
            ->post(route('admin.superblogs.rss-sources.fetch', [$superblog, $source]))
            ->assertRedirect(route('admin.superblogs.rss-sources.index', $superblog))
            ->assertSessionHas('status', 'Fetch completed. 1 new item imported.');

        $item = $source->rssItems()->firstOrFail();
        $this->assertSame('A story', $item->title);
        $this->assertSame('The original feed body', $item->content);
        $this->assertSame('A short summary', $item->summary);
        $this->assertNotNull($source->fresh()->last_fetched_at);

        $this->post(route('admin.superblogs.rss-sources.fetch', [$superblog, $source]))
            ->assertSessionHas('status', 'Fetch completed. 0 new items imported.');

        $this->assertDatabaseCount('rss_items', 1);
    }

    public function test_invalid_or_private_feed_targets_are_rejected_without_a_request(): void
    {
        $staff = $this->makeUser();
        $superblog = $this->makeSuperblog($staff, 'private-blog');
        $superblog->staff()->attach($staff->getKey());
        $source = $this->makeSource($superblog, 'http://127.0.0.1/feed.xml');
        Http::preventStrayRequests();

        $this->actingAs($staff)
            ->post(route('admin.superblogs.rss-sources.fetch', [$superblog, $source]))
            ->assertRedirect(route('admin.superblogs.rss-sources.index', $superblog))
            ->assertSessionHas('error', 'This feed URL resolves to a private or reserved network address.');

        $metadataHost = $this->makeSource($superblog, 'http://metadata.google.internal/latest/meta-data/');
        $this->post(route('admin.superblogs.rss-sources.fetch', [$superblog, $metadataHost]))
            ->assertSessionHas('error', 'This feed URL is not allowed. Use a public HTTP or HTTPS feed URL.');

        $this->mock(RssFeedDnsResolver::class)
            ->shouldReceive('resolve')
            ->with('private.example.com')
            ->andReturn(['169.254.169.254']);
        $privateDns = $this->makeSource($superblog, 'https://private.example.com/feed.xml');
        $this->post(route('admin.superblogs.rss-sources.fetch', [$superblog, $privateDns]))
            ->assertSessionHas('error', 'This feed URL resolves to a private or reserved network address.');

        Http::assertNothingSent();
    }

    public function test_malformed_feed_and_network_failure_show_safe_errors(): void
    {
        $staff = $this->makeUser();
        $superblog = $this->makeSuperblog($staff, 'failure-blog');
        $superblog->staff()->attach($staff->getKey());
        $source = $this->makeSource($superblog);
        $this->allowPublicFeedHost();
        Http::fake(['*' => Http::response('<rss><channel><item>', 200)]);

        $this->actingAs($staff)
            ->post(route('admin.superblogs.rss-sources.fetch', [$superblog, $source]))
            ->assertSessionHas('error', 'The feed contains malformed XML.');

        Http::fake(['*' => Http::failedConnection()]);

        $this->post(route('admin.superblogs.rss-sources.fetch', [$superblog, $source]))
            ->assertSessionHas('error', 'The feed could not be reached. Check its URL and try again.');
        $this->assertDatabaseCount('rss_items', 0);
    }

    public function test_large_and_non_feed_responses_are_rejected(): void
    {
        $staff = $this->makeUser();
        $superblog = $this->makeSuperblog($staff, 'large-feed-blog');
        $superblog->staff()->attach($staff->getKey());
        $source = $this->makeSource($superblog);
        $this->allowPublicFeedHost();
        Http::fake([
            '*' => Http::sequence()
                ->push('', 200, ['Content-Length' => '2097153'])
                ->push('<html><body>not a feed</body></html>', 200),
        ]);

        $this->actingAs($staff)
            ->post(route('admin.superblogs.rss-sources.fetch', [$superblog, $source]))
            ->assertSessionHas('error', 'The feed is larger than the allowed 2 MB limit.');

        $this->post(route('admin.superblogs.rss-sources.fetch', [$superblog, $source]))
            ->assertSessionHas('error', 'The response is not a supported RSS or Atom feed.');
        $this->assertDatabaseCount('rss_items', 0);
    }

    public function test_users_cannot_fetch_or_view_items_for_unassigned_or_other_superblogs(): void
    {
        $owner = $this->makeUser();
        $staff = $this->makeUser();
        $allowed = $this->makeSuperblog($owner, 'allowed-rss-blog');
        $other = $this->makeSuperblog($owner, 'other-rss-blog');
        $allowed->staff()->attach($staff->getKey());
        $source = $this->makeSource($other);
        $item = $source->rssItems()->create(['title' => 'Private story', 'url' => 'https://news.example/story']);

        $this->actingAs($staff)
            ->get(route('admin.superblogs.rss-sources.items.index', [$allowed, $source]))
            ->assertNotFound();
        $this->post(route('admin.superblogs.rss-sources.fetch', [$allowed, $source]))
            ->assertNotFound();
        $this->get(route('admin.superblogs.rss-sources.items.index', [$other, $source]))
            ->assertForbidden();
        $this->delete(route('admin.superblogs.rss-sources.items.destroy', [$other, $source, $item]))
            ->assertForbidden();
        $this->get(route('admin.superblogs.rss-sources.items.clear', [$other, $source]))
            ->assertForbidden();
        $this->delete(route('admin.superblogs.rss-sources.items.clear.destroy', [$other, $source]))
            ->assertForbidden();

        $this->assertDatabaseHas('rss_items', ['id' => $item->getKey()]);
    }

    public function test_item_page_displays_safe_links_and_item_deletion_preserves_related_article(): void
    {
        $staff = $this->makeUser();
        $superblog = $this->makeSuperblog($staff, 'items-blog');
        $superblog->staff()->attach($staff->getKey());
        $source = $this->makeSource($superblog);
        $item = $source->rssItems()->create([
            'title' => 'Story title',
            'url' => 'https://news.example/story?a=1&b=2',
            'summary' => 'Story summary',
        ]);
        $article = $superblog->articles()->create([
            'rss_item_id' => $item->getKey(),
            'title' => 'Article title',
            'slug' => 'article-title',
            'content' => 'Transformed article',
            'source_type' => 'rss',
        ]);

        $this->actingAs($staff)
            ->get(route('admin.superblogs.rss-sources.items.index', [$superblog, $source]))
            ->assertOk()
            ->assertSee('Story title')
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertSee('Story summary');

        $this->delete(route('admin.superblogs.rss-sources.items.destroy', [$superblog, $source, $item]))
            ->assertRedirect(route('admin.superblogs.rss-sources.items.index', [$superblog, $source]));

        $this->assertDatabaseMissing('rss_items', ['id' => $item->getKey()]);
        $this->assertDatabaseHas('articles', ['id' => $article->getKey(), 'rss_item_id' => null]);
        $this->assertDatabaseHas('rss_sources', ['id' => $source->getKey()]);
    }

    public function test_clear_all_items_only_deletes_items_for_selected_source(): void
    {
        $staff = $this->makeUser();
        $superblog = $this->makeSuperblog($staff, 'clear-items-blog');
        $superblog->staff()->attach($staff->getKey());
        $source = $this->makeSource($superblog);
        $otherSource = $this->makeSource($superblog, 'https://feeds.example.com/other.xml');
        $item = $source->rssItems()->create(['title' => 'Selected', 'url' => 'https://news.example/selected']);
        $otherItem = $otherSource->rssItems()->create(['title' => 'Other', 'url' => 'https://news.example/other']);
        $article = $superblog->articles()->create([
            'rss_item_id' => $item->getKey(),
            'title' => 'Derived article',
            'slug' => 'derived-article',
            'content' => 'Article content',
            'source_type' => 'rss',
        ]);

        $this->actingAs($staff)
            ->get(route('admin.superblogs.rss-sources.items.clear', [$superblog, $source]))
            ->assertOk()
            ->assertSee('permanently deletes every RSS item');

        $this->delete(route('admin.superblogs.rss-sources.items.clear.destroy', [$superblog, $source]))
            ->assertRedirect(route('admin.superblogs.rss-sources.items.index', [$superblog, $source]));

        $this->assertDatabaseMissing('rss_items', ['id' => $item->getKey()]);
        $this->assertDatabaseHas('rss_items', ['id' => $otherItem->getKey()]);
        $this->assertDatabaseHas('rss_sources', ['id' => $source->getKey()]);
        $this->assertDatabaseHas('articles', ['id' => $article->getKey(), 'rss_item_id' => null]);
    }

    private function allowPublicFeedHost(): void
    {
        $resolver = $this->mock(RssFeedDnsResolver::class);
        $resolver->shouldReceive('resolve')->andReturn(['93.184.216.34']);
    }

    private function feed(): string
    {
        return <<<'XML'
            <?xml version="1.0"?>
            <rss version="2.0"><channel><title>Feed</title><item>
                <guid>story-1</guid><title>A story</title><link>https://news.example/story</link>
                <pubDate>Tue, 20 Oct 2026 12:00:00 GMT</pubDate>
                <description>A short summary</description><content:encoded xmlns:content="http://purl.org/rss/1.0/modules/content/">The original feed body</content:encoded>
            </item></channel></rss>
            XML;
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

    private function makeSource(Superblog $superblog, string $url = 'https://feeds.example.com/feed.xml'): RssSource
    {
        return $superblog->rssSources()->create(['name' => 'News feed', 'url' => $url, 'is_active' => true]);
    }
}
