<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ScoringParameterSeeder extends Seeder
{
    public function run(): void
    {
        $parameters = [
            // ── Lifecycle thresholds ─────────────────────────────────────────
            ['group' => 'lifecycle', 'key' => 'emerging_upper',       'value' => 20,  'description' => 'Max momentum score to be classified EMERGING'],
            ['group' => 'lifecycle', 'key' => 'early_adoption_upper',  'value' => 40,  'description' => 'Max momentum score for EARLY_ADOPTION'],
            ['group' => 'lifecycle', 'key' => 'accelerating_upper',    'value' => 65,  'description' => 'Max momentum score for ACCELERATING'],
            ['group' => 'lifecycle', 'key' => 'mainstream_upper',      'value' => 80,  'description' => 'Max momentum score for MAINSTREAM'],
            ['group' => 'lifecycle', 'key' => 'peak_upper',            'value' => 90,  'description' => 'Max momentum score for PEAK'],
            ['group' => 'lifecycle', 'key' => 'saturation_upper',      'value' => 75,  'description' => 'Saturation score threshold for SATURATED classification'],
            ['group' => 'lifecycle', 'key' => 'decline_upper',         'value' => 40,  'description' => 'Max momentum score in DECLINING stage'],
            ['group' => 'lifecycle', 'key' => 'death_threshold',       'value' => 15,  'description' => 'Momentum below this = DEAD'],

            // ── Momentum score weights ───────────────────────────────────────
            ['group' => 'momentum', 'key' => 'growth_velocity_weight',    'value' => 0.30, 'description' => 'Weight of WoW growth velocity in momentum score'],
            ['group' => 'momentum', 'key' => 'growth_acceleration_weight','value' => 0.20, 'description' => 'Weight of velocity acceleration'],
            ['group' => 'momentum', 'key' => 'search_growth_weight',      'value' => 0.20, 'description' => 'Weight of Google Trends growth'],
            ['group' => 'momentum', 'key' => 'social_growth_weight',      'value' => 0.10, 'description' => 'Weight of social post growth'],
            ['group' => 'momentum', 'key' => 'creator_adoption_weight',   'value' => 0.10, 'description' => 'Weight of new creator adoptions'],
            ['group' => 'momentum', 'key' => 'brand_adoption_weight',     'value' => 0.05, 'description' => 'Weight of new brand adoptions'],
            ['group' => 'momentum', 'key' => 'geographic_spread_weight',  'value' => 0.05, 'description' => 'Weight of geographic expansion'],

            // ── Commercial opportunity weights ───────────────────────────────
            ['group' => 'commercial_opportunity', 'key' => 'momentum_weight',           'value' => 0.20, 'description' => 'Weight of momentum score'],
            ['group' => 'commercial_opportunity', 'key' => 'demand_weight',             'value' => 0.15, 'description' => 'Weight of demand/search signals'],
            ['group' => 'commercial_opportunity', 'key' => 'creator_adoption_weight',   'value' => 0.10, 'description' => 'Weight of creator adoption'],
            ['group' => 'commercial_opportunity', 'key' => 'sales_signal_weight',       'value' => 0.15, 'description' => 'Weight of inferred sales signals'],
            ['group' => 'commercial_opportunity', 'key' => 'competition_inverse_weight','value' => 0.15, 'description' => 'Weight of low competition (inverted competition score)'],
            ['group' => 'commercial_opportunity', 'key' => 'growth_weight',             'value' => 0.10, 'description' => 'Weight of growth score'],
            ['group' => 'commercial_opportunity', 'key' => 'longevity_weight',          'value' => 0.05, 'description' => 'Weight of estimated trend longevity'],
            ['group' => 'commercial_opportunity', 'key' => 'brand_fit_weight',          'value' => 0.05, 'description' => 'Weight of fit with brand positioning'],
            ['group' => 'commercial_opportunity', 'key' => 'novelty_weight',            'value' => 0.05, 'description' => 'Weight of trend novelty/freshness'],
            ['group' => 'commercial_opportunity', 'key' => 'brand_fit_baseline',        'value' => 65,   'description' => 'Default brand fit score (0-100) when not overridden'],

            // ── Convergence weights ──────────────────────────────────────────
            ['group' => 'convergence', 'key' => 'google_trends_weight', 'value' => 0.25, 'description' => 'Weight of Google Trends signal'],
            ['group' => 'convergence', 'key' => 'reddit_weight',        'value' => 0.20, 'description' => 'Weight of Reddit community signal'],
            ['group' => 'convergence', 'key' => 'brand_weight',         'value' => 0.20, 'description' => 'Weight of brand adoption signal'],
            ['group' => 'convergence', 'key' => 'creator_weight',       'value' => 0.15, 'description' => 'Weight of creator/influencer signal'],
            ['group' => 'convergence', 'key' => 'ecommerce_weight',     'value' => 0.10, 'description' => 'Weight of e-commerce signal'],
            ['group' => 'convergence', 'key' => 'blog_weight',          'value' => 0.05, 'description' => 'Weight of blog/editorial signal'],
            ['group' => 'convergence', 'key' => 'other_weight',         'value' => 0.05, 'description' => 'Weight of other sources'],

            // ── Chile 60-day forecast weights (sum of positive weights = 1.0) ─
            ['group' => 'chile_forecast', 'key' => 'global_momentum_weight',       'value' => 0.20, 'description' => 'Weight of global trend momentum'],
            ['group' => 'chile_forecast', 'key' => 'chile_momentum_weight',        'value' => 0.20, 'description' => 'Weight of Chile-specific momentum (search + mentions)'],
            ['group' => 'chile_forecast', 'key' => 'cross_source_convergence_weight', 'value' => 0.12, 'description' => 'Weight of the trend convergence score'],
            ['group' => 'chile_forecast', 'key' => 'creator_adoption_weight',      'value' => 0.10, 'description' => 'Weight of Chile creator adoption'],
            ['group' => 'chile_forecast', 'key' => 'brand_adoption_weight',        'value' => 0.10, 'description' => 'Weight of Chile brand adoption'],
            ['group' => 'chile_forecast', 'key' => 'search_growth_weight',         'value' => 0.13, 'description' => 'Weight of Chile search growth velocity'],
            ['group' => 'chile_forecast', 'key' => 'historical_transfer_weight',   'value' => 0.15, 'description' => 'Weight of the historical country-transfer signal'],
            // Penalties are subtracted from the weighted sum before clamping to 0-100.
            ['group' => 'chile_forecast', 'key' => 'saturation_penalty_weight',    'value' => 0.20, 'description' => 'Penalty multiplier for global saturation score'],
            ['group' => 'chile_forecast', 'key' => 'competition_penalty_weight',   'value' => 0.10, 'description' => 'Penalty multiplier for competition score'],
            ['group' => 'chile_forecast', 'key' => 'declining_penalty_weight',     'value' => 0.25, 'description' => 'Penalty applied when global lifecycle stage is DECLINING/SATURATED/DEAD'],
            ['group' => 'chile_forecast', 'key' => 'already_peaked_chile_penalty', 'value' => 0.30, 'description' => 'Penalty applied when the trend is already PEAK/MAINSTREAM in Chile'],
            ['group' => 'chile_forecast', 'key' => 'min_transfer_sample_size',     'value' => 3,    'description' => 'Minimum historical trend_countries pairs before a transfer lag is trusted'],
            ['group' => 'chile_forecast', 'key' => 'default_transfer_lag_days',    'value' => 60,   'description' => 'Fallback assumed lag (days) when no historical transfer data exists yet'],

            // ── Signal reliability (0-100) ───────────────────────────────────
            ['group' => 'signal_reliability', 'key' => 'google_trends',  'value' => 85, 'description' => 'Reliability of Google Trends data'],
            ['group' => 'signal_reliability', 'key' => 'reddit',         'value' => 70, 'description' => 'Reliability of Reddit data'],
            ['group' => 'signal_reliability', 'key' => 'brand_official', 'value' => 80, 'description' => 'Reliability of brand official data'],
            ['group' => 'signal_reliability', 'key' => 'creator_post',   'value' => 65, 'description' => 'Reliability of creator post data'],
            ['group' => 'signal_reliability', 'key' => 'blog_editorial', 'value' => 60, 'description' => 'Reliability of blog/editorial'],
            ['group' => 'signal_reliability', 'key' => 'ecommerce',      'value' => 75, 'description' => 'Reliability of e-commerce data'],
            ['group' => 'signal_reliability', 'key' => 'forum',          'value' => 50, 'description' => 'Reliability of forum data'],
            ['group' => 'signal_reliability', 'key' => 'social_comment', 'value' => 40, 'description' => 'Reliability of social comment data'],
        ];

        foreach ($parameters as $param) {
            DB::table('scoring_parameters')->updateOrInsert(
                ['parameter_group' => $param['group'], 'parameter_key' => $param['key']],
                [
                    'parameter_value' => $param['value'],
                    'description'     => $param['description'],
                    'updated_at'      => now(),
                ]
            );
        }
    }
}
