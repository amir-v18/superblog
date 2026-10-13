<?php

namespace App\Policies;

use App\Models\Superblog;
use App\Models\User;

class SuperblogPolicy
{
    public function viewAny(User $user): bool
    {
        // Collection queries must also use accessibleTo() to filter individual records.
        return $user->canAccessAdmin();
    }

    public function view(User $user, Superblog $superblog): bool
    {
        return $user->canAccessSuperblog($superblog);
    }

    public function create(User $user): bool
    {
        return $user->canAccessAdmin() && $user->is_super_admin;
    }

    public function update(User $user, Superblog $superblog): bool
    {
        return $user->canAccessSuperblog($superblog);
    }

    public function delete(User $user, Superblog $superblog): bool
    {
        return $user->canAccessSuperblog($superblog);
    }
}
