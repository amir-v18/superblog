<?php

namespace Tests\Feature\Admin;

use App\Models\Superblog;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StaffDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_admin_can_delete_an_eligible_account_and_its_access_records(): void
    {
        $admin = $this->makeUser('admin@example.com', true, 'approved');
        $staff = $this->makeUser('staff@example.com', false, 'rejected');
        $assignedBlog = $this->makeSuperblog($admin, 'assigned-blog');
        $staff->assignedSuperblogs()->attach($assignedBlog);
        config(['session.driver' => 'database', 'session.connection' => null, 'session.table' => 'sessions']);
        DB::table('sessions')->insert([
            'id' => 'staff-session',
            'user_id' => $staff->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'test-session',
            'last_activity' => now()->timestamp,
        ]);
        DB::table('password_reset_tokens')->insert([
            'email' => $staff->email,
            'token' => 'test-reset-token',
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.staff.destroy', $staff))
            ->assertRedirect(route('admin.staff.index'))
            ->assertSessionHas('status', 'Permanently deleted the account for '.$staff->name.'.');

        $this->assertDatabaseMissing('users', ['id' => $staff->id]);
        $this->assertDatabaseMissing('superblog_user', ['user_id' => $staff->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'staff-session']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $staff->email]);
        $this->assertDatabaseHas('superblogs', ['id' => $assignedBlog->id, 'user_id' => $admin->id]);
    }

    public function test_signed_in_account_cannot_be_deleted(): void
    {
        $admin = $this->makeUser('admin@example.com', true, 'approved');

        $this->actingAs($admin)
            ->from(route('admin.staff.index'))
            ->delete(route('admin.staff.destroy', $admin))
            ->assertRedirect(route('admin.staff.index'))
            ->assertSessionHasErrors('staff');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_super_admin_cannot_be_deleted_through_a_direct_request(): void
    {
        $admin = $this->makeUser('admin@example.com', true, 'approved');
        $otherAdmin = $this->makeUser('other-admin@example.com', true, 'approved');

        $this->actingAs($admin)
            ->from(route('admin.staff.index'))
            ->delete(route('admin.staff.destroy', $otherAdmin))
            ->assertRedirect(route('admin.staff.index'))
            ->assertSessionHasErrors('staff');

        $this->assertDatabaseHas('users', ['id' => $otherAdmin->id, 'is_super_admin' => true]);
    }

    public function test_user_who_owns_a_superblog_cannot_be_deleted(): void
    {
        $admin = $this->makeUser('admin@example.com', true, 'approved');
        $owner = $this->makeUser('owner@example.com', false, 'approved');
        $blog = $this->makeSuperblog($owner, 'owned-blog');

        $this->actingAs($admin)
            ->from(route('admin.staff.index'))
            ->delete(route('admin.staff.destroy', $owner))
            ->assertRedirect(route('admin.staff.index'))
            ->assertSessionHasErrors('staff');

        $this->assertDatabaseHas('users', ['id' => $owner->id]);
        $this->assertDatabaseHas('superblogs', ['id' => $blog->id, 'user_id' => $owner->id]);
    }

    public function test_ordinary_staff_cannot_delete_accounts(): void
    {
        $staff = $this->makeUser('staff@example.com', false, 'approved');
        $target = $this->makeUser('target@example.com', false, 'pending');

        $this->actingAs($staff)
            ->delete(route('admin.staff.destroy', $target))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_dashboard_shows_delete_action_only_for_eligible_staff(): void
    {
        $admin = $this->makeUser('admin@example.com', true, 'approved');
        $eligible = $this->makeUser('eligible@example.com', false, 'pending', 'Eligible Staff');
        $owner = $this->makeUser('owner@example.com', false, 'pending', 'Blog Owner');
        $this->makeSuperblog($owner, 'owned-blog');
        $otherAdmin = $this->makeUser('other-admin@example.com', true, 'approved');

        $this->actingAs($admin)
            ->get(route('admin.staff.index'))
            ->assertOk()
            ->assertSee(route('admin.staff.confirm-delete', $eligible), false)
            ->assertSee('aria-label="Delete Eligible Staff"', false)
            ->assertDontSee('aria-label="Delete Staff Admin"', false)
            ->assertDontSee('aria-label="Delete Blog Owner"', false)
            ->assertDontSee('aria-label="Delete Staff Other Admin"', false)
            ->assertSee('Owned Superblogs must be transferred before deletion.')
            ->assertSee('Approve')
            ->assertSee('Reject');
    }

    public function test_confirmation_page_identifies_account_and_uses_csrf_protected_delete_form(): void
    {
        $admin = $this->makeUser('admin@example.com', true, 'approved');
        $staff = $this->makeUser('staff@example.com', false, 'pending', 'Staff Member');

        $this->actingAs($admin)
            ->get(route('admin.staff.confirm-delete', $staff))
            ->assertOk()
            ->assertSee('Delete Staff Member?')
            ->assertSee('staff@example.com')
            ->assertSee('This permanently deletes this staff account')
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('Yes, permanently delete account');
    }

    public function test_confirmation_page_rejects_accounts_that_cannot_be_deleted(): void
    {
        $admin = $this->makeUser('admin@example.com', true, 'approved');
        $owner = $this->makeUser('owner@example.com', false, 'approved');
        $this->makeSuperblog($owner, 'owned-blog');

        $this->actingAs($admin)
            ->from(route('admin.staff.index'))
            ->get(route('admin.staff.confirm-delete', $owner))
            ->assertRedirect(route('admin.staff.index'))
            ->assertSessionHasErrors('staff');
    }

    private function makeUser(string $email, bool $superAdmin, string $approvalStatus, ?string $name = null): User
    {
        $user = User::factory()->create([
            'name' => $name ?? 'Staff '.str($email)->before('@')->title(),
            'email' => $email,
        ]);
        $user->is_super_admin = $superAdmin;
        $user->has_all_superblogs_access = false;
        $user->approval_status = $approvalStatus;
        $user->save();

        return $user;
    }

    private function makeSuperblog(User $owner, string $slug): Superblog
    {
        $template = Template::create([
            'name' => "Template {$slug}",
            'slug' => "template-{$slug}",
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);

        return $owner->superblogs()->create([
            'template_id' => $template->id,
            'name' => str($slug)->replace('-', ' ')->title()->toString(),
            'slug' => $slug,
        ]);
    }
}
