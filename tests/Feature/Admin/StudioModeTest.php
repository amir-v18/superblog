<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\StudioModeController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSuperblogs;
use Tests\TestCase;

class StudioModeTest extends TestCase
{
    use CreatesSuperblogs, RefreshDatabase;

    private const PLAIN_TAG = '<html lang="sv">';

    private const LIGHT_TAG = '<html lang="sv" data-studio-mode="light">';

    public function test_without_a_cookie_the_html_tag_is_unchanged(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);

        $this->get(route('login'))->assertOk()
            ->assertSee(self::PLAIN_TAG, false)
            ->assertDontSee('data-studio-mode', false);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee(self::PLAIN_TAG, false)
            ->assertDontSee('data-studio-mode', false);
    }

    public function test_a_dark_or_unknown_cookie_writes_no_attribute(): void
    {
        foreach (['dark', 'blue', ''] as $value) {
            $this->withCookie(StudioModeController::COOKIE, $value)
                ->get(route('login'))
                ->assertSee(self::PLAIN_TAG, false)
                ->assertDontSee('data-studio-mode', false);
        }
    }

    public function test_the_light_cookie_writes_the_attribute_on_the_dashboard_and_the_login_page(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);

        $this->withCookie(StudioModeController::COOKIE, 'light')
            ->get(route('login'))->assertOk()
            ->assertSee(self::LIGHT_TAG, false);

        $this->withCookie(StudioModeController::COOKIE, 'light')
            ->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee(self::LIGHT_TAG, false);
    }

    public function test_a_guest_can_save_the_choice(): void
    {
        $this->assertSaved(null, route('login'));
    }

    public function test_an_editor_can_save_the_choice(): void
    {
        $this->assertSaved($this->makeUser(approved: true), route('admin.dashboard'));
    }

    public function test_a_super_admin_can_save_the_choice(): void
    {
        $this->assertSaved($this->makeUser(approved: true, superAdmin: true), route('admin.dashboard'));
    }

    public function test_dark_can_be_saved_too(): void
    {
        $this->from(route('login'))
            ->post(route('studio-mode.update'), ['mode' => 'dark'])
            ->assertRedirect(route('login'))
            ->assertCookie(StudioModeController::COOKIE, 'dark');
    }

    public function test_other_values_are_rejected_and_no_cookie_is_set(): void
    {
        foreach ([['mode' => 'blue'], ['mode' => ''], []] as $payload) {
            $this->from(route('login'))
                ->post(route('studio-mode.update'), $payload)
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors('mode')
                ->assertCookieMissing(StudioModeController::COOKIE);
        }
    }

    public function test_a_previous_page_on_another_site_is_never_used_as_the_target(): void
    {
        $this->withHeader('referer', 'https://example.org/elsewhere')
            ->post(route('studio-mode.update'), ['mode' => 'light'])
            ->assertRedirect(route('admin.dashboard'))
            ->assertCookie(StudioModeController::COOKIE, 'light');
    }

    public function test_the_preview_and_the_public_site_never_get_the_attribute(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin);
        $superblog->update(['status' => 'published', 'settings' => ['theme' => 'bondebladet']]);

        // The builder preview is a page of its own: the magazine's theme on html, robots meta and header intact.
        $this->withCookie(StudioModeController::COOKIE, 'light')
            ->actingAs($admin)->get(route('admin.superblogs.preview', $superblog))->assertOk()
            ->assertDontSee('data-studio-mode', false)
            ->assertSee('data-theme="bondebladet"', false)
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->withCookie(StudioModeController::COOKIE, 'light')
            ->get(route('public.superblogs.show', $superblog))->assertOk()
            ->assertDontSee('data-studio-mode', false)
            ->assertSee('data-theme="bondebladet"', false);

        // The admin pages keep their own robots meta in both modes.
        $this->withCookie(StudioModeController::COOKIE, 'light')
            ->actingAs($admin)->get(route('admin.dashboard'))
            ->assertSee(self::LIGHT_TAG, false)
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    private function assertSaved(?User $user, string $previous): void
    {
        if ($user) {
            $this->actingAs($user);
        }

        $response = $this->from($previous)
            ->post(route('studio-mode.update'), ['mode' => 'light'])
            ->assertRedirect($previous)
            ->assertCookie(StudioModeController::COOKIE, 'light');

        // Kept for a long time, so the choice survives closing the browser.
        $cookie = $response->getCookie(StudioModeController::COOKIE, false);
        $this->assertGreaterThan(now()->addYear()->getTimestamp(), $cookie->getExpiresTime());
    }
}
