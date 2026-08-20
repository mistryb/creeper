<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creep_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('creep_target_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('brand')->nullable();
            $table->string('sku')->nullable();
            // Minor units (pence, cents). Never floats for money.
            $table->unsignedBigInteger('price_amount')->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('availability')->default('unknown');
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('review_count')->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->json('extra')->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();

            // Powers the price history chart and "previous snapshot" lookup.
            $table->index(['creep_target_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_snapshots');
    }
};
