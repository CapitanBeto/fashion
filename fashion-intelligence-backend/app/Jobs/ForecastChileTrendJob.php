<?php

namespace App\Jobs;

use App\Models\Trend;
use App\Services\PythonEngineClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ForecastChileTrendJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 2;

    public function __construct(
        public readonly ?string $experimentKey = null,
        public readonly ?int    $trendId = null,
        public readonly int     $horizonDays = 60,
    ) {}

    public function handle(PythonEngineClient $engine): void
    {
        if ($this->trendId) {
            $this->forecastSingleTrend($engine, $this->trendId);
            return;
        }

        $query = Trend::query()->notDemo();

        if ($this->experimentKey) {
            $niche = config("fashion.experiments.{$this->experimentKey}.niche");
            if ($niche) {
                $query->forNiche($niche);
            }
        }

        $count = 0;
        $query->chunk(50, function ($trends) use ($engine, &$count) {
            foreach ($trends as $trend) {
                try {
                    $this->forecastSingleTrend($engine, $trend->id);
                    $count++;
                } catch (\Exception $e) {
                    Log::warning("[ForecastChile] Failed to forecast trend {$trend->id}: " . $e->getMessage());
                }
            }
        });

        Log::info("[ForecastChile] Forecasted {$count} trends for experiment: " . ($this->experimentKey ?? 'all'));
    }

    private function forecastSingleTrend(PythonEngineClient $engine, int $trendId): void
    {
        $result = $engine->forecastTrend($trendId, null, $this->horizonDays);

        Log::debug("[ForecastChile] Trend {$trendId} forecasted", [
            'probability' => $result['probability'] ?? null,
            'chile_stage' => $result['current_chile_stage'] ?? null,
        ]);
    }
}
