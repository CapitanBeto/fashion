<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The "Chile Transfer Model": how long a trend typically takes to travel
        // from one country to another. Computed from real trend_countries.first_seen_at
        // pairs by python/forecasting/chile_transfer.py — never hand-entered.
        Schema::create('country_transfer_lags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_country_id')->constrained('countries')->cascadeOnDelete();
            $table->foreignId('target_country_id')->constrained('countries')->cascadeOnDelete();
            $table->foreignId('niche_id')->nullable()->constrained()->nullOnDelete();

            $table->integer('sample_size')->default(0);
            $table->integer('median_lag_days')->nullable();
            $table->decimal('avg_lag_days', 8, 2)->nullable();
            $table->integer('min_lag_days')->nullable();
            $table->integer('max_lag_days')->nullable();
            // 0-100, driven by sample_size — low/zero when there isn't enough
            // history yet, so the forecast can honestly say "insufficient data".
            $table->integer('confidence')->default(0);

            $table->timestamp('calculated_at')->useCurrent();
            $table->timestamps();
        });

        // NULLS NOT DISTINCT so the "all niches" row (niche_id = NULL) per country
        // pair upserts correctly instead of accumulating duplicates — same fix
        // as commercial_opportunities.trend_id/country_id.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE country_transfer_lags ADD CONSTRAINT country_transfer_lags_pair_niche_unique '
                . 'UNIQUE NULLS NOT DISTINCT (source_country_id, target_country_id, niche_id)'
            );
        } else {
            Schema::table('country_transfer_lags', function (Blueprint $table) {
                $table->unique(['source_country_id', 'target_country_id', 'niche_id'], 'country_transfer_lags_pair_niche_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('country_transfer_lags');
    }
};
