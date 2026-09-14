<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // python/llm/creative_director.py upserts with
        // ON CONFLICT (trend_id, country_id) DO UPDATE, but the original
        // 2024_01_01_000006_create_intelligence_tables migration never
        // created the matching unique constraint. Postgres requires one
        // for ON CONFLICT to be valid, so every upsert failed at runtime.
        Schema::table('commercial_opportunities', function (Blueprint $table) {
            $table->unique(['trend_id', 'country_id'], 'commercial_opportunities_trend_country_unique');
        });
    }

    public function down(): void
    {
        Schema::table('commercial_opportunities', function (Blueprint $table) {
            $table->dropUnique('commercial_opportunities_trend_country_unique');
        });
    }
};
