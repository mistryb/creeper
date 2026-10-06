<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();

            // An account can run several businesses, and everything Creeper
            // watches is watched on behalf of one of them.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('name');

            // In the owner's own words: what they sell, to whom, and what sets
            // them apart. This is the context every competitor is read against.
            $table->text('description');

            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
