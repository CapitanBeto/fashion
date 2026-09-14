<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Color extends Model
{
    protected $fillable = [
        'name', 'hex', 'rgb', 'hsl', 'color_family', 'temperature',
    ];

    protected $casts = [
        'rgb' => 'array',
        'hsl' => 'array',
    ];

    public function trends(): BelongsToMany
    {
        return $this->belongsToMany(Trend::class, 'trend_colors')
            ->withPivot(['frequency', 'percentage']);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_colors')
            ->withPivot(['percentage_area', 'is_dominant']);
    }

    public function scopeByFamily($query, string $family)
    {
        return $query->where('color_family', $family);
    }

    public function scopeByTemperature($query, string $temperature)
    {
        return $query->where('temperature', $temperature);
    }
}
