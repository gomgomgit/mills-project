<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes `jumlah_cages` from `mill_settings` — entity-catalog v9 dropped
 * this field entirely. The Cages Tipped Time grid's checklist column count
 * is now derived dynamically as COUNT(machinery WHERE station_id = the
 * Cages Track station), not a manually-configured number (see
 * cages-track-record's updated constraint text in entity-catalog v9).
 *
 * DROP COLUMN requires doctrine/dbal (already added to composer.json).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mill_settings', function (Blueprint $table) {
            $table->dropColumn('jumlah_cages');
        });
    }

    public function down(): void
    {
        Schema::table('mill_settings', function (Blueprint $table) {
            $table->unsignedInteger('jumlah_cages')->default(1);
        });
    }
};
