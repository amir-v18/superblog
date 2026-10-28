<?php

namespace Tests\Feature\Auth;

use App\Models\Superblog;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureGoogle();
    }

    public function test_linked_super_admin_signs_in_without_changing_privileges(): void
    {
        $admin = $this->makeUser('admin@example.com', 'approved', true, false, 'google-admin');
        $beforeId = session()->getId();
        Socialite::fake('google', $this->googleUser('google-admin', 'admin@example.com'));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertOk();

        $this->assertAuthenticatedAs($admin);
        $this->assertNotSame($beforeId, session()->getId());
        $this->assertTrue($admin->fresh()->is_super_admin);
        $this->assertSame('approved', $admin->fresh()->approval_status);
        $this->assertFalse($admin->fresh()->has_all_superblogs_access);
    }

    public function test_linked_staff_signs_in_with_existing_status_and_assignments_unchanged(): void
    {
        $owner = $this->makeUser('owner@example.com', 'approved', true);
        $staff = $this->makeUser('staff@example.com', 'approved', false, false, 'google-staff');
        $blog = $this->makeSuperblog($owner);
        $staff->assignedSuperblogs()->attach($blog);
        Socialite::fake('google', $this->googleUser('google-staff', 'different-verified-email@example.com'));

        $this->get(route('auth.google.callback'))->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($staff);
        $this->assertSame('approved', $staff->fresh()->approval_status);
        $this->assertFalse($staff->fresh()->is_super_admin);
        $this->assertFalse($staff->fresh()->has_all_superblogs_access);
        $this->assertDatabaseHas('superblog_user', ['user_id' => $staff->id, 'superblog_id' => $blog->id]);
    }

    public function test_new_verified_identity_can_create_only_pending_ordinary_staff(): void
    {
        Socialite::fake('google', $this->googleUser('google-new', 'new@example.com'));

        $this->get(route('auth.google.callback'))
            ->assertOk()
            ->assertSee('Create a staff account?')
            ->assertSee('google-new');
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);

        $this->post(route('auth.google.create-account'))->assertRedirect(route('admin.dashboard'));

        $newUser = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($newUser);
        $this->assertSame('pending', $newUser->approval_status);
        $this->assertFalse($newUser->is_super_admin);
        $this->assertFalse($newUser->has_all_superblogs_access);
        $this->assertSame('google-new', $newUser->google_subject);
        $this->assertSame(0, $newUser->assignedSuperblogs()->count());
        $this->assertNotSame('new@example.com', $newUser->password);
    }

    public function test_existing_unlinked_email_is_not_auto_linked_or_modified(): void
    {
        $existing = $this->makeUser('collision@example.com', 'approved', true);
        $password = $existing->password;
        Socialite::fake('google', $this->googleUser('unmapped-subject', 'collision@example.com'));

        $this->get(route('auth.google.callback'))
            ->assertOk()
            ->assertSee('Administrator setup required')
            ->assertSee('unmapped-subject');

        $this->assertGuest();
        $this->assertSame($password, $existing->fresh()->password);
        $this->assertNull($existing->fresh()->google_subject);
        $this->assertSame(1, User::count());
    }

    public function test_unverified_google_email_cannot_create_an_account(): void
    {
        Socialite::fake('google', GoogleUser::fake([
            'id' => 'unverified-subject',
            'email' => 'unverified@example.com',
            'email_verified' => false,
        ]));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Use a Google account with a verified email address.');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'unverified@example.com']);
    }

    public function test_duplicate_google_subject_authenticates_only_the_already_linked_user(): void
    {
        $linked = $this->makeUser('linked@example.com', 'rejected', false, false, 'same-subject');
        $other = $this->makeUser('other@example.com', 'approved', true);
        Socialite::fake('google', $this->googleUser('same-subject', 'other@example.com'));

        $this->get(route('auth.google.callback'))->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($linked);
        $this->assertSame('rejected', $linked->fresh()->approval_status);
        $this->assertTrue($other->fresh()->is_super_admin);
        $this->assertNull($other->fresh()->google_subject);
        $this->get(route('admin.dashboard'))->assertForbidden()->assertSee('access has been revoked');
    }

    public function test_pending_linked_staff_stays_blocked_from_admin(): void
    {
        $pending = $this->makeUser('pending@example.com', 'pending', false, false, 'google-pending');
        Socialite::fake('google', $this->googleUser('google-pending', 'pending@example.com'));

        $this->get(route('auth.google.callback'))->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertForbidden()->assertSee('awaiting approval');
        $this->assertAuthenticatedAs($pending);
    }

    public function test_mapping_command_requires_explicit_valid_super_admin_and_never_overwrites(): void
    {
        $admin = $this->makeUser('admin@example.com', 'approved', true);
        $staff = $this->makeUser('staff@example.com', 'pending');

        $this->artisan('superblog:map-google-identity', ['user_id' => 999, 'google_subject' => 'missing'])
            ->assertFailed();
        $this->artisan('superblog:map-google-identity', ['user_id' => $admin->id, 'google_subject' => 'subject-admin'])
            ->expectsOutputToContain('--initial-super-admin')
            ->assertFailed();
        $this->artisan('superblog:map-google-identity', [
            'user_id' => $admin->id,
            'google_subject' => 'subject-admin',
            '--initial-super-admin' => true,
        ])->expectsConfirmation("Map Google identity to approved bootstrap super-admin #{$admin->id} ({$admin->email})? The subject itself will not be displayed.", 'yes')
            ->assertSuccessful();

        $this->artisan('superblog:map-google-identity', ['user_id' => $staff->id, 'google_subject' => 'subject-admin'])
            ->expectsOutputToContain('already mapped')
            ->assertFailed();
        $this->artisan('superblog:map-google-identity', ['user_id' => $admin->id, 'google_subject' => 'replacement', '--initial-super-admin' => true])
            ->expectsOutputToContain('never overwritten')
            ->assertFailed();

        $this->assertSame('subject-admin', $admin->fresh()->google_subject);
        $this->assertNull($staff->fresh()->google_subject);
        $this->assertSame('pending', $staff->fresh()->approval_status);
        $this->assertFalse($staff->fresh()->is_super_admin);
    }

    public function test_staff_mapping_preserves_assignments_and_approval(): void
    {
        $owner = $this->makeUser('owner@example.com', 'approved', true);
        $staff = $this->makeUser('staff@example.com', 'rejected');
        $blog = $this->makeSuperblog($owner);
        $staff->assignedSuperblogs()->attach($blog);

        $this->artisan('superblog:map-google-identity', ['user_id' => $staff->id, 'google_subject' => 'staff-subject'])
            ->expectsConfirmation("Map Google identity to staff account #{$staff->id} ({$staff->email})? The subject itself will not be displayed.", 'yes')
            ->assertSuccessful();

        $this->assertSame('staff-subject', $staff->fresh()->google_subject);
        $this->assertSame('rejected', $staff->fresh()->approval_status);
        $this->assertFalse($staff->fresh()->is_super_admin);
        $this->assertDatabaseHas('superblog_user', ['user_id' => $staff->id, 'superblog_id' => $blog->id]);
    }

    public function test_google_redirect_requests_account_selection_and_missing_configuration_is_friendly(): void
    {
        $provider = \Mockery::mock();
        $provider->shouldReceive('with')->once()->with(['prompt' => 'select_account'])->andReturnSelf();
        $provider->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.google.test'));
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $this->get(route('auth.google.redirect'))->assertRedirect('https://accounts.google.test');

        config(['services.google.client_secret' => null]);
        $this->get(route('auth.google.redirect'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Google sign-in is not configured. Contact your administrator.');
    }

    public function test_oauth_state_failure_and_cancellation_do_not_sign_in(): void
    {
        $this->get(route('auth.google.callback', ['code' => 'fake-code']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Google sign-in could not be verified. Please try again.');
        $this->assertGuest();

        $this->get(route('auth.google.callback', ['error' => 'access_denied']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Google sign-in was cancelled or could not be completed. Please try again.');
        $this->assertGuest();
    }

    public function test_password_login_remains_available_in_testing_and_logout_still_works(): void
    {
        $user = User::factory()->create(['password' => Hash::make('test-password')]);
        $user->approval_status = 'approved';
        $user->save();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'test-password'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_password_login_is_available_when_request_environment_is_local(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        $user = User::factory()->create(['password' => Hash::make('test-password')]);
        $user->approval_status = 'approved';
        $user->save();
        $this->app->detectEnvironment(fn () => 'local');

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'test-password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_password_login_route_registered_in_testing_is_blocked_at_request_time_in_production(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        $user = User::factory()->create(['password' => Hash::make('test-password')]);
        $user->approval_status = 'approved';
        $user->save();

        // The route is present because this app booted in testing, as with a cached route file built locally.
        $this->assertNotNull(app('router')->getRoutes()->getByName('login.store'));
        $this->app->detectEnvironment(fn () => 'production');

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'test-password'])
            ->assertNotFound();

        $this->assertGuest();
    }

    private function configureGoogle(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'https://superblog.test/auth/google/callback',
        ]);
    }

    private function googleUser(string $subject, string $email): GoogleUser
    {
        return GoogleUser::fake([
            'id' => $subject,
            'name' => 'Google User',
            'email' => $email,
            'email_verified' => true,
        ]);
    }

    private function makeUser(string $email, string $status, bool $superAdmin = false, bool $allAccess = false, ?string $subject = null): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->approval_status = $status;
        $user->is_super_admin = $superAdmin;
        $user->has_all_superblogs_access = $allAccess;
        $user->google_subject = $subject;
        $user->save();

        return $user;
    }

    private function makeSuperblog(User $owner): Superblog
    {
        $template = Template::create([
            'name' => 'Google Test Template',
            'slug' => 'google-test-template-'.$owner->id,
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);

        return $owner->superblogs()->create([
            'template_id' => $template->id,
            'name' => 'Google Test Blog',
            'slug' => 'google-test-blog-'.$owner->id,
        ]);
    }
}
