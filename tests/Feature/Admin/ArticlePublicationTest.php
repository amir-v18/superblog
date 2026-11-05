<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Superblog;
use App\Models\Template;
use App\Models\User;
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
            ->assertSee('6 Publicera')
            ->assertSee('grid grid-cols-3', false)
            ->assertSee('href="'.route('admin.superblogs.edit', [$superblog, 'flik' => 'publicera']).'"', false)
            ->assertSee('>Publicera</button>', false)
            ->assertSee('action="'.route('admin.superblogs.articles.publish-selected', $superblog).'"', false)
            ->assertSee('method="POST"', false)
            ->assertSee('Superblocket markeras som publicerat.');

        $this->assertSame([
            'selected' => 2,
            'to_publish' => 1,
            'to_withdraw' => 1,
        ], $response->viewData('publicationSummary'));
    }

    public function test_publicera_step_changes_to_avpublicera_for_live_superblog(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $superblog->update(['status' => 'published']);

        $this->actingAs($staff)
            ->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'publicera']))
            ->assertOk()
            ->assertSee('6 Avpublicera')
            ->assertSee('Avpublicera Superblog')
            ->assertSee('action="'.route('admin.superblogs.unpublish', $superblog).'"', false)
            ->assertDontSee('Bekräfta och publicera');
    }

    public function test_unpublishing_takes_the_superblog_offline_without_changing_article_publication(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $superblog->update(['status' => 'published']);
        $publishedAt = now()->subDays(4)->startOfSecond();
        $article = $this->makeArticle($superblog, 'Still published article', status: 'published', selected: true, publishedAt: $publishedAt);

        $this->actingAs($staff)
            ->post(route('admin.superblogs.unpublish', $superblog))
            ->assertRedirect(route('admin.superblogs.edit', [$superblog, 'flik' => 'publicera']))
            ->assertSessionHas('status', 'Superblocket är avpublicerat. Artiklar och publiceringsdatum är oförändrade.');

        $this->assertSame('draft', $superblog->fresh()->status);
        $this->assertSame('published', $article->fresh()->status);
        $this->assertTrue($publishedAt->equalTo($article->fresh()->published_at));

        $this->get(route('admin.superblogs.published'))
            ->assertOk()
            ->assertSee('Inga publicerade Superblogs ännu.');
        $this->get(route('public.superblogs.articles.show', [$superblog, $article]))
            ->assertNotFound();

        // Site editors keep access to both the builder and a private preview while the public site is offline.
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
        $unselectedPublished = $this->makeArticle($superblog, 'Unselected published', status: 'published', publishedAt: now()->subDays(2));
        $unselectedDraft = $this->makeArticle($superblog, 'Unselected draft');

        $otherOwner = $this->makeApprovedUser(superAdmin: true);
        $otherSuperblog = $this->makeSuperblog($otherOwner, 'Other Superblog');
        $otherSelectedDraft = $this->makeArticle($otherSuperblog, 'Other selected draft', selected: true);
        $otherPublishedAt = now()->subDay()->startOfSecond();
        $otherUnselectedPublished = $this->makeArticle($otherSuperblog, 'Other published', status: 'published', publishedAt: $otherPublishedAt);

        $this->actingAs($staff)
            ->post(route('admin.superblogs.articles.publish-selected', $superblog))
            ->assertRedirect(route('admin.superblogs.edit', [$superblog, 'flik' => 'publicera']))
            ->assertSessionHas('status', 'Publicering klar: 1 artikel publicerades och 1 artikel drogs tillbaka. Superblocket publicerades.');

        $this->assertSame('published', $selectedDraft->fresh()->status);
        $this->assertNotNull($selectedDraft->fresh()->published_at);
        $this->assertSame('published', $selectedPublished->fresh()->status);
        $this->assertTrue($publishedAt->equalTo($selectedPublished->fresh()->published_at));
        $this->assertSame('draft', $unselectedPublished->fresh()->status);
        $this->assertNull($unselectedPublished->fresh()->published_at);
        $this->assertSame('draft', $unselectedDraft->fresh()->status);
        $this->assertNull($unselectedDraft->fresh()->published_at);
        $this->assertSame('draft', $otherSelectedDraft->fresh()->status);
        $this->assertSame('published', $otherUnselectedPublished->fresh()->status);
        $this->assertTrue($otherPublishedAt->equalTo($otherUnselectedPublished->fresh()->published_at));
        $this->assertSame('published', $superblog->fresh()->status);
        $this->assertSame('draft', $otherSuperblog->fresh()->status);
        $this->actingAs($staff)
            ->get(route('admin.superblogs.published'))
            ->assertOk()
            ->assertSee($superblog->name)
            ->assertDontSee($otherSuperblog->name);

        // A repeated submit is safe and does not reset an existing publication date.
        $publishedAtAfterFirstRun = $selectedPublished->fresh()->published_at;
        $this->post(route('admin.superblogs.articles.publish-selected', $superblog))
            ->assertSessionHas('status', 'Publicering klar: 0 artiklar publicerades och 0 artiklar drogs tillbaka. Superblocket var redan publicerat.');
        $this->assertTrue($publishedAtAfterFirstRun->equalTo($selectedPublished->fresh()->published_at));
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
            ->assertSessionHas('status', 'Publicering klar: 1 artikel publicerades och 1 artikel drogs tillbaka. Superblocket publicerades.');

        $this->assertSame('published', $selectedDraft->fresh()->status);
        $this->assertSame('draft', $unselectedDraft->fresh()->status);
        $this->assertSame('draft', $unselectedPublished->fresh()->status);
        $this->assertSame('published', $superblog->fresh()->status);
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
        [$staff, $superblog] = $this->makeAssignedSuperblog();
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
    }

    private function makeAssignedSuperblog(): array
    {
        $owner = $this->makeApprovedUser(superAdmin: true);
        $staff = $this->makeApprovedUser();
        $superblog = $this->makeSuperblog($owner, 'Publication Superblog');
        $superblog->staff()->attach($staff->getKey());

        return [$staff, $superblog];
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
