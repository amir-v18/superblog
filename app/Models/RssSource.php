<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['superblog_id', 'name', 'url', 'is_active', 'last_fetched_at'])]
class RssSource extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_fetched_at' => 'datetime',
        ];
    }

    public function superblog(): BelongsTo
    {
        return $this->belongsTo(Superblog::class);
    }

    public function rssItems(): HasMany
    {
        return $this->hasMany(RssItem::class);
    }
}
