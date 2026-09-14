<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommercialOpportunityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'trend_id'       => $this->trend_id,
            'country_id'     => $this->country_id,

            'scores' => [
                'opportunity'  => $this->opportunity_score,
                'demand'       => $this->demand_score,
                'competition'  => $this->competition_score,
                'momentum'     => $this->momentum_score,
                'timing'       => $this->timing_score,
                'confidence'   => $this->confidence,
            ],

            'product_concept' => [
                'name'            => $this->suggested_product_name,
                'silhouette'      => $this->suggested_silhouette,
                'colors'          => $this->suggested_colors,
                'graphics'        => $this->suggested_graphics,
                'price_min'       => $this->suggested_price_min,
                'price_max'       => $this->suggested_price_max,
                'price_midpoint'  => $this->price_midpoint,
                'target_audience' => $this->suggested_target_audience,
            ],

            'analysis' => [
                'reasoning' => $this->reasoning,
                'risks'     => $this->risks,
                'evidence'  => $this->evidence,
            ],

            'trend'   => $this->whenLoaded('trend', fn () => new TrendResource($this->trend)),
            'country' => $this->whenLoaded('country', fn () => [
                'id'   => $this->country->id,
                'name' => $this->country->name,
                'iso2' => $this->country->iso2,
            ]),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
