<?php

namespace App\Livewire;

use App\Models\PredictionOutcome;
use App\Models\Trend;
use App\Jobs\CalculateScoresJob;
use Livewire\Component;

class TrendDetail extends Component
{
    public Trend $trend;
    public string $selectedCountry = '';
    public bool   $rescoring = false;

    public function mount(string $slug): void
    {
        $this->trend = Trend::where('slug', $slug)
            ->with([
                'niche',
                'primaryCountry',
                'snapshots' => fn ($q) => $q->orderBy('snapshot_date')->limit(90),
                'countries',
                'colors'      => fn ($q) => $q->orderByPivot('frequency', 'desc')->limit(8),
                'silhouettes' => fn ($q) => $q->orderByPivot('frequency', 'desc')->limit(6),
                'trendGraphics' => fn ($q) => $q->orderByPivot('frequency', 'desc')->limit(6),
                'brands'   => fn ($q) => $q->limit(12),
                'creators' => fn ($q) => $q->limit(12),
                'opportunities' => fn ($q) => $q->with('country')->orderByDesc('opportunity_score'),
                'predictions'   => fn ($q) => $q->latest('prediction_date')->limit(1),
            ])
            ->withCount(['brands', 'creators', 'products', 'mentions'])
            ->firstOrFail();
    }

    public function rescore(): void
    {
        $this->rescoring = true;
        CalculateScoresJob::dispatch(null, $this->trend->id)->onQueue('scoring');
        $this->dispatch('notify', message: 'Scoring job dispatched. Refresh in ~30 seconds.');
    }

    public function render()
    {
        return view('livewire.trend-detail', [
            'historicalAccuracy' => PredictionOutcome::historicalAccuracy(),
        ])->layout('layouts.app', ['title' => $this->trend->name . ' — Fashion Intelligence']);
    }
}
