<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class TrendSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'trend_id', 'country_id', 'snapshot_date',
        'mention_count', 'search_interest', 'reddit_score',
        'brand_count', 'creator_count', 'product_count', 'post_count',
        'momentum_score', 'growth_velocity', 'growth_acceleration',
    ];

    protected $casts = [
        'snapshot_date'      => 'date',
        'growth_velocity'    => 'float',
        'growth_acceleration' => 'float',
        'created_at'         => 'datetime',
    ];

    public function trend(): BelongsTo
    {
        return $this->belongsTo(Trend::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function scopeForPeriod(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('snapshot_date', [$from, $to]);
    }

    public function getIsGrowingAttribute(): bool
    {
        return ($this->growth_velocity ?? 0) > 0;
    }

    public function getIsAcceleratingAttribute(): bool
    {
        return ($this->growth_acceleration ?? 0) > 0;
    }
}
