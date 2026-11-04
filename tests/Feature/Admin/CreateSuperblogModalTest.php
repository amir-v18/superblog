<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('id="domain" name="domain" value=""', false);

        $content = $response->getContent();
        $this->assertSame(1, substr_count($content, 'value="Nytt magasin från modalen"'));
        $this->assertSame(1, substr_count($content, 'value="Dagens Budbärare"'));
        // The error list is shown once, in the modal, not again above the builder form.
        $this->assertSame(1, substr_count($content, 'Rätta följande:'));
        $this->assertDatabaseCount('superblogs', 1);
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
