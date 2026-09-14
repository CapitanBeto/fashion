<?php

namespace App\Livewire;

use App\Models\Trend;
use App\Models\CommercialOpportunity;
use App\Models\ScrapeRun;
use Livewire\Component;
use Livewire\WithPagination;

class TrendDashboard extends Component
{
    use WithPagination;

    // ── Filters ───────────────────────────────────────────────────────────────
    public string $search         = '';
    public string $lifecycleStage = '';
    public string $nicheSlug      = '';
    public string $sortBy         = 'commercial_opportunity_score';
    public bool   $risingOnly     = false;
    public bool   $hideDemo       = true;
    // Was 30 — hid every trend on a fresh install (or any trend with no
    // scoring signal yet) with no indication why, since confidence starts
    // at 0. Defaulting to 0 shows real trends immediately; the filter is
    // still there for anyone who wants to raise it.
    public int    $minConfidence  = 0;

    protected $queryString = [
        'search'         => ['except' => ''],
        'lifecycleStage' => ['except' => '', 'as' => 'stage'],
        'nicheSlug'      => ['except' => '', 'as' => 'niche'],
        'sortBy'         => ['except' => 'commercial_opportunity_score', 'as' => 'sort'],
        'risingOnly'     => ['except' => false, 'as' => 'rising'],
    ];

    public function updatedSearch(): void    { $this->resetPage(); }
    public function updatedLifecycleStage(): void { $this->resetPage(); }
    public function updatedNicheSlug(): void { $this->resetPage(); }
    public function updatedRisingOnly(): void { $this->resetPage(); }

    public function sortBy(string $column): void
    {
        $this->sortBy = $column;
        $this->resetPage();
    }

    public function render()
    {
        $query = Trend::query()
            ->with(['niche', 'primaryCountry'])
            ->withCount(['brands', 'creators', 'products']);

        if ($this->search) {
            $term = $this->search;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'ilike', "%{$term}%")
                  ->orWhere('description', 'ilike', "%{$term}%");
            });
        }

        if ($this->lifecycleStage) {
            $query->where('lifecycle_stage', $this->lifecycleStage);
        }

        if ($this->nicheSlug) {
            $query->forNiche($this->nicheSlug);
        }

        if ($this->risingOnly) {
            $query->rising();
        }

        if ($this->hideDemo) {
            $query->notDemo();
        }

        $query->withHighConfidence($this->minConfidence);

        $allowedSorts = [
            'commercial_opportunity_score', 'momentum_score',
            'growth_score', 'death_probability', 'confidence',
            'first_seen_at', 'creator_score', 'brand_score',
        ];

        if (in_array($this->sortBy, $allowedSorts)) {
            $query->orderByDesc($this->sortBy);
        }

        $trends = $query->paginate(25);

        // Summary stats for header cards
        $stats = [
            'total'       => Trend::notDemo()->count(),
            'rising'      => Trend::rising()->notDemo()->count(),
            'emerging'    => Trend::where('lifecycle_stage', 'EMERGING')->notDemo()->count(),
            'last_run'    => ScrapeRun::byStatus('completed')->latest('finished_at')->value('finished_at'),
        ];

        $niches = \App\Models\Niche::active()->orderBy('priority', 'desc')->get(['id', 'name', 'slug']);

        return view('livewire.trend-dashboard', [
            'trends' => $trends,
            'stats'  => $stats,
            'niches' => $niches,
        ])->layout('layouts.app', ['title' => 'Trends — Fashion Intelligence']);
    }
}
