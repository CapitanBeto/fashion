<?php

namespace App\Console\Commands;

use App\Jobs\ForecastChileTrendJob;
use App\Services\PythonEngineClient;
use Illuminate\Console\Command;

class ForecastChileCommand extends Command
{
    protected $signature = 'experiment:forecast-chile
                            {key? : Experiment key to scope by niche (omit to forecast every non-demo trend)}
                            {--horizon=60 : Forecast horizon in days}
                            {--sync : Run synchronously instead of dispatching to the queue}';

    protected $description = 'Run the Chile 60-day trend-adoption forecast for existing trends';

    public function handle(PythonEngineClient $engine): int
    {
        $key     = $this->argument('key');
        $horizon = (int) $this->option('horizon');

        $this->line('Checking Python engine...');
        if (!$engine->ping()) {
            $this->error('Python engine is not reachable. Start it with: uvicorn api.main:app --port 8001');
            return self::FAILURE;
        }
        $this->info('Python engine: OK');

        if ($this->option('sync')) {
            $this->line('Running Chile forecast synchronously...');
            (new ForecastChileTrendJob($key, null, $horizon))->handle($engine);
            $this->info('Chile forecast completed. View results: php artisan tinker');
            $this->line("  >>> App\\Models\\TrendPrediction::where('prediction_horizon', {$horizon})->latest('created_at')->with('trend')->limit(10)->get(['trend_id','growth_probability','confidence'])");
        } else {
            ForecastChileTrendJob::dispatch($key, null, $horizon)->onQueue('scoring');
            $this->info('Chile forecast dispatched to queue.');
        }

        return self::SUCCESS;
    }
}
