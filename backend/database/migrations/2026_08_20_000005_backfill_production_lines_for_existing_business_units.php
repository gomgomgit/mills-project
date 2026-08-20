<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Data migration: for every EXISTING `business_units` row (created before
 * Production Line existed — e.g. via BusinessUnitService::create()'s old
 * auto-15-station behavior, or the demo seeder), create one default
 * `production_lines` row ("Line 1") owned by that business unit, then
 * point every existing `stations` row (matched by its own
 * `business_unit_id`, still present per the previous migration's docblock)
 * at that new production line.
 *
 * Runs entirely through the DB facade (query builder), not Eloquent
 * models — the standard convention for one-shot data migrations in this
 * codebase, so this migration keeps working even if `App\Models\Station`/
 * `BusinessUnit` change shape later.
 *
 * Idempotent-adjacent: `down()` intentionally does nothing destructive
 * (deleting the backfilled production_lines/reverting stations would lose
 * real data if this migration ran a while ago and users then created MORE
 * production lines/stations on top) — same "no-op down() for a data
 * migration" convention already accepted elsewhere in this project.
 */
return new class extends Migration
{
    public function up(): void
    {
        $businessUnits = DB::table('business_units')->select('id')->get();
        $now = now();

        foreach ($businessUnits as $businessUnit) {
            $productionLineId = (string) Str::uuid();

            DB::table('production_lines')->insert([
                'id' => $productionLineId,
                'business_unit_id' => $businessUnit->id,
                'name' => 'Line 1',
                'code' => null,
                'description' => null,
                'created_by' => null,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('stations')
                ->where('business_unit_id', $businessUnit->id)
                ->update(['production_line_id' => $productionLineId]);
        }
    }

    public function down(): void
    {
        // Intentionally a no-op — see class docblock.
    }
};
