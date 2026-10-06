<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_analyses', function (Blueprint $table) {
            $table->id();

            // What was analysed: the user's own business, or (later) one of
            // its competitors. Every run is its own row, so re-running keeps
            // the earlier reports for comparison rather than overwriting them.
            $table->morphs('analyzable');

            // The key that paid. Nulled if that key is deleted; the report it
            // bought stays.
            $table->foreignId('api_key_id')->nullable()->constrained()->nullOnDelete();

            $table->string('status')->default('queued');

            // The structured report the model returned, once validated.
            $table->json('report')->nullable();

            // The website that was read alongside the description, if any.
            $table->string('source_url', 2048)->nullable();

            $table->string('provider', 32)->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();

            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['analyzable_type', 'analyzable_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_analyses');
    }
};
