<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Candidate Instagram usernames worth manually investigating —
        // discovered via web search (never via automated Instagram access).
        // The user approves candidates here; approved usernames are mirrored
        // to cuentas_aprobadas.txt at the project root so they can be typed
        // manually into python/data/manual_instagram_import.json later.
        Schema::create('instagram_candidate_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('username', 100)->unique();

            // Username of the account that led to discovering this one
            // (e.g. 'stussy' -> its creative director -> their collaborator),
            // building a traceable discovery chain. Null for a root/seed account.
            $table->string('discovered_via', 100)->nullable();

            // Why this account is of interest (e.g. "creative director of Stussy").
            $table->text('reason')->nullable();

            // Where this claim came from (a URL, article title) — never
            // fabricated, always traceable to an actual web search result.
            $table->text('source_note')->nullable();

            $table->string('status', 20)->default('pending'); // pending | approved | rejected

            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_candidate_accounts');
    }
};
