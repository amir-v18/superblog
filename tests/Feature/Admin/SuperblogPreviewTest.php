<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\CreatesSuperblogs;
use Tests\TestCase;

class SuperblogPreviewTest extends TestCase
{
    use CreatesSuperblogs, RefreshDatabase;

    public function test_users_without_access_cannot_see_the_preview(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $staff = $this->makeUser(approved: true);
        $superblog = $this->makeSuperblog($admin);

        $this->actingAs($staff)->get(route('admin.superblogs.preview', $superblog))
            ->assertForbidden();
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $superblog = $this->makeSuperblog($this->makeUser(approved: true, superAdmin: true));

        $this->get(route('admin.superblogs.preview', $superblog))
            ->assertRedirect(route('login'));
    }

    public function test_admin_sees_the_news_home_template_without_indexing(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin);

        $this->actingAs($admin)->get(route('admin.superblogs.preview', $superblog))
            ->assertOk()
            ->assertViewIs('templates.news.home')
            ->assertSee('<span data-live-name>Dagens Budbärare</span>', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_the_templates_site_from_the_registry_is_shown(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin);
        $superblog->template->update(['slug' => 'classic']);
        // A folder that does not exist: the error names the view, which shows the registry was read.
        config(['templates.list.classic.site' => 'templates.registry-check']);

        $this->withoutExceptionHandling();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('View [templates.registry-check.home] not found.');

        $this->actingAs($admin)->get(route('admin.superblogs.preview', $superblog));
    }

    public function test_a_template_missing_from_the_registry_gets_the_default_templates_site(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        // makeSuperblog's template has the slug "news", which is not in the registry.
        $superblog = $this->makeSuperblog($admin);
        config(['templates.list.modern.site' => 'templates.news']);

        $this->actingAs($admin)->get(route('admin.superblogs.preview', $superblog))
            ->assertOk()
            ->assertViewIs('templates.news.home');

        $superblog->template->update(['slug' => 'tech']);

        $this->get(route('admin.superblogs.preview', $superblog))
            ->assertOk()
            ->assertViewIs('templates.news.home');
    }

    public function test_missing_or_unknown_theme_falls_back_to_the_first_theme(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin);

        $this->actingAs($admin)->get(route('admin.superblogs.preview', $superblog))
            ->assertSee('data-theme="bondebladet"', false);

        $superblog->update(['settings' => ['theme' => ['name' => 'okant']]]);

        $this->get(route('admin.superblogs.preview', $superblog))
            ->assertSee('data-theme="bondebladet"', false);
    }

    public function test_without_a_saved_theme_the_templates_theme_is_used(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin);
        $superblog->template->update(['slug' => 'classic']);

        $this->actingAs($admin)->get(route('admin.superblogs.preview', $superblog))
            ->assertSee('data-theme="budbararen"', false)
            ->assertDontSee('data-theme="bondebladet"', false);

        // A saved theme still wins over the template's theme.
        $superblog->update(['settings' => ['theme' => ['name' => 'techenspraket']]]);

        $this->get(route('admin.superblogs.preview', $superblog))
            ->assertSee('data-theme="techenspraket"', false)
            ->assertDontSee('data-theme="budbararen"', false);
    }

    public function test_saved_theme_is_used_in_the_preview(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin);
        $superblog->update(['settings' => ['theme' => ['name' => 'techenspraket']]]);

        $this->actingAs($admin)->get(route('admin.superblogs.preview', $superblog))
            ->assertSee('data-theme="techenspraket"', false)
            ->assertDontSee('data-theme="bondebladet"', false);
    }
}
