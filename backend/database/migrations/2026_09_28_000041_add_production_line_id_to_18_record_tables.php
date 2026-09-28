<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 2 of 3 — adds `production_line_id` to all 18 `*_records` tables
 * (nullable here; backfilled by ...000042; made NOT NULL by ...000043).
 *
 * WHY THE COLUMN EXISTS AT ALL — this is the whole reason for the series
 * ---------------------------------------------------------------------
 * Until now a record's production line could only be DERIVED, at read
 * time, through `station.production_line_id`. That makes the derivation a
 * function of the station's CURRENT configuration, not of what actually
 * happened: moving one station to another production line silently
 * REWRITES THE ENTIRE HISTORY of every record that station ever produced,
 * retroactively and without a trace. Yesterday's Sterilizer log sheet
 * would start reporting that it belonged to Line B, and nothing in the
 * database would record that it had ever said Line A.
 *
 * Storing the line ON THE RECORD makes that fact permanent. A record is
 * operational evidence of a shift that has already been worked; the line
 * it was worked on is part of the evidence, not part of today's
 * configuration. `station_id` stays exactly as it is — it still says which
 * station produced the row — and `production_line_id` is now a
 * point-in-time SNAPSHOT of where that station sat when the row was
 * written, deliberately allowed to diverge from
 * `station.production_line_id` afterwards. That divergence is the feature.
 *
 * Production line is a CHOSEN CONTEXT, not an account binding: there is no
 * `users.production_line_id` and there must not be one (see
 * App\Support\Concerns\ScopesToActorMill). The value stored here is
 * derived SERVER-SIDE from the station that stage 1's
 * resolveActiveStationForActor() already resolved and mill-checked — it is
 * never read off the request body, even though the client does send
 * `production_line_id` in order to SELECT the station.
 *
 * SHAPE OF THIS SERIES
 * --------------------
 * Three migrations (add nullable -> backfill -> NOT NULL), each looping
 * over all 18 tables, exactly mirroring the 3-step precedent this project
 * already used for the same column on `machinery_groups` / `machinery`
 * (2026_08_20_000008 / 000009 / 000010). The 3-step split is not stylistic
 * — a single ADD COLUMN NOT NULL cannot succeed on tables that already
 * hold rows, so the backfill must sit between the two schema steps. The
 * loop rather than 54 hand-written migrations: the operation is
 * character-for-character identical per table, and 54 files would make a
 * future reviewer diff 54 things to discover they are the same thing.
 *
 * `onDelete` = RESTRICT, deliberately NOT the `cascadeOnDelete()` used by
 * the machinery precedent. Machinery is CONFIGURATION owned by a line —
 * deleting the line reasonably deletes its machines. An operational record
 * is HISTORY, and a DELETE on `production_lines` that quietly erased
 * production history would be a catastrophically different thing. RESTRICT
 * is also exactly what these same 18 tables already declare on their
 * `station_id` FK (all 18 are ON DELETE RESTRICT, verified against
 * pg_constraint), so this adds no new blocking behaviour: because
 * `stations.production_line_id` cascades, deleting a line already tries to
 * cascade into `stations` and is already refused there by the records'
 * `station_id` RESTRICT. This FK just states the same protection directly.
 *
 * INDEX: composite `(production_line_id, <date column>)` per table — the
 * shape every list/report/export query uses (filter by line, then range or
 * order by date). 17 tables use `date`; `weighbridge_records` has no
 * `date` column at all and uses `record_datetime` instead, hence the map
 * below rather than a hardcoded column name. Laravel's generated index
 * names stay under PostgreSQL's 63-char identifier limit for all 18 (the
 * longest, process_quality_control_records, lands at 62), so no explicit
 * names are needed.
 */
return new class extends Migration
{
    /**
     * The 18 record tables and the date column each one actually has.
     *
     * @var array<string, string>
     */
    private const TABLES = [
        'boiler_room_records' => 'date',
        'cages_track_records' => 'date',
        'clarification_records' => 'date',
        'cpo_dispatch_records' => 'date',
        'depricarping_records' => 'date',
        'effluent_plant_records' => 'date',
        'engine_room_records' => 'date',
        'grading_records' => 'date',
        'kernel_dispatch_records' => 'date',
        'kernel_plant_records' => 'date',
        'pressing_records' => 'date',
        'process_quality_control_records' => 'date',
        'process_water_records' => 'date',
        'solid_waste_disposal_records' => 'date',
        'sterilizer_records' => 'date',
        'storage_tank_records' => 'date',
        'threshing_records' => 'date',
        // NOT `date` — weighbridge_records has never had one.
        'weighbridge_records' => 'record_datetime',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $dateColumn) {
            Schema::table($table, function (Blueprint $blueprint) use ($dateColumn) {
                $blueprint->foreignUuid('production_line_id')
                    ->nullable()
                    ->after('station_id')
                    ->constrained('production_lines')
                    ->restrictOnDelete();

                $blueprint->index(['production_line_id', $dateColumn]);
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES, true) as $table => $dateColumn) {
            Schema::table($table, function (Blueprint $blueprint) use ($dateColumn) {
                $blueprint->dropIndex(['production_line_id', $dateColumn]);
                $blueprint->dropConstrainedForeignId('production_line_id');
            });
        }
    }
};
