<?php

namespace Tests\Feature\Admin;

use App\Models\Superblog;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperblogThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_theme_is_saved_and_shown_as_selected(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin);

        $this->actingAs($admin)->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'utseende']))
            ->assertOk()
            ->assertSee('Inget tema är valt än.');

        $this->put(route('admin.superblogs.theme.update', $superblog), ['theme' => 'techenspraket'])
            ->assertRedirect(route('admin.superblogs.edit', [$superblog, 'flik' => 'utseende']))
            ->assertSessionHas('status', 'Utseendet har sparats.');

        $this->assertSame('techenspraket', $superblog->fresh()->settings['theme']['name']);

        $this->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'utseende']))
            ->assertSee('value="techenspraket" required checked', false)
            ->assertDontSee('Inget tema är valt än.');
    }

    public function test_an_invalid_theme_is_rejected(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin, ['theme' => ['name' => 'bondebladet']]);

        foreach (['hackat', 'bondebladet;}', ''] as $theme) {
            $this->actingAs($admin)->put(route('admin.superblogs.theme.update', $superblog), ['theme' => $theme])
                ->assertSessionHasErrors('theme');
        }

        $this->assertSame(['theme' => ['name' => 'bondebladet']], $superblog->fresh()->settings);
    }

    public function test_users_without_access_cannot_change_the_theme(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $staff = $this->makeUser(approved: true);
        $superblog = $this->makeSuperblog($admin);

        $this->actingAs($staff)->put(route('admin.superblogs.theme.update', $superblog), ['theme' => 'techenspraket'])
            ->assertForbidden();

        $this->assertNull($superblog->fresh()->settings);
    }

    public function test_saving_a_theme_keeps_other_settings(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin, [
            'foo' => 'bar',
            'theme' => ['name' => 'bondebladet', 'primary' => '#123456'],
        ]);

        $this->actingAs($admin)->put(route('admin.superblogs.theme.update', $superblog), ['theme' => 'techenspraket']);

        $settings = $superblog->fresh()->settings;
        $this->assertSame('bar', $settings['foo']);
        $this->assertSame(['name' => 'techenspraket', 'primary' => '#123456'], $settings['theme']);
    }

    private function makeSuperblog(User $owner, ?array $settings = null): Superblog
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
            'settings' => $settings,
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
