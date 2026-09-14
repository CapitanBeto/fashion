<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;

class Creator extends Model
{
    protected $fillable = [
        'name', 'slug', 'handle', 'aliases', 'creator_type',
        'country_id', 'niche_id', 'platform_handles', 'follower_counts',
        'engagement_rates', 'commercial_influence_score', 'trend_influence_score',
        'awareness_score', 'metadata', 'is_demo',
    ];

    protected $casts = [
        'aliases'          => 'array',
        'platform_handles' => 'array',
        'follower_counts'  => 'array',
        'engagement_rates' => 'array',
        'metadata'         => 'array',
        'is_demo'          => 'boolean',
    ];

    // creator | celebrity | designer | athlete | musician | model | brand_founder
    const TYPES = ['creator', 'celebrity', 'designer', 'athlete', 'musician', 'model', 'brand_founder'];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function niche(): BelongsTo
    {
        return $this->belongsTo(Niche::class);
    }

    public function trends(): BelongsToMany
    {
        return $this->belongsToMany(Trend::class, 'creator_trends')
            ->withPivot(['first_seen_at', 'frequency', 'confidence']);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('creator_type', $type);
    }

    public function scopeHighInfluence(Builder $query, int $min = 60): Builder
    {
        return $query->where('commercial_influence_score', '>=', $min);
    }

    public function scopeNotDemo(Builder $query): Builder
    {
        return $query->where('is_demo', false);
    }

    public function getTotalFollowersAttribute(): int
    {
        return array_sum($this->follower_counts ?? []);
    }

    public function getFollowersOnPlatformAttribute(): ?int
    {
        // Returns Instagram followers by default; override by checking platform_handles
        return $this->follower_counts['instagram'] ?? $this->follower_counts['tiktok'] ?? null;
    }
}
