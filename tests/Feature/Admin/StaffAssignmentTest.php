<?php

namespace Tests\Feature\Admin;

use App\Models\Superblog;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_super_admin_can_add_and_remove_staff_assignments(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $staff = $this->makeUser(approved: true);
        $first = $this->makeSuperblog($admin, 'assignment-first');
        $second = $this->makeSuperblog($admin, 'assignment-second');
        $third = $this->makeSuperblog($admin, 'assignment-third');
        $staff->assignedSuperblogs()->attach($first);

        $this->actingAs($admin)
            ->get(route('admin.staff.index'))
            ->assertOk()
            ->assertSee('Superblog assignments')
            ->assertSee('Assignment First')
            ->assertSee('Save assignments');

        $this->post(route('admin.staff.update', $staff), [
            'action' => 'assign',
            'assignments' => [$second->id, $third->id],
        ])->assertRedirect(route('admin.staff.index'))
            ->assertSessionHas('status', "Superblog assignments updated for {$staff->name}.");

        $this->assertDatabaseMissing('superblog_user', [
            'user_id' => $staff->id,
            'superblog_id' => $first->id,
        ]);
        $this->assertDatabaseHas('superblog_user', [
            'user_id' => $staff->id,
            'superblog_id' => $second->id,
        ]);
        $this->assertDatabaseHas('superblog_user', [
            'user_id' => $staff->id,
            'superblog_id' => $third->id,
        ]);

        $this->post(route('admin.staff.update', $staff), ['action' => 'assign'])
            ->assertRedirect(route('admin.staff.index'));

        $this->assertDatabaseMissing('superblog_user', ['user_id' => $staff->id]);
        $this->assertSame('approved', $staff->fresh()->approval_status);
        $this->assertFalse($staff->fresh()->is_super_admin);
        $this->assertFalse($staff->fresh()->has_all_superblogs_access);
    }

    public function test_ordinary_staff_cannot_change_assignments_with_a_direct_request(): void
    {
        $owner = $this->makeUser(approved: true, superAdmin: true);
        $staff = $this->makeUser(approved: true);
        $assigned = $this->makeSuperblog($owner, 'staff-assigned');
        $unassigned = $this->makeSuperblog($owner, 'staff-unassigned');
        $staff->assignedSuperblogs()->attach($assigned);

        $this->actingAs($staff)
            ->post(route('admin.staff.update', $staff), [
                'action' => 'assign',
                'assignments' => [$unassigned->id],
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('superblog_user', [
            'user_id' => $staff->id,
            'superblog_id' => $assigned->id,
        ]);
        $this->assertDatabaseMissing('superblog_user', [
            'user_id' => $staff->id,
            'superblog_id' => $unassigned->id,
        ]);
    }

    public function test_assignments_control_staff_access_while_approval_and_super_admin_access_still_apply(): void
    {
        $owner = $this->makeUser(approved: true, superAdmin: true);
        $staff = $this->makeUser(approved: true);
        $assigned = $this->makeSuperblog($owner, 'assigned-access');
        $unassigned = $this->makeSuperblog($owner, 'unassigned-access');
        $staff->assignedSuperblogs()->attach($assigned);

        $this->actingAs($staff)
            ->get(route('admin.superblogs.edit', $assigned))
            ->assertOk();
        $this->get(route('admin.superblogs.edit', $unassigned))->assertForbidden();

        $staff->approval_status = 'pending';
        $staff->save();

        $this->get(route('admin.dashboard'))
            ->assertForbidden()
            ->assertSee('Your account is awaiting approval');
        $this->assertFalse($staff->fresh()->canAccessSuperblog($assigned));

        $this->actingAs($owner)
            ->get(route('admin.superblogs.edit', $unassigned))
            ->assertOk();
        $this->assertTrue($owner->fresh()->canAccessSuperblog($unassigned));
    }

    public function test_invalid_superblog_ids_and_broader_access_accounts_cannot_be_assigned(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $staff = $this->makeUser(approved: true);
        $superblog = $this->makeSuperblog($admin, 'valid-assignment');

        $this->actingAs($admin)
            ->from(route('admin.staff.index'))
            ->post(route('admin.staff.update', $staff), [
                'action' => 'assign',
                'assignments' => [999999],
            ])
            ->assertRedirect(route('admin.staff.index'))
            ->assertSessionHasErrors('assignments.0');

        $this->assertDatabaseMissing('superblog_user', ['user_id' => $staff->id]);

        $allAccessStaff = $this->makeUser(approved: true);
        $allAccessStaff->has_all_superblogs_access = true;
        $allAccessStaff->save();

        $this->post(route('admin.staff.update', $allAccessStaff), [
            'action' => 'assign',
            'assignments' => [$superblog->id],
        ])->assertSessionHasErrors('assignments');
        $this->assertDatabaseMissing('superblog_user', ['user_id' => $allAccessStaff->id]);

        $this->post(route('admin.staff.update', $admin), [
            'action' => 'assign',
            'assignments' => [$superblog->id],
        ])->assertSessionHasErrors('assignments');
        $this->assertTrue($admin->fresh()->is_super_admin);
        $this->assertDatabaseMissing('superblog_user', ['user_id' => $admin->id]);
    }

    private function makeUser(bool $approved = false, bool $superAdmin = false): User
    {
        $user = User::factory()->create();
        $user->approval_status = $approved ? 'approved' : 'pending';
        $user->is_super_admin = $superAdmin;
        $user->has_all_superblogs_access = false;
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
            'template_id' => $template->getKey(),
            'name' => str($slug)->replace('-', ' ')->title()->toString(),
            'slug' => $slug,
        ]);
    }
}
