<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Trends ───────────────────────────────────────────────────────────
        Schema::create('trends', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->foreignId('niche_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('primary_country_id')->nullable()->constrained('countries')->nullOnDelete();
            // known | emergent
            $table->string('detection_method', 20)->default('known');
            $table->json('keywords')->default('[]');
            $table->text('description')->nullable();

            // ── Scores (all 0-100) ──────────────────────────────────────────
            $table->integer('momentum_score')->default(0);
            $table->integer('growth_score')->default(0);
            $table->integer('search_score')->default(0);
            $table->integer('creator_score')->default(0);
            $table->integer('brand_score')->default(0);
            $table->integer('competition_score')->default(0);
            $table->integer('commercial_opportunity_score')->default(0);
            $table->integer('convergence_score')->default(0);
            $table->integer('saturation_score')->default(0);
            $table->integer('virality_score')->default(0);
            $table->integer('desirability_score')->default(0);
            $table->integer('commercial_strength_score')->default(0);
            $table->integer('death_probability')->default(0);
            $table->integer('confidence')->default(0);

            // ── Lifecycle ──────────────────────────────────────────────────
            // EMERGING | EARLY_ADOPTION | ACCELERATING | MAINSTREAM | PEAK
            // SATURATED | DECLINING | DEAD
            $table->string('lifecycle_stage', 30)->default('EMERGING');
            $table->integer('stage_confidence')->default(0);
            $table->timestamp('stage_started_at')->nullable();
            $table->timestamp('estimated_peak')->nullable();
            $table->timestamp('estimated_decline')->nullable();
            $table->timestamp('estimated_death')->nullable();

            $table->timestamp('first_seen_at')->useCurrent();
            $table->timestamp('last_updated_at')->useCurrent();
            $table->boolean('is_demo')->default(false);
            $table->json('metadata')->default('{}');
            $table->timestamps();

            $table->index('momentum_score');
            $table->index('commercial_opportunity_score');
            $table->index('lifecycle_stage');
            $table->index('detection_method');
            $table->index('niche_id');
        });

        // ─── Trend Mentions ───────────────────────────────────────────────────
        Schema::create('trend_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trend_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raw_document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            // search | social | community | creator | brand | product | sales_proxy | media
            $table->string('signal_type', 30)->nullable();
            $table->text('mention_text')->nullable();
            $table->decimal('sentiment', 4, 3)->nullable(); // -1 to 1
            $table->decimal('confidence', 5, 2)->nullable();
            $table->timestamp('mentioned_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('trend_id');
            $table->index('signal_type');
            $table->index('mentioned_at');
        });

        // ─── Trend Snapshots (time series) ────────────────────────────────────
        Schema::create('trend_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trend_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->date('snapshot_date');

            // Raw counts
            $table->integer('mention_count')->default(0);
            $table->integer('search_interest')->default(0); // 0-100 Google Trends scale
            $table->integer('reddit_score')->default(0);
            $table->integer('brand_count')->default(0);
            $table->integer('creator_count')->default(0);
            $table->integer('product_count')->default(0);
            $table->integer('post_count')->default(0);

            // Calculated metrics
            $table->integer('momentum_score')->default(0);
            $table->decimal('growth_velocity', 8, 4)->nullable();     // WoW %
            $table->decimal('growth_acceleration', 8, 4)->nullable(); // velocity delta

            $table->timestamp('created_at')->useCurrent();

            $table->unique(['trend_id', 'country_id', 'snapshot_date']);
            $table->index(['trend_id', 'snapshot_date']);
        });

        // ─── Trend Lifecycle History ──────────────────────────────────────────
        Schema::create('trend_lifecycle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trend_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 30);
            $table->integer('confidence')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->json('evidence')->default('[]');
            $table->timestamp('created_at')->useCurrent();

            $table->index('trend_id');
        });

        // ─── Trend Association Tables ──────────────────────────────────────────
        Schema::create('brand_trends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trend_id')->constrained()->cascadeOnDelete();
            $table->date('adoption_date')->nullable();
            $table->integer('product_count')->default(0);
            $table->integer('confidence')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['brand_id', 'trend_id']);
        });

        Schema::create('creator_trends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trend_id')->constrained()->cascadeOnDelete();
            $table->timestamp('first_seen_at')->nullable();
            $table->integer('frequency')->default(0);
            $table->integer('confidence')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['creator_id', 'trend_id']);
        });

        Schema::create('product_trends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trend_id')->constrained()->cascadeOnDelete();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['product_id', 'trend_id']);
        });

        Schema::create('trend_countries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trend_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->integer('strength')->default(0); // 0-100
            $table->string('stage', 30)->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['trend_id', 'country_id']);
        });

        Schema::create('trend_colors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trend_id')->constrained()->cascadeOnDelete();
            $table->foreignId('color_id')->constrained()->cascadeOnDelete();
            $table->integer('frequency')->default(0);
            $table->decimal('percentage', 5, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('trend_silhouettes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trend_id')->constrained()->cascadeOnDelete();
            $table->foreignId('silhouette_id')->constrained()->cascadeOnDelete();
            $table->integer('frequency')->default(0);
            $table->decimal('percentage', 5, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('trend_graphics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trend_id')->constrained()->cascadeOnDelete();
            $table->foreignId('graphic_id')->constrained()->cascadeOnDelete();
            $table->integer('frequency')->default(0);
            $table->decimal('percentage', 5, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // ─── Engagement & Search Metrics ──────────────────────────────────────
        Schema::create('engagement_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 50);
            $table->unsignedBigInteger('entity_id');
            $table->string('platform', 50)->nullable();
            $table->date('metric_date')->nullable();
            $table->integer('followers')->nullable();
            $table->integer('following')->nullable();
            $table->integer('posts')->nullable();
            $table->bigInteger('likes')->nullable();
            $table->bigInteger('comments')->nullable();
            $table->bigInteger('shares')->nullable();
            $table->bigInteger('saves')->nullable();
            $table->bigInteger('views')->nullable();
            $table->decimal('engagement_rate', 8, 4)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entity_type', 'entity_id']);
            $table->index('metric_date');
        });

        Schema::create('search_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trend_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('keyword', 255);
            $table->date('metric_date');
            $table->integer('interest')->default(0); // 0-100 Google Trends
            $table->json('related_queries')->default('[]');
            $table->json('related_topics')->default('[]');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['trend_id', 'country_id', 'keyword', 'metric_date']);
            $table->index(['trend_id', 'metric_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_metrics');
        Schema::dropIfExists('engagement_metrics');
        Schema::dropIfExists('trend_graphics');
        Schema::dropIfExists('trend_silhouettes');
        Schema::dropIfExists('trend_colors');
        Schema::dropIfExists('trend_countries');
        Schema::dropIfExists('product_trends');
        Schema::dropIfExists('creator_trends');
        Schema::dropIfExists('brand_trends');
        Schema::dropIfExists('trend_lifecycle');
        Schema::dropIfExists('trend_snapshots');
        Schema::dropIfExists('trend_mentions');
        Schema::dropIfExists('trends');
    }
};
