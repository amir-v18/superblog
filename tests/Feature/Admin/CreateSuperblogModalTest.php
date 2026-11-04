<?php

namespace Tests\Feature\Admin;

use App\Models\Template;
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
            route('admin.superblogs.edit', $superblog),
            route('admin.superblogs.rss-sources.index', $superblog),
        ] as $url) {
            $this->actingAs($admin)->get($url)
                ->assertOk()
                ->assertSee('id="create-superblog"', false);
        }

        // Staff may open the same magazine pages, but not create Superblogs.
        foreach ([
            route('admin.dashboard'),
            route('admin.superblogs.edit', $superblog),
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
        $modern = $this->makeTemplate('Modern magasin', 'modern', 'Stor huvudartikel med bild, mest läst och ljusa kort. Lugnt och luftigt.');
        $tech = $this->makeTemplate('Tech', 'tech', 'Samma struktur i mörkare ton med monospace-etiketter och annonsplats i kanten.');
        $plain = $this->makeTemplate('Editorial Default', 'editorial-default', null);
        $inactive = $this->makeTemplate('Klassisk tidning', 'klassisk', 'Nyhetsblad med spalter.', active: false);

        $content = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->getContent();
        // The modal sits after </main>, so the dashboard cards are left out.
        $modal = Str::after($content, '</main>');
        $form = Str::between($modal, '<form', '</form>');

        // One radio per active template, inside the form, so the choice is sent with it.
        $this->assertSame(3, substr_count($form, 'name="template_id"'));
        foreach ([$modern, $tech, $plain] as $template) {
            $this->assertStringContainsString('name="template_id" value="'.$template->id.'"', $form);
        }
        $this->assertStringNotContainsString('value="'.$inactive->id.'"', $form);
        $this->assertStringNotContainsString('Klassisk tidning', $modal);

        // Modern is preselected, the others are not.
        $this->assertStringContainsString('value="'.$modern->id.'" required checked', $form);
        $this->assertStringNotContainsString('value="'.$tech->id.'" required checked', $form);
        $this->assertStringNotContainsString('value="'.$plain->id.'" required checked', $form);

        // Descriptions come from the database; a template without one gets no empty line.
        $this->assertStringContainsString('Stor huvudartikel med bild, mest läst och ljusa kort. Lugnt och luftigt.', $form);
        $this->assertStringContainsString('Samma struktur i mörkare ton med monospace-etiketter och annonsplats i kanten.', $form);
        $this->assertSame(2, substr_count($form, '<small class="px-3 pb-3 text-xs leading-[1.4] text-studio-muted">'));

        // Thumbnails: modern and tech in their themes, the unmapped slug in the grey fallback.
        $this->assertStringContainsString('data-theme="bondebladet"', $form);
        $this->assertStringContainsString('data-theme="techenspraket"', $form);
        $this->assertSame(2, substr_count($form, 'data-theme='));
        $this->assertSame(1, substr_count($form, 'border-b border-studio-line bg-studio-bg'));
    }

    public function test_first_active_template_is_preselected_without_modern(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $tech = $this->makeTemplate('Tech', 'tech', null);
        $first = $this->makeTemplate('Allmän', 'allman', null);

        $form = Str::between(
            Str::after($this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->getContent(), '</main>'),
            '<form', '</form>'
        );

        // Templates are listed by name, so "Allmän" comes first and is chosen.
        $this->assertStringContainsString('value="'.$first->id.'" required checked', $form);
        $this->assertStringNotContainsString('value="'.$tech->id.'" required checked', $form);
    }

    public function test_the_chosen_template_is_saved(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $this->makeTemplate('Modern magasin', 'modern', null);
        $tech = $this->makeTemplate('Tech', 'tech', null);

        $this->actingAs($admin)->post(route('admin.superblogs.store'), [
            '_form' => 'create-superblog',
            'name' => 'Teknikbladet',
            'template_id' => $tech->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('superblogs', ['name' => 'Teknikbladet', 'template_id' => $tech->id]);
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
