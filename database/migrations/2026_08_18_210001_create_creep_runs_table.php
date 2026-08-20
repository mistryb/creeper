<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creep_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creep_target_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('queued');
            $table->string('driver');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['creep_target_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creep_runs');
    }
};
