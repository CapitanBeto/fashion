<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The plain UNIQUE(trend_id, country_id) added in the previous migration
        // treats every NULL country_id as distinct from every other NULL (standard
        // SQL semantics) — but python/llm/creative_director.py always upserts with
        // country_id = NULL for the current (country-agnostic) MVP pipeline, so
        // ON CONFLICT never actually matched an existing row: each experiment
        // re-run kept INSERTing a new commercial_opportunities row instead of
        // updating the one already there (confirmed by direct testing).
        // PostgreSQL 15+ supports NULLS NOT DISTINCT to fix exactly this.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE commercial_opportunities DROP CONSTRAINT IF EXISTS commercial_opportunities_trend_country_unique');
            DB::statement('ALTER TABLE commercial_opportunities ADD CONSTRAINT commercial_opportunities_trend_country_unique UNIQUE NULLS NOT DISTINCT (trend_id, country_id)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE commercial_opportunities DROP CONSTRAINT IF EXISTS commercial_opportunities_trend_country_unique');
            Schema::table('commercial_opportunities', function ($table) {
                $table->unique(['trend_id', 'country_id'], 'commercial_opportunities_trend_country_unique');
            });
        }
    }
};
