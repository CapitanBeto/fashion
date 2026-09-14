<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class RawImage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'raw_document_id', 'source_url', 'local_path', 'sha256',
        'phash', 'dhash', 'width', 'height', 'format',
        'file_size_bytes', 'analyzed', 'analysis_result',
    ];

    protected $casts = [
        'analysis_result' => 'array',
        'analyzed'        => 'boolean',
        'created_at'      => 'datetime',
    ];

    public function rawDocument(): BelongsTo
    {
        return $this->belongsTo(RawDocument::class);
    }

    public function scopeUnanalyzed(Builder $query): Builder
    {
        return $query->where('analyzed', false);
    }

    public function scopeAnalyzed(Builder $query): Builder
    {
        return $query->where('analyzed', true);
    }

    public function getAspectRatioAttribute(): ?float
    {
        if (!$this->width || !$this->height) {
            return null;
        }
        return round($this->width / $this->height, 2);
    }

    public function getFileSizeKbAttribute(): ?float
    {
        return $this->file_size_bytes ? round($this->file_size_bytes / 1024, 1) : null;
    }
}
