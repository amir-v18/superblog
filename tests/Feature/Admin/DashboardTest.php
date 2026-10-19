<?php

namespace Tests\Feature\Admin;

use App\Models\Superblog;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_superblogs_are_grouped_by_status_with_counts(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $live = $this->makeSuperblog($admin, 'Live Blog', 'published');
        $this->makeSuperblog($admin, 'Draft Blog', 'draft');

        $live->rssSources()->create(['name' => 'Feed', 'url' => 'https://example.com/feed']);
        $this->makeArticle($live, 'published-one', 'published');
        $this->makeArticle($live, 'draft-one', 'draft');

        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        $this->assertSame(['Live Blog'], $response->viewData('published')->pluck('name')->all());
        $this->assertSame(['Draft Blog'], $response->viewData('drafts')->pluck('name')->all());

        $card = $response->viewData('published')->first();
        $this->assertSame(1, $card->rss_sources_count);
        $this->assertSame(1, $card->published_articles_count);
    }

    public function test_staff_only_see_assigned_superblogs(): void
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $staff = $this->makeUser(approved: true);
        $assigned = $this->makeSuperblog($admin, 'Assigned Blog', 'draft');
        $this->makeSuperblog($admin, 'Other Blog', 'draft');
        $assigned->staff()->attach($staff);

        $this->actingAs($staff)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Assigned Blog')
            ->assertDontSee('Other Blog');
    }

    public function test_unapproved_users_cannot_open_the_dashboard(): void
    {
        $this->actingAs($this->makeUser())->get(route('admin.dashboard'))->assertForbidden();
    }

    private function makeSuperblog(User $owner, string $name, string $status): Superblog
    {
        $template = Template::firstOrCreate(['slug' => 'default'], [
            'name' => 'Default',
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);

        return $owner->superblogs()->create([
            'template_id' => $template->id,
            'name' => $name,
            'slug' => str($name)->slug(),
            'status' => $status,
        ]);
    }

    private function makeArticle(Superblog $superblog, string $slug, string $status): void
    {
        $superblog->articles()->create([
            'title' => $slug,
            'slug' => $slug,
            'content' => 'Content',
            'source_type' => 'manual',
            'status' => $status,
        ]);
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
