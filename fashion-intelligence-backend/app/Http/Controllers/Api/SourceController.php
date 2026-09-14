<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Source;
use App\Models\SourceTarget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SourceController extends Controller
{
    /**
     * GET /api/sources
     */
    public function index(): JsonResponse
    {
        $sources = Source::with(['targets' => fn ($q) => $q->active()])
            ->orderBy('name')
            ->get()
            ->map(fn ($source) => [
                'id'               => $source->id,
                'name'             => $source->name,
                'slug'             => $source->slug,
                'type'             => $source->type,
                'active'           => $source->active,
                'reliability_score' => $source->reliability_score,
                'active_targets_count' => $source->active_targets_count,
            ]);

        return response()->json(['sources' => $sources]);
    }

    /**
     * POST /api/sources
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'              => 'required|string|max:100',
            'slug'              => 'required|string|max:100|unique:sources,slug',
            'type'              => 'required|in:' . implode(',', Source::TYPES),
            'config'            => 'nullable|array',
            'reliability_score' => 'nullable|integer|between:0,100',
            'active'            => 'boolean',
        ]);

        $source = Source::create($data);

        return response()->json(['source' => $source], 201);
    }

    /**
     * PATCH /api/sources/{source}
     */
    public function update(Request $request, Source $source): JsonResponse
    {
        $data = $request->validate([
            'name'              => 'sometimes|string|max:100',
            'config'            => 'nullable|array',
            'reliability_score' => 'nullable|integer|between:0,100',
            'active'            => 'boolean',
        ]);

        $source->update($data);

        return response()->json(['source' => $source]);
    }

    /**
     * GET /api/sources/{source}/targets
     */
    public function targets(Source $source): JsonResponse
    {
        $targets = $source->targets()
            ->with(['country', 'niche'])
            ->orderByDesc('priority')
            ->get()
            ->map(fn ($t) => [
                'id'           => $t->id,
                'target_type'  => $t->target_type,
                'target_value' => $t->target_value,
                'priority'     => $t->priority,
                'frequency'    => $t->frequency,
                'depth'        => $t->depth,
                'active'       => $t->active,
                'country'      => $t->country ? ['id' => $t->country->id, 'iso2' => $t->country->iso2] : null,
                'niche'        => $t->niche ? ['id' => $t->niche->id, 'slug' => $t->niche->slug] : null,
            ]);

        return response()->json(['targets' => $targets]);
    }

    /**
     * POST /api/sources/{source}/targets
     */
    public function addTarget(Request $request, Source $source): JsonResponse
    {
        $data = $request->validate([
            'target_type'  => 'required|in:' . implode(',', SourceTarget::TARGET_TYPES),
            'target_value' => 'required|string',
            'country_id'   => 'nullable|exists:countries,id',
            'niche_id'     => 'nullable|exists:niches,id',
            'priority'     => 'nullable|integer',
            'frequency'    => 'in:' . implode(',', SourceTarget::FREQUENCIES),
            'depth'        => 'nullable|integer|between:1,10',
            'active'       => 'boolean',
            'config'       => 'nullable|array',
        ]);

        $target = $source->targets()->create($data);

        return response()->json(['target' => $target], 201);
    }

    /**
     * PATCH /api/sources/targets/{target}
     */
    public function updateTarget(Request $request, SourceTarget $target): JsonResponse
    {
        $data = $request->validate([
            'priority'  => 'nullable|integer',
            'frequency' => 'in:' . implode(',', SourceTarget::FREQUENCIES),
            'depth'     => 'nullable|integer|between:1,10',
            'active'    => 'boolean',
            'config'    => 'nullable|array',
        ]);

        $target->update($data);

        return response()->json(['target' => $target]);
    }
}
