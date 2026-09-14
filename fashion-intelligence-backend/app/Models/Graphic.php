<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Graphic extends Model
{
    protected $fillable = ['name', 'slug', 'placement', 'description'];

    public function trends(): BelongsToMany
    {
        return $this->belongsToMany(Trend::class, 'trend_graphics')
            ->withPivot(['frequency', 'percentage']);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_graphics')
            ->withPivot(['confidence']);
    }
}
