<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrendPrediction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'trend_id', 'country_id', 'model_name', 'model_version', 'prompt_version',
        'prediction_horizon', 'prediction_date', 'target_date',
        'growth_probability', 'decline_probability', 'saturation_probability',
        'death_probability', 'momentum_forecast', 'confidence',
        'reasoning', 'input_snapshot',
    ];

    protected $casts = [
        'prediction_date'  => 'date',
        'target_date'      => 'date',
        'momentum_forecast' => 'array',
        'input_snapshot'   => 'array',
        'created_at'       => 'datetime',
    ];

    public function trend(): BelongsTo
    {
        return $this->belongsTo(Trend::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function outcome(): HasMany
    {
        return $this->hasMany(PredictionOutcome::class, 'prediction_id');
    }

    public function getDominantOutcomeAttribute(): string
    {
        $probs = [
            'growth'     => $this->growth_probability ?? 0,
            'decline'    => $this->decline_probability ?? 0,
            'saturation' => $this->saturation_probability ?? 0,
            'death'      => $this->death_probability ?? 0,
        ];
        return array_keys($probs, max($probs))[0];
    }
}
