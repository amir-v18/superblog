<?php

namespace Tests\Feature\Admin;

use App\Models\Superblog;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_superblogs_are_grouped_by_status_with_counts(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $live = $this->makeSuperblog($admin, 'Live Blog', 'published');
        $this->makeSuperblog($admin, 'Draft Blog', 'draft');

        $live->rssSources()->create(['name' => 'Feed', 'url' => 'https://example.com/feed']);
        $this->makeArticle($live, 'published-one', 'published');
        $this->makeArticle($live, 'draft-one', 'draft');

        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        $this->assertSame(['Live Blog'], $response->viewData('published')->pluck('name')->all());
        $this->assertSame(['Draft Blog'], $response->viewData('drafts')->pluck('name')->all());

        $card = $response->viewData('published')->first();
        $this->assertSame(1, $card->rss_sources_count);
        $this->assertSame(1, $card->published_articles_count);
    }

    public function test_staff_only_see_assigned_superblogs(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $staff = $this->makeUser(approved: true);
        $assigned = $this->makeSuperblog($admin, 'Assigned Blog', 'draft');
        $this->makeSuperblog($admin, 'Other Blog', 'draft');
        $assigned->staff()->attach($staff);

        $this->actingAs($staff)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Assigned Blog')
            ->assertDontSee('Other Blog');
    }

    public function test_create_modal_is_shown_only_to_users_who_can_create(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $staff = $this->makeUser(approved: true);
        $template = Template::create([
            'name' => 'News',
            'slug' => 'news',
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);

        // A single active template is preselected.
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('id="create-superblog"', false)
            ->assertSee('value="'.$template->id.'" required checked', false);

        $this->actingAs($staff)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('id="create-superblog"', false);
    }

    public function test_creating_a_superblog_opens_it(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $template = Template::create([
            'name' => 'News',
            'slug' => 'news',
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.superblogs.store'), [
            '_form' => 'create-superblog',
            'name' => 'Dagens Budbärare',
            'template_id' => $template->id,
            'domain' => 'dagensbudbarare.se',
        ]);

        $superblog = Superblog::where('domain', 'dagensbudbarare.se')->firstOrFail();
        $response->assertRedirect(route('admin.superblogs.edit', $superblog));
    }

    public function test_card_strip_uses_the_saved_theme(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $this->makeSuperblog($admin, 'Themed Blog', 'draft')
            ->update(['settings' => ['theme' => ['name' => 'techenspraket']]]);
        $this->makeSuperblog($admin, 'Plain Blog', 'draft');
        $this->makeSuperblog($admin, 'Broken Blog', 'draft')
            ->update(['settings' => ['theme' => ['name' => 'hackat']]]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-theme="techenspraket"', false)
            ->assertDontSee('data-theme="hackat"', false)
            ->assertSee('h-2 bg-studio-line', false);

        // Only the valid theme gets a themed strip; the other two fall back to the neutral one.
        // Counted from the card sections only, since the sidebar's theme dots also use data-theme.
        $cards = Str::after($response->getContent(), 'id="published-heading"');
        $this->assertSame(1, substr_count($cards, 'data-theme='));
    }

    public function test_draft_card_shows_the_template_name(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        // Inactive, so the create modal does not list it and the name can only come from the card.
        $template = Template::create([
            'name' => 'Klassisk tidning',
            'slug' => 'klassisk',
            'layout' => 'layouts.public',
            'is_active' => false,
        ]);
        $admin->superblogs()->create([
            'template_id' => $template->id,
            'name' => 'Draft Blog',
            'slug' => 'draft-blog',
            'status' => 'draft',
        ]);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Klassisk tidning');
    }

    public function test_unapproved_users_cannot_open_the_dashboard(): void
    {
        $this->actingAs($this->makeUser())->get(route('admin.dashboard'))->assertForbidden();
    }

    private function makeSuperblog(User $owner, string $name, string $status): Superblog
    {
        $template = Template::firstOrCreate(['slug' => 'default'], [
            'name' => 'Default',
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

    private function makeArticle(Superblog $superblog, string $slug, string $status): void
    {
        $superblog->articles()->create([
            'title' => $slug,
            'slug' => $slug,
            'content' => 'Content',
            'source_type' => 'manual',
            'status' => $status,
        ]);
    }

    private function makeUser(bool $approved = false, bool $superAdmin = false): User
    {
        $user = User::factory()->create();
        $user->approval_status = $approved ? 'approved' : 'pending';
        $user->is_super_admin = $superAdmin;
        $user->save();

        return $user;
    }
}
