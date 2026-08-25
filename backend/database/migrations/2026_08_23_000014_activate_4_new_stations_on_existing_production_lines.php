<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data migration: activates the 4 newly-promoted MVP stations (Threshing,
 * Pressing, Depricarping, Kernel Plant) on every EXISTING `stations` row —
 * ProductionLineService::DEFAULT_STATIONS only governs NEW Production
 * Lines created from this point forward; rows already provisioned before
 * this change keep their old name/type/is_active values unless backfilled
 * here.
 *
 * Matched by the OLD placeholder name (case-sensitive, exact match — these
 * were seeded verbatim from the old DEFAULT_STATIONS list, so an exact
 * match is safe and avoids touching any Admin-renamed station):
 *   'Thresher'     -> name 'Threshing',    type 'threshing',    is_active true
 *   'Press'        -> name 'Pressing',     type 'pressing',     is_active true
 *   'Digester'     -> name 'Depricarping', type 'depricarping', is_active true
 *   'Kernel Plant' -> name unchanged,      type 'kernel-plant', is_active true
 *
 * Runs entirely through the DB facade (query builder), not Eloquent
 * models — matches the convention of every other one-shot data migration
 * in this codebase (see 2026_08_20_000005_backfill_production_lines_for_existing_business_units.php).
 *
 * down() reverts every row it touched back to its exact prior
 * name/type/is_active — safe because the up() mapping is a fixed,
 * reversible 1:1 table (unlike the production-line backfill migration,
 * which creates new rows and therefore cannot cleanly revert).
 */
return new class extends Migration
{
    /** @var array<string, array{name: string, type: string}> */
    private array $activations = [
        'Thresher' => ['name' => 'Threshing', 'type' => 'threshing'],
        'Press' => ['name' => 'Pressing', 'type' => 'pressing'],
        'Digester' => ['name' => 'Depricarping', 'type' => 'depricarping'],
        'Kernel Plant' => ['name' => 'Kernel Plant', 'type' => 'kernel-plant'],
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
    }

    public function down(): void
    {
        $reverse = [
            'threshing' => ['name' => 'Thresher', 'type' => 'other'],
            'pressing' => ['name' => 'Press', 'type' => 'other'],
            'depricarping' => ['name' => 'Digester', 'type' => 'other'],
            'kernel-plant' => ['name' => 'Kernel Plant', 'type' => 'other'],
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
    }
};
