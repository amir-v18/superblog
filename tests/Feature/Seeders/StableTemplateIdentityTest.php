<?php

namespace Tests\Feature\Seeders;

use App\Models\Template;
use App\Models\User;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class StableTemplateIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_id_migration_maps_renamed_templates_without_changing_superblog_relationships(): void
    {
        $this->insertLegacyTemplate(3, 'A name an admin chose', 'a-custom-slug', true);
        $this->insertLegacyTemplate(4, 'Another custom name', 'another-custom-slug', false);
        $user = User::factory()->create();
        $modernBlog = $user->superblogs()->create([
            'template_id' => 3,
            'name' => 'Modern Superblog',
            'slug' => 'modern-superblog',
        ]);
        $classicBlog = $user->superblogs()->create([
            'template_id' => 4,
            'name' => 'Classic Superblog',
            'slug' => 'classic-superblog',
        ]);

        $this->stableKeyMigration()->up();

        $this->assertDatabaseHas('templates', [
            'id' => 3,
            'name' => 'A name an admin chose',
            'slug' => 'a-custom-slug',
            'seed_key' => 'template-modern',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('templates', [
            'id' => 4,
            'name' => 'Another custom name',
            'slug' => 'another-custom-slug',
            'seed_key' => 'template-classic',
            'is_active' => false,
        ]);
        $this->assertSame(3, $modernBlog->fresh()->template_id);
        $this->assertSame(4, $classicBlog->fresh()->template_id);
    }

    public function test_mapping_migration_fails_safely_when_an_existing_row_owns_the_requested_key(): void
    {
        $this->insertLegacyTemplate(3, 'Modern', 'modern', true, 'template-classic');
        $this->insertLegacyTemplate(4, 'Classic', 'classic', true);

        try {
            $this->stableKeyMigration()->up();
            $this->fail('Expected the migration to reject a key assigned to the wrong template ID.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('template ID 3 already has a different key', $exception->getMessage());
        }

        $this->assertNull(Template::findOrFail(4)->seed_key);
        $this->assertSame('template-classic', Template::findOrFail(3)->seed_key);
    }

    public function test_mapping_migration_rolls_back_if_an_unmapped_template_exists(): void
    {
        $this->insertLegacyTemplate(3, 'Modern', 'modern', true);
        $this->insertLegacyTemplate(4, 'Classic', 'classic', true);
        $this->insertLegacyTemplate(5, 'Unknown legacy template', 'unknown', false);

        try {
            $this->stableKeyMigration()->up();
            $this->fail('Expected the migration to reject the unkeyed template ID 5.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Explicitly map these template IDs first: 5', $exception->getMessage());
        }

        $this->assertNull(Template::findOrFail(3)->seed_key);
        $this->assertNull(Template::findOrFail(4)->seed_key);
        $this->assertNull(Template::findOrFail(5)->seed_key);
    }

    public function test_seeder_refuses_to_duplicate_or_guess_an_unkeyed_existing_template(): void
    {
        $template = Template::create([
            'name' => 'Renamed Modern',
            'slug' => 'a-different-slug',
            'layout' => 'layouts.public',
            'is_active' => false,
        ]);

        try {
            app(TemplateSeeder::class)->run();
            $this->fail('Expected the seeder to stop when a template has no stable key.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Unmapped template IDs: '.$template->id, $exception->getMessage());
        }

        $this->assertDatabaseCount('templates', 1);
        $this->assertNull($template->fresh()->seed_key);
    }

    public function test_fresh_template_seeding_uses_stable_keys_and_is_idempotent(): void
    {
        app(TemplateSeeder::class)->run();
        app(TemplateSeeder::class)->run();

        $this->assertDatabaseCount('templates', count(config('templates.list')));
        $this->assertDatabaseHas('templates', ['seed_key' => 'template-modern']);
        $this->assertDatabaseHas('templates', ['seed_key' => 'template-classic']);
    }

    private function insertLegacyTemplate(int $id, string $name, string $slug, bool $active, ?string $seedKey = null): void
    {
        DB::table('templates')->insert([
            'id' => $id,
            'name' => $name,
            'slug' => $slug,
            'seed_key' => $seedKey,
            'layout' => 'layouts.public',
            'is_active' => $active,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function stableKeyMigration(): object
    {
        return require database_path('migrations/2026_11_16_000001_assign_stable_template_keys.php');
    }
}
