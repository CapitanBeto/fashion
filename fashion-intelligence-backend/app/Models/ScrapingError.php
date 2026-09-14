<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScrapingError extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'scrape_run_id', 'url', 'error_type', 'error_message',
        'http_status', 'provider', 'retry_count',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function scrapeRun(): BelongsTo
    {
        return $this->belongsTo(ScrapeRun::class);
    }
}
