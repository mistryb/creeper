<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('watched_pages', function (Blueprint $table) {
            // pricing, releases, messaging or other. Set by the preset the
            // page was started from, changeable after; what the dashboard
            // slices a competitor's activity by.
            $table->string('category', 32)->default('other')->after('watch_for');
        });
    }

    public function down(): void
    {
        Schema::table('watched_pages', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
