<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Superblog;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishedSuperblogNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_publicerade_sidebar_section_is_hidden_without_accessible_live_superblogs(): void
    {
        $admin = $this->makeUser(superAdmin: true);
        $this->makeSuperblog($admin, 'Draft magazine', 'draft');

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('href="'.route('admin.superblogs.published').'"', false);
    }

    public function test_publicerade_sidebar_section_appears_for_accessible_live_superblogs(): void
    {
        $admin = $this->makeUser(superAdmin: true);
        $live = $this->makeSuperblog($admin, 'Live magazine', 'published');

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('admin.superblogs.published').'"', false)
            ->assertSee('href="'.route('admin.superblogs.edit', $live).'"', false)
            ->assertDontSee('href="'.route('admin.articles.index').'"', false);
    }

    public function test_publicerade_page_lists_only_accessible_live_superblogs(): void
    {
        $owner = $this->makeUser(superAdmin: true);
        $staff = $this->makeUser();
        $accessibleLive = $this->makeSuperblog($owner, 'Assigned live', 'published');
        $this->makeSuperblog($owner, 'Unassigned live', 'published');
        $this->makeSuperblog($owner, 'Assigned draft', 'draft')->staff()->attach($staff);
        $accessibleLive->staff()->attach($staff);

        $response = $this->actingAs($staff)->get(route('admin.superblogs.published'))
            ->assertOk()
            ->assertSee('Assigned live')
            ->assertSee('href="'.route('admin.superblogs.edit', [$accessibleLive, 'flik' => 'innehall']).'"', false)
            ->assertDontSee('Unassigned live');

        $this->assertSame(['Assigned live'], $response->viewData('superblogs')->pluck('name')->all());
    }

    public function test_superblog_articles_tab_only_shows_articles_for_selected_superblog(): void
    {
        $owner = $this->makeUser(superAdmin: true);
        $first = $this->makeSuperblog($owner, 'First live', 'published');
        $second = $this->makeSuperblog($owner, 'Second live', 'published');
        $this->makeArticle($first, 'First article');
        $this->makeArticle($second, 'Second article');

        $this->actingAs($owner)
            ->get(route('admin.superblogs.articles.index', $first))
            ->assertRedirect(route('admin.superblogs.edit', [$first, 'flik' => 'innehall']));

        $this->get(route('admin.superblogs.edit', [$first, 'flik' => 'innehall']))
            ->assertOk()
            ->assertSee('First article')
            ->assertDontSee('Second article')
            ->assertSee('1 Grund')
            ->assertSee('6 Avpublicera')
            ->assertDontSee('aria-label="Superblog"', false);
    }

    public function test_draft_and_published_superblogs_use_the_same_six_step_builder(): void
    {
        $owner = $this->makeUser(superAdmin: true);
        $draft = $this->makeSuperblog($owner, 'Draft builder', 'draft');
        $published = $this->makeSuperblog($owner, 'Published builder', 'published');

        foreach ([[$draft, '6 Publicera'], [$published, '6 Avpublicera']] as [$superblog, $publicationLabel]) {
            $this->actingAs($owner)
                ->get(route('admin.superblogs.edit', $superblog))
                ->assertOk()
                ->assertSee('1 Grund')
                ->assertSee('2 Utseende')
                ->assertSee('3 Flöden')
                ->assertSee('4 Innehåll')
                ->assertSee('5 Skriv artikel')
                ->assertSee($publicationLabel)
                ->assertSee('aria-label="Byggarens steg"', false)
                ->assertDontSee('aria-label="Superblog"', false)
                ->assertSee('Förhandsvisning av '.$superblog->name);
        }
    }

    public function test_assigned_editor_sees_three_renumbered_steps_for_both_draft_and_published_superblogs(): void
    {
        $owner = $this->makeUser(superAdmin: true);
        $editor = $this->makeUser();
        $draft = $this->makeSuperblog($owner, 'Draft editor view', 'draft');
        $published = $this->makeSuperblog($owner, 'Live editor view', 'published');
        $draft->staff()->attach($editor);
        $published->staff()->attach($editor);

        foreach ([$draft, $published] as $superblog) {
            $this->actingAs($editor)
                ->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall']))
                ->assertOk()
                ->assertSee('1 Innehåll')
                ->assertSee('2 Skriv artikel')
                ->assertSee('3 Publicera')
                ->assertDontSee('4 Innehåll')
                ->assertDontSee('5 Skriv artikel')
                ->assertDontSee('6 Publicera')
                ->assertDontSee('1 Grund')
                ->assertDontSee('2 Utseende')
                ->assertDontSee('3 Flöden');
        }
    }

    public function test_legacy_superblog_overview_route_opens_the_builder(): void
    {
        $owner = $this->makeUser(superAdmin: true);
        $published = $this->makeSuperblog($owner, 'Live route', 'published');

        $this->actingAs($owner)
            ->get(route('admin.superblogs.show', $published))
            ->assertRedirect(route('admin.superblogs.edit', $published));
    }

    public function test_staff_cannot_open_another_superblogs_articles_tab(): void
    {
        $owner = $this->makeUser(superAdmin: true);
        $staff = $this->makeUser();
        $assigned = $this->makeSuperblog($owner, 'Assigned live', 'published');
        $unassigned = $this->makeSuperblog($owner, 'Unassigned live', 'published');
        $assigned->staff()->attach($staff);

        $this->actingAs($staff)
            ->get(route('admin.superblogs.articles.index', $unassigned))
            ->assertForbidden();
    }

    public function test_global_articles_overview_remains_across_accessible_superblogs(): void
    {
        $owner = $this->makeUser(superAdmin: true);
        $staff = $this->makeUser();
        $first = $this->makeSuperblog($owner, 'First live', 'published');
        $second = $this->makeSuperblog($owner, 'Second live', 'published');
        $unassigned = $this->makeSuperblog($owner, 'Other live', 'published');
        $first->staff()->attach($staff);
        $second->staff()->attach($staff);
        $this->makeArticle($first, 'First article');
        $this->makeArticle($second, 'Second article');
        $this->makeArticle($unassigned, 'Other article');

        $this->actingAs($staff)->get(route('admin.articles.index'))
            ->assertOk()
            ->assertSee('First article')
            ->assertSee('Second article')
            ->assertDontSee('Other article');
    }

    private function makeSuperblog(User $owner, string $name, string $status): Superblog
    {
        $template = Template::firstOrCreate(['slug' => 'navigation-test'], [
            'name' => 'Navigation test',
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);

        return $owner->superblogs()->create([
            'template_id' => $template->id,
            'name' => $name,
            'slug' => str($name)->slug(),
            'status' => $status,
        ]);
    }

    private function makeArticle(Superblog $superblog, string $title): Article
    {
        return $superblog->articles()->create([
            'title' => $title,
            'slug' => str($title)->slug(),
            'content' => 'Article content',
            'source_type' => 'manual',
            'status' => 'draft',
        ]);
    }

    private function makeUser(bool $superAdmin = false): User
    {
        $user = User::factory()->create();
        $user->approval_status = 'approved';
        $user->is_super_admin = $superAdmin;
        $user->save();

        return $user;
    }
}
