<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Brand extends Model
{
    protected $fillable = [
        'name', 'slug', 'aliases', 'country_id', 'niche_id',
        'website', 'social_urls', 'category', 'price_position',
        'estimated_scale', 'active', 'confidence', 'entity_hash', 'metadata', 'is_demo',
    ];

    protected $casts = [
        'aliases'     => 'array',
        'social_urls' => 'array',
        'metadata'    => 'array',
        'active'      => 'boolean',
        'is_demo'     => 'boolean',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function niche(): BelongsTo
    {
        return $this->belongsTo(Niche::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function trends(): BelongsToMany
    {
        return $this->belongsToMany(Trend::class, 'brand_trends')
            ->withPivot(['adoption_date', 'product_count', 'confidence']);
    }

    public function competitors(): HasMany
    {
        return $this->hasMany(Competitor::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeByPricePosition(Builder $query, string $position): Builder
    {
        return $query->where('price_position', $position);
    }

    public function scopeNotDemo(Builder $query): Builder
    {
        return $query->where('is_demo', false);
    }
}
