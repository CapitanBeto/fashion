<?php

namespace App\Console\Commands;

use App\Jobs\CalculateScoresJob;
use App\Jobs\ForecastChileTrendJob;
use App\Jobs\RunExperimentJob;
use App\Services\PythonEngineClient;
use Illuminate\Console\Command;

class RunExperimentCommand extends Command
{
    protected $signature = 'experiment:run
                            {key : Experiment key (e.g. hoodie_test_01)}
                            {--sync : Run synchronously instead of dispatching to queue}
                            {--countries= : Override countries (comma-separated ISO2)}
                            {--days= : Override period_days}';

    protected $description = 'Run a Fashion Intelligence experiment';

    public function handle(PythonEngineClient $engine): int
    {
        $key = $this->argument('key');

        $available = array_keys(config('fashion.experiments', []));
        if (!in_array($key, $available)) {
            $this->error("Unknown experiment key: {$key}");
            $this->line('Available experiments: ' . implode(', ', $available));
            return self::FAILURE;
        }

        // Check Python engine is reachable
        $this->line('Checking Python engine...');
        if (!$engine->ping()) {
            $this->error('Python engine is not reachable. Start it with: uvicorn api.main:app --port 8001');
            return self::FAILURE;
        }
        $this->info('Python engine: OK');

        // Build overrides
        $overrides = [];

        if ($countries = $this->option('countries')) {
            $overrides['countries'] = explode(',', strtoupper($countries));
        }

        if ($days = $this->option('days')) {
            $overrides['period_days'] = (int) $days;
        }

        $config = array_merge(config("fashion.experiments.{$key}", []), $overrides);

        $this->newLine();
        $this->info("Fashion Intelligence — Experiment: {$key}");
        $this->line('─────────────────────────────────');
        $this->line('Niche:    ' . ($config['niche'] ?? 'all'));
        $this->line('Countries:' . implode(', ', $config['countries'] ?? []));
        $this->line('Keywords: ' . count($config['keywords'] ?? []) . ' configured');
        $this->line('Period:   ' . ($config['period_days'] ?? 180) . ' days');
        $this->line('Mode:     ' . config('fashion.scraping.mode', 'FREE'));
        $this->newLine();

        if ($this->option('sync')) {
            $this->line('Running synchronously...');
            $this->runSync($engine, $key, $config);
        } else {
            RunExperimentJob::dispatch($key, $overrides)->onQueue('experiments');
            $this->info("Experiment dispatched to queue.");
            $this->line('Monitor with: php artisan queue:work --queue=experiments,scoring,default');
            $this->line("API status:   GET /api/v1/experiments/{$key}/status");
        }

        return self::SUCCESS;
    }

    private function runSync(PythonEngineClient $engine, string $key, array $config): void
    {
        $this->line('Step 1/4: Collecting data...');
        $bar = $this->output->createProgressBar(4);
        $bar->start();

        try {
            $result = $engine->runExperiment($key, $config);
            $bar->advance();

            $this->newLine();
            $this->line('Step 2/4: Calculating scores...');
            $bar->advance();

            // Reuse the same per-trend scoring logic as the queued path (CalculateScoresJob),
            // run synchronously here instead of dispatching to the queue.
            (new CalculateScoresJob($key))->handle($engine);

            $this->newLine();
            $this->line('Step 3/4: Forecasting Chile 60-day adoption...');
            $bar->advance();

            (new ForecastChileTrendJob($key))->handle($engine);

            $bar->advance();
            $bar->finish();
            $this->newLine(2);

            $this->info('Experiment completed successfully!');
            $this->line('─────────────────────────────────');
            $this->line('Trends found:       ' . ($result['trends_found'] ?? 0));
            $this->line('Documents scraped:  ' . ($result['documents_scraped'] ?? 0));
            $this->line('Documents processed:' . ($result['documents_processed'] ?? 0));
            $this->line('Duration:           ' . ($result['duration_seconds'] ?? '?') . 's');
            $this->newLine();
            $this->line('View the Chile Fashion Radar: ' . url('/'));
            $this->line('Or in tinker:');
            $this->line('  >>> App\Models\TrendPrediction::latest(\'created_at\')->with(\'trend\')->limit(10)->get([\'trend_id\',\'growth_probability\',\'confidence\'])');

        } catch (\Exception $e) {
            $bar->finish();
            $this->newLine();
            $this->error('Experiment failed: ' . $e->getMessage());
        }
    }
}
