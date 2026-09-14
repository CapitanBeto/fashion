<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class SourceTarget extends Model
{
    protected $fillable = [
        'source_id', 'country_id', 'niche_id', 'target_type',
        'target_value', 'priority', 'frequency', 'depth', 'active', 'config',
    ];

    protected $casts = [
        'config' => 'array',
        'active' => 'boolean',
    ];

    // subreddit | keyword | url | actor | hashtag
    const TARGET_TYPES = ['subreddit', 'keyword', 'url', 'actor', 'hashtag'];

    // daily | weekly | hourly | manual
    const FREQUENCIES = ['hourly', 'daily', 'weekly', 'manual'];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function niche(): BelongsTo
    {
        return $this->belongsTo(Niche::class);
    }

    public function scrapeRuns(): HasMany
    {
        return $this->hasMany(ScrapeRun::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeDue(Builder $query): Builder
    {
        // Returns targets that should be run now based on frequency and last run
        return $query->where('active', true)
            ->where(function ($q) {
                $q->whereDoesntHave('scrapeRuns', function ($r) {
                    $r->where('status', 'completed')
                        ->where('created_at', '>', now()->subHour());
                })->orWhere('frequency', 'manual');
            });
    }
}
