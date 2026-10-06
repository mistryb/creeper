<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            /*
             * The business picked in the sidebar chooser. Nullable, and nulled
             * when that business is deleted: the chooser then falls back to
             * whichever business the user set up first.
             */
            $table->foreignId('current_business_id')->nullable()->after('remember_token')->constrained('businesses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_business_id');
        });
    }
};
