<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creeper stops watching pages and starts writing digests.
 *
 * Everything a watched page produced — runs, readings, changes — and the
 * one-off analyses go. A business gains a digest schedule and the key it
 * spends; a competitor remembers whether Creeper found it and why; and two
 * new tables keep one row per discovery run and per digest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('creep_changes');
        Schema::dropIfExists('page_snapshots');
        Schema::dropIfExists('creep_runs');
        Schema::dropIfExists('watched_pages');
        Schema::dropIfExists('business_analyses');
        Schema::dropIfExists('landscape_analyses');

        Schema::table('businesses', function (Blueprint $table) {
            // The key discovery and every digest spend. Nulled when that key
            // is deleted, after which digests fail until another is picked.
            $table->foreignId('api_key_id')->nullable()->after('user_id')->constrained()->nullOnDelete();

            $table->string('digest_frequency')->default('weekly')->after('description');
            $table->timestamp('last_digest_at')->nullable()->after('digest_frequency');
            $table->timestamp('next_digest_at')->nullable()->after('last_digest_at')->index();
        });

        Schema::table('competitors', function (Blueprint $table) {
            // Set when Creeper found them rather than the user; `reason` is
            // the one line it gave for picking them.
            $table->timestamp('discovered_at')->nullable()->after('description');
            $table->text('reason')->nullable()->after('discovered_at');
        });

        Schema::create('competitor_discoveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('api_key_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status');
            $table->json('report')->nullable();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('digests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('api_key_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status');
            $table->json('report')->nullable();

            // The stretch of time the digest covers: from the last one sent
            // (or the company's creation) up to the moment it was queued.
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->timestamp('sent_at')->nullable();

            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digests');
        Schema::dropIfExists('competitor_discoveries');

        Schema::table('competitors', function (Blueprint $table) {
            $table->dropColumn(['discovered_at', 'reason']);
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('api_key_id');
            $table->dropColumn(['digest_frequency', 'last_digest_at', 'next_digest_at']);
        });
    }
};
