<?php

namespace Tests\Feature\Admin;

use App\Models\Template;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class TemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_an_approved_super_admin_can_open_template_management(): void
    {
        $staff = $this->makeUser(approved: true);
        $pendingAdmin = $this->makeUser(superAdmin: true);
        $template = Template::create([
            'name' => 'Protected',
            'slug' => 'protected',
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('admin.templates.index'))
            ->assertForbidden();

        $this->put(route('admin.templates.update', $template), [
            'name' => 'Unauthorized',
            'layout' => 'layouts.public',
            'is_active' => '1',
        ])->assertForbidden();

        $this->actingAs($pendingAdmin)
            ->get(route('admin.templates.index'))
            ->assertForbidden();

        $this->assertDatabaseCount('templates', 1);
        $this->assertDatabaseHas('templates', ['id' => $template->id, 'name' => 'Protected']);
    }

    public function test_templates_cannot_be_created_from_the_admin(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);

        // Templates come from config/templates.php, since each one needs its own views in the code.
        $this->assertFalse(Route::has('admin.templates.create'));
        $this->assertFalse(Route::has('admin.templates.store'));
        $this->assertFalse(Route::has('admin.templates.delete'));
        $this->assertFalse(Route::has('admin.templates.destroy'));

        $this->actingAs($admin)->get(route('admin.templates.index'))
            ->assertOk()
            ->assertDontSee('Create template')
            ->assertDontSee('Delete');

        $this->post('/admin/templates', [
            'name' => 'Sneaky',
            'layout' => 'layouts.public',
            'is_active' => '1',
        ])->assertMethodNotAllowed();
        $this->assertDatabaseCount('templates', 0);
    }

    public function test_super_admin_can_edit_a_valid_template(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $template = Template::create([
            'name' => 'News Layout',
            'slug' => 'old-slug',
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);
        $template->forceFill(['seed_key' => 'template-modern'])->save();

        $this->actingAs($admin)->get(route('admin.templates.edit', $template))
            ->assertOk()
            ->assertSee('Edit template')
            ->assertSee('action="'.route('admin.templates.update', $template).'"', false)
            ->assertSee('name="name"', false)
            ->assertSee('name="is_active"', false)
            ->assertDontSee('name="slug"', false)
            ->assertDontSee('name="description"', false)
            ->assertDontSee('name="layout"', false)
            ->assertDontSee('name="settings"', false)
            ->assertDontSee('name="seed_key"', false);

        $this->put(route('admin.templates.update', $template), [
            'name' => 'Renamed catalog entry',
            'is_active' => '1',
            // Extra fields from a manually crafted request must be ignored.
            'slug' => 'attacker-changed-slug',
            'description' => 'attacker changed description',
            'layout' => 'attacker.changed.layout',
            'settings' => ['theme' => 'attacker-theme'],
            'seed_key' => 'template-classic',
        ])->assertRedirect(route('admin.templates.index'));

        $template->refresh();
        $this->assertSame('Renamed catalog entry', $template->name);
        $this->assertSame('old-slug', $template->slug);
        $this->assertNull($template->description);
        $this->assertSame('layouts.public', $template->layout);
        $this->assertNull($template->settings);
        $this->assertTrue($template->is_active);
        $this->assertSame('template-modern', $template->seed_key);

        $this->put(route('admin.templates.update', $template), [
            'name' => 'Updated catalog entry',
            'is_active' => '0',
        ])->assertRedirect(route('admin.templates.index'));

        $this->assertDatabaseHas('templates', [
            'id' => $template->id,
            'name' => 'Updated catalog entry',
            'is_active' => false,
            'slug' => 'old-slug',
            'layout' => 'layouts.public',
            'seed_key' => 'template-modern',
        ]);
    }

    public function test_duplicate_template_names_are_rejected(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        Template::create([
            'name' => 'Existing name',
            'slug' => 'existing',
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);
        $other = Template::create([
            'name' => 'Other name',
            'slug' => 'other',
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->from(route('admin.templates.edit', $other))
            ->put(route('admin.templates.update', $other), [
                'name' => 'Existing name',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.templates.edit', $other))
            ->assertSessionHasErrors(['name']);

        $this->assertDatabaseCount('templates', 2);
        $this->assertDatabaseHas('templates', ['id' => $other->id, 'name' => 'Other name', 'slug' => 'other']);
    }

    public function test_the_database_also_enforces_unique_template_names(): void
    {
        $this->assertTrue(Schema::hasIndex('templates', 'templates_name_unique', 'unique'));

        Template::create([
            'name' => 'Unique name',
            'slug' => 'unique-one',
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);
        Template::create([
            'name' => 'Unique name',
            'slug' => 'unique-two',
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);
    }

    public function test_unique_name_migration_stops_without_changing_existing_duplicate_names(): void
    {
        Schema::table('templates', fn (Blueprint $table) => $table->dropUnique('templates_name_unique'));
        $first = Template::create(['name' => 'Duplicate', 'slug' => 'duplicate-one', 'layout' => 'layouts.public', 'is_active' => true]);
        $second = Template::create(['name' => 'Duplicate', 'slug' => 'duplicate-two', 'layout' => 'layouts.public', 'is_active' => true]);
        $migration = require database_path('migrations/2026_11_16_000002_make_template_names_unique.php');

        try {
            $migration->up();
            $this->fail('Expected the migration to stop when existing template names are duplicated.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('duplicates exist: Duplicate', $exception->getMessage());
        }

        $this->assertDatabaseCount('templates', 2);
        $this->assertDatabaseHas('templates', ['id' => $first->id, 'name' => 'Duplicate']);
        $this->assertDatabaseHas('templates', ['id' => $second->id, 'name' => 'Duplicate']);
        $this->assertFalse(Schema::hasIndex('templates', 'templates_name_unique', 'unique'));
    }

    public function test_inactive_template_cannot_be_selected_for_a_new_superblog_but_can_remain_on_an_existing_one(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $active = Template::create([
            'name' => 'Active',
            'slug' => 'active',
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);
        $inactive = Template::create([
            'name' => 'Inactive',
            'slug' => 'inactive',
            'layout' => 'layouts.public',
            'is_active' => false,
        ]);

        $this->actingAs($admin)->get(route('admin.superblogs.create'))
            ->assertOk()
            ->assertSee('Active')
            ->assertDontSee('Inactive');

        $this->post(route('admin.superblogs.store'), [
            'name' => 'New Blog',
            'template_id' => $inactive->id,
        ])->assertSessionHasErrors('template_id');
        $this->assertDatabaseMissing('superblogs', ['slug' => 'new-blog']);

        $existing = $admin->superblogs()->create([
            'template_id' => $inactive->id,
            'name' => 'Existing Blog',
            'slug' => 'existing-blog',
        ]);

        $this->get(route('admin.superblogs.edit', $existing))
            ->assertOk()
            ->assertSee('Vald mall')
            ->assertSee('Inactive')
            ->assertDontSee('value="'.$inactive->id.'"', false);

        $this->put(route('admin.superblogs.update', $existing), [
            'name' => 'Existing Blog Updated',
            'slug' => 'existing-blog',
        ])->assertRedirect(route('admin.superblogs.edit', $existing));

        $this->assertDatabaseHas('superblogs', [
            'id' => $existing->id,
            'template_id' => $inactive->id,
        ]);
        $this->assertDatabaseHas('templates', ['id' => $active->id]);
    }

    public function test_templates_cannot_be_deleted_even_through_a_direct_request(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $template = Template::create([
            'name' => 'In Use',
            'slug' => 'in-use',
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);
        $blog = $admin->superblogs()->create([
            'template_id' => $template->id,
            'name' => 'Test Blog',
            'slug' => 'test-blog',
        ]);

        $this->actingAs($admin)->delete('/admin/templates/'.$template->id)->assertMethodNotAllowed();
        $this->assertDatabaseHas('templates', ['id' => $template->id]);
        $this->assertDatabaseHas('superblogs', ['id' => $blog->id, 'template_id' => $template->id]);
    }

    private function makeUser(bool $approved = false, bool $superAdmin = false): User
    {
        $user = User::factory()->create();
        $user->approval_status = $approved ? 'approved' : 'pending';
        $user->is_super_admin = $superAdmin;
        $user->save();

        return $user;
    }
}
