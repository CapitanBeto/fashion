<?php

namespace App\Livewire;

use App\Jobs\RunExperimentJob;
use App\Models\ScrapeRun;
use App\Services\PythonEngineClient;
use Livewire\Component;

class ExperimentPanel extends Component
{
    public string $selectedKey = '';
    public bool   $dispatching = false;
    public ?array $engineStatus = null;

    public function mount(): void
    {
        $keys = array_keys(config('fashion.experiments', []));
        $this->selectedKey = $keys[0] ?? '';
    }

    public function runExperiment(): void
    {
        if (!$this->selectedKey) return;

        RunExperimentJob::dispatch($this->selectedKey)->onQueue('experiments');
        $this->dispatching = true;
        $this->dispatch('notify', message: "Experiment '{$this->selectedKey}' dispatched to queue.");
    }

    public function checkEngine(PythonEngineClient $engine): void
    {
        $up = $engine->ping();
        $this->engineStatus = ['up' => $up];
    }

    public function render()
    {
        $experiments = collect(config('fashion.experiments', []))
            ->map(fn ($config, $key) => [
                'key'          => $key,
                'name'         => $config['name'] ?? $key,
                'niche'        => $config['niche'] ?? null,
                'countries'    => $config['countries'] ?? [],
                'keywords'     => count($config['keywords'] ?? []),
                'subreddits'   => count($config['subreddits'] ?? []),
                'period_days'  => $config['period_days'] ?? 180,
            ]);

        $recentRuns = ScrapeRun::with('source')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $runsByExperiment = $recentRuns->groupBy('experiment_name');

        return view('livewire.experiment-panel', [
            'experiments'       => $experiments,
            'runsByExperiment'  => $runsByExperiment,
        ])->layout('layouts.app', ['title' => 'Experiments — Fashion Intelligence']);
    }
}
