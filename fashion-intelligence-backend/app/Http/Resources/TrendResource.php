<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrendResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'   => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,

            // Classification
            'niche'           => $this->whenLoaded('niche', fn () => [
                'id'   => $this->niche->id,
                'name' => $this->niche->name,
                'slug' => $this->niche->slug,
            ]),
            'primary_country' => $this->whenLoaded('primaryCountry', fn () => [
                'id'   => $this->primaryCountry->id,
                'name' => $this->primaryCountry->name,
                'iso2' => $this->primaryCountry->iso2,
            ]),

            // Lifecycle
            'lifecycle_stage'   => $this->lifecycle_stage,
            'lifecycle_label'   => $this->lifecycle_label,
            'lifecycle_color'   => $this->lifecycle_color,
            'stage_confidence'  => $this->stage_confidence,
            'stage_started_at'  => $this->stage_started_at?->toDateString(),
            'detection_method'  => $this->detection_method,
            'is_rising'         => $this->is_rising,
            'is_dying'          => $this->is_dying,

            // Scores (all 0-100)
            'scores' => [
                'momentum'              => $this->momentum_score,
                'growth'                => $this->growth_score,
                'search'                => $this->search_score,
                'creator'               => $this->creator_score,
                'brand'                 => $this->brand_score,
                'competition'           => $this->competition_score,
                'commercial_opportunity' => $this->commercial_opportunity_score,
                'convergence'           => $this->convergence_score,
                'saturation'            => $this->saturation_score,
                'virality'              => $this->virality_score,
                'desirability'          => $this->desirability_score,
                'confidence'            => $this->confidence,
                'death_probability'     => $this->death_probability,
            ],

            // Forecasts
            'estimated_peak'    => $this->estimated_peak?->toDateString(),
            'estimated_decline' => $this->estimated_decline?->toDateString(),
            'estimated_death'   => $this->estimated_death?->toDateString(),

            // Metadata
            'keywords'       => $this->keywords,
            'description'    => $this->description,
            'first_seen_at'  => $this->first_seen_at?->toDateString(),
            'last_updated_at' => $this->last_updated_at?->toDateString(),

            // Counts (loaded via withCount)
            'brands_count'   => $this->whenCounted('brands'),
            'creators_count' => $this->whenCounted('creators'),
            'products_count' => $this->whenCounted('products'),

            // Related (when loaded)
            'latest_snapshot'  => $this->whenLoaded('snapshots', function () {
                $snap = $this->snapshots->last();
                return $snap ? [
                    'date'               => $snap->snapshot_date->toDateString(),
                    'momentum_score'     => $snap->momentum_score,
                    'mention_count'      => $snap->mention_count,
                    'search_interest'    => $snap->search_interest,
                    'growth_velocity'    => $snap->growth_velocity,
                    'growth_acceleration' => $snap->growth_acceleration,
                ] : null;
            }),

            'top_opportunity' => $this->whenLoaded('opportunities', function () {
                $opp = $this->opportunities->sortByDesc('opportunity_score')->first();
                return $opp ? new CommercialOpportunityResource($opp) : null;
            }),

            'countries' => $this->whenLoaded('countries', fn () =>
                $this->countries->map(fn ($c) => [
                    'id'       => $c->id,
                    'name'     => $c->name,
                    'iso2'     => $c->iso2,
                    'strength' => $c->pivot->strength,
                    'stage'    => $c->pivot->stage,
                ])
            ),

            'colors' => $this->whenLoaded('colors', fn () =>
                $this->colors->map(fn ($c) => [
                    'id'         => $c->id,
                    'name'       => $c->name,
                    'hex'        => $c->hex,
                    'family'     => $c->color_family,
                    'frequency'  => $c->pivot->frequency,
                    'percentage' => $c->pivot->percentage,
                ])
            ),

            'silhouettes' => $this->whenLoaded('silhouettes', fn () =>
                $this->silhouettes->map(fn ($s) => [
                    'id'         => $s->id,
                    'name'       => $s->name,
                    'frequency'  => $s->pivot->frequency,
                    'percentage' => $s->pivot->percentage,
                ])
            ),
        ];
    }
}
