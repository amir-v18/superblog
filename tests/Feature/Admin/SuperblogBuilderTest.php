<?php

namespace Tests\Feature\Admin;

use App\Models\Superblog;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperblogBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_builder_shows_the_basics_tab_by_default_and_the_appearance_tab_on_request(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin);

        $this->actingAs($admin)->get(route('admin.superblogs.edit', $superblog))
            ->assertOk()
            ->assertSee('name="name"', false)
            ->assertSee('name="template_id"', false)
            ->assertSee('name="domain"', false)
            ->assertSee('type="hidden" name="slug" value="dagens-budbarare"', false)
            ->assertDontSee('name="theme"', false);

        $this->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'utseende']))
            ->assertOk()
            ->assertSee('name="theme"', false)
            ->assertDontSee('name="domain"', false);
    }

    public function test_saving_keeps_slug_and_description_and_stays_in_the_builder(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin);

        // Mirrors the Grund form: the hidden slug is sent, description is not part of the form.
        $this->actingAs($admin)->put(route('admin.superblogs.update', $superblog), [
            'name' => 'Nya Budbäraren',
            'slug' => 'dagens-budbarare',
            'template_id' => $superblog->template_id,
            'domain' => 'nyabudbararen.se',
        ])->assertRedirect(route('admin.superblogs.edit', $superblog));

        $this->assertDatabaseHas('superblogs', [
            'id' => $superblog->id,
            'name' => 'Nya Budbäraren',
            'slug' => 'dagens-budbarare',
            'domain' => 'nyabudbararen.se',
            'description' => 'Behålls',
        ]);
    }

    public function test_users_without_access_cannot_open_or_save_the_builder(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $staff = $this->makeUser(approved: true);
        $superblog = $this->makeSuperblog($admin);

        $this->actingAs($staff)->get(route('admin.superblogs.edit', $superblog))->assertForbidden();
        $this->put(route('admin.superblogs.update', $superblog), [
            'name' => 'Kapat',
            'slug' => 'dagens-budbarare',
            'template_id' => $superblog->template_id,
        ])->assertForbidden();

        $this->assertDatabaseHas('superblogs', ['id' => $superblog->id, 'name' => 'Dagens Budbärare']);
    }

    public function test_empty_magazine_hint_shows_real_counts_until_an_article_is_published(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin);

        $this->actingAs($admin)->get(route('admin.superblogs.edit', $superblog))
            ->assertSee('Tomt magasin.')
            ->assertSee('Lägg till ett RSS-flöde för att komma igång.')
            ->assertDontSee('publicerade artiklar');

        $source = $superblog->rssSources()->create(['name' => 'Lokalnytt', 'url' => 'https://exempel.se/rss.xml', 'is_active' => true]);
        $superblog->rssSources()->create(['name' => 'Sport', 'url' => 'https://exempel.se/sport.xml', 'is_active' => false]);
        foreach (range(1, 3) as $n) {
            $source->rssItems()->create(['title' => "Post $n", 'url' => "https://exempel.se/$n"]);
        }
        // A draft is not published, so the magazine still counts as empty.
        $superblog->articles()->create(['title' => 'Utkast', 'slug' => 'utkast', 'content' => 'Text', 'source_type' => 'manual']);

        $this->get(route('admin.superblogs.edit', $superblog))
            ->assertSee('Tomt magasin.')
            ->assertSee('2 flöden · 3 hämtade poster · 0 publicerade artiklar.')
            ->assertSee('Platshållarna fylls när artiklar publiceras.')
            ->assertSee(route('admin.superblogs.edit', [$superblog, 'flik' => 'floden']), false);

        $superblog->articles()->create(['title' => 'Klar', 'slug' => 'klar', 'content' => 'Text', 'source_type' => 'manual', 'status' => 'published', 'published_at' => now()]);

        $this->get(route('admin.superblogs.edit', $superblog))
            ->assertDontSee('Tomt magasin.');
    }

    private function makeSuperblog(User $owner): Superblog
    {
        $template = Template::create([
            'name' => 'News',
            'slug' => 'news',
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);

        return $owner->superblogs()->create([
            'template_id' => $template->id,
            'name' => 'Dagens Budbärare',
            'slug' => 'dagens-budbarare',
            'description' => 'Behålls',
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
