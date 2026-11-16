<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SuperAdminCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_super_admin_creates_an_approved_admin(): void
    {
        $this->artisan('superblog:create-super-admin')
            ->expectsQuestion('Name', 'First Admin')
            ->expectsQuestion('Email', 'first-admin@example.com')
            ->expectsQuestion('Password', 'a-secure-test-password')
            ->expectsQuestion('Confirm password', 'a-secure-test-password')
            ->expectsOutput('The approved admin account was created.')
            ->assertExitCode(0);

        $user = User::where('email', 'first-admin@example.com')->firstOrFail();
        $this->assertTrue($user->is_super_admin);
        $this->assertSame('approved', $user->approval_status);
        $this->assertNotSame('a-secure-test-password', $user->password);
    }

    public function test_create_super_admin_allows_multiple_admins_and_preserves_existing_admin(): void
    {
        $existing = $this->makeUser('existing-admin@example.com', true, 'approved');
        $existing->google_subject = 'existing-admin-subject';
        $existing->save();

        $this->artisan('superblog:create-super-admin')
            ->expectsQuestion('Name', 'Second Admin')
            ->expectsQuestion('Email', 'second-admin@example.com')
            ->expectsQuestion('Password', 'another-secure-password')
            ->expectsQuestion('Confirm password', 'another-secure-password')
            ->expectsOutput('The approved admin account was created.')
            ->assertExitCode(0);

        $second = User::where('email', 'second-admin@example.com')->firstOrFail();
        $this->assertTrue($second->is_super_admin);
        $this->assertSame('approved', $second->approval_status);
        $this->assertSame($existing->id, $existing->fresh()->id);
        $this->assertSame('existing-admin-subject', $existing->fresh()->google_subject);
        $this->assertTrue($existing->fresh()->is_super_admin);
        $this->assertSame('approved', $existing->fresh()->approval_status);
    }

    public function test_create_admin_does_not_depend_on_a_migrations_lock_row(): void
    {
        DB::table('migrations')->delete();

        $this->artisan('superblog:create-super-admin')
            ->expectsQuestion('Name', 'Local Admin')
            ->expectsQuestion('Email', 'local-admin@example.com')
            ->expectsQuestion('Password', 'a-secure-test-password')
            ->expectsQuestion('Confirm password', 'a-secure-test-password')
            ->expectsOutput('The approved admin account was created.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'email' => 'local-admin@example.com',
            'is_super_admin' => true,
            'approval_status' => 'approved',
        ]);
    }

    public function test_make_super_admin_can_promote_another_account_without_changing_existing_admin(): void
    {
        $existing = $this->makeUser('existing-admin@example.com', true, 'approved');
        $existing->google_subject = 'existing-admin-subject';
        $existing->save();
        $target = $this->makeUser('staff@example.com', false, 'rejected');
        $target->google_subject = 'target-google-subject';
        $target->save();

        $this->artisan('superblog:make-super-admin', ['email' => $target->email])
            ->expectsConfirmation('Approve staff@example.com and grant access to every Superblog?', 'yes')
            ->expectsOutput('The existing account is now an approved admin.')
            ->assertExitCode(0);

        $this->assertTrue($target->fresh()->is_super_admin);
        $this->assertSame('approved', $target->fresh()->approval_status);
        $this->assertSame('target-google-subject', $target->fresh()->google_subject);
        $this->assertTrue($existing->fresh()->is_super_admin);
        $this->assertSame('existing-admin-subject', $existing->fresh()->google_subject);
    }

    public function test_make_super_admin_can_promote_the_first_existing_account(): void
    {
        $target = $this->makeUser('first-admin@example.com', false, 'pending');

        $this->artisan('superblog:make-super-admin', ['email' => $target->email])
            ->expectsConfirmation('Approve first-admin@example.com and grant access to every Superblog?', 'yes')
            ->expectsOutput('The existing account is now an approved admin.')
            ->assertExitCode(0);

        $this->assertTrue($target->fresh()->is_super_admin);
        $this->assertSame('approved', $target->fresh()->approval_status);
    }

    public function test_make_super_admin_can_promote_multiple_accounts_in_sequence(): void
    {
        $first = $this->makeUser('first@example.com', false, 'pending');
        $second = $this->makeUser('second@example.com', false, 'rejected');

        foreach ([$first, $second] as $user) {
            $this->artisan('superblog:make-super-admin', ['email' => $user->email])
                ->expectsConfirmation("Approve {$user->email} and grant access to every Superblog?", 'yes')
                ->expectsOutput('The existing account is now an approved admin.')
                ->assertExitCode(0);
        }

        $this->assertTrue($first->fresh()->is_super_admin);
        $this->assertSame('approved', $first->fresh()->approval_status);
        $this->assertTrue($second->fresh()->is_super_admin);
        $this->assertSame('approved', $second->fresh()->approval_status);
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
