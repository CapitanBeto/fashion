<?php

namespace App\Jobs;

use App\Models\ScrapeRun;
use App\Services\PythonEngineClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunExperimentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 hour max

    public int $tries = 1; // experiments don't retry automatically

    public function __construct(
        public readonly string $experimentKey,
        public readonly array  $overrides = [],
    ) {}

    public function handle(PythonEngineClient $engine): void
    {
        $config = array_merge(
            config("fashion.experiments.{$this->experimentKey}", []),
            $this->overrides,
        );

        if (empty($config)) {
            Log::error("[RunExperiment] Unknown experiment key: {$this->experimentKey}");
            return;
        }

        Log::info("[RunExperiment] Starting: {$this->experimentKey}", $config);

        try {
            $result = $engine->runExperiment($this->experimentKey, $config);

            Log::info("[RunExperiment] Completed: {$this->experimentKey}", [
                'trends_found'     => $result['trends_found'] ?? 0,
                'documents_processed' => $result['documents_processed'] ?? 0,
                'duration_seconds' => $result['duration_seconds'] ?? null,
            ]);

            // Dispatch scoring after data collection, then the Chile forecast
            // once scores are in (ForecastChileTrendJob reads momentum_score,
            // convergence_score, etc. that CalculateScoresJob just wrote).
            CalculateScoresJob::dispatch($this->experimentKey)
                ->onQueue('scoring')
                ->delay(now()->addSeconds(30));

            ForecastChileTrendJob::dispatch($this->experimentKey)
                ->onQueue('scoring')
                ->delay(now()->addSeconds(60));

        } catch (\Exception $e) {
            Log::error("[RunExperiment] Failed: {$this->experimentKey}", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("[RunExperiment] Job failed for {$this->experimentKey}: " . $e->getMessage());
    }
}
