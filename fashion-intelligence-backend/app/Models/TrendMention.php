<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class TrendMention extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'trend_id', 'raw_document_id', 'source_id', 'country_id',
        'signal_type', 'mention_text', 'sentiment', 'confidence', 'mentioned_at',
    ];

    protected $casts = [
        'sentiment'    => 'float',
        'confidence'   => 'float',
        'mentioned_at' => 'datetime',
        'created_at'   => 'datetime',
    ];

    // search | social | community | creator | brand | product | sales_proxy | media
    const SIGNAL_TYPES = ['search', 'social', 'community', 'creator', 'brand', 'product', 'sales_proxy', 'media'];

    public function trend(): BelongsTo
    {
        return $this->belongsTo(Trend::class);
    }

    public function rawDocument(): BelongsTo
    {
        return $this->belongsTo(RawDocument::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function scopeBySignalType(Builder $query, string $type): Builder
    {
        return $query->where('signal_type', $type);
    }

    public function scopePositive(Builder $query, float $threshold = 0.1): Builder
    {
        return $query->where('sentiment', '>=', $threshold);
    }

    public function scopeNegative(Builder $query, float $threshold = -0.1): Builder
    {
        return $query->where('sentiment', '<=', $threshold);
    }
}
