<?php

namespace Tests\Feature\Admin;

use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->post(route('admin.templates.store'), [
            'name' => 'Unauthorized',
            'layout' => 'layouts.public',
            'is_active' => '1',
        ])->assertForbidden();

        $this->delete(route('admin.templates.destroy', $template))->assertForbidden();

        $this->actingAs($pendingAdmin)
            ->get(route('admin.templates.index'))
            ->assertForbidden();

        $this->assertDatabaseCount('templates', 1);
    }

    public function test_super_admin_can_create_and_edit_a_valid_template(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);

        $this->actingAs($admin)->post(route('admin.templates.store'), [
            'name' => 'News Layout',
            'slug' => '',
            'description' => 'A clean layout for news.',
            'layout' => 'layouts.public',
            'is_active' => '1',
        ])->assertRedirect(route('admin.templates.index'));

        $template = Template::where('name', 'News Layout')->firstOrFail();
        $this->assertSame('news-layout', $template->slug);
        $this->assertTrue($template->is_active);

        $this->put(route('admin.templates.update', $template), [
            'name' => 'Updated News Layout',
            'slug' => 'updated-news-layout',
            'description' => '',
            'layout' => 'layouts.public',
            'is_active' => '0',
        ])->assertRedirect(route('admin.templates.index'));

        $this->assertDatabaseHas('templates', [
            'id' => $template->id,
            'name' => 'Updated News Layout',
            'is_active' => false,
        ]);
    }

    public function test_invalid_layout_and_duplicate_slug_are_rejected(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        Template::create([
            'name' => 'Existing',
            'slug' => 'existing',
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->from(route('admin.templates.create'))
            ->post(route('admin.templates.store'), [
                'name' => 'Duplicate',
                'slug' => 'existing',
                'layout' => 'layouts.not-real',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.templates.create'))
            ->assertSessionHasErrors(['slug', 'layout']);

        $this->assertDatabaseCount('templates', 1);
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
            ->assertSee('Inactive (inactive)');

        $this->put(route('admin.superblogs.update', $existing), [
            'name' => 'Existing Blog Updated',
            'slug' => 'existing-blog',
            'template_id' => $inactive->id,
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseHas('superblogs', [
            'id' => $existing->id,
            'template_id' => $inactive->id,
        ]);
        $this->assertDatabaseHas('templates', ['id' => $active->id]);
    }

    public function test_template_in_use_cannot_be_deleted_but_unused_template_can(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $template = Template::create([
            'name' => 'In Use',
            'slug' => 'in-use',
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);
        $admin->superblogs()->create([
            'template_id' => $template->id,
            'name' => 'Test Blog',
            'slug' => 'test-blog',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.templates.destroy', $template))
            ->assertRedirect(route('admin.templates.index'))
            ->assertSessionHasErrors('template');
        $this->assertDatabaseHas('templates', ['id' => $template->id]);

        $unused = Template::create([
            'name' => 'Unused',
            'slug' => 'unused',
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);
        $this->delete(route('admin.templates.destroy', $unused))
            ->assertRedirect(route('admin.templates.index'));
        $this->assertDatabaseMissing('templates', ['id' => $unused->id]);
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
