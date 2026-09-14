<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Enable pgvector extension (PostgreSQL only)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
            DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
        }

        // ─── Countries ────────────────────────────────────────────────────────
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->char('iso2', 2)->unique();
            $table->char('iso3', 3)->nullable();
            $table->string('language', 10)->nullable();
            $table->char('currency', 3)->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // ─── Niches ───────────────────────────────────────────────────────────
        Schema::create('niches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->json('keywords')->default('[]');
            $table->json('excluded_keywords')->default('[]');
            $table->integer('priority')->default(0);
            $table->boolean('active')->default(true);
            $table->json('metadata')->default('{}');
            $table->timestamps();
        });

        // ─── Silhouettes ──────────────────────────────────────────────────────
        Schema::create('silhouettes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // ─── Graphics ─────────────────────────────────────────────────────────
        Schema::create('graphics', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 100)->unique();
            $table->string('placement', 100)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // ─── Colors ───────────────────────────────────────────────────────────
        Schema::create('colors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->char('hex', 7)->nullable();
            $table->json('rgb')->nullable();
            $table->json('hsl')->nullable();
            $table->string('color_family', 50)->nullable();
            $table->string('temperature', 20)->nullable(); // warm, cool, neutral
            $table->timestamps();
        });

        // ─── Materials ────────────────────────────────────────────────────────
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 100)->unique();
            $table->timestamps();
        });

        // ─── Scoring Parameters ───────────────────────────────────────────────
        Schema::create('scoring_parameters', function (Blueprint $table) {
            $table->id();
            $table->string('parameter_group', 100);
            $table->string('parameter_key', 100);
            $table->decimal('parameter_value', 8, 4);
            $table->text('description')->nullable();
            $table->timestamp('updated_at')->useCurrent();
            $table->unique(['parameter_group', 'parameter_key']);
        });

        // ─── Prompt Versions ──────────────────────────────────────────────────
        Schema::create('prompt_versions', function (Blueprint $table) {
            $table->id();
            $table->string('prompt_key', 100)->unique();
            $table->string('version', 20);
            $table->string('task', 100)->nullable();
            $table->text('system_prompt')->nullable();
            $table->text('user_template')->nullable();
            $table->json('output_schema')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_versions');
        Schema::dropIfExists('scoring_parameters');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('colors');
        Schema::dropIfExists('graphics');
        Schema::dropIfExists('silhouettes');
        Schema::dropIfExists('niches');
        Schema::dropIfExists('countries');
    }
};
