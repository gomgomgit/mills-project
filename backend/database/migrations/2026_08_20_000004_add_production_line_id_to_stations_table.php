<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `production_line_id` to `stations` — its new real hierarchical
 * parent (Production Line, entity-catalog v9). Nullable here so existing
 * `stations` rows don't break on this ADD COLUMN — the very next migration
 * (2026_08_20_000005_backfill_production_lines_for_existing_business_units.php)
 * backfills every row, and the one after that
 * (2026_08_20_000006_make_production_line_id_not_null_on_stations_table.php)
 * makes it NOT NULL once the backfill is guaranteed complete.
 *
 * `business_unit_id` is deliberately left UNCHANGED (not dropped, not
 * renamed) — it stays as a denormalized column per entity-catalog v9's
 * `station` entity docblock, so the many existing call sites already
 * resolving stations by `business_unit_id` (WeighbridgeRecordService,
 * MillSettingService, mobile sync, etc.) keep working without this single
 * migration needing to touch them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->foreignUuid('production_line_id')->nullable()->after('business_unit_id')->constrained('production_lines')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('production_line_id');
        });
    }
};
