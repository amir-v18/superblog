<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'layout', 'settings', 'is_active'])]
class Template extends Model
{
    public static function availableLayouts(): array
    {
        return ['layouts.public' => 'Default public layout'];
    }

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function superblogs(): HasMany
    {
        return $this->hasMany(Superblog::class);
    }
}
