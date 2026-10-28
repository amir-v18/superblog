<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'google_subject'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function superblogs(): HasMany
    {
        return $this->hasMany(Superblog::class);
    }

    public function assignedSuperblogs(): BelongsToMany
    {
        return $this->belongsToMany(Superblog::class);
    }

    public function canAccessAdmin(): bool
    {
        // Approval applies even to super admins, so rejection revokes all admin access.
        return $this->approval_status === 'approved';
    }

    public function canAccessSuperblog(Superblog $superblog): bool
    {
        if (! $this->canAccessAdmin()) {
            return false;
        }

        return $this->is_super_admin
            || $this->has_all_superblogs_access
            || $this->assignedSuperblogs()->whereKey($superblog->getKey())->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'has_all_superblogs_access' => 'boolean',
        ];
    }
}
