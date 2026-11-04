<?php

namespace Tests\Concerns;

use App\Models\Superblog;
use App\Models\Template;
use App\Models\User;

trait CreatesSuperblogs
{
    private function makeSuperblog(User $owner): Superblog
    {
        $template = Template::create([
            'name' => 'News',
            'slug' => 'news',
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);

        return $owner->superblogs()->create([
            'template_id' => $template->id,
            'name' => 'Dagens Budbärare',
            'slug' => 'dagens-budbarare',
            // The builder test checks that saving the Grund form keeps this description.
            'description' => 'Behålls',
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
