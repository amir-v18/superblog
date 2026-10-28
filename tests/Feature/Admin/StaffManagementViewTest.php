<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffManagementViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_actions_are_hidden_for_another_super_admin_row_but_remain_for_ordinary_staff(): void
    {
        $admin = $this->makeUser('admin@example.com', true, 'approved');
        $duplicateAdminRow = $this->makeUser('duplicate@example.com', true, 'pending', 'google-person-subject');
        $staff = $this->makeUser('staff@example.com', false, 'pending');

        $this->actingAs($admin)
            ->get(route('admin.staff.index'))
            ->assertOk()
            ->assertDontSee(route('admin.staff.update', $duplicateAdminRow), false)
            ->assertSee(route('admin.staff.update', $staff), false)
            ->assertSee('Approve')
            ->assertSee('Reject');
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
