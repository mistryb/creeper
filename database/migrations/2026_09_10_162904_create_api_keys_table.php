<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // What the user calls this key on the screen where they pick one.
            $table->string('name');

            // Which provider it belongs to. A key on its own doesn't say where
            // to send it, and Creeper doesn't guess.
            $table->string('provider', 32);

            // Encrypted at rest, so this is a text column rather than a
            // string — ciphertext is far longer than the key it holds.
            $table->text('key');

            // The last four characters, in the clear, so the settings screen
            // can tell two keys apart without ever decrypting either.
            $table->string('hint', 8);

            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        // Every key a user had before this table existed becomes the first
        // entry on their keyring. The ciphertext moves across untouched: both
        // columns are encrypted with the same application key.
        DB::table('users')
            ->whereNotNull('creep_api_key')
            ->orderBy('id')
            ->each(function (object $user): void {
                DB::table('api_keys')->insert([
                    'user_id' => $user->id,
                    'name' => 'My key',
                    'provider' => $user->creep_api_provider ?? 'anthropic',
                    'key' => $user->creep_api_key,
                    'hint' => $user->creep_api_key_hint ?? '',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['creep_api_key', 'creep_api_key_hint', 'creep_api_provider']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('creep_api_key')->nullable()->after('password');
            $table->string('creep_api_key_hint', 8)->nullable()->after('creep_api_key');
            $table->string('creep_api_provider', 32)->nullable()->after('creep_api_key_hint');
        });

        // Only one key fits back on a user, so the oldest wins.
        DB::table('api_keys')->orderBy('id')->each(function (object $key): void {
            DB::table('users')
                ->where('id', $key->user_id)
                ->whereNull('creep_api_key')
                ->update([
                    'creep_api_key' => $key->key,
                    'creep_api_key_hint' => $key->hint,
                    'creep_api_provider' => $key->provider,
                ]);
        });

        Schema::dropIfExists('api_keys');
    }
};
