<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Silhouette extends Model
{
    protected $fillable = ['name', 'slug', 'description'];

    public function trends(): BelongsToMany
    {
        return $this->belongsToMany(Trend::class, 'trend_silhouettes')
            ->withPivot(['frequency', 'percentage']);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_silhouettes')
            ->withPivot(['confidence']);
    }
}
