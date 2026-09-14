<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class RawPost extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'raw_document_id', 'platform', 'platform_id', 'community', 'author',
        'title', 'body', 'score', 'upvote_ratio', 'comment_count', 'award_count',
        'is_stickied', 'is_nsfw', 'flair', 'link_url', 'image_urls',
        'published_at', 'scraped_at', 'metadata',
    ];

    protected $casts = [
        'image_urls'   => 'array',
        'metadata'     => 'array',
        'is_stickied'  => 'boolean',
        'is_nsfw'      => 'boolean',
        'published_at' => 'datetime',
        'scraped_at'   => 'datetime',
        'created_at'   => 'datetime',
    ];

    const PLATFORMS = ['reddit', 'twitter', 'instagram', 'tiktok', 'youtube'];

    public function rawDocument(): BelongsTo
    {
        return $this->belongsTo(RawDocument::class);
    }

    public function scopeByPlatform(Builder $query, string $platform): Builder
    {
        return $query->where('platform', $platform);
    }

    public function scopeHighScore(Builder $query, int $min = 100): Builder
    {
        return $query->where('score', '>=', $min);
    }

    public function scopeByCommunity(Builder $query, string $community): Builder
    {
        return $query->where('community', $community);
    }

    public function getEngagementScoreAttribute(): float
    {
        // Simple proxy combining score and comments
        return ($this->score ?? 0) + (($this->comment_count ?? 0) * 2);
    }
}
