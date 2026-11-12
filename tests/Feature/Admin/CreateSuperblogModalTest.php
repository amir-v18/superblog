<?php

namespace Tests\Feature\Admin;

use App\Models\Superblog;
use App\Models\Template;
use App\Models\User;
use App\Support\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesSuperblogs;
use Tests\TestCase;

class CreateSuperblogModalTest extends TestCase
{
    use CreatesSuperblogs, RefreshDatabase;

    public function test_modal_is_on_every_admin_page_for_admins_but_not_for_staff(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $staff = $this->makeUser(approved: true);
        $superblog = $this->makeSuperblog($admin);
        $superblog->staff()->attach($staff);

        foreach ([
            route('admin.dashboard'),
            route('admin.templates.index'),
            route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall']),
            route('admin.superblogs.rss-sources.index', $superblog),
        ] as $url) {
            $this->actingAs($admin)->get($url)
                ->assertOk()
                ->assertSee('id="create-superblog"', false);
        }

        // Staff may open the same magazine pages, but not create Superblogs.
        foreach ([
            route('admin.dashboard'),
            route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall']),
            route('admin.superblogs.rss-sources.index', $superblog),
        ] as $url) {
            $this->actingAs($staff)->get($url)
                ->assertOk()
                ->assertDontSee('id="create-superblog"', false)
                ->assertDontSee('+ Nytt magasin');
        }
    }

    public function test_a_failed_modal_submit_on_the_builder_stays_in_the_modal(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin);

        $response = $this->actingAs($admin)
            ->from(route('admin.superblogs.edit', $superblog))
            ->followingRedirects()
            ->post(route('admin.superblogs.store'), [
                '_form' => 'create-superblog',
                'name' => 'Nytt magasin från modalen',
                'template_id' => $superblog->template_id,
                'domain' => 'ogiltig',
            ])
            ->assertOk()
            ->assertSee('data-open-on-load', false)
            // The modal gets the typed values back; the builder keeps the saved ones.
            ->assertSee('id="create-domain" name="domain" value="ogiltig"', false)
            ->assertSee('id="domain" name="domain" value=""', false)
            // The error sits under the modal's domain field and is announced with it.
            ->assertSee('aria-describedby="create-domain-error create-domain-help"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('<p id="create-domain-error" class="mt-2 text-[13px] font-semibold text-studio-bad">The domain field format is invalid.</p>', false);

        $content = $response->getContent();
        $this->assertSame(1, substr_count($content, 'value="Nytt magasin från modalen"'));
        $this->assertSame(1, substr_count($content, 'value="Dagens Budbärare"'));
        // Shown exactly once: under the modal's field, with no error list in the modal or above the builder form.
        $this->assertSame(1, substr_count($content, 'The domain field format is invalid.'));
        $this->assertSame(0, substr_count($content, 'Rätta följande:'));
        $this->assertSame(1, substr_count($content, 'aria-invalid="true"'));
        $this->assertDatabaseCount('superblogs', 1);
    }

    public function test_modal_shows_one_card_per_active_template(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        // Created first and alphabetically first, so only the registry can put Modern ahead of it.
        $classic = $this->makeTemplate('Klassisk tidning', 'classic', 'Nyhetsblad med spalter, dubbla linjer, titelblock och retroannonser.');
        $modern = $this->makeTemplate('Modern magasin', 'modern', 'Stor huvudartikel med bild, mest läst och ljusa kort. Lugnt och luftigt.');
        $tech = $this->makeTemplate('Tech', 'tech', 'Samma struktur i mörkare ton.', active: false);
        $editorial = $this->makeTemplate('Editorial Default', 'editorial-default', null, active: false);

        $modal = $this->modal($admin);
        $form = $this->modalForm($admin);

        // One radio per active template, inside the form, so the choice is sent with it. Inactive ones are left out.
        $this->assertSame(2, substr_count($form, 'name="template_id"'));
        foreach ([$tech, $editorial] as $inactive) {
            $this->assertStringNotContainsString('value="'.$inactive->id.'"', $form);
            $this->assertStringNotContainsString($inactive->name, $modal);
        }

        // Cards follow the registry's order, not the name order.
        $this->assertLessThan(
            strpos($form, 'value="'.$classic->id.'"'),
            strpos($form, 'value="'.$modern->id.'"'),
        );

        // Modern is preselected, Klassisk tidning is not.
        $this->assertStringContainsString('value="'.$modern->id.'" required checked', $form);
        $this->assertStringNotContainsString('value="'.$classic->id.'" required checked', $form);

        // Descriptions come from the database.
        $this->assertStringContainsString('Stor huvudartikel med bild, mest läst och ljusa kort. Lugnt och luftigt.', $form);
        $this->assertStringContainsString('Nyhetsblad med spalter, dubbla linjer, titelblock och retroannonser.', $form);

        // Thumbnails: both templates in their own themes, so no card falls back to grey.
        $this->assertStringContainsString('data-theme="bondebladet"', $form);
        $this->assertStringContainsString('data-theme="budbararen"', $form);
        $this->assertSame(2, substr_count($form, 'data-theme='));
        $this->assertSame(0, substr_count($form, 'border-b border-studio-line bg-studio-bg'));

        // Three columns as in the design, even with two templates.
        $this->assertStringContainsString('grid gap-3 sm:grid-cols-3', $form);
    }

