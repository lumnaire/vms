<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Morning / afternoon trading sessions.
 *
 * A fish market runs two sessions. A vendor who lands 15 kg of bammer at dawn and
 * another 5 kg after lunch is not declaring the same fish twice — they are
 * describing two different moments of the same day, and a consumer standing at the
 * stall at 2 PM cares which one they are being quoted for.
 *
 * This column is what lets one entry carry that distinction. Previously a second
 * submission for the same fish and quality class on the same day was refused
 * outright ("You already have an entry for this fish type and quality class
 * today"), which forced the vendor to either merge two real deliveries into one
 * dishonest number or lose the second delivery altogether. With the session on
 * the entry, 15 kg AM and 5 kg PM are two truthful lines.
 *
 * The column is named market_session rather than session because SESSION is a
 * reserved word in MySQL, and a name that has to be quoted in hand-written SQL is
 * a name that will eventually be quoted wrong.
 *
 * vendor_sale_report_items gets the same snapshot for the same reason its other
 * columns are snapshotted: a closed trading day has to keep saying which session
 * its figures belonged to, whatever happens to the entry afterwards.
 *
 * Note there is deliberately NO unique index on
 * (vendor, fish_type, quality_class, session, day). A vendor may submit the same
 * fish twice in the same session — the second delivery genuinely happened — so
 * uniqueness is a rule about this system, not about the database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_inventories', function (Blueprint $table) {
            $table->enum('market_session', ['AM', 'PM'])
                ->default('AM')
                ->after('quality_class');

            // Backs the carry-forward clash check, which now asks whether the
            // target day already holds this fish in this session. The existing
            // (vendor, fish_type, entry_date) index covers the prefix, so this is
            // only worth its own entry once the session is part of the lookup.
            $table->index(
                ['vendor_id', 'fish_type_id', 'entry_date', 'market_session'],
                'vendor_inventories_vendor_fish_day_session_idx'
            );
        });

        Schema::table('vendor_sale_report_items', function (Blueprint $table) {
            $table->enum('market_session', ['AM', 'PM'])
                ->default('AM')
                ->after('quality_class');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_sale_report_items', function (Blueprint $table) {
            $table->dropColumn('market_session');
        });

        Schema::table('vendor_inventories', function (Blueprint $table) {
            $table->dropIndex('vendor_inventories_vendor_fish_day_session_idx');
            $table->dropColumn('market_session');
        });
    }
};
