<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Niche extends Model
{
    protected $fillable = [
        'name', 'slug', 'keywords', 'excluded_keywords', 'priority', 'active', 'metadata',
    ];

    protected $casts = [
        'keywords'          => 'array',
        'excluded_keywords' => 'array',
        'metadata'          => 'array',
        'active'            => 'boolean',
    ];

    public function trends(): HasMany
    {
        return $this->hasMany(Trend::class);
    }

    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class);
    }

    public function creators(): HasMany
    {
        return $this->hasMany(Creator::class);
    }

    public function sourceTargets(): HasMany
    {
        return $this->hasMany(SourceTarget::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true)->orderBy('priority', 'desc');
    }
}
