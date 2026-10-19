<?php

namespace App\Policies;

use App\Models\Template;
use App\Models\User;

class TemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin === true && $user->approval_status === 'approved';
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Template $template): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Template $template): bool
    {
        return $this->viewAny($user);
    }
}
