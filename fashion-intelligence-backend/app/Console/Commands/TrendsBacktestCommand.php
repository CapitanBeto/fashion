<?php

namespace App\Console\Commands;

use App\Models\Trend;
use App\Services\PythonEngineClient;
use Illuminate\Console\Command;

class TrendsBacktestCommand extends Command
{
    protected $signature = 'trends:backtest
                            {--geo=ES,CL : Comma-separated country codes to backtest against}';

    protected $description = 'Refresh historical calibration for every real trend using real Google Trends history (walk-forward backtest)';

    public function handle(PythonEngineClient $engine): int
    {
        $geos = array_filter(array_map('trim', explode(',', (string) $this->option('geo'))));

        $trends = Trend::query()->notDemo()->get();

        if ($trends->isEmpty()) {
            $this->info('No real trends to backtest yet.');
            return self::SUCCESS;
        }

        $this->line("Backtesting {$trends->count()} trends across " . implode(', ', $geos) . '...');
        $totalCheckpoints = 0;
        $accuracies = [];

        foreach ($trends as $trend) {
            $keyword = $trend->keywords[0] ?? $trend->name;

            foreach ($geos as $geo) {
                try {
                    $result = $engine->backtestTrend($trend->id, $keyword, $geo);
                    $created = $result['checkpoints_created'] ?? 0;
                    $totalCheckpoints += $created;

                    if ($created > 0 && isset($result['mean_accuracy'])) {
                        $accuracies[] = $result['mean_accuracy'];
                        $this->line("  {$trend->name} ({$geo}): {$created} checkpoints, {$result['mean_accuracy']}% accuracy");
                    } else {
                        $this->line("  {$trend->name} ({$geo}): " . ($result['note'] ?? 'no new checkpoints'));
                    }
                } catch (\Exception $e) {
                    $this->warn("  {$trend->name} ({$geo}): failed — " . $e->getMessage());
                }
            }
        }

        $overall = count($accuracies) ? round(array_sum($accuracies) / count($accuracies), 1) : null;
        $this->info("Done. {$totalCheckpoints} new checkpoints created." . ($overall !== null ? " Overall mean accuracy this run: {$overall}%." : ''));

        return self::SUCCESS;
    }
}
