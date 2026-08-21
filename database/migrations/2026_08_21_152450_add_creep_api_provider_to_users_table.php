<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Which provider the key belongs to. A key on its own doesn't say
            // where to send it, and Creeper doesn't guess.
            $table->string('creep_api_provider', 32)->nullable()->after('creep_api_key_hint');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('creep_api_provider');
        });
    }
};
