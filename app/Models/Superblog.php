<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'template_id', 'name', 'slug', 'domain', 'description', 'settings', 'status'])]
class Superblog extends Model
{
    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        if (! $user->canAccessAdmin()) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->is_super_admin || $user->has_all_superblogs_access) {
            return $query;
        }

        return $query->whereHas('staff', fn (Builder $staff) => $staff->whereKey($user->getKey()));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function rssSources(): HasMany
    {
        return $this->hasMany(RssSource::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}
