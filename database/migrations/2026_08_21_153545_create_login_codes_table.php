<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The one-time codes that are the only way into this application.
     *
     * Keyed by email rather than by user, because a code is issued before we
     * know whether an account exists — verifying the code is what creates one.
     * Only one code is ever outstanding per address: asking for another
     * replaces it, so an old code in an old email stops working.
     */
    public function up(): void
    {
        Schema::create('login_codes', function (Blueprint $table) {
            $table->string('email')->primary();

            // Hashed, so a leak of this table hands over nothing usable. The
            // hash is deliberately slow: six digits is only a million
            // combinations, and bcrypt is what makes guessing them expensive.
            $table->string('code_hash');

            // Wrong guesses against this code. The code dies at five.
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->timestamp('expires_at');
            $table->timestamp('created_at')->nullable();

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_codes');
    }
};
