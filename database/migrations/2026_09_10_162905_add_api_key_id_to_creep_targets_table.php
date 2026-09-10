<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creep_targets', function (Blueprint $table) {
            /*
             * Which key this target spends. Nullable because deleting a key
             * must not take the targets that used it down with it — they are
             * left keyless, which the settings form asks the user to fix.
             */
            $table->foreignId('api_key_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        // Targets that predate the keyring go on their owner's first key,
        // which is the one they were already being crept with.
        DB::table('creep_targets')->orderBy('id')->each(function (object $target): void {
            $keyId = DB::table('api_keys')
                ->where('user_id', $target->user_id)
                ->orderBy('id')
                ->value('id');

            if ($keyId !== null) {
                DB::table('creep_targets')->where('id', $target->id)->update(['api_key_id' => $keyId]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('creep_targets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('api_key_id');
        });
    }
};
