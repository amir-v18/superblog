<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Superblog;
use App\Models\Template;
use App\Models\User;
use App\Support\ArticleContentDocument;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ArticlePublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_open_publicera_and_see_the_pending_changes(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $this->makeArticle($superblog, 'Selected draft', selected: true);
        $this->makeArticle($superblog, 'Selected published', status: 'published', selected: true, publishedAt: now());
        $this->makeArticle($superblog, 'Will be withdrawn', status: 'published');
        $this->makeArticle($superblog, 'Unselected draft');

        $response = $this->actingAs($staff)
            ->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'publicera']))
            ->assertOk()
            ->assertSee('3 Publicera')
            ->assertDontSee('1 Grund')
            ->assertDontSee('2 Utseende')
            ->assertDontSee('3 Flöden')
            ->assertSee('grid grid-cols-3', false)
            ->assertSee('href="'.route('admin.superblogs.edit', [$superblog, 'flik' => 'publicera']).'"', false)
            ->assertSee('>Publicera valda artiklar</button>', false)
            ->assertSee('action="'.route('admin.superblogs.articles.publish-selected', $superblog).'"', false)
            ->assertSee('method="POST"', false)
            ->assertSee('Superbloggens publiceringsstatus ändras inte här.');

        $this->assertSame([
            'selected' => 2,
            'to_publish' => 1,
            'to_withdraw' => 1,
        ], $response->viewData('publicationSummary'));
    }

    public function test_assigned_editor_cannot_unpublish_a_live_superblog_from_the_publicera_step(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $superblog->update(['status' => 'published']);

        $this->actingAs($staff)
            ->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'publicera']))
            ->assertOk()
            ->assertSee('3 Publicera')
            ->assertSee('Publicera artiklar')
            ->assertSee('Superbloggens publiceringsstatus ändras inte här.')
            ->assertDontSee('Avpublicera Superblog')
            ->assertDontSee('action="'.route('admin.superblogs.unpublish', $superblog).'"', false);

        $this->post(route('admin.superblogs.unpublish', $superblog))->assertForbidden();
        $this->assertSame('published', $superblog->fresh()->status);
    }

    public function test_publication_without_a_domain_is_blocked_and_guides_user_to_grund(): void
    {
        [$staff, $superblog] = $this->makeAdminSuperblog();
        $superblog->update(['domain' => null]);
        $article = $this->makeArticle($superblog, 'Still a draft', selected: true);

        $this->actingAs($staff)
            ->from(route('admin.superblogs.edit', [$superblog, 'flik' => 'publicera']))
            ->followingRedirects()
            ->post(route('admin.superblogs.articles.publish-selected', $superblog))
            ->assertOk()
            ->assertSee('Ange en domän under Grund innan du publicerar.')
            ->assertSee('Gå till Grund')
            ->assertSee('<button type="submit" disabled', false);

        $this->assertSame('draft', $superblog->fresh()->status);
        $this->assertSame('draft', $article->fresh()->status);
        $this->assertNull($article->fresh()->published_at);
    }

    public function test_invalid_article_structure_is_blocked_by_individual_batch_and_selection_publication(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $article = $this->makeArticle($superblog, 'Invalid structure', selected: true);
        $article->update(['content' => '<p>Introduction.</p><h3>Orphan subsection</h3>']);

        $this->actingAs($staff)
            ->from(route('admin.superblogs.articles.edit', [$superblog, $article]))
            ->post(route('admin.superblogs.articles.publish', [$superblog, $article]))
            ->assertSessionHasErrors(['content' => 'Artikel ”Invalid structure”: Lägg till en H2-huvudrubrik innan du använder en H3-underrubrik.']);

        $this->post(route('admin.superblogs.articles.publish-selected', $superblog))
            ->assertSessionHasErrors('content');

        $superblog->update(['status' => 'published']);
        $this->put(route('admin.superblogs.articles.selection.update', $superblog), [
            'visible_article_ids' => [$article->id],
            'selected_article_ids' => [$article->id],
        ])->assertSessionHasErrors('content');

        $article->refresh();
        $this->assertSame('draft', $article->status);
        $this->assertTrue($article->is_selected_for_publication);
        $this->assertNull($article->published_at);
        $this->assertSame('published', $superblog->fresh()->status);
    }

    public function test_structured_document_can_be_published_and_rendered_publicly(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $article = $this->makeArticle($superblog, 'Structured draft', selected: true);
        (new ArticleContentDocument)->storeOn($article, [
            'version' => 1,
            'blocks' => [[
                'id' => '1a912ca0-1111-4111-8111-111111111111',
                'type' => 'introduction',
                'runs' => [['text' => 'A structured introduction.']],
            ]],
        ]);
        $article->save();

        $this->actingAs($staff)
            ->post(route('admin.superblogs.articles.publish', [$superblog, $article]))
            ->assertSessionHas('status', 'Artikeln har publicerats.');

        $this->assertSame('published', $article->fresh()->status);
        $this->assertNotNull($article->fresh()->published_at);

        $superblog->update(['status' => 'published']);

        $this->get(route('public.superblogs.articles.show', [$superblog, $article]))
            ->assertOk()
            ->assertSee('Structured draft')
            ->assertSee('A structured introduction.');
    }

    public function test_valid_placeholder_domain_allows_superblog_publication_without_dns_verification(): void
    {
        [$staff, $superblog] = $this->makeAdminSuperblog();
        $superblog->update(['domain' => 'teknikbyn.se']);
        $article = $this->makeArticle($superblog, 'Ready to publish', selected: true);

        $this->actingAs($staff)
            ->post(route('admin.superblogs.articles.publish-selected', $superblog))
            ->assertRedirect(route('admin.superblogs.edit', [$superblog, 'flik' => 'publicera']))
            ->assertSessionHasNoErrors();

        $this->assertSame('published', $superblog->fresh()->status);
        $this->assertSame('published', $article->fresh()->status);
        $this->assertNotNull($article->fresh()->published_at);
    }

    public function test_publication_is_blocked_when_other_required_grund_fields_are_invalid(): void
    {
        [$staff, $superblog] = $this->makeAdminSuperblog();
        $superblog->update(['name' => '', 'slug' => 'invalid slug', 'domain' => 'teknikbyn.se']);

        $this->actingAs($staff)
            ->post(route('admin.superblogs.articles.publish-selected', $superblog))
            ->assertSessionHasErrors(['name', 'slug']);

        $this->assertSame('draft', $superblog->fresh()->status);
    }

    public function test_publication_rejects_a_malformed_domain(): void
    {
        [$staff, $superblog] = $this->makeAdminSuperblog();
        $superblog->update(['domain' => 'https://teknikbyn.se/path']);

        $this->actingAs($staff)
            ->post(route('admin.superblogs.articles.publish-selected', $superblog))
            ->assertSessionHasErrors('domain');

        $this->assertSame('draft', $superblog->fresh()->status);
    }

    public function test_publishing_does_not_change_existing_appearance_settings(): void
    {
        [$staff, $superblog] = $this->makeAdminSuperblog();
        $settings = ['theme' => ['name' => 'bondebladet', 'primary' => '#123456']];
        $superblog->update(['domain' => 'teknikbyn.se', 'settings' => $settings]);

        $this->actingAs($staff)->post(route('admin.superblogs.articles.publish-selected', $superblog));

        $this->assertSame($settings, $superblog->fresh()->settings);
    }

    public function test_admin_unpublishing_takes_the_superblog_offline_without_changing_article_publication(): void
    {
        [$staff, $superblog] = $this->makeAdminSuperblog();
        $superblog->update(['status' => 'published']);
        $publishedAt = now()->subDays(4)->startOfSecond();
        $article = $this->makeArticle($superblog, 'Still published article', status: 'published', selected: true, publishedAt: $publishedAt);
        $contentModifiedAt = now()->subDay()->startOfSecond();
        $article->forceFill(['content_modified_at' => $contentModifiedAt])->save();

        $this->actingAs($staff)
            ->post(route('admin.superblogs.unpublish', $superblog))
            ->assertRedirect(route('admin.superblogs.edit', [$superblog, 'flik' => 'publicera']))
            ->assertSessionHas('status', 'Superblocket är avpublicerat. Artiklar och publiceringsdatum är oförändrade.');

        $this->assertSame('draft', $superblog->fresh()->status);
        $this->assertSame('published', $article->fresh()->status);
        $this->assertTrue($publishedAt->equalTo($article->fresh()->published_at));
        $this->assertTrue($contentModifiedAt->equalTo($article->fresh()->content_modified_at));

        $this->get(route('admin.superblogs.published'))
            ->assertOk()
            ->assertSee('Inga publicerade Superblogs ännu.');
        $this->get(route('public.superblogs.articles.show', [$superblog, $article]))
            ->assertNotFound();

        // Admins keep access to the builder and a private preview while the public site is offline.
        $this->get(route('admin.superblogs.edit', $superblog))->assertOk();
        $this->get(route('admin.superblogs.preview', $superblog))
            ->assertOk()
            ->assertSee('Still published article')
            ->assertSee(route('public.superblogs.articles.show', [$superblog, $article]), false);
    }

    public function test_unassigned_user_cannot_unpublish_a_superblog(): void
    {
        $owner = $this->makeApprovedUser(superAdmin: true);
        $superblog = $this->makeSuperblog($owner, 'Protected live Superblog');
        $superblog->update(['status' => 'published']);
        $article = $this->makeArticle($superblog, 'Protected article', status: 'published', publishedAt: now());
        $unassigned = $this->makeApprovedUser();

        $this->actingAs($unassigned)
            ->post(route('admin.superblogs.unpublish', $superblog))
            ->assertForbidden();

        $this->assertSame('published', $superblog->fresh()->status);
        $this->assertSame('published', $article->fresh()->status);
        $this->assertNotNull($article->fresh()->published_at);
    }

    public function test_batch_publication_applies_all_four_selection_and_status_combinations(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $selectedDraft = $this->makeArticle($superblog, 'Selected draft', selected: true);
        $publishedAt = now()->subDays(3)->startOfSecond();
        $selectedPublished = $this->makeArticle($superblog, 'Selected published', status: 'published', selected: true, publishedAt: $publishedAt);
        $selectedModifiedAt = now()->subDay()->startOfSecond();
        $selectedPublished->forceFill(['content_modified_at' => $selectedModifiedAt])->save();
        $unselectedPublishedAt = now()->subDays(2)->startOfSecond();
        $unselectedPublished = $this->makeArticle($superblog, 'Unselected published', status: 'published', publishedAt: $unselectedPublishedAt);
        $unselectedModifiedAt = now()->subHours(10)->startOfSecond();
        $unselectedPublished->forceFill(['content_modified_at' => $unselectedModifiedAt])->save();
        $unselectedDraft = $this->makeArticle($superblog, 'Unselected draft');

        $otherOwner = $this->makeApprovedUser(superAdmin: true);
        $otherSuperblog = $this->makeSuperblog($otherOwner, 'Other Superblog');
        $otherSelectedDraft = $this->makeArticle($otherSuperblog, 'Other selected draft', selected: true);
        $otherPublishedAt = now()->subDay()->startOfSecond();
        $otherUnselectedPublished = $this->makeArticle($otherSuperblog, 'Other published', status: 'published', publishedAt: $otherPublishedAt);

        $this->actingAs($staff)
            ->post(route('admin.superblogs.articles.publish-selected', $superblog))
            ->assertRedirect(route('admin.superblogs.edit', [$superblog, 'flik' => 'publicera']))
            ->assertSessionHas('status', 'Artikelpublicering klar: 1 artikel publicerades och 1 artikel drogs tillbaka. Superbloggens status ändrades inte.');

        $this->assertSame('published', $selectedDraft->fresh()->status);
        $this->assertNotNull($selectedDraft->fresh()->published_at);
        $this->assertNull($selectedDraft->fresh()->content_modified_at);
        $this->assertSame('published', $selectedPublished->fresh()->status);
        $this->assertTrue($publishedAt->equalTo($selectedPublished->fresh()->published_at));
        $this->assertTrue($selectedModifiedAt->equalTo($selectedPublished->fresh()->content_modified_at));
        $this->assertSame('draft', $unselectedPublished->fresh()->status);
        $this->assertTrue($unselectedPublishedAt->equalTo($unselectedPublished->fresh()->published_at));
        $this->assertTrue($unselectedModifiedAt->equalTo($unselectedPublished->fresh()->content_modified_at));
        $this->assertSame('draft', $unselectedDraft->fresh()->status);
        $this->assertNull($unselectedDraft->fresh()->published_at);
        $this->assertSame('draft', $otherSelectedDraft->fresh()->status);
        $this->assertSame('published', $otherUnselectedPublished->fresh()->status);
        $this->assertTrue($otherPublishedAt->equalTo($otherUnselectedPublished->fresh()->published_at));
        $this->assertSame('draft', $superblog->fresh()->status);
        $this->assertSame('draft', $otherSuperblog->fresh()->status);

        // A repeated submit is safe and does not reset an existing publication date.
        $publishedAtAfterFirstRun = $selectedPublished->fresh()->published_at;
        $this->post(route('admin.superblogs.articles.publish-selected', $superblog))
            ->assertSessionHas('status', 'Artikelpublicering klar: 0 artiklar publicerades och 0 artiklar drogs tillbaka. Superbloggens status ändrades inte.');
        $this->assertTrue($publishedAtAfterFirstRun->equalTo($selectedPublished->fresh()->published_at));
        $this->assertTrue($selectedModifiedAt->equalTo($selectedPublished->fresh()->content_modified_at));
    }

    public function test_republishing_preserves_first_publication_and_content_modified_at(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $superblog->update(['status' => 'published']);
        $firstPublishedAt = now()->subDays(5)->startOfSecond();
        $article = $this->makeArticle($superblog, 'Republished story', status: 'published', selected: true, publishedAt: $firstPublishedAt);
        $firstModifiedAt = now()->subDays(2)->startOfSecond();
        $article->forceFill(['content_modified_at' => $firstModifiedAt])->save();

        $this->actingAs($staff)->put(route('admin.superblogs.articles.selection.update', $superblog), [
            'visible_article_ids' => [$article->getKey()],
            'selected_article_ids' => [],
        ])->assertRedirect();

        $this->assertSame('draft', $article->fresh()->status);
        $this->assertTrue($firstPublishedAt->equalTo($article->fresh()->published_at));
        $this->assertTrue($firstModifiedAt->equalTo($article->fresh()->content_modified_at));

        $this->travelTo(now()->addMinute()->startOfSecond());
        $this->put(route('admin.superblogs.articles.selection.update', $superblog), [
            'visible_article_ids' => [$article->getKey()],
            'selected_article_ids' => [$article->getKey()],
        ])->assertRedirect();

        $this->assertSame('published', $article->fresh()->status);
        $this->assertTrue($firstPublishedAt->equalTo($article->fresh()->published_at));
        $this->assertTrue($firstModifiedAt->equalTo($article->fresh()->content_modified_at));
    }

    public function test_batch_republication_preserves_first_publication_and_content_modified_at(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $firstPublishedAt = now()->subDays(7)->startOfSecond();
        $article = $this->makeArticle($superblog, 'Batch republished story', status: 'draft', selected: true, publishedAt: $firstPublishedAt);
        $firstModifiedAt = now()->subDays(3)->startOfSecond();
        $article->forceFill(['content_modified_at' => $firstModifiedAt])->save();
        $this->travelTo(now()->addMinute()->startOfSecond());

        $this->actingAs($staff)
            ->post(route('admin.superblogs.articles.publish-selected', $superblog))
            ->assertRedirect();

        $this->assertSame('published', $article->fresh()->status);
        $this->assertTrue($firstPublishedAt->equalTo($article->fresh()->published_at));
        $this->assertTrue($firstModifiedAt->equalTo($article->fresh()->content_modified_at));
    }

    public function test_publication_uses_the_saved_innehall_selection_and_reports_actual_counts(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $selectedDraft = $this->makeArticle($superblog, 'Selected from Innehåll');
        $unselectedDraft = $this->makeArticle($superblog, 'Still a draft');
        $unselectedPublished = $this->makeArticle($superblog, 'Withdrawn from Innehåll', status: 'published', publishedAt: now());

        $this->actingAs($staff)
            ->put(route('admin.superblogs.articles.selection.update', [$superblog, 'flik' => 'innehall']), [
                'visible_article_ids' => [$selectedDraft->id, $unselectedDraft->id, $unselectedPublished->id],
                'selected_article_ids' => [$selectedDraft->id],
            ])
            ->assertRedirect();

        $summary = $this->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'publicera']))
            ->assertOk()
            ->viewData('publicationSummary');

        $this->assertSame(['selected' => 1, 'to_publish' => 1, 'to_withdraw' => 1], $summary);

        $this->post(route('admin.superblogs.articles.publish-selected', $superblog))
            ->assertSessionHas('status', 'Artikelpublicering klar: 1 artikel publicerades och 1 artikel drogs tillbaka. Superbloggens status ändrades inte.');

        $this->assertSame('published', $selectedDraft->fresh()->status);
        $this->assertSame('draft', $unselectedDraft->fresh()->status);
        $this->assertSame('draft', $unselectedPublished->fresh()->status);
        $this->assertSame('draft', $superblog->fresh()->status);
    }

    public function test_unauthorized_user_cannot_open_or_run_batch_publication(): void
    {
        $owner = $this->makeApprovedUser(superAdmin: true);
        $superblog = $this->makeSuperblog($owner, 'Restricted Superblog');
        $article = $this->makeArticle($superblog, 'Private selected draft', selected: true);
        $unassigned = $this->makeApprovedUser();

        $this->actingAs($unassigned)
            ->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'publicera']))
            ->assertForbidden();

        $this->post(route('admin.superblogs.articles.publish-selected', $superblog))
            ->assertForbidden();

        $this->assertSame('draft', $article->fresh()->status);
        $this->assertNull($article->fresh()->published_at);
    }

    public function test_batch_publication_rolls_back_articles_if_publishing_the_superblog_fails(): void
    {
        [$staff, $superblog] = $this->makeAdminSuperblog();
        $selectedDraft = $this->makeArticle($superblog, 'Would publish', selected: true);
        $unselectedPublished = $this->makeArticle($superblog, 'Would withdraw', status: 'published', publishedAt: now()->subDay());
        $failureInjected = false;

        DB::listen(function (QueryExecuted $query) use (&$failureInjected): void {
            if (! $failureInjected && preg_match('/^\s*update\s+[`"]?superblogs[`"]?/i', $query->sql) === 1) {
                $failureInjected = true;
                throw new \RuntimeException('Simulated failure while publishing the Superblog.');
            }
        });

        $this->actingAs($staff)
            ->post(route('admin.superblogs.articles.publish-selected', $superblog))
            ->assertServerError();

        $this->assertTrue($failureInjected);
        $this->assertSame('draft', $selectedDraft->fresh()->status);
        $this->assertNull($selectedDraft->fresh()->published_at);
        $this->assertSame('published', $unselectedPublished->fresh()->status);
        $this->assertNotNull($unselectedPublished->fresh()->published_at);
        $this->assertSame('draft', $superblog->fresh()->status);
    }

    public function test_batch_publication_makes_selected_articles_public_and_withdraws_unselected_articles(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $selected = $this->makeArticle($superblog, 'Now public', selected: true);
        $withdrawn = $this->makeArticle($superblog, 'No longer public', status: 'published', publishedAt: now());

        $this->actingAs($staff)
            ->post(route('admin.superblogs.articles.publish-selected', $superblog))
            ->assertRedirect();

        $this->assertSame('published', $selected->fresh()->status);
        $this->assertSame('draft', $withdrawn->fresh()->status);
        $this->assertSame('draft', $superblog->fresh()->status);
    }

    public function test_admin_can_publish_the_whole_superblog_with_selected_articles(): void
    {
        [$admin, $superblog] = $this->makeAdminSuperblog();
        $article = $this->makeArticle($superblog, 'Admin selected article', selected: true);

        $this->actingAs($admin)
            ->post(route('admin.superblogs.articles.publish-selected', $superblog))
            ->assertSessionHas('status', 'Artikelpublicering klar: 1 artikel publicerades och 0 artiklar drogs tillbaka. Superblocket publicerades.');

        $this->assertSame('published', $article->fresh()->status);
        $this->assertSame('published', $superblog->fresh()->status);
    }

    private function makeAssignedSuperblog(): array
    {
        $owner = $this->makeApprovedUser(superAdmin: true);
        $staff = $this->makeApprovedUser();
        $superblog = $this->makeSuperblog($owner, 'Publication Superblog');
        $superblog->staff()->attach($staff->getKey());

        return [$staff, $superblog];
    }

    private function makeAdminSuperblog(): array
    {
        $admin = $this->makeApprovedUser(superAdmin: true);
        $superblog = $this->makeSuperblog($admin, 'Admin Publication Superblog');

        return [$admin, $superblog];
    }

    private function makeSuperblog(User $owner, string $name): Superblog
    {
        $template = Template::create([
            'name' => 'Publication template '.$name,
            'slug' => str($name)->slug().'-publication-template',
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);

        return $owner->superblogs()->create([
            'template_id' => $template->id,
            'name' => $name,
            'slug' => str($name)->slug(),
            'domain' => str($name)->slug().'.example.test',
        ]);
    }

    private function makeArticle(
        Superblog $superblog,
        string $title,
        string $status = 'draft',
        bool $selected = false,
        mixed $publishedAt = null,
    ): Article {
        return $superblog->articles()->create([
            'title' => $title,
            'slug' => str($title)->slug(),
            'content' => 'Article content',
            'source_type' => 'manual',
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
