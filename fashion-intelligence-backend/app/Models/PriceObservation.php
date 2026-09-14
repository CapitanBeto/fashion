<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceObservation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'product_id', 'source_url', 'price', 'currency', 'country_id',
        'price_position', 'is_sale', 'original_price', 'discount_percentage',
    ];

    protected $casts = [
        'price'               => 'decimal:2',
        'original_price'      => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'is_sale'             => 'boolean',
        'observed_at'         => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function getSavingsAttribute(): ?float
    {
        if ($this->is_sale && $this->original_price) {
            return round($this->original_price - $this->price, 2);
        }
        return null;
    }
}
