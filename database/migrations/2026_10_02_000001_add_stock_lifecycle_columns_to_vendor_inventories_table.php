<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_inventories', function (Blueprint $table) {
            // ── Carrying stock forward ────────────────────────────────
            //
            // Unsold fish does not stop being the vendor's stock when the day
            // ends, so it has to be resubmitted on a later day to stay sellable.
            // Rather than rewriting entry_date — which would rewrite a day that
            // has already been declared — the leftover becomes a NEW entry that
            // points back at the one it came from. That keeps each trading day a
            // separate, immutable statement and lets the sale report close both
            // days independently.

            // The entry this one's stock was carried from. Set only on resubmitted
            // entries, so the lineage of a fish can be walked back to the morning
            // it was first logged.
            //
            // Deliberately not a database foreign key: SQLite cannot add one to an
            // existing table, and this self-reference is lineage bookkeeping rather
            // than an integrity rule. CarryForwardStock keeps it consistent.
            $table->unsignedBigInteger('carried_from_id')->nullable()->after('entry_date');

            // When this entry's leftover stock was handed to another entry. The
            // flag is what stops the same kilograms being counted on both lines:
            // without it, every remaining-stock total would double the book the
            // first time a vendor resubmitted anything.
            $table->timestamp('carried_out_at')->nullable()->after('carried_from_id');

            // ── Writing stock off ─────────────────────────────────────
            //
            // Stock past the freshness window can no longer be sold, so it needs an
            // ending. Reporting it lets the vendor clear the kilograms off their
            // stall and out of every remaining-stock total, with a reason on record
            // instead of a figure quietly rotting in a column forever.
            $table->timestamp('disposed_at')->nullable()->after('carried_out_at');
            $table->string('disposed_reason')->nullable()->after('disposed_at');

            // Backs both the one-entry-per-fish-per-day guard on submission and the
            // clash check when carrying stock onto another day.
            $table->index(
                ['vendor_id', 'fish_type_id', 'entry_date'],
                'vendor_inventories_vendor_fish_day_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('vendor_inventories', function (Blueprint $table) {
            $table->dropIndex('vendor_inventories_vendor_fish_day_index');
            $table->dropColumn(['carried_from_id', 'carried_out_at', 'disposed_at', 'disposed_reason']);
        });
    }
};
