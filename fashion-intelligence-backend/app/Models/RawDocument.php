<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

class RawDocument extends Model
{
    protected $fillable = [
        'scrape_run_id', 'source_id', 'country_id', 'source_url', 'canonical_url',
        'document_type', 'scraped_at', 'published_at', 'author', 'title',
        'body', 'html_raw', 'html_hash', 'content_hash', 'language',
        'metadata', 'processing_status', 'is_demo',
    ];

    protected $casts = [
        'metadata'     => 'array',
        'scraped_at'   => 'datetime',
        'published_at' => 'datetime',
        'is_demo'      => 'boolean',
    ];

    // post | product | article | trend_data | editorial | lookbook
    const DOCUMENT_TYPES = ['post', 'product', 'article', 'trend_data', 'editorial', 'lookbook'];

    // pending | processing | processed | failed | skipped
    const STATUSES = ['pending', 'processing', 'processed', 'failed', 'skipped'];

    public function scrapeRun(): BelongsTo
    {
        return $this->belongsTo(ScrapeRun::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function rawPost(): HasOne
    {
        return $this->hasOne(RawPost::class);
    }

    public function rawImages(): HasMany
    {
        return $this->hasMany(RawImage::class);
    }

    public function trendMentions(): HasMany
    {
        return $this->hasMany(TrendMention::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('processing_status', 'pending');
    }

    public function scopeProcessed(Builder $query): Builder
    {
        return $query->where('processing_status', 'processed');
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('document_type', $type);
    }

    public function scopeForExperiment(Builder $query, string $name): Builder
    {
        return $query->whereHas('scrapeRun', fn ($q) => $q->where('experiment_name', $name));
    }

    public function markAsProcessing(): void
    {
        $this->update(['processing_status' => 'processing']);
    }

    public function markAsProcessed(): void
    {
        $this->update(['processing_status' => 'processed']);
    }

    public function markAsFailed(): void
    {
        $this->update(['processing_status' => 'failed']);
    }

    public function getWordCountAttribute(): int
    {
        return str_word_count($this->body ?? '');
    }
}
