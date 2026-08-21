<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data migration: backfills `machinery_groups.production_line_id` and
 * `machinery.production_line_id` from each row's own `station_id`, by
 * joining to `stations.production_line_id` (already backfilled and
 * NOT NULL as of 2026_08_20_000005/000006).
 *
 * Runs entirely through the DB facade (query builder), not Eloquent
 * models — same one-shot data-migration convention as
 * 2026_08_20_000005_backfill_production_lines_for_existing_business_units.php.
 *
 * No-op down() — same "no-op down() for a data migration" convention
 * already accepted elsewhere in this project (reverting would risk
 * losing production_line_id values on rows created/changed after this
 * migration ran).
 */
return new class extends Migration
{
    public function up(): void
    {
        $stations = DB::table('stations')->select('id', 'production_line_id')->get()->keyBy('id');

        foreach (['machinery_groups', 'machinery'] as $table) {
            $rows = DB::table($table)->select('id', 'station_id')->get();

            foreach ($rows as $row) {
                $station = $stations->get($row->station_id);

                if ($station !== null) {
                    DB::table($table)
                        ->where('id', $row->id)
                        ->update(['production_line_id' => $station->production_line_id]);
                }
            }
        }
    }

    public function down(): void
    {
        // Intentionally a no-op — see class docblock.
    }
};
