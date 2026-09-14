<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Raw Documents ────────────────────────────────────────────────────
        Schema::create('raw_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scrape_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->text('source_url');
            $table->text('canonical_url')->nullable();
            // post | product | article | trend_data | editorial | lookbook
            $table->string('document_type', 50)->nullable();
            $table->timestamp('scraped_at')->useCurrent();
            $table->timestamp('published_at')->nullable();
            $table->string('author', 255)->nullable();
            $table->text('title')->nullable();
            $table->longText('body')->nullable();
            $table->longText('html_raw')->nullable();
            $table->char('html_hash', 64)->nullable();
            $table->char('content_hash', 64)->nullable()->unique();
            $table->char('language', 5)->nullable();
            $table->json('metadata')->default('{}');
            // pending | processing | processed | failed | skipped
            $table->string('processing_status', 20)->default('pending');
            $table->boolean('is_demo')->default(false);
            $table->timestamps();

            $table->index('content_hash');
            $table->index('source_id');
            $table->index('published_at');
            $table->index('processing_status');
            $table->index('document_type');
        });

        // ─── Raw Posts ────────────────────────────────────────────────────────
        Schema::create('raw_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_document_id')->constrained()->cascadeOnDelete();
            // reddit | twitter | instagram | tiktok | youtube
            $table->string('platform', 50);
            $table->string('platform_id', 255)->nullable();
            $table->string('community', 255)->nullable(); // subreddit, hashtag, channel
            $table->string('author', 255)->nullable();
            $table->text('title')->nullable();
            $table->longText('body')->nullable();
            $table->integer('score')->nullable();
            $table->decimal('upvote_ratio', 5, 4)->nullable();
            $table->integer('comment_count')->nullable();
            $table->integer('award_count')->nullable();
            $table->boolean('is_stickied')->default(false);
            $table->boolean('is_nsfw')->default(false);
            $table->string('flair', 255)->nullable();
            $table->text('link_url')->nullable();
            $table->json('image_urls')->default('[]');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scraped_at')->useCurrent();
            $table->json('metadata')->default('{}');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['platform', 'platform_id']);
            $table->index('community');
            $table->index('published_at');
        });

        // ─── Raw Products ─────────────────────────────────────────────────────
        Schema::create('raw_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_document_id')->nullable()->constrained()->nullOnDelete();
            $table->text('source_url');
            $table->string('brand_name', 255)->nullable();
            $table->text('product_name')->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->char('currency', 3)->nullable();
            // in_stock | out_of_stock | pre_order | discontinued
            $table->string('availability', 50)->nullable();
            $table->json('images')->default('[]');
            $table->json('colors_raw')->default('[]');
            $table->json('materials_raw')->default('[]');
            $table->string('category_raw', 255)->nullable();
            $table->json('tags')->default('[]');
            $table->json('metadata')->default('{}');
            $table->timestamp('scraped_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->index('brand_name');
            $table->index('scraped_at');
        });

        // ─── Raw Images ───────────────────────────────────────────────────────
        Schema::create('raw_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_document_id')->nullable()->constrained()->nullOnDelete();
            $table->text('source_url');
            $table->text('local_path')->nullable();
            $table->char('sha256', 64)->nullable()->unique();
            $table->char('phash', 16)->nullable();
            $table->char('dhash', 16)->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->string('format', 10)->nullable();
            $table->integer('file_size_bytes')->nullable();
            $table->boolean('analyzed')->default(false);
            $table->json('analysis_result')->default('{}');
            $table->timestamp('created_at')->useCurrent();

            $table->index('analyzed');
            $table->index('phash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_images');
        Schema::dropIfExists('raw_products');
        Schema::dropIfExists('raw_posts');
        Schema::dropIfExists('raw_documents');
    }
};
