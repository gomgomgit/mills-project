<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Data migration: removes the 'Loading Ramp' placeholder station (type
 * 'other') from every EXISTING `production_lines` row — it turned out to
 * be a duplicate name for the already-active Cages Track station, not a
 * distinct station, so it's dropped from the canonical list entirely
 * rather than merely deactivated. ProductionLineService::DEFAULT_STATIONS
 * only governs NEW Production Lines created from this point forward; rows
 * already provisioned before this change keep their 'Loading Ramp' row
 * unless deleted here.
 *
 * Matched by (name, type) exact match — these were seeded verbatim from
 * the old DEFAULT_STATIONS list, so this is safe and won't touch any
 * Admin-renamed station.
 *
 * Runs entirely through the DB facade (query builder), not Eloquent
 * models — matches the convention of every other one-shot data migration
 * in this codebase (see 2026_08_31_000022_activate_6_and_add_4_new_stations_on_existing_production_lines.php).
 *
 * down() re-inserts a 'Loading Ramp' row (type 'other', is_active false)
 * for every Production Line that doesn't already have one — safe and
 * idempotent, mirroring the up() migration's own insert pattern.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('stations')
            ->where('name', 'Loading Ramp')
            ->where('type', 'other')
            ->delete();
    }

    public function down(): void
    {
        $productionLines = DB::table('production_lines')->get();

        foreach ($productionLines as $productionLine) {
            $exists = DB::table('stations')
                ->where('production_line_id', $productionLine->id)
                ->where('name', 'Loading Ramp')
                ->where('type', 'other')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('stations')->insert([
                'id' => (string) Str::uuid(),
                'business_unit_id' => $productionLine->business_unit_id,
                'production_line_id' => $productionLine->id,
                'name' => 'Loading Ramp',
                'type' => 'other',
                'is_active' => false,
                'code' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
