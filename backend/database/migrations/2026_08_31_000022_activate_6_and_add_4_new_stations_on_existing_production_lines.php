<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Data migration: activates the 6 newly-promoted stations (Clarification,
 * Boiler Room, Effluent Plant, Engine Room, Process Water, Storage Tank)
 * and inserts the 4 brand-new stations (Solid Waste Disposal, Kernel
 * Dispatch, CPO Dispatch, Process Quality Control) on every EXISTING
 * `production_lines` row — ProductionLineService::DEFAULT_STATIONS only
 * governs NEW Production Lines created from this point forward; rows
 * already provisioned before this change keep their old name/type/
 * is_active values (for the 6 promotions) or don't exist at all (for the
 * 4 new stations) unless backfilled here.
 *
 * Matched by the OLD placeholder name (case-sensitive, exact match — these
 * were seeded verbatim from the old DEFAULT_STATIONS list, so an exact
 * match is safe and avoids touching any Admin-renamed station):
 *   'Clarification'      -> name unchanged,       type 'clarification',      is_active true
 *   'Boiler'              -> name 'Boiler Room',    type 'boiler-room',        is_active true
 *   'Effluent Treatment'  -> name 'Effluent Plant',  type 'effluent-plant',     is_active true
 *   'Engine Room'         -> name unchanged,        type 'engine-room',        is_active true
 *   'Water Treatment'     -> name 'Process Water',   type 'process-water',      is_active true
 *   'Bulking Storage'     -> name 'Storage Tank',    type 'storage-tank',       is_active true
 *
 * The 4 new stations don't exist on any Production Line prior to this
 * migration — they're inserted fresh for every existing `production_lines`
 * row, mirroring the exact column set ProductionLineService::create()
 * writes for a Station (business_unit_id denormalized from the parent
 * Production Line, production_line_id, name, type, is_active, code left
 * null, timestamps). An existence check on (production_line_id, type)
 * guards each insert so a re-run of this migration in a weird state
 * doesn't create duplicate rows:
 *   'Solid Waste Disposal'      type 'solid-waste-disposal'      is_active true
 *   'Kernel Dispatch'           type 'kernel-dispatch'           is_active true
 *   'CPO Dispatch'              type 'cpo-dispatch'              is_active true
 *   'Process Quality Control'   type 'process-quality-control'   is_active true
 *
 * Runs entirely through the DB facade (query builder), not Eloquent
 * models — matches the convention of every other one-shot data migration
 * in this codebase (see 2026_08_23_000014_activate_4_new_stations_on_existing_production_lines.php).
 *
 * down() reverts the 6 promoted rows back to their exact prior
 * name/type/is_active (safe — the up() mapping is a fixed, reversible 1:1
 * table) and deletes the 4 newly-inserted station types outright (safe —
 * those types didn't exist before this migration at all).
 */
return new class extends Migration
{
    /** @var array<string, array{name: string, type: string}> */
    private array $activations = [
        'Clarification' => ['name' => 'Clarification', 'type' => 'clarification'],
        'Boiler' => ['name' => 'Boiler Room', 'type' => 'boiler-room'],
        'Effluent Treatment' => ['name' => 'Effluent Plant', 'type' => 'effluent-plant'],
        'Engine Room' => ['name' => 'Engine Room', 'type' => 'engine-room'],
        'Water Treatment' => ['name' => 'Process Water', 'type' => 'process-water'],
        'Bulking Storage' => ['name' => 'Storage Tank', 'type' => 'storage-tank'],
    ];

    /** @var array<int, array{name: string, type: string}> */
    private array $newStations = [
        ['name' => 'Solid Waste Disposal', 'type' => 'solid-waste-disposal'],
        ['name' => 'Kernel Dispatch', 'type' => 'kernel-dispatch'],
        ['name' => 'CPO Dispatch', 'type' => 'cpo-dispatch'],
        ['name' => 'Process Quality Control', 'type' => 'process-quality-control'],
    ];

    public function up(): void
    {
        foreach ($this->activations as $oldName => $target) {
            DB::table('stations')
                ->where('name', $oldName)
                ->where('type', 'other')
                ->update([
                    'name' => $target['name'],
                    'type' => $target['type'],
                    'is_active' => true,
                    'updated_at' => now(),
                ]);
        }

        $productionLines = DB::table('production_lines')->get();

        foreach ($productionLines as $productionLine) {
            foreach ($this->newStations as $station) {
                $doesntExist = DB::table('stations')
                    ->where('production_line_id', $productionLine->id)
                    ->where('type', $station['type'])
                    ->doesntExist();

                if (! $doesntExist) {
                    continue;
                }

                DB::table('stations')->insert([
                    'id' => (string) Str::uuid(),
                    'business_unit_id' => $productionLine->business_unit_id,
                    'production_line_id' => $productionLine->id,
                    'name' => $station['name'],
                    'type' => $station['type'],
                    'is_active' => true,
                    'code' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $reverse = [
            'clarification' => ['name' => 'Clarification', 'type' => 'other'],
            'boiler-room' => ['name' => 'Boiler', 'type' => 'other'],
            'effluent-plant' => ['name' => 'Effluent Treatment', 'type' => 'other'],
            'engine-room' => ['name' => 'Engine Room', 'type' => 'other'],
            'process-water' => ['name' => 'Water Treatment', 'type' => 'other'],
            'storage-tank' => ['name' => 'Bulking Storage', 'type' => 'other'],
        ];

        foreach ($reverse as $currentType => $target) {
            DB::table('stations')
                ->where('type', $currentType)
                ->update([
                    'name' => $target['name'],
                    'type' => $target['type'],
                    'is_active' => false,
                    'updated_at' => now(),
                ]);
        }

        DB::table('stations')
            ->whereIn('type', array_column($this->newStations, 'type'))
            ->delete();
    }
};
