<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('changelog_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creep_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('creep_target_id')->constrained()->cascadeOnDelete();
            $table->string('product')->nullable();
            $table->string('latest_version', 64)->nullable();
            $table->date('latest_released_on')->nullable();
            // Denormalised so a list of targets can be drawn without opening
            // every releases blob.
            $table->unsignedInteger('release_count')->default(0);
            $table->unsignedInteger('feature_count')->default(0);
            // The releases themselves, newest first, each with its features.
            // Shaped by ChangelogPayload, never by the agent directly.
            $table->json('releases');
            $table->json('extra')->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();

            // Powers the "what shipped since last time" lookup.
            $table->index(['creep_target_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('changelog_snapshots');
    }
};
