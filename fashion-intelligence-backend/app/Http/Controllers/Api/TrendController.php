<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrendResource;
use App\Http\Resources\CommercialOpportunityResource;
use App\Models\Trend;
use App\Models\CommercialOpportunity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TrendController extends Controller
{
    /**
     * GET /api/trends
     * List trends with filtering, sorting, pagination.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Trend::query()
            ->with(['niche', 'primaryCountry'])
            ->withCount(['brands', 'creators', 'products']);

        // Filters
        if ($request->filled('lifecycle_stage')) {
            $query->where('lifecycle_stage', strtoupper($request->lifecycle_stage));
        }

        if ($request->filled('niche')) {
            $query->forNiche($request->niche);
        }

        if ($request->filled('country')) {
            $query->forCountry($request->country);
        }

        if ($request->filled('min_opportunity')) {
            $query->highOpportunity((int) $request->min_opportunity);
        }

        if ($request->boolean('rising')) {
            $query->rising();
        }

        if ($request->boolean('emergent')) {
            $query->emergent();
        }

        if ($request->boolean('hide_demo')) {
            $query->notDemo();
        }

        if ($request->filled('min_confidence')) {
            $query->withHighConfidence((int) $request->min_confidence);
        }

        // Search by name or keyword
        if ($request->filled('q')) {
            $term = $request->q;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'ilike', "%{$term}%")
                  ->orWhereJsonContains('keywords', strtolower($term));
            });
        }

        // Sorting
        $sortBy  = $request->get('sort_by', 'commercial_opportunity_score');
        $sortDir = $request->get('sort_dir', 'desc');

        $allowedSorts = [
            'commercial_opportunity_score', 'momentum_score', 'growth_score',
            'confidence', 'first_seen_at', 'last_updated_at', 'death_probability',
            'creator_score', 'brand_score',
        ];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $perPage = min((int) $request->get('per_page', 20), 100);

        return TrendResource::collection($query->paginate($perPage));
    }

    /**
     * GET /api/trends/{trend}
     * Full detail for a single trend, including snapshots and opportunities.
     */
    public function show(Trend $trend): TrendResource
    {
        $trend->load([
            'niche',
            'primaryCountry',
            'snapshots',
            'countries',
            'colors',
            'silhouettes',
            'trendGraphics',
            'brands' => fn ($q) => $q->limit(10),
            'creators' => fn ($q) => $q->limit(10),
            'opportunities' => fn ($q) => $q->with('country')->orderByDesc('opportunity_score')->limit(5),
            'predictions' => fn ($q) => $q->latest('prediction_date')->limit(1),
        ]);

        $trend->loadCount(['brands', 'creators', 'products', 'mentions']);

        return new TrendResource($trend);
    }

    /**
     * GET /api/trends/{trend}/snapshots
     * Time-series data for charts.
     */
    public function snapshots(Request $request, Trend $trend): JsonResponse
    {
        $days = min((int) $request->get('days', 90), 365);
        $countryId = $request->get('country_id');

        $query = $trend->snapshots()
            ->where('snapshot_date', '>=', now()->subDays($days));

        if ($countryId) {
            $query->where('country_id', $countryId);
        } else {
            $query->whereNull('country_id');
        }

        $snapshots = $query->orderBy('snapshot_date')->get([
            'snapshot_date', 'momentum_score', 'search_interest',
            'mention_count', 'brand_count', 'creator_count',
            'growth_velocity', 'growth_acceleration',
        ]);

        return response()->json([
            'trend_id'  => $trend->id,
            'days'      => $days,
            'snapshots' => $snapshots,
        ]);
    }

    /**
     * GET /api/trends/{trend}/opportunities
     * Commercial opportunities for a trend.
     */
    public function opportunities(Trend $trend): AnonymousResourceCollection
    {
        $opportunities = $trend->opportunities()
            ->with('country')
            ->orderByDesc('opportunity_score')
            ->get();

        return CommercialOpportunityResource::collection($opportunities);
    }

    /**
     * GET /api/trends/rising
     * Shortcut: top rising trends.
     */
    public function rising(): AnonymousResourceCollection
    {
        $trends = Trend::rising()
            ->notDemo()
            ->withHighConfidence(30)
            ->with(['niche', 'primaryCountry'])
            ->withCount(['brands', 'creators'])
            ->limit(20)
            ->get();

        return TrendResource::collection($trends);
    }

    /**
     * GET /api/trends/opportunities
     * Top commercial opportunities across all trends.
     */
    public function topOpportunities(Request $request): AnonymousResourceCollection
    {
        $minScore = (int) $request->get('min_score', 50);
        $countryId = $request->get('country_id');

        $query = CommercialOpportunity::with(['trend.niche', 'country'])
            ->highScore($minScore)
            ->highConfidence(30);

        if ($countryId) {
            $query->where('country_id', $countryId);
        }

        $opportunities = $query->limit(30)->get();

        return CommercialOpportunityResource::collection($opportunities);
    }
}
