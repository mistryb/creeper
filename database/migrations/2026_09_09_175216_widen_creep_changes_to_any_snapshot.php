<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Changes are the one part of the pipeline every creep type shares: the
     * dashboard's feed and the change e-mails read the same table whether a
     * price moved or a release shipped. Snapshots are not shared — each type
     * has its own table — so these two columns can no longer point at
     * `product_snapshots` in particular.
     *
     * Nothing loads a snapshot back through them; they are provenance, for
     * telling which two readings a change was found between. The cascade that
     * matters, from `creep_target_id`, is untouched.
     */
    public function up(): void
    {
        Schema::table('creep_changes', function (Blueprint $table) {
            $table->dropForeign(['from_snapshot_id']);
            $table->dropForeign(['to_snapshot_id']);
        });
    }

    public function down(): void
    {
        Schema::table('creep_changes', function (Blueprint $table) {
            $table->foreign('from_snapshot_id')->references('id')->on('product_snapshots')->cascadeOnDelete();
            $table->foreign('to_snapshot_id')->references('id')->on('product_snapshots')->cascadeOnDelete();
        });
    }
};
