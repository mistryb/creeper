<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landscape_analyses', function (Blueprint $table) {
            $table->id();

            // A business against all its competitors at once. One row per run,
            // so a dimension can be followed across runs.
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('api_key_id')->nullable()->constrained()->nullOnDelete();

            $table->string('status')->default('queued');

            // Summary, actions, the dimensions, a row per company, and the
            // positioning map — once validated.
            $table->json('report')->nullable();

            $table->string('provider', 32)->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();

            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landscape_analyses');
    }
};
