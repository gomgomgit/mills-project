<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Stage 2 of 3, step 2 — data migration: backfills
 * `production_line_id` on all 18 `*_records` tables from each row's own
 * `station_id`, via `stations.production_line_id` (NOT NULL since
 * 2026_08_20_000006).
 *
 * This is the ONE moment where deriving the line from the station is
 * correct: at this instant the derived value and the historical value are
 * by definition the same, because no station has been moved since the rows
 * were written — there was no column in which a move could have been
 * recorded, which is precisely the gap ...000041 closes. From the next
 * write onwards the value is a snapshot and must never be re-derived.
 *
 * Deterministic by construction: every one of the 18 tables has
 * `station_id` NOT NULL with a RESTRICT FK to `stations`, so no row can be
 * orphaned or null-keyed and every row resolves to exactly one line.
 * Verified on the dev database before writing this migration: 90 rows
 * total across the 18 tables, 0 with a null `station_id`, 0 orphans.
 *
 * Runs through the DB facade (query builder), not Eloquent — same one-shot
 * data-migration convention as 2026_08_20_000009_backfill_production_line_id
 * _on_machinery_groups_and_machinery.php, whose in-memory station map this
 * mirrors. Model-free on purpose: a data migration must keep working after
 * the models above it have moved on.
 *
 * Updates are grouped per (table, production_line_id) so each table costs
 * one SELECT plus one UPDATE per distinct line rather than one UPDATE per
 * row — the machinery precedent's per-row loop was fine for its row count;
 * 18 tables makes the grouping worth the four extra lines.
 *
 * No-op down() — same convention as the machinery backfill: reverting
 * would blank out values on rows created after this ran, and ...000041's
 * down() drops the column outright anyway.
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
        $lineByStation = DB::table('stations')
            ->select('id', 'production_line_id')
            ->pluck('production_line_id', 'id');

        foreach (self::TABLES as $table) {
            $rows = DB::table($table)
                ->whereNull('production_line_id')
                ->select('id', 'station_id')
                ->get();

            /** @var array<string, list<string>> $idsByLine */
            $idsByLine = [];

            foreach ($rows as $row) {
                $lineId = $lineByStation[$row->station_id] ?? null;

                if ($lineId !== null) {
                    $idsByLine[(string) $lineId][] = $row->id;
                }
            }

            foreach ($idsByLine as $lineId => $ids) {
                DB::table($table)
                    ->whereIn('id', $ids)
                    ->update(['production_line_id' => $lineId]);
            }
        }
    }

    public function down(): void
    {
        // Intentionally a no-op — see class docblock.
    }
};
