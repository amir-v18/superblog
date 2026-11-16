<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffManagementViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_actions_are_available_for_other_admins_and_ordinary_staff_but_not_self(): void
    {
        $admin = $this->makeUser('admin@example.com', true, 'approved');
        $admin->update(['name' => 'Current Admin']);
        $duplicateAdminRow = $this->makeUser('duplicate@example.com', true, 'pending', 'google-person-subject');
        $staff = $this->makeUser('staff@example.com', false, 'pending');

        $this->actingAs($admin)
            ->get(route('admin.staff.index'))
            ->assertOk()
            ->assertSee(route('admin.staff.update', $duplicateAdminRow), false)
            ->assertSee(route('admin.staff.update', $staff), false)
            ->assertSee('Demote to editor')
            ->assertSee('Approve')
            ->assertSee('Reject')
            ->assertDontSee('Demote Current Admin to editor?', false);
    }

    private function makeUser(string $email, bool $superAdmin, string $approvalStatus, ?string $googleSubject = null): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->is_super_admin = $superAdmin;
        $user->approval_status = $approvalStatus;
        $user->google_subject = $googleSubject;
        $user->save();

        return $user;
    }
}
