<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `production_line_id` to `machinery_groups` and `machinery` —
 * entity-catalog v10 renames their denormalized `business_unit_id`
 * convenience FK to `production_line_id`, mirroring the same
 * denormalization pattern `station.business_unit_id` already uses
 * (denormalized from `production_line.business_unit_id` — see
 * 2026_08_20_000004_add_production_line_id_to_stations_table.php).
 *
 * Nullable here so existing rows don't break on this ADD COLUMN — the
 * next migration (...000009_backfill...) backfills every row from
 * `stations.production_line_id` via each row's own `station_id`, and the
 * one after that (...000010_make_production_line_id_not_null...) makes
 * both NOT NULL once the backfill is guaranteed complete.
 *
 * `business_unit_id` is left UNCHANGED on both tables here — it is
 * dropped only in the final migration of this series
 * (...000011_drop_business_unit_id...), after every call site has been
 * repointed to `production_line_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machinery_groups', function (Blueprint $table) {
            $table->foreignUuid('production_line_id')->nullable()->after('business_unit_id')->constrained('production_lines')->cascadeOnDelete();
        });

        Schema::table('machinery', function (Blueprint $table) {
            $table->foreignUuid('production_line_id')->nullable()->after('business_unit_id')->constrained('production_lines')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('machinery', function (Blueprint $table) {
            $table->dropConstrainedForeignId('production_line_id');
        });

        Schema::table('machinery_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('production_line_id');
        });
    }
};
