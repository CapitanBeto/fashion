<?php

namespace App\Console\Commands;

use App\Models\PredictionOutcome;
use App\Models\TrendPrediction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EvaluatePredictionsCommand extends Command
{
    protected $signature = 'predictions:evaluate';

    protected $description = 'Backtest matured Chile trend predictions (target_date has passed) against what actually happened';

    public function handle(): int
    {
        $due = TrendPrediction::query()
            ->whereDate('target_date', '<=', now())
            ->whereDoesntHave('outcome')
            ->get();

        if ($due->isEmpty()) {
            $this->info('No matured predictions to evaluate yet.');
            return self::SUCCESS;
        }

        $evaluated = 0;

        foreach ($due as $prediction) {
            $outcome = $this->evaluate($prediction);
            if ($outcome) {
                $evaluated++;
                $this->line(sprintf(
                    'Trend %d: predicted %d%%, actual growth %.1f%%, accuracy %.0f%%',
                    $prediction->trend_id,
                    $prediction->growth_probability,
                    $outcome->actual_growth,
                    $outcome->accuracy_score,
                ));
            }
        }

        $this->info("Evaluated {$evaluated} of {$due->count()} matured predictions.");
        return self::SUCCESS;
    }

    /**
     * Compare a matured prediction to reality using trend_snapshots (the only
     * real time-series table we have for a country) and the trend's current
     * trend_countries.stage as a proxy for "stage at target_date" — an
     * intentional simplification since we don't store per-country stage
     * history over time yet. Documented here rather than hidden.
     */
    private function evaluate(TrendPrediction $prediction): ?PredictionOutcome
    {
        $before = DB::table('trend_snapshots')
            ->where('trend_id', $prediction->trend_id)
            ->where('country_id', $prediction->country_id)
            ->whereDate('snapshot_date', '<=', $prediction->prediction_date)
            ->orderByDesc('snapshot_date')
            ->first();

        $after = DB::table('trend_snapshots')
            ->where('trend_id', $prediction->trend_id)
            ->where('country_id', $prediction->country_id)
            ->whereDate('snapshot_date', '>=', $prediction->target_date)
            ->orderBy('snapshot_date')
            ->first();

        if (!$before || !$after) {
            // Not enough time-series history yet to score this prediction —
            // skip rather than fabricate an outcome. It stays pending and
            // will be picked up once more snapshots exist.
            return null;
        }

        $baseline = max($before->search_interest, 1);
        $actualGrowth = (($after->search_interest - $before->search_interest) / $baseline) * 100;

        $stage = DB::table('trend_countries')
            ->where('trend_id', $prediction->trend_id)
            ->where('country_id', $prediction->country_id)
            ->value('stage');

        // Normalize actual growth onto the same 0-100 scale as the predicted
        // probability so the two are comparable, then score how close the
        // prediction was. This is a baseline calibration metric, not a
        // statistically rigorous one — good enough to start accumulating
        // real prediction/outcome pairs for later, more principled scoring.
        $actualAsProbability = max(0, min(100, 50 + $actualGrowth));
        $forecastError = $prediction->growth_probability - $actualAsProbability;
        $accuracy = max(0, 100 - abs($forecastError));

        return PredictionOutcome::create([
            'prediction_id'          => $prediction->id,
            'actual_growth'          => round($actualGrowth, 4),
            'actual_lifecycle_stage' => $stage,
            'accuracy_score'         => round($accuracy, 2),
            'forecast_error'         => round($forecastError, 4),
        ]);
    }
}
