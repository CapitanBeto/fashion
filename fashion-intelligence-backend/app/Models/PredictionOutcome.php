<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictionOutcome extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'prediction_id', 'actual_growth', 'actual_lifecycle_stage',
        'accuracy_score', 'forecast_error',
    ];

    protected $casts = [
        'actual_growth'   => 'float',
        'accuracy_score'  => 'float',
        'forecast_error'  => 'float',
        'evaluated_at'    => 'datetime',
    ];

    public function prediction(): BelongsTo
    {
        return $this->belongsTo(TrendPrediction::class, 'prediction_id');
    }

    public function getIsAccurateAttribute(): bool
    {
        return ($this->accuracy_score ?? 0) >= 70;
    }

    /**
     * Real calibration number: how accurate this system's forecasts have
     * actually been, averaged across every evaluated prediction (live and
     * historical-backtest alike). Returns null when there isn't yet a
     * single real outcome to compute from — never a fabricated default.
     */
    public static function historicalAccuracy(): ?array
    {
        $count = static::count();
        if ($count === 0) {
            return null;
        }

        return [
            'mean'  => round((float) static::avg('accuracy_score'), 1),
            'count' => $count,
        ];
    }
}
