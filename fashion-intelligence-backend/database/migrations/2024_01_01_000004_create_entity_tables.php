<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Brands ───────────────────────────────────────────────────────────
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->json('aliases')->default('[]');
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('niche_id')->nullable()->constrained()->nullOnDelete();
            $table->text('website')->nullable();
            $table->json('social_urls')->default('{}');
            $table->string('category', 100)->nullable();
            // low | mid | premium | luxury
            $table->string('price_position', 20)->nullable();
            // micro | small | medium | large | mega
            $table->string('estimated_scale', 20)->nullable();
            $table->boolean('active')->default(true);
            $table->integer('confidence')->default(50); // entity resolution confidence 0-100
            $table->char('entity_hash', 64)->nullable();
            $table->json('metadata')->default('{}');
            $table->boolean('is_demo')->default(false);
            $table->timestamps();

            $table->index('slug');
            $table->index('country_id');
            $table->index('niche_id');
        });

        // ─── Products ─────────────────────────────────────────────────────────
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->text('name');
            $table->string('slug', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('category', 100)->nullable();
            $table->string('sub_category', 100)->nullable();
            $table->text('source_url')->nullable();
            $table->json('image_urls')->default('[]');
            $table->decimal('price', 12, 2)->nullable();
            $table->char('currency', 3)->nullable();
            // in_stock | out_of_stock | pre_order | limited | discontinued
            $table->string('availability', 50)->nullable();
            $table->boolean('is_sold_out')->default(false);
            $table->boolean('has_been_restocked')->default(false);
            $table->date('launch_date')->nullable();
            $table->json('metadata')->default('{}');
            $table->boolean('is_demo')->default(false);
            $table->timestamps();

            $table->index('brand_id');
            $table->index('category');
            $table->index('is_sold_out');
        });

        // ─── Product Attributes ───────────────────────────────────────────────
        Schema::create('product_colors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('color_id')->constrained()->cascadeOnDelete();
            $table->decimal('percentage_area', 5, 2)->nullable();
            $table->boolean('is_dominant')->default(false);
        });

        Schema::create('product_silhouettes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('silhouette_id')->constrained()->cascadeOnDelete();
            $table->decimal('confidence', 5, 2)->nullable();
        });

        Schema::create('product_graphics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('graphic_id')->constrained()->cascadeOnDelete();
            $table->decimal('confidence', 5, 2)->nullable();
        });

        Schema::create('product_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
        });

        // ─── Creators ─────────────────────────────────────────────────────────
        Schema::create('creators', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->string('handle', 255)->nullable();
            $table->json('aliases')->default('[]');
            // creator | celebrity | designer | athlete | musician | model | brand_founder
            $table->string('creator_type', 50)->default('creator');
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('niche_id')->nullable()->constrained()->nullOnDelete();
            $table->json('platform_handles')->default('{}');
            $table->json('follower_counts')->default('{}');
            $table->json('engagement_rates')->default('{}');
            $table->integer('commercial_influence_score')->default(0); // 0-100
            $table->integer('trend_influence_score')->default(0);      // 0-100
            $table->integer('awareness_score')->default(0);             // 0-100
            $table->json('metadata')->default('{}');
            $table->boolean('is_demo')->default(false);
            $table->timestamps();

            $table->index('creator_type');
            $table->index('country_id');
        });

        // ─── Communities ──────────────────────────────────────────────────────
        Schema::create('communities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            // reddit | discord | instagram | tiktok | youtube | forum
            $table->string('platform', 50);
            $table->string('platform_id', 255)->nullable();
            $table->foreignId('niche_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('member_count')->nullable();
            $table->integer('active_member_count')->nullable();
            $table->string('post_frequency', 20)->nullable();
            $table->json('metadata')->default('{}');
            $table->timestamps();
        });

        // ─── Entities (knowledge graph nodes) ────────────────────────────────
        Schema::create('entities', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 50); // brand | product | creator | trend | color | silhouette
            $table->unsignedBigInteger('entity_id');
            $table->string('canonical_name', 255)->nullable();
            $table->json('aliases')->default('[]');
            $table->integer('confidence')->default(100);
            $table->unsignedBigInteger('merged_into')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entity_type', 'entity_id']);
        });

        // ─── Entity Relationships ─────────────────────────────────────────────
        Schema::create('entity_relationships', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 50);
            $table->unsignedBigInteger('subject_id');
            // USES | WEARS | ADOPTS | SELLS | CREATES | APPEARS_IN | HAS | PART_OF
            $table->string('predicate', 100);
            $table->string('object_type', 50);
            $table->unsignedBigInteger('object_id');
            $table->integer('confidence')->default(50);
            $table->json('evidence')->default('[]');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['object_type', 'object_id']);
            $table->index('predicate');
        });

        // ─── Price Observations ───────────────────────────────────────────────
        Schema::create('price_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->text('source_url')->nullable();
            $table->decimal('price', 12, 2);
            $table->char('currency', 3)->nullable();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            // low | mid | premium | luxury
            $table->string('price_position', 20)->nullable();
            $table->timestamp('observed_at')->useCurrent();
            $table->boolean('is_sale')->default(false);
            $table->decimal('original_price', 12, 2)->nullable();
            $table->decimal('discount_percentage', 5, 2)->nullable();

            $table->index(['product_id', 'observed_at']);
        });

        // ─── Collaborations ───────────────────────────────────────────────────
        Schema::create('collaborations', function (Blueprint $table) {
            $table->id();
            $table->string('entity_a_type', 50)->nullable();
            $table->unsignedBigInteger('entity_a_id')->nullable();
            $table->string('entity_b_type', 50)->nullable();
            $table->unsignedBigInteger('entity_b_id')->nullable();
            $table->string('collaboration_type', 100)->nullable();
            $table->timestamp('announced_at')->nullable();
            $table->timestamp('launched_at')->nullable();
            $table->integer('impact_score')->nullable();
            $table->text('evidence_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collaborations');
        Schema::dropIfExists('price_observations');
        Schema::dropIfExists('entity_relationships');
        Schema::dropIfExists('entities');
        Schema::dropIfExists('communities');
        Schema::dropIfExists('creators');
        Schema::dropIfExists('product_materials');
        Schema::dropIfExists('product_graphics');
        Schema::dropIfExists('product_silhouettes');
        Schema::dropIfExists('product_colors');
        Schema::dropIfExists('products');
        Schema::dropIfExists('brands');
    }
};
