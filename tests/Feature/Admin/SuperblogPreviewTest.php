<?php

namespace Tests\Feature\Admin;

use App\Models\Superblog;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperblogPreviewTest extends TestCase
{
    use RefreshDatabase;

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
