<?php

namespace App\Livewire;

use App\Models\Country;
use App\Models\PredictionOutcome;
use App\Models\Trend;
use App\Models\TrendPrediction;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ChileFashionRadar extends Component
{
    public int $horizonDays = 60;

    public function render()
    {
        $chile = Country::where('iso2', 'CL')->first();
        $chileId = $chile?->id;

        // ── NOW: already strong in Chile ──────────────────────────────────
        $now = collect();
        if ($chileId) {
            // Demo trends (is_demo=true) are intentionally NOT excluded here —
            // they're shown with a visible badge (see the view) so the
            // dashboard can be previewed while real signal is thin, without
            // ever being indistinguishable from real results.
            $now = Trend::query()
                ->join('trend_countries', function ($join) use ($chileId) {
                    $join->on('trend_countries.trend_id', '=', 'trends.id')
                        ->where('trend_countries.country_id', $chileId)
                        ->whereIn('trend_countries.stage', ['MAINSTREAM', 'PEAK']);
                })
                ->orderByDesc('trend_countries.strength')
                ->select('trends.*', 'trend_countries.strength as chile_strength', 'trend_countries.stage as chile_stage')
                ->limit(10)
                ->get();
        }

        // ── NEXT 60 DAYS: latest forecast per trend, ranked by probability ─
        $nextUp = collect();
        if ($chileId) {
            $latestIds = DB::table('trend_predictions')
                ->select(DB::raw('MAX(id) as id'))
                ->where('country_id', $chileId)
                ->where('prediction_horizon', $this->horizonDays)
                // historical_backtest rows exist purely to build the
                // historical-accuracy calibration number — they're dated in
                // the past and must never be surfaced as "today's" forecast.
                ->where('model_name', '!=', 'historical_backtest')
                ->groupBy('trend_id')
                ->pluck('id');

            $nextUp = TrendPrediction::query()
                ->whereIn('id', $latestIds)
                ->where('growth_probability', '>', 0)
                ->with('trend')
                ->orderByDesc('growth_probability')
                ->limit(10)
                ->get()
                ->filter(fn ($p) => $p->trend !== null);
        }

        // ── EARLY SIGNALS: real global signal, still small/absent in Chile ─
        $earlySignals = collect();
        if ($chileId) {
            $earlySignals = Trend::query()
                ->where('momentum_score', '>', 30)
                ->whereNotIn('lifecycle_stage', ['DECLINING', 'SATURATED', 'DEAD'])
                ->leftJoin('trend_countries', function ($join) use ($chileId) {
                    $join->on('trend_countries.trend_id', '=', 'trends.id')
                        ->where('trend_countries.country_id', $chileId);
                })
                ->where(function ($q) {
                    $q->whereNull('trend_countries.strength')
                        ->orWhere('trend_countries.strength', '<', 20);
                })
                ->orderByDesc('trends.momentum_score')
                ->select('trends.*', 'trend_countries.strength as chile_strength')
                ->limit(10)
                ->get();
        }

        // ── DECLINING ──────────────────────────────────────────────────────
        $declining = Trend::query()
            ->where(function ($q) use ($chileId) {
                $q->whereIn('lifecycle_stage', ['DECLINING', 'SATURATED', 'DEAD']);
                if ($chileId) {
                    $q->orWhereHas('countries', function ($cq) use ($chileId) {
                        $cq->where('countries.id', $chileId)
                            ->whereIn('trend_countries.stage', ['DECLINING', 'SATURATED']);
                    });
                }
            })
            ->orderByDesc('death_probability')
            ->limit(10)
            ->get();

        return view('livewire.chile-fashion-radar', [
            'chileMissing' => !$chileId,
            'historicalAccuracy' => PredictionOutcome::historicalAccuracy(),
            'now'          => $now,
            'nextUp'       => $nextUp,
            'earlySignals' => $earlySignals,
            'declining'    => $declining,
        ])->layout('layouts.app', ['title' => 'Fashion Radar — Chile']);
    }
}
