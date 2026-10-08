<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['rss_source_id', 'external_id', 'guid', 'title', 'url', 'author', 'published_at', 'content', 'summary', 'content_hash'])]
class RssItem extends Model
{
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function rssSource(): BelongsTo
    {
        return $this->belongsTo(RssSource::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}
