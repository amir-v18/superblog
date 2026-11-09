<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Superblog;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualArticleCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_open_the_write_article_step_and_editor(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();

        $this->actingAs($staff)
            ->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'skriv-artikel']))
            ->assertOk()
            ->assertSee('5 Skriv artikel')
            ->assertSee('6 Publicera')
            ->assertSee(route('admin.superblogs.articles.create', $superblog), false);

        $this->get(route('admin.superblogs.articles.create', $superblog))
            ->assertOk()
            ->assertSee('New manual article')
            ->assertDontSee('Excerpt')
            ->assertSee(route('admin.superblogs.articles.store', $superblog), false);
    }

    public function test_authorized_user_can_create_a_manual_draft_for_the_current_superblog(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $otherSuperblog = $this->makeSuperblog($this->makeApprovedUser(superAdmin: true), 'Other Superblog');

        $response = $this->actingAs($staff)->post(route('admin.superblogs.articles.store', $superblog), [
            'title' => 'A manual story',
            'slug' => 'A Manual Story',
            'content' => 'The article body.',
            // These fields are deliberately forged: the server owns publication and source state.
            'superblog_id' => $otherSuperblog->id,
            'rss_item_id' => 99,
            'source_type' => 'rss',
            'status' => 'published',
            'is_selected_for_publication' => true,
            'published_at' => now()->toDateTimeString(),
        ]);

        $article = Article::query()->where('slug', 'a-manual-story')->firstOrFail();
        $response->assertRedirect(route('admin.superblogs.articles.edit', [$superblog, $article]))
            ->assertSessionHas('status', 'Draft article created.');

        $this->assertSame($superblog->id, $article->superblog_id);
        $this->assertSame('manual', $article->source_type);
        $this->assertSame('draft', $article->status);
        $this->assertNull($article->rss_item_id);
        $this->assertTrue($article->is_selected_for_publication);
        $this->assertNull($article->published_at);

        $this->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall']))
            ->assertOk()
            ->assertSee('A manual story')
            ->assertSee('name="selected_article_ids[]" value="'.$article->id.'"', false)
            ->assertSee('name="selected_article_ids[]" value="'.$article->id.'" checked', false);
    }

    public function test_manual_article_saved_from_the_form_is_shown_on_the_public_page(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();

        $this->actingAs($staff)->post(route('admin.superblogs.articles.store', $superblog), [
            'title' => 'A public manual story',
            'slug' => 'a-public-manual-story',
            'content' => '<p>The manual body text.</p>',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $article = Article::query()->where('slug', 'a-public-manual-story')->firstOrFail();
        $this->post(route('admin.superblogs.articles.publish', [$superblog, $article]))
            ->assertSessionHas('status', 'Article published.');
        $superblog->update(['status' => 'published']);

        $this->get(route('public.superblogs.articles.show', [$superblog, $article]))
            ->assertOk()
            ->assertSee('A public manual story')
            ->assertSee('The manual body text.');
    }

    public function test_a_posted_excerpt_field_is_ignored_without_an_error(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();

        // Dropped twice: the form rules leave it out of the validated data, and the model would discard
        // it anyway since it is not fillable. Only if both let it through would saving fail on the
        // missing column, and this test would turn red.
        $this->actingAs($staff)->post(route('admin.superblogs.articles.store', $superblog), [
            'title' => 'An old form post',
            'slug' => 'an-old-form-post',
            'content' => '<p>The body text.</p>',
            'excerpt' => 'Old ingress text',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $article = Article::query()->where('slug', 'an-old-form-post')->firstOrFail();
        $this->assertArrayNotHasKey('excerpt', $article->getAttributes());

        $this->post(route('admin.superblogs.articles.publish', [$superblog, $article]));
        $superblog->update(['status' => 'published']);

        $this->get(route('public.superblogs.articles.show', [$superblog, $article]))
            ->assertOk()
            ->assertSee('The body text.')
            ->assertDontSee('Old ingress text');
    }

    public function test_unassigned_user_cannot_create_or_edit_articles(): void
    {
        $owner = $this->makeApprovedUser(superAdmin: true);
        $superblog = $this->makeSuperblog($owner, 'Restricted Superblog');
        $article = $this->makeManualArticle($superblog, 'Private draft');
        $unassigned = $this->makeApprovedUser();

        $this->actingAs($unassigned)
            ->get(route('admin.superblogs.articles.create', $superblog))
            ->assertForbidden();

        $this->post(route('admin.superblogs.articles.store', $superblog), [
            'title' => 'Unauthorized draft',
            'slug' => 'unauthorized-draft',
            'content' => 'This must not be saved.',
        ])->assertForbidden();

        $this->get(route('admin.superblogs.articles.edit', [$superblog, $article]))
            ->assertForbidden();

        $this->put(route('admin.superblogs.articles.update', [$superblog, $article]), [
            'title' => 'Changed privately',
            'slug' => 'changed-privately',
            'content' => 'Changed body.',
        ])->assertForbidden();

        $this->assertDatabaseCount('articles', 1);
        $this->assertSame('Private draft', $article->fresh()->title);
    }

    public function test_article_from_another_superblog_cannot_be_opened_or_edited(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $otherOwner = $this->makeApprovedUser(superAdmin: true);
        $otherSuperblog = $this->makeSuperblog($otherOwner, 'Another Superblog');
        $article = $this->makeManualArticle($otherSuperblog, 'Another blog article');

        $this->actingAs($staff)
            ->get(route('admin.superblogs.articles.edit', [$superblog, $article]))
            ->assertNotFound();

        $this->put(route('admin.superblogs.articles.update', [$superblog, $article]), [
            'title' => 'Tampered title',
            'slug' => 'tampered-title',
            'content' => 'Tampered body.',
        ])->assertNotFound();

        $this->assertSame('Another blog article', $article->fresh()->title);
    }

    public function test_editing_manual_article_preserves_its_status_selection_and_publication_time(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $publishedAt = now()->subDay()->startOfSecond();
        $article = $this->makeManualArticle($superblog, 'Published manual story', [
            'status' => 'published',
            'is_selected_for_publication' => false,
            'published_at' => $publishedAt,
        ]);

        $this->actingAs($staff)
            ->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall']))
            ->assertOk()
            ->assertSee(route('admin.superblogs.articles.edit', [$superblog, $article]), false)
            ->assertSee('Manuell');

        $this->put(route('admin.superblogs.articles.update', [$superblog, $article]), [
            'title' => 'Updated manual story',
            'slug' => 'updated-manual-story',
            'content' => 'Updated body.',
            'status' => 'draft',
            'is_selected_for_publication' => true,
            'published_at' => null,
        ])->assertRedirect(route('admin.superblogs.articles.edit', [$superblog, $article]));

        $article->refresh();
        $this->assertSame('Updated manual story', $article->title);
        $this->assertSame('manual', $article->source_type);
        $this->assertSame('published', $article->status);
        $this->assertFalse($article->is_selected_for_publication);
        $this->assertTrue($publishedAt->equalTo($article->published_at));
    }

    public function test_new_manual_draft_can_be_found_and_edited_from_innehall(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $article = $this->makeManualArticle($superblog, 'Find me in Innehåll');

        $this->actingAs($staff)
            ->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'skriv-artikel']))
            ->assertOk()
            ->assertSee(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall']), false);

        $this->get(route('admin.superblogs.articles.edit', [$superblog, $article]))
            ->assertOk()
            ->assertSee('Find me in Innehåll');
    }

    private function makeAssignedSuperblog(): array
    {
        $owner = $this->makeApprovedUser(superAdmin: true);
        $staff = $this->makeApprovedUser();
        $superblog = $this->makeSuperblog($owner, 'Manual article Superblog');
        $superblog->staff()->attach($staff->getKey());

        return [$staff, $superblog];
    }

    private function makeSuperblog(User $owner, string $name): Superblog
    {
        $template = Template::create([
            'name' => 'Manual article template '.$name,
            'slug' => str($name)->slug().'-manual-template',
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);

        return $owner->superblogs()->create([
            'template_id' => $template->id,
            'name' => $name,
            'slug' => str($name)->slug(),
        ]);
    }

    private function makeManualArticle(Superblog $superblog, string $title, array $attributes = []): Article
    {
        return $superblog->articles()->create(array_merge([
            'title' => $title,
            'slug' => str($title)->slug(),
            'content' => 'Article body.',
            'source_type' => 'manual',
            'status' => 'draft',
            'is_selected_for_publication' => false,
        ], $attributes));
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
