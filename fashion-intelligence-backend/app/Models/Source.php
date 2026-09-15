<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Source extends Model
{
    protected $fillable = [
        'name', 'slug', 'type', 'config', 'reliability_score', 'active',
    ];

    protected $casts = [
        'config' => 'array',
        'active' => 'boolean',
    ];

    // google_trends | reddit | web | apify | scraperapi | zyte | oxylabs | decodo | manual_import
    const TYPES = ['google_trends', 'reddit', 'web', 'apify', 'scraperapi', 'zyte', 'oxylabs', 'decodo', 'manual_import'];

    public function targets(): HasMany
    {
        return $this->hasMany(SourceTarget::class);
    }

    public function scrapeRuns(): HasMany
    {
        return $this->hasMany(ScrapeRun::class);
    }

    public function rawDocuments(): HasMany
    {
        return $this->hasMany(RawDocument::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function getActiveTargetsCountAttribute(): int
    {
        return $this->targets()->where('active', true)->count();
    }
}
