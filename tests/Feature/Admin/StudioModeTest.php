<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\StudioModeController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
            ->assertDontSee('data-studio-mode="', false);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee(self::PLAIN_TAG, false)
            ->assertDontSee('data-studio-mode="', false);
    }

    public function test_a_dark_or_unknown_cookie_writes_no_attribute(): void
    {
        foreach (['dark', 'blue', ''] as $value) {
            $this->withCookie(StudioModeController::COOKIE, $value)
                ->get(route('login'))
                ->assertSee(self::PLAIN_TAG, false)
                ->assertDontSee('data-studio-mode="', false);
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

    public function test_the_switch_offers_light_mode_to_a_guest_an_editor_and_a_super_admin(): void
    {
        $this->assertSwitch($this->get(route('login'))->assertOk()->getContent(), 'light');

        $editor = $this->makeUser(approved: true);
        $this->assertSwitch($this->actingAs($editor)->get(route('admin.dashboard'))->assertOk()->getContent(), 'light');

        $admin = $this->makeUser(approved: true, superAdmin: true);
        $this->assertSwitch($this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->getContent(), 'light');
    }

    public function test_in_light_mode_the_switch_offers_dark_mode_to_every_role(): void
    {
        $this->assertSwitch($this->withCookie(StudioModeController::COOKIE, 'light')
            ->get(route('login'))->assertOk()->getContent(), 'dark');

        foreach ([$this->makeUser(approved: true), $this->makeUser(approved: true, superAdmin: true)] as $user) {
            $this->assertSwitch($this->withCookie(StudioModeController::COOKIE, 'light')
                ->actingAs($user)->get(route('admin.dashboard'))->assertOk()->getContent(), 'dark');
        }
    }

    public function test_the_builder_has_exactly_the_corner_switch_on_every_width_like_other_pages(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin);
        $this->actingAs($admin);

        $corner = 'data-studio-mode-form class="absolute top-2 right-4 z-10">';

        foreach (['grund', 'utseende', 'floden', 'innehall', 'skriv-artikel', 'publicera'] as $tab) {
            $html = $this->get(route('admin.superblogs.edit', [$superblog, 'flik' => $tab]))->assertOk()->getContent();

            // One switch, the corner one, with no width condition on its classes; none in the preview column.
            $this->assertSame(1, substr_count($html, 'data-studio-mode-form'), "flik $tab");
            $this->assertStringContainsString($corner, $html, "flik $tab");
            $this->assertStringNotContainsString('data-studio-mode-form', Str::after($html, '<section aria-label="Förhandsvisning"'), "flik $tab");
        }

        $this->assertSame(1, substr_count($this->get(route('admin.dashboard'))->getContent(), $corner));
    }

    public function test_the_preview_and_the_public_site_have_no_switch(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin);
        $superblog->update(['status' => 'published', 'settings' => ['theme' => 'bondebladet']]);

        $this->withCookie(StudioModeController::COOKIE, 'light')
            ->actingAs($admin)->get(route('admin.superblogs.preview', $superblog))->assertOk()
            ->assertDontSee('data-studio-mode-form', false)
            ->assertDontSee(route('studio-mode.update'), false);

        $this->withCookie(StudioModeController::COOKIE, 'light')
            ->get(route('public.superblogs.show', $superblog))->assertOk()
            ->assertDontSee('data-studio-mode-form', false)
            ->assertDontSee(route('studio-mode.update'), false);
    }

    public function test_a_request_from_the_switch_script_gets_no_content_but_the_cookie(): void
    {
        $this->postJson(route('studio-mode.update'), ['mode' => 'light'])
            ->assertNoContent()
            ->assertCookie(StudioModeController::COOKIE, 'light');

        $this->postJson(route('studio-mode.update'), ['mode' => 'blue'])
            ->assertUnprocessable()
            ->assertCookieMissing(StudioModeController::COOKIE);
    }

    /**
     * The switch offers $next: both mode buttons are there in one labelled set, each posts its own mode, and just
     * the button of the current mode, the other one, is marked as pressed.
     */
    private function assertSwitch(string $html, string $next): void
    {
        $current = $next === 'light' ? 'dark' : 'light';
        $labels = ['dark' => 'Mörkt läge', 'light' => 'Ljust läge'];

        $this->assertSame(1, substr_count($html, 'data-studio-mode-form'));
        $this->assertStringContainsString('action="'.route('studio-mode.update').'"', $html);
        $this->assertStringContainsString('role="group" aria-label="Färgläge"', $html);
        $this->assertSame(2, substr_count($html, 'data-studio-mode-option'));

        foreach (['dark', 'light'] as $mode) {
            $pressed = $mode === $current ? 'true' : 'false';
            $this->assertStringContainsString('name="mode" value="'.$mode.'" aria-label="'.$labels[$mode].'" aria-pressed="'.$pressed.'"', $html);
        }
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
