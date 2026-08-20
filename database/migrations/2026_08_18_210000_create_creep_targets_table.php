<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creep_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('product');
            $table->string('url', 2048);
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
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creep_targets');
    }
};
