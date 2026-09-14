<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Community extends Model
{
    protected $fillable = [
        'name', 'slug', 'platform', 'platform_id',
        'niche_id', 'country_id', 'member_count',
        'active_member_count', 'post_frequency', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    // reddit | discord | instagram | tiktok | youtube | forum
    const PLATFORMS = ['reddit', 'discord', 'instagram', 'tiktok', 'youtube', 'forum'];

    public function niche(): BelongsTo
    {
        return $this->belongsTo(Niche::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function scopeByPlatform(Builder $query, string $platform): Builder
    {
        return $query->where('platform', $platform);
    }

    public function getSubredditNameAttribute(): ?string
    {
        if ($this->platform !== 'reddit') {
            return null;
        }
        return ltrim($this->platform_id ?? $this->name, 'r/');
    }
}
