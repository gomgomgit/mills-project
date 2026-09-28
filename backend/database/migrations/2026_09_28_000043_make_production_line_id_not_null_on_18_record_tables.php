<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 2 of 3, step 3 — makes `production_line_id` NOT NULL on all 18
 * `*_records` tables, now that ...000042 has backfilled every existing
 * row.
 *
 * NOT NULL is the point, not a tidy-up: a record whose line were nullable
 * would be a record whose line has to be guessed from its station again,
 * which is the exact retroactive-rewrite behaviour ...000041 exists to
 * end. The DB is the last place that can guarantee no future code path
 * (a seeder, a console command, a raw insert) writes a record with no
 * line at all.
 *
 * ->change() requires doctrine/dbal (already a project dependency — see
 * 2026_08_20_000006_make_production_line_id_not_null_on_stations_table.php).
 * Note that `->change()` on SQLite behaves differently from PostgreSQL;
 * the test suite builds its schema from scratch on SQLite each run, so the
 * enforcement was verified directly against PostgreSQL rather than being
 * inferred from a green suite.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'boiler_room_records',
        'cages_track_records',
        'clarification_records',
        'cpo_dispatch_records',
        'depricarping_records',
        'effluent_plant_records',
        'engine_room_records',
        'grading_records',
        'kernel_dispatch_records',
        'kernel_plant_records',
        'pressing_records',
        'process_quality_control_records',
        'process_water_records',
        'solid_waste_disposal_records',
        'sterilizer_records',
        'storage_tank_records',
        'threshing_records',
        'weighbridge_records',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignUuid('production_line_id')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignUuid('production_line_id')->nullable()->change();
            });
        }
    }
};
