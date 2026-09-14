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

class CalculateScoresJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 2;

    public function __construct(
        public readonly ?string $experimentKey = null,
        public readonly ?int    $trendId = null,
    ) {}

    public function handle(PythonEngineClient $engine): void
    {
        if ($this->trendId) {
            $this->scoreSingleTrend($engine, $this->trendId);
            return;
        }

        // Score all trends from experiment
        $query = Trend::query()->notDemo();

        if ($this->experimentKey) {
            $config  = config("fashion.experiments.{$this->experimentKey}", []);
            $niche   = $config['niche'] ?? null;
            if ($niche) {
                $query->forNiche($niche);
            }
        }

        $count = 0;
        $query->chunk(50, function ($trends) use ($engine, &$count) {
            foreach ($trends as $trend) {
                try {
                    $this->scoreSingleTrend($engine, $trend->id);
                    $count++;
                } catch (\Exception $e) {
                    Log::warning("[CalculateScores] Failed to score trend {$trend->id}: " . $e->getMessage());
                }
            }
        });

        Log::info("[CalculateScores] Scored {$count} trends for experiment: " . ($this->experimentKey ?? 'all'));
    }

    private function scoreSingleTrend(PythonEngineClient $engine, int $trendId): void
    {
        $result = $engine->calculateScores($trendId);

        Trend::where('id', $trendId)->update([
            'momentum_score'               => $result['momentum_score'] ?? 0,
            'growth_score'                 => $result['growth_score'] ?? 0,
            'convergence_score'            => $result['convergence_score'] ?? 0,
            'saturation_score'             => $result['saturation_score'] ?? 0,
            'virality_score'               => $result['virality_score'] ?? 0,
            'commercial_opportunity_score' => $result['commercial_opportunity_score'] ?? 0,
            'competition_score'            => $result['competition_score'] ?? 0,
            'death_probability'            => $result['death_probability'] ?? 0,
            'confidence'                   => $result['confidence'] ?? 0,
            'lifecycle_stage'              => $result['lifecycle_stage'] ?? 'EMERGING',
            'stage_confidence'             => $result['stage_confidence'] ?? 0,
            'last_updated_at'              => now(),
        ]);

        Log::debug("[CalculateScores] Trend {$trendId} scored", $result);
    }
}
