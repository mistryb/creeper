<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retire every password-shaped credential.
     *
     * A one-time code to the address on file is now the only way in, so
     * anything else that could authenticate a user is either removed or
     * emptied. The password column survives as a nullable, unused column —
     * reversible if passwords are ever wanted back — but no hash is left in
     * it, because a dormant credential is still a credential.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();

            /*
             * An address change now has to be proved before it takes effect.
             * The address is the only credential, so a typo in this field
             * would otherwise lock somebody out of their own account for good.
             */
            $table->string('pending_email')->nullable()->after('email_verified_at');

            // Fortify's two-factor challenge only ever fires after a password
            // login, so with passwords gone it could never run. Keeping the
            // columns would suggest a protection that was not there.
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
            ]);
        });

        DB::table('users')->update(['password' => null]);

        // Both tables exist only to serve flows that no longer exist.
        Schema::dropIfExists('passkeys');
        Schema::dropIfExists('password_reset_tokens');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pending_email');

            $table->text('two_factor_secret')->after('password')->nullable();
            $table->text('two_factor_recovery_codes')->after('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->after('two_factor_recovery_codes')->nullable();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        /*
         * Passwords cannot come back: they were destroyed on the way up, and
         * the column is left nullable so that is not silently papered over.
         * Every account has to be given a new password out of band.
         */
    }
};
