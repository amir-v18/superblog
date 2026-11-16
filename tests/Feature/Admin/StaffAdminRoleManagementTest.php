<?php

namespace Tests\Feature\Admin;

use App\Models\Superblog;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAdminRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_admin_can_promote_an_approved_editor_without_changing_identity_or_assignments(): void
    {
        $admin = $this->makeUser('admin@example.com', true, 'approved', 'admin-subject');
        $editor = $this->makeUser('editor@example.com', false, 'approved', 'editor-subject');
        $superblog = $this->makeSuperblog($admin);
        $editor->assignedSuperblogs()->attach($superblog);

        $this->actingAs($admin)
            ->post(route('admin.staff.update', $editor), ['action' => 'promote_admin'])
            ->assertRedirect(route('admin.staff.index'))
            ->assertSessionHas('status', "{$editor->name} is now an admin.");

        $updatedEditor = $editor->fresh();
        $this->assertSame($editor->id, $updatedEditor->id);
        $this->assertTrue($updatedEditor->is_super_admin);
        $this->assertSame('approved', $updatedEditor->approval_status);
        $this->assertSame('editor-subject', $updatedEditor->google_subject);
        $this->assertDatabaseHas('superblog_user', [
            'user_id' => $editor->id,
            'superblog_id' => $superblog->id,
        ]);
        $this->assertSame('admin-subject', $admin->fresh()->google_subject);
    }

    public function test_admin_cannot_promote_a_pending_editor(): void
    {
        $admin = $this->makeUser('admin@example.com', true, 'approved');
        $pending = $this->makeUser('pending@example.com', false, 'pending', 'pending-subject');

        $this->actingAs($admin)
            ->from(route('admin.staff.index'))
            ->post(route('admin.staff.update', $pending), ['action' => 'promote_admin'])
            ->assertRedirect(route('admin.staff.index'))
            ->assertSessionHasErrors('staff', 'Only approved editors can be promoted to admin.');

        $this->assertFalse($pending->fresh()->is_super_admin);
        $this->assertSame('pending', $pending->fresh()->approval_status);
        $this->assertSame('pending-subject', $pending->fresh()->google_subject);
    }

    public function test_approved_admin_can_demote_another_admin_and_keep_its_approval_identity_and_assignments(): void
    {
        $admin = $this->makeUser('admin@example.com', true, 'approved', 'admin-subject');
        $target = $this->makeUser('other-admin@example.com', true, 'pending', 'other-admin-subject');
        $superblog = $this->makeSuperblog($admin);
        $target->assignedSuperblogs()->attach($superblog);

        $this->actingAs($admin)
            ->post(route('admin.staff.update', $target), ['action' => 'demote_admin'])
            ->assertRedirect(route('admin.staff.index'))
            ->assertSessionHas('status', "{$target->name} is now an editor.");

        $updatedTarget = $target->fresh();
        $this->assertSame($target->id, $updatedTarget->id);
        $this->assertFalse($updatedTarget->is_super_admin);
        $this->assertSame('pending', $updatedTarget->approval_status);
        $this->assertSame('other-admin-subject', $updatedTarget->google_subject);
        $this->assertDatabaseHas('superblog_user', [
            'user_id' => $target->id,
            'superblog_id' => $superblog->id,
        ]);
        $this->assertSame(1, User::query()->where('is_super_admin', true)->count());
    }

    public function test_admin_cannot_demote_their_own_account(): void
    {
        $admin = $this->makeUser('admin@example.com', true, 'approved', 'admin-subject');

        $this->actingAs($admin)
            ->from(route('admin.staff.index'))
            ->post(route('admin.staff.update', $admin), ['action' => 'demote_admin'])
            ->assertRedirect(route('admin.staff.index'))
            ->assertSessionHasErrors('staff', 'You cannot demote your own admin account.');

        $this->assertTrue($admin->fresh()->is_super_admin);
        $this->assertSame('approved', $admin->fresh()->approval_status);
        $this->assertSame('admin-subject', $admin->fresh()->google_subject);
    }

    public function test_ordinary_approved_editor_cannot_promote_or_demote_accounts_directly(): void
    {
        $editor = $this->makeUser('editor@example.com', false, 'approved');
        $target = $this->makeUser('target@example.com', false, 'approved');
        $otherAdmin = $this->makeUser('admin@example.com', true, 'approved');

        $this->actingAs($editor)
            ->post(route('admin.staff.update', $target), ['action' => 'promote_admin'])
            ->assertForbidden();
        $this->post(route('admin.staff.update', $otherAdmin), ['action' => 'demote_admin'])
            ->assertForbidden();

        $this->assertFalse($target->fresh()->is_super_admin);
        $this->assertTrue($otherAdmin->fresh()->is_super_admin);
    }

    public function test_unapproved_admin_cannot_use_staff_role_actions(): void
    {
        $pendingAdmin = $this->makeUser('pending-admin@example.com', true, 'pending');
        $editor = $this->makeUser('editor@example.com', false, 'approved');

        $this->actingAs($pendingAdmin)
            ->post(route('admin.staff.update', $editor), ['action' => 'promote_admin'])
            ->assertForbidden();

        $this->assertFalse($editor->fresh()->is_super_admin);
    }

    public function test_staff_page_shows_confirmed_role_actions_only_for_eligible_accounts(): void
    {
        $admin = $this->makeUser('admin@example.com', true, 'approved');
        $otherAdmin = $this->makeUser('other-admin@example.com', true, 'approved');
        $approvedEditor = $this->makeUser('editor@example.com', false, 'approved');
        $pendingEditor = $this->makeUser('pending@example.com', false, 'pending');
        $otherAdmin->update(['name' => 'Other Admin']);
        $approvedEditor->update(['name' => 'Approved Editor']);
        $pendingEditor->update(['name' => 'Pending Editor']);
        $admin->update(['name' => 'Current Admin']);

        $this->actingAs($admin)
            ->get(route('admin.staff.index'))
            ->assertOk()
            ->assertSee('value="demote_admin"', false)
            ->assertSee('value="promote_admin"', false)
            ->assertSee('onsubmit="return confirm(', false)
            ->assertSee('Demote Other Admin to editor?', false)
            ->assertSee('Promote Approved Editor to admin?', false)
            ->assertDontSee('Promote Pending Editor to admin?', false)
            ->assertDontSee('Demote Current Admin to editor?', false);
    }

    private function makeUser(string $email, bool $isAdmin, string $approvalStatus, ?string $googleSubject = null): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->is_super_admin = $isAdmin;
        $user->approval_status = $approvalStatus;
        $user->google_subject = $googleSubject;
        $user->save();

        return $user;
    }

    private function makeSuperblog(User $owner): Superblog
    {
        $template = Template::firstOrCreate(['slug' => 'staff-role-test'], [
            'name' => 'Staff role test',
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);

        return $owner->superblogs()->create([
            'template_id' => $template->id,
            'name' => 'Role access test',
            'slug' => 'role-access-test',
        ]);
    }
}
