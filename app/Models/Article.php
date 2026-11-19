<?php

namespace App\Models;

use App\Enums\ArticleContentFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['superblog_id', 'rss_item_id', 'title', 'slug', 'content', 'content_format', 'source_type', 'status', 'is_locked', 'is_selected_for_publication', 'published_at'])]
class Article extends Model
{
    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
            'is_selected_for_publication' => 'boolean',
            'published_at' => 'datetime',
            'content_modified_at' => 'datetime',
            'content_format' => ArticleContentFormat::class,
        ];
    }

    public function superblog(): BelongsTo
    {
        return $this->belongsTo(Superblog::class);
    }

    public function rssItem(): BelongsTo
    {
        return $this->belongsTo(RssItem::class);
    }

    public function articleLinks(): HasMany
    {
        return $this->hasMany(ArticleLink::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }
}
