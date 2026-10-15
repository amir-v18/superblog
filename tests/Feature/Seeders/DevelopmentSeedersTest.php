<?php

namespace Tests\Feature\Seeders;

use App\Models\Template;
use App\Models\User;
use Database\Seeders\StaffTestSeeder;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class DevelopmentSeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_seeder_is_idempotent_and_preserves_existing_template_edits(): void
    {
        $seeder = app(TemplateSeeder::class);
        $seeder->run();

        $template = Template::where('seed_key', 'initial-editorial')->firstOrFail();
        $template->name = 'Locally Customized Template';
        $template->slug = 'my-custom-editorial';
        $template->save();

        $seeder->run();

        $this->assertDatabaseCount('templates', 1);
        $this->assertDatabaseHas('templates', [
            'id' => $template->id,
            'name' => 'Locally Customized Template',
            'slug' => 'my-custom-editorial',
            'seed_key' => 'initial-editorial',
        ]);
        $this->assertSame('layouts.public', $template->fresh()->layout);
    }

    public function test_renamed_legacy_template_can_be_adopted_and_seed_reruns_preserve_it(): void
    {
        $template = Template::create([
            'name' => 'My renamed layout',
            'slug' => 'my-renamed-layout',
            'layout' => 'layouts.public',
            'is_active' => false,
        ]);

        $this->artisan('superblog:adopt-initial-template', ['template_id' => $template->id])
            ->expectsConfirmation("Assign initial-editorial to template ID {$template->id}?", 'yes')
            ->assertExitCode(0);

        app(TemplateSeeder::class)->run();

        $this->assertDatabaseCount('templates', 1);
        $this->assertDatabaseHas('templates', [
            'id' => $template->id,
            'name' => 'My renamed layout',
            'slug' => 'my-renamed-layout',
            'is_active' => false,
            'seed_key' => 'initial-editorial',
        ]);
    }

    public function test_template_adoption_rejects_missing_ids_and_key_conflicts(): void
    {
        $this->artisan('superblog:adopt-initial-template', ['template_id' => 'not-an-id'])
            ->expectsOutput('Provide a valid positive template database ID.')
            ->assertExitCode(1);

        $this->artisan('superblog:adopt-initial-template', ['template_id' => 999])
            ->expectsOutput('No template exists with ID 999.')
            ->assertExitCode(1);

        $keyOwner = Template::create([
            'name' => 'Already adopted',
            'slug' => 'already-adopted',
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);
        $keyOwner->forceFill(['seed_key' => 'initial-editorial'])->save();

        $other = Template::create([
            'name' => 'Other template',
            'slug' => 'other-template',
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);

        $this->artisan('superblog:adopt-initial-template', ['template_id' => $other->id])
            ->expectsOutput('The initial template key is already assigned to another template; no changes were made.')
            ->assertExitCode(1);

        $this->assertNull($other->fresh()->seed_key);
        $this->assertSame('initial-editorial', $keyOwner->fresh()->seed_key);
    }

    public function test_template_adoption_does_not_overwrite_a_different_key_on_the_selected_record(): void
    {
        $template = Template::create([
            'name' => 'Already keyed',
            'slug' => 'already-keyed',
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);
        $template->forceFill(['seed_key' => 'another-key'])->save();

        $this->artisan('superblog:adopt-initial-template', ['template_id' => $template->id])
            ->expectsOutput("Template ID {$template->id} already has a different seed key; no changes were made.")
            ->assertExitCode(1);

        $this->assertSame('another-key', $template->fresh()->seed_key);
    }

    public function test_template_adoption_is_blocked_outside_local_and_testing(): void
    {
        $template = Template::create([
            'name' => 'Legacy template',
            'slug' => 'legacy-template',
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);
        $this->app->detectEnvironment(fn () => 'production');

        try {
            $this->artisan('superblog:adopt-initial-template', ['template_id' => $template->id])
                ->expectsOutput('Template adoption is allowed only in local or testing environments.')
                ->assertExitCode(1);
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }

        $this->assertNull($template->fresh()->seed_key);
    }

    public function test_seed_key_migration_backfills_the_legacy_slug(): void
    {
        $template = Template::create([
            'name' => 'Editorial Default',
            'slug' => 'editorial-default',
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);

        Schema::table('templates', function ($table) {
            $table->dropUnique(['seed_key']);
            $table->dropColumn('seed_key');
        });

        $migration = require database_path('migrations/2026_10_15_000001_add_seed_key_to_templates_table.php');
        $migration->up();

        $this->assertSame('initial-editorial', $template->fresh()->seed_key);
    }

    public function test_staff_test_seeder_creates_a_pending_staff_user_and_does_not_overwrite_it(): void
    {
        $seeder = app(StaffTestSeeder::class);
        $seeder->run();

        $staff = User::where('email', 'staff@example.com')->firstOrFail();
        $this->assertSame('pending', $staff->approval_status);
        $this->assertFalse($staff->is_super_admin);
        $this->assertFalse($staff->has_all_superblogs_access);
        $this->assertTrue(Hash::check('Superblog-Staff-Local-2026!', $staff->password));

        $staff->name = 'Changed by Developer';
        $staff->password = Hash::make('my-own-test-password');
        $staff->approval_status = 'approved';
        $staff->is_super_admin = true;
        $staff->has_all_superblogs_access = true;
        $staff->save();

        $seeder->run();

        $staff->refresh();
        $this->assertDatabaseCount('users', 1);
        $this->assertSame('Changed by Developer', $staff->name);
        $this->assertTrue(Hash::check('my-own-test-password', $staff->password));
        $this->assertSame('approved', $staff->approval_status);
        $this->assertTrue($staff->is_super_admin);
        $this->assertTrue($staff->has_all_superblogs_access);
    }

    public function test_staff_test_seeder_fails_before_writing_in_non_development_environment(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        try {
            app(StaffTestSeeder::class)->run();
            $this->fail('The staff test seeder should be blocked outside local and testing.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('only be seeded in local or testing', $exception->getMessage());
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }

        $this->assertDatabaseCount('users', 0);
    }
}
