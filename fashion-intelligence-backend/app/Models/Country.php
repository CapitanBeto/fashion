<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;

class Country extends Model
{
    protected $fillable = [
        'name', 'iso2', 'iso3', 'language', 'currency', 'priority', 'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class);
    }

    public function creators(): HasMany
    {
        return $this->hasMany(Creator::class);
    }

    public function trends(): BelongsToMany
    {
        return $this->belongsToMany(Trend::class, 'trend_countries')
            ->withPivot(['strength', 'stage', 'first_seen_at']);
    }

    public function rawDocuments(): HasMany
    {
        return $this->hasMany(RawDocument::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true)->orderBy('priority', 'desc');
    }

    public function getIso2Attribute(string $value): string
    {
        return strtoupper($value);
    }
}
