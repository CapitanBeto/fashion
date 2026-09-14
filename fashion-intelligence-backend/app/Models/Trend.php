<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\LifecycleStage;

class Trend extends Model
{
    protected $fillable = [
        'name', 'slug', 'niche_id', 'primary_country_id',
        'detection_method', 'keywords', 'description',
        'momentum_score', 'growth_score', 'search_score',
        'creator_score', 'brand_score', 'competition_score',
        'commercial_opportunity_score', 'convergence_score',
        'saturation_score', 'virality_score', 'desirability_score',
        'commercial_strength_score', 'death_probability', 'confidence',
        'lifecycle_stage', 'stage_confidence', 'stage_started_at',
        'estimated_peak', 'estimated_decline', 'estimated_death',
        'first_seen_at', 'last_updated_at', 'is_demo', 'metadata',
    ];

    protected $casts = [
        'keywords'          => 'array',
        'metadata'          => 'array',
        'stage_started_at'  => 'datetime',
        'estimated_peak'    => 'datetime',
        'estimated_decline' => 'datetime',
        'estimated_death'   => 'datetime',
        'first_seen_at'     => 'datetime',
        'last_updated_at'   => 'datetime',
        'is_demo'           => 'boolean',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function niche(): BelongsTo
    {
        return $this->belongsTo(Niche::class);
    }

    public function primaryCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'primary_country_id');
    }

    public function mentions(): HasMany
    {
        return $this->hasMany(TrendMention::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(TrendSnapshot::class)->orderBy('snapshot_date');
    }

    public function lifecycleHistory(): HasMany
    {
        return $this->hasMany(TrendLifecycle::class)->orderBy('started_at');
    }

    public function brands(): BelongsToMany
    {
        // brand_trends only has created_at (no updated_at), so withTimestamps()
        // (which requires both) is not usable here — confirmed by the 500 it
        // caused on every trend detail page load.
        return $this->belongsToMany(Brand::class, 'brand_trends')
            ->withPivot(['adoption_date', 'product_count', 'confidence', 'created_at']);
    }

    public function creators(): BelongsToMany
    {
        return $this->belongsToMany(Creator::class, 'creator_trends')
            ->withPivot(['first_seen_at', 'frequency', 'confidence']);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_trends')
            ->withPivot(['confidence']);
    }

    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'trend_countries')
            ->withPivot(['strength', 'stage', 'first_seen_at']);
    }

    public function colors(): BelongsToMany
    {
        return $this->belongsToMany(Color::class, 'trend_colors')
            ->withPivot(['frequency', 'percentage']);
    }

    public function silhouettes(): BelongsToMany
    {
        return $this->belongsToMany(Silhouette::class, 'trend_silhouettes')
            ->withPivot(['frequency', 'percentage']);
    }

    public function trendGraphics(): BelongsToMany
    {
        return $this->belongsToMany(Graphic::class, 'trend_graphics')
            ->withPivot(['frequency', 'percentage']);
    }

    public function searchMetrics(): HasMany
    {
        return $this->hasMany(SearchMetric::class);
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(TrendPrediction::class);
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(CommercialOpportunity::class);
    }

    public function competitors(): HasMany
    {
        return $this->hasMany(Competitor::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeRising(Builder $query): Builder
    {
        return $query->whereIn('lifecycle_stage', [
            'EMERGING', 'EARLY_ADOPTION', 'ACCELERATING'
        ])->orderByDesc('momentum_score');
    }

    public function scopeDying(Builder $query): Builder
    {
        return $query->whereIn('lifecycle_stage', [
            'DECLINING', 'SATURATED', 'DEAD'
        ])->orderByDesc('death_probability');
    }

    public function scopeHighOpportunity(Builder $query, int $minScore = 60): Builder
    {
        return $query->where('commercial_opportunity_score', '>=', $minScore)
            ->orderByDesc('commercial_opportunity_score');
    }

    public function scopeEmergent(Builder $query): Builder
    {
        return $query->where('detection_method', 'emergent')
            ->where('lifecycle_stage', 'EMERGING');
    }

    public function scopeForCountry(Builder $query, string|int $countryId): Builder
    {
        if (is_string($countryId) && strlen($countryId) <= 3) {
            return $query->whereHas('countries', function ($q) use ($countryId) {
                $q->where('iso2', strtoupper($countryId));
            });
        }

        return $query->whereHas('countries', function ($q) use ($countryId) {
            $q->where('countries.id', $countryId);
        });
    }

    public function scopeForNiche(Builder $query, string|int $niche): Builder
    {
        if (is_string($niche)) {
            return $query->whereHas('niche', function ($q) use ($niche) {
                $q->where('slug', $niche);
            });
        }

        return $query->where('niche_id', $niche);
    }

    public function scopeWithHighConfidence(Builder $query, int $min = 50): Builder
    {
        return $query->where('confidence', '>=', $min);
    }

    public function scopeNotDemo(Builder $query): Builder
    {
        return $query->where('is_demo', false);
    }

    // ─── Computed Properties ──────────────────────────────────────────────────

    public function getIsRisingAttribute(): bool
    {
        return in_array($this->lifecycle_stage, [
            'EMERGING', 'EARLY_ADOPTION', 'ACCELERATING', 'MAINSTREAM'
        ]);
    }

    public function getIsDyingAttribute(): bool
    {
        return in_array($this->lifecycle_stage, ['DECLINING', 'SATURATED', 'DEAD']);
    }

    public function getLifecycleLabelAttribute(): string
    {
        return match ($this->lifecycle_stage) {
            'EMERGING'       => 'Emerging',
            'EARLY_ADOPTION' => 'Early Adoption',
            'ACCELERATING'   => 'Accelerating',
            'MAINSTREAM'     => 'Mainstream',
            'PEAK'           => 'Peak',
            'SATURATED'      => 'Saturated',
            'DECLINING'      => 'Declining',
            'DEAD'           => 'Dead',
            default          => 'Unknown',
        };
    }

    public function getLifecycleColorAttribute(): string
    {
        return match ($this->lifecycle_stage) {
            'EMERGING'       => 'blue',
            'EARLY_ADOPTION' => 'cyan',
            'ACCELERATING'   => 'green',
            'MAINSTREAM'     => 'yellow',
            'PEAK'           => 'orange',
            'SATURATED'      => 'amber',
            'DECLINING'      => 'red',
            'DEAD'           => 'gray',
            default          => 'neutral',
        };
    }

    public function getLatestSnapshotAttribute(): ?TrendSnapshot
    {
        return $this->snapshots()->latest('snapshot_date')->first();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function recordSnapshot(array $data, ?int $countryId = null): TrendSnapshot
    {
        return $this->snapshots()->updateOrCreate(
            [
                'trend_id'      => $this->id,
                'country_id'    => $countryId,
                'snapshot_date' => now()->toDateString(),
            ],
            $data
        );
    }
}