    public function test_a_template_without_description_gets_no_empty_line(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $this->makeTemplate('Modern magasin', 'modern', 'Stor huvudartikel med bild.');
        $this->makeTemplate('Klassisk tidning', 'classic', null);

        $form = $this->modalForm($admin);

        $this->assertSame(1, substr_count($form, '<small class="px-3 pb-3 text-xs leading-[1.4] text-studio-muted">'));
    }

    public function test_first_card_is_preselected_when_the_default_is_inactive(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $modern = $this->makeTemplate('Modern magasin', 'modern', null, active: false);
        $other = $this->makeTemplate('Allmän', 'allman', null);
        $classic = $this->makeTemplate('Klassisk tidning', 'classic', null);

        $form = $this->modalForm($admin);

        // Classic is in the registry and comes before templates missing from it, so it is the first card and chosen.
        $this->assertStringNotContainsString('value="'.$modern->id.'"', $form);
        $this->assertStringContainsString('value="'.$classic->id.'" required checked', $form);
        $this->assertStringNotContainsString('value="'.$other->id.'" required checked', $form);
    }

    public function test_the_preselected_template_comes_from_the_registry(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $modern = $this->makeTemplate('Modern magasin', 'modern', null);
        $classic = $this->makeTemplate('Klassisk tidning', 'classic', null);
        config(['templates.default' => 'classic']);

        $form = $this->modalForm($admin);

        $this->assertStringContainsString('value="'.$classic->id.'" required checked', $form);
        $this->assertStringNotContainsString('value="'.$modern->id.'" required checked', $form);
    }

    public function test_the_chosen_template_is_saved(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $this->makeTemplate('Modern magasin', 'modern', null);
        $classic = $this->makeTemplate('Klassisk tidning', 'classic', null);

        $this->actingAs($admin)->post(route('admin.superblogs.store'), [
            '_form' => 'create-superblog',
            'name' => 'Allmänna Tidningen',
            'template_id' => $classic->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('superblogs', ['name' => 'Allmänna Tidningen', 'template_id' => $classic->id]);
    }

    public function test_a_new_magazine_starts_in_its_templates_theme(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $templates = [
            'Klassisk' => [$this->makeTemplate('Klassisk tidning', 'classic', null), 'budbararen'],
            'Modern' => [$this->makeTemplate('Modern magasin', 'modern', null), 'bondebladet'],
            // Not in config/templates.php, so it has no theme of its own.
            'Egen' => [$this->makeTemplate('Egen mall', 'egen-mall', null), Theme::DEFAULT],
        ];

        foreach ($templates as $name => [$template, $theme]) {
            $this->actingAs($admin)->post(route('admin.superblogs.store'), [
                '_form' => 'create-superblog',
                'name' => "{$name} Nyheter",
                'template_id' => $template->id,
            ])->assertSessionHasNoErrors();

            $superblog = Superblog::where('name', "{$name} Nyheter")->firstOrFail();
            $this->assertSame(['theme' => ['name' => $theme]], $superblog->settings, $name);
        }
    }

    public function test_duplicate_name_error_is_shown_under_the_name_field(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin);

        $content = $this->actingAs($admin)
            ->from(route('admin.dashboard'))
            ->followingRedirects()
            ->post(route('admin.superblogs.store'), [
                '_form' => 'create-superblog',
                'name' => 'Dagens Budbärare',
                'template_id' => $superblog->template_id,
            ])
            ->assertOk()
            ->assertSee('data-open-on-load', false)
            ->assertSee('aria-invalid="true" aria-describedby="create-name-error"', false)
            ->getContent();

        $this->assertMatchesRegularExpression('#<p id="create-name-error" class="[^"]*">The slug has already been taken\.</p>#', $content);
        $this->assertDatabaseCount('superblogs', 1);
    }

    /** The modal on the dashboard. It sits after </main>, so the dashboard cards are left out. */
    private function modal(User $admin): string
    {
        $content = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        return Str::after($content, '</main>');
    }

    /** The modal's form, where the template cards are. */
    private function modalForm(User $admin): string
    {
        return Str::between($this->modal($admin), '<form', '</form>');
    }

    private function makeTemplate(string $name, string $slug, ?string $description, bool $active = true): Template
    {
        return Template::create([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'layout' => 'layouts.public',
            'is_active' => $active,
        ]);
    }

    public function test_a_failed_builder_save_does_not_fill_or_open_the_modal(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin);

        $response = $this->actingAs($admin)
            ->from(route('admin.superblogs.edit', $superblog))
            ->followingRedirects()
            ->put(route('admin.superblogs.update', $superblog), [
                'name' => 'Nytt namn i byggaren',
                'slug' => 'dagens-budbarare',
                'template_id' => $superblog->template_id,
                'domain' => 'ogiltig',
            ])
            ->assertOk()
            ->assertDontSee('data-open-on-load', false)
            ->assertSee('id="domain" name="domain" value="ogiltig"', false)
            ->assertSee('id="create-domain" name="domain" value=""', false);

        $content = $response->getContent();
        $this->assertSame(1, substr_count($content, 'value="Nytt namn i byggaren"'));
        $this->assertSame(1, substr_count($content, 'Rätta följande:'));
    }
}
