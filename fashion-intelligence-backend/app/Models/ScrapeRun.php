<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class ScrapeRun extends Model
{
    protected $fillable = [
        'uuid', 'source_id', 'source_target_id', 'experiment_name', 'provider',
        'status', 'started_at', 'finished_at', 'duration_seconds',
        'records_collected', 'records_processed', 'requests_made', 'requests_failed',
        'estimated_cost', 'actual_cost', 'error_count', 'config', 'log', 'summary',
    ];

    protected $casts = [
        'config'      => 'array',
        'log'         => 'array',
        'summary'     => 'array',
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    // pending | running | completed | failed | stopped | cancelled
    const STATUSES = ['pending', 'running', 'completed', 'failed', 'stopped', 'cancelled'];

    protected static function booted(): void
    {
        static::creating(function (ScrapeRun $run) {
            if (empty($run->uuid)) {
                $run->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function sourceTarget(): BelongsTo
    {
        return $this->belongsTo(SourceTarget::class);
    }

    public function rawDocuments(): HasMany
    {
        return $this->hasMany(RawDocument::class);
    }

    public function errors(): HasMany
    {
        return $this->hasMany(ScrapingError::class);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeForExperiment(Builder $query, string $name): Builder
    {
        return $query->where('experiment_name', $name);
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function markAsStarted(): void
    {
        $this->update(['status' => 'running', 'started_at' => now()]);
    }

    public function markAsCompleted(array $summary = []): void
    {
        $this->update([
            'status'           => 'completed',
            'finished_at'      => now(),
            'duration_seconds' => now()->diffInSeconds($this->started_at),
            'summary'          => $summary,
        ]);
    }

    public function markAsFailed(string $reason = ''): void
    {
        $this->update([
            'status'           => 'failed',
            'finished_at'      => now(),
            'duration_seconds' => $this->started_at ? now()->diffInSeconds($this->started_at) : null,
        ]);
    }

    public function appendLog(string $message, string $level = 'info'): void
    {
        $log   = $this->log ?? [];
        $log[] = ['ts' => now()->toIso8601String(), 'level' => $level, 'msg' => $message];
        $this->update(['log' => $log]);
    }

    public function getSuccessRateAttribute(): float
    {
        if ($this->requests_made === 0) {
            return 0.0;
        }
        return round(($this->requests_made - $this->requests_failed) / $this->requests_made * 100, 1);
    }
}
