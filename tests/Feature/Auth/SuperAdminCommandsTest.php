<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SuperAdminCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_super_admin_creates_the_first_approved_super_admin(): void
    {
        $this->artisan('superblog:create-super-admin')
            ->expectsQuestion('Name', 'First Admin')
            ->expectsQuestion('Email', 'first-admin@example.com')
            ->expectsQuestion('Password', 'a-secure-test-password')
            ->expectsQuestion('Confirm password', 'a-secure-test-password')
            ->expectsOutput('The approved super-admin account was created.')
            ->assertExitCode(0);

        $user = User::where('email', 'first-admin@example.com')->firstOrFail();
        $this->assertTrue($user->is_super_admin);
        $this->assertSame('approved', $user->approval_status);
        $this->assertNotSame('a-secure-test-password', $user->password);
    }

    public function test_create_super_admin_refuses_to_create_a_second_super_admin(): void
    {
        $this->makeUser('existing-admin@example.com', true, 'approved');

        $this->artisan('superblog:create-super-admin')
            ->expectsQuestion('Name', 'Second Admin')
            ->expectsQuestion('Email', 'second-admin@example.com')
            ->expectsQuestion('Password', 'another-secure-password')
            ->expectsQuestion('Confirm password', 'another-secure-password')
            ->expectsOutput('A super admin already exists. No account was created.')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseMissing('users', ['email' => 'second-admin@example.com']);
    }

    public function test_make_super_admin_refuses_a_second_promotion_without_changing_the_target(): void
    {
        $this->makeUser('existing-admin@example.com', true, 'approved');
        $target = $this->makeUser('staff@example.com', false, 'rejected');

        $this->artisan('superblog:make-super-admin', ['email' => $target->email])
            ->expectsConfirmation('Approve staff@example.com and grant access to every Superblog?', 'yes')
            ->expectsOutput('A super admin already exists. The account was not changed.')
            ->assertExitCode(1);

        $this->assertFalse($target->fresh()->is_super_admin);
        $this->assertSame('rejected', $target->fresh()->approval_status);
    }

    public function test_make_super_admin_can_promote_the_first_existing_account(): void
    {
        $target = $this->makeUser('first-admin@example.com', false, 'pending');

        $this->artisan('superblog:make-super-admin', ['email' => $target->email])
            ->expectsConfirmation('Approve first-admin@example.com and grant access to every Superblog?', 'yes')
            ->expectsOutput('The existing account is now an approved super admin.')
            ->assertExitCode(0);

        $this->assertTrue($target->fresh()->is_super_admin);
        $this->assertSame('approved', $target->fresh()->approval_status);
    }

    public function test_make_super_admin_fails_closed_when_the_lock_row_is_missing(): void
    {
        $target = $this->makeUser('staff@example.com', false, 'rejected');
        DB::table('migrations')->delete();

        $this->artisan('superblog:make-super-admin', ['email' => $target->email])
            ->expectsConfirmation('Approve staff@example.com and grant access to every Superblog?', 'yes')
            ->expectsOutput('Could not safely verify the super-admin lock. No account was promoted.')
            ->assertExitCode(1);

        $this->assertFalse($target->fresh()->is_super_admin);
        $this->assertSame('rejected', $target->fresh()->approval_status);
    }

    public function test_create_super_admin_fails_closed_when_the_lock_row_is_missing(): void
    {
        DB::table('migrations')->delete();

        $this->artisan('superblog:create-super-admin')
            ->expectsQuestion('Name', 'First Admin')
            ->expectsQuestion('Email', 'first-admin@example.com')
            ->expectsQuestion('Password', 'a-secure-test-password')
            ->expectsQuestion('Confirm password', 'a-secure-test-password')
            ->expectsOutput('Could not safely verify the super-admin lock. No account was created.')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'first-admin@example.com']);
    }

    private function makeUser(string $email, bool $superAdmin, string $approvalStatus): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->is_super_admin = $superAdmin;
        $user->approval_status = $approvalStatus;
        $user->save();

        return $user;
    }
}
