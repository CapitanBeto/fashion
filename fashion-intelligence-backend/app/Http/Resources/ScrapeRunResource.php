<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScrapeRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'uuid'            => $this->uuid,
            'experiment_name' => $this->experiment_name,
            'provider'        => $this->provider,
            'status'          => $this->status,

            'source' => $this->whenLoaded('source', fn () => [
                'id'   => $this->source->id,
                'name' => $this->source->name,
                'type' => $this->source->type,
            ]),

            'stats' => [
                'records_collected'  => $this->records_collected,
                'records_processed'  => $this->records_processed,
                'requests_made'      => $this->requests_made,
                'requests_failed'    => $this->requests_failed,
                'success_rate'       => $this->success_rate,
                'error_count'        => $this->error_count,
                'duration_seconds'   => $this->duration_seconds,
            ],

            'cost' => [
                'estimated' => $this->estimated_cost,
                'actual'    => $this->actual_cost,
            ],

            'summary'     => $this->summary,
            'started_at'  => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'created_at'  => $this->created_at->toIso8601String(),
        ];
    }
}
