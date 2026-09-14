<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Same class of bug as commercial_opportunities (trend_id, country_id):
        // the existing UNIQUE(trend_id, country_id, snapshot_date) treats every
        // NULL country_id as distinct, so the new "global" snapshot upsert in
        // experiments.py (country_id = NULL, used for mention_count) would
        // insert a fresh duplicate row every single experiment run instead of
        // updating today's row.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE trend_snapshots DROP CONSTRAINT IF EXISTS trend_snapshots_trend_id_country_id_snapshot_date_unique');
            DB::statement(
                'ALTER TABLE trend_snapshots ADD CONSTRAINT trend_snapshots_trend_id_country_id_snapshot_date_unique '
                . 'UNIQUE NULLS NOT DISTINCT (trend_id, country_id, snapshot_date)'
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE trend_snapshots DROP CONSTRAINT IF EXISTS trend_snapshots_trend_id_country_id_snapshot_date_unique');
            Schema::table('trend_snapshots', function ($table) {
                $table->unique(['trend_id', 'country_id', 'snapshot_date'], 'trend_snapshots_trend_id_country_id_snapshot_date_unique');
            });
        }
    }
};
