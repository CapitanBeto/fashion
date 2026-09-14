<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PythonEngineClient
{
    private PendingRequest $http;

    public function __construct()
    {
        // No automatic ->retry() here on purpose: this client is shared by
        // runExperiment(), which can legitimately run for several minutes
        // (Google Trends now needs real pacing between requests). A single
        // transient connection hiccup on a long-held connection would
        // otherwise make Laravel silently fire a SECOND full experiment run
        // while the first is still executing server-side — reproduced
        // directly: two overlapping "Step 1" Google Trends passes in the
        // engine log from one artisan command. calculateScores()/
        // forecastTrend() are per-trend and fast enough that a manual retry
        // isn't worth the same risk trade-off.
        $this->http = Http::baseUrl(config('fashion.python_engine.url'))
            ->timeout(config('fashion.python_engine.timeout', 120))
            ->withHeaders([
                'X-Engine-Secret' => config('fashion.python_engine.secret'),
                'Accept'          => 'application/json',
                'Content-Type'    => 'application/json',
            ]);
    }

    /**
     * Trigger a full experiment run in the Python engine.
     */
    public function runExperiment(string $key, array $config): array
    {
        $response = $this->http->post('/experiments/run', [
            'experiment_key' => $key,
            'config'         => $config,
        ]);

        $this->assertSuccess($response, 'runExperiment');
        return $response->json();
    }

    /**
     * Ask the Python engine to NLP-process a single raw document.
     */
    public function processDocument(array $payload): array
    {
        $response = $this->http->post('/documents/process', $payload);

        $this->assertSuccess($response, 'processDocument');
        return $response->json();
    }

    /**
     * Ask the Python engine to recalculate all scores for a trend.
     */
    public function calculateScores(int $trendId): array
    {
        $response = $this->http->post("/trends/{$trendId}/scores");

        $this->assertSuccess($response, 'calculateScores');
        return $response->json();
    }

    /**
     * Ask the Python engine for the Chile trend-adoption forecast.
     */
    public function forecastTrend(int $trendId, ?int $countryId = null, int $horizonDays = 60): array
    {
        $response = $this->http->post("/trends/{$trendId}/forecast", [
            'country_id'   => $countryId,
            'horizon_days' => $horizonDays,
        ]);

        $this->assertSuccess($response, 'forecastTrend');
        return $response->json();
    }

    /**
     * Walk-forward historical backtest using real Google Trends history —
     * writes real trend_predictions + prediction_outcomes pairs immediately
     * (the "future" for each historical checkpoint is already known).
     */
    public function backtestTrend(int $trendId, string $keyword, string $geo): array
    {
        $response = $this->http->post("/trends/{$trendId}/backtest", [
            'keyword' => $keyword,
            'geo'     => $geo,
        ]);

        $this->assertSuccess($response, 'backtestTrend');
        return $response->json();
    }

    /**
     * Ask the Python engine for the Creative Director analysis on a commercial opportunity.
     */
    public function generateProductConcept(int $opportunityId): array
    {
        $response = $this->http->post("/opportunities/{$opportunityId}/concept");

        $this->assertSuccess($response, 'generateProductConcept');
        return $response->json();
    }

    /**
     * Health check — returns true if Python engine is reachable.
     *
     * Builds its own request rather than reusing $this->http: PendingRequest::timeout()
     * mutates the instance it's called on, and since this client is registered as a
     * singleton, calling ping() before any other method would otherwise permanently
     * clamp every later request (e.g. runExperiment()) to a 5-second timeout too.
     */
    public function ping(): bool
    {
        try {
            $response = Http::baseUrl(config('fashion.python_engine.url'))
                ->withHeaders([
                    'X-Engine-Secret' => config('fashion.python_engine.secret'),
                    'Accept'          => 'application/json',
                ])
                ->timeout(10)
                ->get('/health');

            return $response->ok();
        } catch (\Exception $e) {
            Log::warning('[PythonEngine] Ping failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieve current scrape mode and budget status.
     */
    public function getScrapingStatus(): array
    {
        $response = $this->http->get('/scraping/status');
        $this->assertSuccess($response, 'getScrapingStatus');
        return $response->json();
    }

    private function assertSuccess(\Illuminate\Http\Client\Response $response, string $method): void
    {
        if ($response->failed()) {
            $body = $response->body();
            Log::error("[PythonEngine] {$method} failed ({$response->status()}): {$body}");
            throw new \RuntimeException(
                "Python engine error in {$method} [{$response->status()}]: " .
                ($response->json('detail') ?? $body)
            );
        }
    }
}
