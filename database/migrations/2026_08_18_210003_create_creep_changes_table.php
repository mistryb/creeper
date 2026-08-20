<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creep_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creep_target_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_snapshot_id')->constrained('product_snapshots')->cascadeOnDelete();
            $table->foreignId('to_snapshot_id')->constrained('product_snapshots')->cascadeOnDelete();
            $table->string('field');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('direction')->default('changed');
            $table->timestamp('detected_at');
            $table->timestamps();

            $table->index(['creep_target_id', 'detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creep_changes');
    }
};
