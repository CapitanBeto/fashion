<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Populates the Fashion Radar — Chile dashboard with clearly-labeled DEMO
 * data so it can be previewed while real Google Trends data is blocked.
 *
 * Every row here has is_demo = true, and every piece of human-readable text
 * (reasoning, evidence) is explicitly prefixed "[DEMO]" — this is never
 * meant to be mistaken for a real pipeline result. Run with:
 *   php artisan db:seed --class=DemoChileForecastSeeder
 * Remove with:
 *   php artisan tinker --execute="App\Models\Trend::where('is_demo', true)->delete();"
 */
class DemoChileForecastSeeder extends Seeder
{
    public function run(): void
    {
        $nicheId = DB::table('niches')->where('slug', 'streetwear')->value('id');
        $chileId = DB::table('countries')->where('iso2', 'CL')->value('id');

        if (!$chileId) {
            $this->command?->error('Chile (CL) not found in countries table — run CountrySeeder first.');
            return;
        }

        $demoTrends = [
            // ── NEXT 60 DAYS: high-probability rising trend ────────────────
            [
                'name' => 'graphic zip-up hoodie',
                'lifecycle_stage' => 'ACCELERATING',
                'momentum_score' => 78, 'growth_score' => 74, 'search_score' => 81,
                'creator_score' => 62, 'brand_score' => 58, 'convergence_score' => 71,
                'saturation_score' => 18, 'competition_score' => 35, 'death_probability' => 4,
                'confidence' => 68, 'commercial_opportunity_score' => 74,
                'chile_stage' => 'EMERGING', 'chile_strength' => 34,
                'forecast_probability' => 81, 'forecast_confidence' => 74,
                'predicted_stage' => 'EARLY_ADOPTION',
                'reasoning' => "[DEMO] Chile 60-day forecast for 'graphic zip-up hoodie': 81%. Rising search interest in Spain (+42% WoW), increasing creator adoption across 3 independent communities, 4 relevant brands adopted recently, low current Chile saturation, and a UK→Spain→Chile historical diffusion pattern with a typical 43-day lag support acceleration. This sits in the early-adoption phase of the fashion cycle — subculture/niche origin giving way to creator amplification, not yet mainstream.",
                'evidence' => ['[DEMO] +42% Google search growth in Spain', '[DEMO] +31% creator adoption across independent communities', '[DEMO] 4 major brands adopted this silhouette recently', '[DEMO] Historical UK→Spain→Chile diffusion pattern, ~43 day median lag'],
                'contradictions' => ['[DEMO] Already showing signs of saturation in the UK market'],
            ],
            // ── ALREADY TRENDING IN CHILE ───────────────────────────────────
            [
                'name' => 'cargo utility pants',
                'lifecycle_stage' => 'MAINSTREAM',
                'momentum_score' => 62, 'growth_score' => 40, 'search_score' => 70,
                'creator_score' => 80, 'brand_score' => 85, 'convergence_score' => 88,
                'saturation_score' => 58, 'competition_score' => 62, 'death_probability' => 12,
                'confidence' => 82, 'commercial_opportunity_score' => 55,
                'chile_stage' => 'MAINSTREAM', 'chile_strength' => 76,
                'forecast_probability' => 22, 'forecast_confidence' => 70,
                'predicted_stage' => 'PEAK',
                'reasoning' => "[DEMO] Already broadly adopted in Chile — high brand and creator convergence, strong local search interest sustained for several months. Forecast probability for further 60-day growth is intentionally low (22%): a trend already at MAINSTREAM in-market has limited remaining upside, and rising saturation/competition scores suggest it is approaching peak rather than continuing to accelerate.",
                'evidence' => ['[DEMO] Sustained high Chile search interest for 4+ months', '[DEMO] Adopted by 12+ brands locally', '[DEMO] High creator convergence across platforms'],
                'contradictions' => ['[DEMO] Saturation score rising — approaching peak, not further growth'],
            ],
            // ── EARLY SIGNAL: small but real, not yet in Chile ──────────────
            [
                'name' => 'micro-fleece vest',
                'lifecycle_stage' => 'EMERGING',
                'momentum_score' => 45, 'growth_score' => 52, 'search_score' => 38,
                'creator_score' => 28, 'brand_score' => 15, 'convergence_score' => 40,
                'saturation_score' => 5, 'competition_score' => 20, 'death_probability' => 8,
                'confidence' => 31, 'commercial_opportunity_score' => 48,
                'chile_stage' => 'EMERGING', 'chile_strength' => 6,
                'forecast_probability' => 47, 'forecast_confidence' => 38,
                'predicted_stage' => 'EMERGING',
                'reasoning' => "[DEMO] Small but accelerating global signal — a handful of independent design communities and two early-adopter creators are pushing this silhouette, with almost no Chile presence yet. This is a genuinely early-cycle, niche/subculture-origin signal: too small to be obvious, but the growth velocity and cross-community independence are the kind of pattern that historically precedes a later acceleration phase. Confidence is intentionally low given the thin data.",
                'evidence' => ['[DEMO] Present in 3 independent design communities, not yet a single dominant source', '[DEMO] Growth velocity accelerating over the last 3 weeks', '[DEMO] Two early-adopter creators posting independently'],
                'contradictions' => ['[DEMO] Almost no brand adoption yet — could stall before reaching Chile'],
            ],
            // ── LIKELY DECLINING ─────────────────────────────────────────────
            [
                'name' => 'logo tracksuit',
                'lifecycle_stage' => 'DECLINING',
                'momentum_score' => 14, 'growth_score' => 8, 'search_score' => 20,
                'creator_score' => 22, 'brand_score' => 40, 'convergence_score' => 30,
                'saturation_score' => 82, 'competition_score' => 75, 'death_probability' => 61,
                'confidence' => 66, 'commercial_opportunity_score' => 15,
                'chile_stage' => 'DECLINING', 'chile_strength' => 25,
                'forecast_probability' => 6, 'forecast_confidence' => 60,
                'predicted_stage' => 'DECLINING',
                'reasoning' => "[DEMO] Global momentum has dropped sharply over the last 8 weeks alongside very high saturation and competition scores — classic late-cycle/decline signals. Chile adoption, where it exists, is already declining in step with the global pattern rather than lagging behind it, so there is little basis for a rebound within 60 days.",
                'evidence' => ['[DEMO] Momentum down 3 consecutive weeks', '[DEMO] Saturation score at 82/100', '[DEMO] Search interest declining in both source markets and Chile'],
                'contradictions' => [],
            ],
        ];

        foreach ($demoTrends as $data) {
            $slug = str($data['name'])->slug();

            $trendId = DB::table('trends')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => $data['name'],
                    'niche_id' => $nicheId,
                    'primary_country_id' => $chileId,
                    'detection_method' => 'known',
                    'keywords' => json_encode([$data['name']]),
                    'description' => '[DEMO] Synthetic example trend for dashboard preview — not derived from live scraping.',
                    'momentum_score' => $data['momentum_score'],
                    'growth_score' => $data['growth_score'],
                    'search_score' => $data['search_score'],
                    'creator_score' => $data['creator_score'],
                    'brand_score' => $data['brand_score'],
                    'convergence_score' => $data['convergence_score'],
                    'saturation_score' => $data['saturation_score'],
                    'competition_score' => $data['competition_score'],
                    'death_probability' => $data['death_probability'],
                    'confidence' => $data['confidence'],
                    'commercial_opportunity_score' => $data['commercial_opportunity_score'],
                    'lifecycle_stage' => $data['lifecycle_stage'],
                    'stage_confidence' => $data['confidence'],
                    'first_seen_at' => now()->subDays(rand(20, 90)),
                    'last_updated_at' => now(),
                    'is_demo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $trend = DB::table('trends')->where('slug', $slug)->first();

            DB::table('trend_countries')->updateOrInsert(
                ['trend_id' => $trend->id, 'country_id' => $chileId],
                [
                    'strength' => $data['chile_strength'],
                    'stage' => $data['chile_stage'],
                    'first_seen_at' => now()->subDays(rand(10, 60)),
                    'created_at' => now(),
                ]
            );

            $horizonCurve = [
                '7' => (int) round($data['forecast_probability'] * 0.55),
                '14' => (int) round($data['forecast_probability'] * 0.65),
                '30' => (int) round($data['forecast_probability'] * 0.82),
                '60' => $data['forecast_probability'],
                '90' => (int) round($data['forecast_probability'] * 1.05),
            ];

            DB::table('trend_predictions')->insert([
                'trend_id' => $trend->id,
                'country_id' => $chileId,
                'model_name' => 'demo_seed',
                'model_version' => '1.0',
                'prediction_horizon' => 60,
                'prediction_date' => now()->toDateString(),
                'target_date' => now()->addDays(60)->toDateString(),
                'growth_probability' => $data['forecast_probability'],
                'decline_probability' => $data['lifecycle_stage'] === 'DECLINING' ? 70 : 0,
                'saturation_probability' => $data['saturation_score'],
                'death_probability' => $data['death_probability'],
                'momentum_forecast' => json_encode($horizonCurve),
                'confidence' => $data['forecast_confidence'],
                'reasoning' => $data['reasoning'],
                'input_snapshot' => json_encode([
                    'global_momentum' => $data['momentum_score'],
                    'chile_momentum' => $data['chile_strength'],
                    'cross_source_convergence' => $data['convergence_score'],
                    'creator_adoption' => $data['creator_score'],
                    'brand_adoption' => $data['brand_score'],
                    'search_growth' => $data['search_score'],
                    'historical_transfer_similarity' => $data['forecast_confidence'],
                    'current_chile_stage' => $data['chile_stage'],
                    'predicted_stage' => $data['predicted_stage'],
                    'horizon_curve' => $horizonCurve,
                    'evidence' => $data['evidence'],
                    'contradictions' => $data['contradictions'],
                    'is_demo' => true,
                ]),
                'created_at' => now(),
            ]);

            $this->command?->info("Seeded demo trend: {$data['name']}");
        }
    }
}
