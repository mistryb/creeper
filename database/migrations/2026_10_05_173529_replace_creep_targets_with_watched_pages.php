<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creep targets become watched pages, each belonging to a competitor.
 *
 * A page is no longer one of a fixed set of types. The user describes what to
 * watch for in their own words (`watch_for`), one reader follows that, and
 * every reading — a summary and a list of named facts — goes in one
 * `page_snapshots` table. A change is a fact that appeared, disappeared or
 * changed value, so `creep_changes` is keyed on the fact's label.
 *
 * Existing targets are deliberately not carried over: a target belonged to a
 * user, a watched page belongs to a competitor, and there is no honest way to
 * guess which competitor a target was watching. So the tables are rebuilt
 * empty, with the same columns keyed on `watched_page_id`.
 */
return new class extends Migration
{
    /**
     * The migrations that built the old tables, replayed by down().
     *
     * @var list<string>
     */
    private const OLD = [
        '2026_08_18_210000_create_creep_targets_table',
        '2026_08_18_210001_create_creep_runs_table',
        '2026_08_18_210002_create_product_snapshots_table',
        '2026_08_18_210003_create_creep_changes_table',
        '2026_09_09_175215_create_changelog_snapshots_table',
        '2026_09_09_175216_widen_creep_changes_to_any_snapshot',
        '2026_09_10_162905_add_api_key_id_to_creep_targets_table',
    ];

    public function up(): void
    {
        $this->dropCreepingTables('creep_targets');

        Schema::create('watched_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competitor_id')->constrained()->cascadeOnDelete();

            // Nullable because deleting a key must not take the pages that
            // used it down with it — they are left keyless, which the
            // settings form asks the user to fix.
            $table->foreignId('api_key_id')->nullable()->constrained()->nullOnDelete();

            $table->string('url', 2048);

            // What the user wants Creeper to look for on the page, in their
            // own words. Handed to the reader on every run.
            $table->text('watch_for');

            $table->string('name')->nullable();
            $table->string('status')->default('active');
            $table->string('frequency')->default('daily');
            $table->boolean('notify_on_change')->default(true);
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('last_crept_at')->nullable();
            $table->timestamp('next_creep_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            // The scheduler sweeps on exactly this pair every minute.
            $table->index(['status', 'next_creep_at']);
            $table->unique(['competitor_id', 'url']);
        });

        Schema::create('creep_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('watched_page_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('queued');
            $table->string('driver');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['watched_page_id', 'created_at']);
        });

        Schema::create('page_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creep_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('watched_page_id')->constrained()->cascadeOnDelete();

            // What the page says about the thing being watched, in a sentence
            // or three.
            $table->text('summary');

            // The named facts read off the page: a list of {label, value}.
            // Comparing these by label between readings is what a change is.
            $table->json('facts');

            $table->json('extra')->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->index(['watched_page_id', 'captured_at']);
        });

        Schema::create('creep_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('watched_page_id')->constrained()->cascadeOnDelete();

            $table->foreignId('from_snapshot_id')->constrained('page_snapshots')->cascadeOnDelete();
            $table->foreignId('to_snapshot_id')->constrained('page_snapshots')->cascadeOnDelete();

            // The fact that moved, by its label.
            $table->string('label');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();

            // added, removed or changed.
            $table->string('kind');
            $table->timestamp('detected_at');
            $table->timestamps();

            $table->index(['watched_page_id', 'detected_at']);
        });
    }

    public function down(): void
    {
        $this->dropCreepingTables('watched_pages');

        foreach (self::OLD as $migration) {
            (require database_path("migrations/{$migration}.php"))->up();
        }
    }

    /**
     * Drop every table hanging off the page table, children first, then the
     * page table itself.
     */
    private function dropCreepingTables(string $pageTable): void
    {
        Schema::dropIfExists('creep_changes');
        Schema::dropIfExists('page_snapshots');
        Schema::dropIfExists('changelog_snapshots');
        Schema::dropIfExists('product_snapshots');
        Schema::dropIfExists('creep_runs');
        Schema::dropIfExists($pageTable);
    }
};
