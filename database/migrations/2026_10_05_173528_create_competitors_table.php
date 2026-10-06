<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitors', function (Blueprint $table) {
            $table->id();

            // Competitors are always somebody's competitors: a business's.
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('url', 2048)->nullable();

            // What the user knows about them, in their own words. Optional:
            // the website and the watched pages say a lot on their own.
            $table->text('description')->nullable();

            $table->timestamps();

            $table->unique(['business_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitors');
    }
};
