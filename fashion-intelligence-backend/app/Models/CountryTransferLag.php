<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CountryTransferLag extends Model
{
    protected $fillable = [
        'source_country_id', 'target_country_id', 'niche_id',
        'sample_size', 'median_lag_days', 'avg_lag_days',
        'min_lag_days', 'max_lag_days', 'confidence', 'calculated_at',
    ];

    protected $casts = [
        'avg_lag_days' => 'float',
        'calculated_at' => 'datetime',
    ];

    public function sourceCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'source_country_id');
    }

    public function targetCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'target_country_id');
    }

    public function niche(): BelongsTo
    {
        return $this->belongsTo(Niche::class);
    }

    public function getHasEnoughDataAttribute(): bool
    {
        return $this->sample_size >= 3;
    }
}
