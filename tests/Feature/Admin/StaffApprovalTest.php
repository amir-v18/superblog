<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_cannot_approve_or_reject_another_super_admin_from_staff_management(): void
    {
        $actor = $this->makeUser('approved', true);
        $target = $this->makeUser('pending', true);

        $this->actingAs($actor)
            ->from(route('admin.staff.index'))
            ->post(route('admin.staff.update', $target), ['action' => 'approve'])
            ->assertRedirect(route('admin.staff.index'))
            ->assertSessionHasErrors('staff');

        $this->assertSame('pending', $target->fresh()->approval_status);

        $target->approval_status = 'approved';
        $target->save();

        $this->from(route('admin.staff.index'))
            ->post(route('admin.staff.update', $target), ['action' => 'reject'])
            ->assertRedirect(route('admin.staff.index'))
            ->assertSessionHasErrors('staff');

        $this->assertSame('approved', $target->fresh()->approval_status);
        $this->assertTrue($target->fresh()->is_super_admin);
    }

    public function test_super_admin_still_cannot_change_own_approval_status(): void
    {
        $admin = $this->makeUser('approved', true);

        $this->actingAs($admin)
            ->from(route('admin.staff.index'))
            ->post(route('admin.staff.update', $admin), ['action' => 'reject'])
            ->assertRedirect(route('admin.staff.index'))
            ->assertSessionHasErrors('staff', 'You cannot change your own account status.');

        $this->assertSame('approved', $admin->fresh()->approval_status);
    }

    private function makeUser(string $approvalStatus, bool $superAdmin): User
    {
        $user = User::factory()->create();
        $user->approval_status = $approvalStatus;
        $user->is_super_admin = $superAdmin;
        $user->has_all_superblogs_access = false;
        $user->save();

        return $user;
    }
}
