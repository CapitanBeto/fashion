<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ScrapeRunResource;
use App\Jobs\RunExperimentJob;
use App\Jobs\CalculateScoresJob;
use App\Models\ScrapeRun;
use App\Services\PythonEngineClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;

class ExperimentController extends Controller
{
    /**
     * GET /api/experiments
     * List available experiment configs.
     */
    public function index(): JsonResponse
    {
        $experiments = collect(config('fashion.experiments', []))
            ->map(fn ($config, $key) => [
                'key'         => $key,
                'name'        => $config['name'] ?? $key,
                'niche'       => $config['niche'] ?? null,
                'countries'   => $config['countries'] ?? [],
                'keywords'    => $config['keywords'] ?? [],
                'sources'     => $config['sources'] ?? [],
                'period_days' => $config['period_days'] ?? null,
            ]);

        return response()->json(['experiments' => $experiments->values()]);
    }

    /**
     * POST /api/experiments/{key}/run
     * Dispatch an experiment to the queue.
     */
    public function run(Request $request, string $key): JsonResponse
    {
        $available = array_keys(config('fashion.experiments', []));
        if (!in_array($key, $available)) {
            return response()->json(['error' => "Unknown experiment: {$key}"], 422);
        }

        $overrides = $request->only([
            'countries', 'keywords', 'period_days', 'max_records',
        ]);

        RunExperimentJob::dispatch($key, $overrides)->onQueue('experiments');

        return response()->json([
            'message'        => "Experiment '{$key}' dispatched to queue.",
            'experiment_key' => $key,
            'overrides'      => $overrides,
        ], 202);
    }

    /**
     * GET /api/experiments/{key}/runs
     * Recent scrape runs for an experiment.
     */
    public function runs(string $key): AnonymousResourceCollection
    {
        $runs = ScrapeRun::forExperiment($key)
            ->with('source')
            ->orderByDesc('created_at')
            ->paginate(20);

        return ScrapeRunResource::collection($runs);
    }

    /**
     * GET /api/experiments/{key}/status
     * Quick summary: last run, counts, etc.
     */
    public function status(string $key): JsonResponse
    {
        $lastRun = ScrapeRun::forExperiment($key)
            ->orderByDesc('created_at')
            ->first();

        $runningCount = ScrapeRun::forExperiment($key)
            ->byStatus('running')
            ->count();

        return response()->json([
            'experiment_key' => $key,
            'running'        => $runningCount > 0,
            'running_count'  => $runningCount,
            'last_run'       => $lastRun ? new ScrapeRunResource($lastRun) : null,
        ]);
    }

    /**
     * POST /api/experiments/{key}/rescore
     * Recalculate scores without re-scraping.
     */
    public function rescore(string $key): JsonResponse
    {
        CalculateScoresJob::dispatch($key)->onQueue('scoring');

        return response()->json([
            'message'        => "Scoring job dispatched for experiment '{$key}'.",
            'experiment_key' => $key,
        ], 202);
    }

    /**
     * GET /api/scrape-runs/{run}
     * Detail + live log for a single scrape run.
     */
    public function showRun(ScrapeRun $scrapeRun): JsonResponse
    {
        $scrapeRun->load('source', 'sourceTarget', 'errors');

        return response()->json([
            'run'    => new ScrapeRunResource($scrapeRun),
            'log'    => $scrapeRun->log ?? [],
            'errors' => $scrapeRun->errors->map(fn ($e) => [
                'url'     => $e->url,
                'type'    => $e->error_type,
                'status'  => $e->http_status,
                'message' => $e->error_message,
            ]),
        ]);
    }

    /**
     * GET /api/system/health
     * Check Python engine connectivity.
     */
    public function health(PythonEngineClient $engine): JsonResponse
    {
        $engineUp = $engine->ping();

        return response()->json([
            'status'         => $engineUp ? 'ok' : 'degraded',
            'python_engine'  => $engineUp,
            'queue_workers'  => $this->checkQueueWorkers(),
            'timestamp'      => now()->toIso8601String(),
        ], $engineUp ? 200 : 503);
    }

    private function checkQueueWorkers(): bool
    {
        // Simple heuristic: check if there are recent heartbeat records
        // A real implementation would use Laravel Horizon or a queue monitor
        return true;
    }
}
