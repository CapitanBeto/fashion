<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Sources ──────────────────────────────────────────────────────────
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            // google_trends | reddit | web | apify | scraperapi | zyte | oxylabs | decodo | fashion_data_api
            $table->string('type', 50);
            $table->json('config')->default('{}');
            $table->integer('reliability_score')->default(50); // 0-100
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // ─── Source Targets ───────────────────────────────────────────────────
        Schema::create('source_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('niche_id')->nullable()->constrained()->nullOnDelete();
            // subreddit | keyword | url | actor | hashtag
            $table->string('target_type', 50)->nullable();
            $table->text('target_value');
            $table->integer('priority')->default(0);
            // daily | weekly | hourly | manual
            $table->string('frequency', 20)->default('daily');
            $table->integer('depth')->default(1); // pages to crawl
            $table->boolean('active')->default(true);
            $table->json('config')->default('{}');
            $table->timestamps();

            $table->index(['source_id', 'active']);
            $table->index(['country_id', 'niche_id']);
        });

        // ─── Scrape Runs ──────────────────────────────────────────────────────
        Schema::create('scrape_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('source_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_target_id')->nullable()->constrained()->nullOnDelete();
            $table->string('experiment_name', 100)->nullable();
            $table->string('provider', 50); // local | playwright | apify | scraperapi | etc.
            // pending | running | completed | failed | stopped | cancelled
            $table->string('status', 20)->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->integer('records_collected')->default(0);
            $table->integer('records_processed')->default(0);
            $table->integer('requests_made')->default(0);
            $table->integer('requests_failed')->default(0);
            $table->decimal('estimated_cost', 10, 6)->default(0);
            $table->decimal('actual_cost', 10, 6)->default(0);
            $table->integer('error_count')->default(0);
            $table->json('config')->default('{}');
            $table->json('log')->default('[]');
            $table->json('summary')->default('{}');
            $table->timestamps();

            $table->index('status');
            $table->index('experiment_name');
            $table->index('created_at');
        });

        // ─── Scraping Errors ──────────────────────────────────────────────────
        Schema::create('scraping_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scrape_run_id')->nullable()->constrained()->nullOnDelete();
            $table->text('url')->nullable();
            $table->string('error_type', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->integer('http_status')->nullable();
            $table->string('provider', 50)->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index('scrape_run_id');
        });

        // ─── Data Quality Logs ────────────────────────────────────────────────
        Schema::create('data_quality_logs', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 50)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->integer('quality_score')->default(0); // 0-100
            $table->json('missing_fields')->default('[]');
            $table->json('issues')->default('[]');
            $table->timestamp('checked_at')->useCurrent();

            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_quality_logs');
        Schema::dropIfExists('scraping_errors');
        Schema::dropIfExists('scrape_runs');
        Schema::dropIfExists('source_targets');
        Schema::dropIfExists('sources');
    }
};
