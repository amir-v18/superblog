<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSuperblogs;
use Tests\TestCase;

class SuperblogBuilderTest extends TestCase
{
    use CreatesSuperblogs, RefreshDatabase;

    public function test_builder_shows_the_basics_tab_by_default_and_the_appearance_tab_on_request(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin);

        // The layout's create modal has name, template_id and domain fields too, so the builder's own
        // fields are matched by id, and template_id is counted: one radio here, one in the modal.
        $basics = $this->actingAs($admin)->get(route('admin.superblogs.edit', $superblog))
            ->assertOk()
            ->assertSee('id="name"', false)
            ->assertSee('id="domain" name="domain"', false)
            ->assertSee('type="hidden" name="slug" value="dagens-budbarare"', false)
            ->assertDontSee('name="theme"', false);
        $this->assertSame(2, substr_count($basics->getContent(), 'name="template_id"'));

        // The only domain field left on this tab is the modal's.
        $appearance = $this->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'utseende']))
            ->assertOk()
            ->assertSee('name="theme"', false)
            ->assertSee('id="create-domain" name="domain"', false);
        $this->assertSame(1, substr_count($appearance->getContent(), 'name="domain"'));
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
}
