<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Product extends Model
{
    protected $fillable = [
        'brand_id', 'country_id', 'name', 'slug', 'description',
        'category', 'sub_category', 'source_url', 'image_urls',
        'price', 'currency', 'availability', 'is_sold_out',
        'has_been_restocked', 'launch_date', 'metadata', 'is_demo',
    ];

    protected $casts = [
        'image_urls'         => 'array',
        'metadata'           => 'array',
        'is_sold_out'        => 'boolean',
        'has_been_restocked' => 'boolean',
        'is_demo'            => 'boolean',
        'launch_date'        => 'date',
        'price'              => 'decimal:2',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function trends(): BelongsToMany
    {
        return $this->belongsToMany(Trend::class, 'product_trends')
            ->withPivot(['confidence']);
    }

    public function colors(): BelongsToMany
    {
        return $this->belongsToMany(Color::class, 'product_colors')
            ->withPivot(['percentage_area', 'is_dominant']);
    }

    public function silhouettes(): BelongsToMany
    {
        return $this->belongsToMany(Silhouette::class, 'product_silhouettes')
            ->withPivot(['confidence']);
    }

    public function graphics(): BelongsToMany
    {
        return $this->belongsToMany(Graphic::class, 'product_graphics')
            ->withPivot(['confidence']);
    }

    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(Material::class, 'product_materials')
            ->withPivot(['is_primary']);
    }

    public function priceObservations(): HasMany
    {
        return $this->hasMany(PriceObservation::class);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('is_sold_out', false)
            ->where('availability', 'in_stock');
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeNotDemo(Builder $query): Builder
    {
        return $query->where('is_demo', false);
    }

    public function getDominantColorAttribute(): ?Color
    {
        return $this->colors()->wherePivot('is_dominant', true)->first();
    }

    public function getPrimaryMaterialAttribute(): ?Material
    {
        return $this->materials()->wherePivot('is_primary', true)->first();
    }
}
