<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Encrypted at rest, so this is a text column rather than a
            // string — ciphertext is far longer than the key it holds.
            $table->text('creep_api_key')->nullable()->after('password');

            // The last four characters, in the clear, so the settings screen
            // can show which key is on file without ever decrypting it.
            $table->string('creep_api_key_hint', 8)->nullable()->after('creep_api_key');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['creep_api_key', 'creep_api_key_hint']);
        });
    }
};
