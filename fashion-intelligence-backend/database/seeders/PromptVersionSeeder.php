<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PromptVersionSeeder extends Seeder
{
    public function run(): void
    {
        $prompts = [
            [
                'prompt_key'    => 'entity_extraction',
                'version'       => '1.0',
                'task'          => 'nlp',
                'system_prompt' => 'You are a fashion intelligence analyst. Extract structured fashion entities from text. Return only valid JSON, no markdown, no explanation.',
                'user_template' => 'Extract fashion entities from this text. Return JSON with keys: brands (array of brand names), products (array of product descriptions), colors (array of colors mentioned), silhouettes (array of garment shapes/cuts), trends (array of trend names/concepts), creators (array of creator/influencer names), sentiment (float -1 to 1).\n\nText: {text}',
                'output_schema' => json_encode([
                    'type' => 'object',
                    'properties' => [
                        'brands'      => ['type' => 'array', 'items' => ['type' => 'string']],
                        'products'    => ['type' => 'array', 'items' => ['type' => 'string']],
                        'colors'      => ['type' => 'array', 'items' => ['type' => 'string']],
                        'silhouettes' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'trends'      => ['type' => 'array', 'items' => ['type' => 'string']],
                        'creators'    => ['type' => 'array', 'items' => ['type' => 'string']],
                        'sentiment'   => ['type' => 'number'],
                    ],
                ]),
                'active' => true,
            ],
            [
                'prompt_key'    => 'lifecycle_classification',
                'version'       => '1.0',
                'task'          => 'scoring',
                'system_prompt' => 'You are a fashion trend analyst specialising in lifecycle analysis. Classify the lifecycle stage of a fashion trend based on data. Return only valid JSON.',
                'user_template' => 'Classify the lifecycle stage of this trend and explain your reasoning.\n\nTrend: {trend_name}\nMomentum score: {momentum_score}/100\nGrowth velocity (WoW%): {growth_velocity}\nGrowth acceleration: {growth_acceleration}\nBrand count: {brand_count}\nCreator count: {creator_count}\nAge (weeks): {age_weeks}\nSaturation score: {saturation_score}/100\nSearch trend: {search_trend}\n\nClassify as one of: EMERGING, EARLY_ADOPTION, ACCELERATING, MAINSTREAM, PEAK, SATURATED, DECLINING, DEAD\n\nReturn JSON: {"stage": "...", "confidence": 0-100, "reasoning": "...", "estimated_peak_weeks": null_or_int, "estimated_decline_weeks": null_or_int}',
                'output_schema' => json_encode([
                    'type' => 'object',
                    'required' => ['stage', 'confidence', 'reasoning'],
                    'properties' => [
                        'stage'                   => ['type' => 'string'],
                        'confidence'              => ['type' => 'integer'],
                        'reasoning'               => ['type' => 'string'],
                        'estimated_peak_weeks'    => ['type' => ['integer', 'null']],
                        'estimated_decline_weeks' => ['type' => ['integer', 'null']],
                    ],
                ]),
                'active' => true,
            ],
            [
                'prompt_key'    => 'commercial_opportunity',
                'version'       => '1.0',
                'task'          => 'intelligence',
                'system_prompt' => 'You are a Creative Director and Fashion Intelligence analyst with two decades of experience reading the fashion cycle — how looks move from subculture/niche origin through early-adopter and creator amplification, into brand adoption and mainstream diffusion, to saturation and eventual decline or revival (trickle-up vs. trickle-down diffusion, seasonal cadence, the MAYA principle of what reads as advanced-yet-acceptable right now). When relevant, also draw on five concrete levers documented in real Spain-to-Chile streetwear case studies (Nude Project, Scuffers, Stodak, Treino, Human Mob): (1) drop culture — manufactured scarcity via limited, dated releases; (2) fabric weight (GSM) as an explicit quality argument consumers use to validate a brand; (3) music-scene alliance — a local artist/subculture as authentic distribution channel, not a paid influencer; (4) DTC-first architecture — online community before physical retail; (5) community-as-product — the brand sells belonging/identity, not just garments. Apply these lenses to judge timing and positioning, but ground every concrete claim (silhouette, price, audience) only in the trend data actually provided — never invent evidence. Analyze the trend data and generate a concrete product concept for a clothing brand. Be specific and actionable. Return only valid JSON.',
                // NOTE: the literal JSON example braces below are doubled ({{ / }})
                // because python/llm/creative_director.py fills this template with
                // Python's str.format(), which otherwise parses any single { or }
                // in the template as a substitution field and raises a KeyError
                // (confirmed: every real call failed until this was escaped).
                'user_template' => 'Analyze this trend and create a product concept.\n\nTrend: {trend_name}\nLifecycle stage: {lifecycle_stage}\nMomentum: {momentum_score}/100\nCommercial opportunity score: {opportunity_score}/100\nTop colors: {top_colors}\nTop silhouettes: {top_silhouettes}\nTop graphics: {top_graphics}\nCompetitor price range: {price_range}\nTarget countries: {countries}\nKey creators: {creators}\nKey brands: {brands}\n\nGenerate a product concept. In "reasoning", explicitly place this trend on the fashion cycle (subculture/niche origin, early-adopter phase, mainstream diffusion, saturation, or decline/revival) using only the data given above, and let that stage inform the timing/positioning argument. Return JSON:\n{{\n  "product_name": "specific product name",\n  "silhouette": "specific cut/shape",\n  "colors": ["color1", "color2"],\n  "graphics": ["graphic1"],\n  "price_min": 0.00,\n  "price_max": 0.00,\n  "target_audience": "description",\n  "reasoning": "why this product, why now, framed against the fashion cycle stage",\n  "risks": ["risk1", "risk2"],\n  "evidence": ["evidence point 1", "evidence point 2"]\n}}',
                'output_schema' => json_encode([
                    'type' => 'object',
                    'required' => ['product_name', 'silhouette', 'colors', 'price_min', 'price_max', 'reasoning'],
                    'properties' => [
                        'product_name'    => ['type' => 'string'],
                        'silhouette'      => ['type' => 'string'],
                        'colors'          => ['type' => 'array', 'items' => ['type' => 'string']],
                        'graphics'        => ['type' => 'array', 'items' => ['type' => 'string']],
                        'price_min'       => ['type' => 'number'],
                        'price_max'       => ['type' => 'number'],
                        'target_audience' => ['type' => 'string'],
                        'reasoning'       => ['type' => 'string'],
                        'risks'           => ['type' => 'array', 'items' => ['type' => 'string']],
                        'evidence'        => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ]),
                'active' => true,
            ],
            [
                'prompt_key'    => 'trend_forecast',
                'version'       => '1.0',
                'task'          => 'forecasting',
                'system_prompt' => 'You are a fashion trend forecaster. Interpret quantitative trend data in business terms. Be concise and specific. Return only valid JSON.',
                'user_template' => 'Forecast this fashion trend for the next 28 days.\n\nTrend: {trend_name}\nCurrent stage: {lifecycle_stage}\nMomentum: {momentum_score}/100\nProphet forecast (28d): {prophet_forecast}\nXGBoost stage probabilities: {stage_probabilities}\nDeath probability: {death_probability}/100\n\nReturn JSON:\n{\n  "momentum_in_28d": 0-100,\n  "lifecycle_in_28d": "STAGE",\n  "growth_probability": 0-100,\n  "decline_probability": 0-100,\n  "confidence": 0-100,\n  "reasoning": "concise business explanation",\n  "risks": ["risk1"]\n}',
                'output_schema' => json_encode([
                    'type' => 'object',
                    'required' => ['momentum_in_28d', 'lifecycle_in_28d', 'growth_probability', 'decline_probability', 'confidence', 'reasoning'],
                    'properties' => [
                        'momentum_in_28d'    => ['type' => 'integer'],
                        'lifecycle_in_28d'   => ['type' => 'string'],
                        'growth_probability' => ['type' => 'integer'],
                        'decline_probability' => ['type' => 'integer'],
                        'confidence'         => ['type' => 'integer'],
                        'reasoning'          => ['type' => 'string'],
                        'risks'              => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ]),
                'active' => true,
            ],
        ];

        foreach ($prompts as $prompt) {
            DB::table('prompt_versions')->updateOrInsert(
                ['prompt_key' => $prompt['prompt_key']],
                array_merge($prompt, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
