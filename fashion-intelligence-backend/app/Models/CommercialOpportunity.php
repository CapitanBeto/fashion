<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class CommercialOpportunity extends Model
{
    protected $fillable = [
        'trend_id', 'country_id', 'opportunity_score', 'demand_score',
        'competition_score', 'momentum_score', 'timing_score', 'confidence',
        'suggested_product_name', 'suggested_silhouette', 'suggested_colors',
        'suggested_graphics', 'suggested_price_min', 'suggested_price_max',
        'suggested_target_audience', 'reasoning', 'risks', 'evidence',
    ];

    protected $casts = [
        'suggested_colors'   => 'array',
        'suggested_graphics' => 'array',
        'risks'              => 'array',
        'evidence'           => 'array',
        'suggested_price_min' => 'decimal:2',
        'suggested_price_max' => 'decimal:2',
    ];

    public function trend(): BelongsTo
    {
        return $this->belongsTo(Trend::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function scopeHighScore(Builder $query, int $min = 60): Builder
    {
        return $query->where('opportunity_score', '>=', $min)
            ->orderByDesc('opportunity_score');
    }

    public function scopeHighConfidence(Builder $query, int $min = 50): Builder
    {
        return $query->where('confidence', '>=', $min);
    }

    public function getPriceMidpointAttribute(): ?float
    {
        if ($this->suggested_price_min && $this->suggested_price_max) {
            return ($this->suggested_price_min + $this->suggested_price_max) / 2;
        }
        return null;
    }
}
