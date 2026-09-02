<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Widens stations.type further to accept the 10 new MVP station types
 * (solid-waste-disposal, process-water, kernel-dispatch, cpo-dispatch,
 * effluent-plant, storage-tank, engine-room, boiler-room, clarification,
 * process-quality-control) alongside the existing 8-value set from
 * 2026_08_23_000001_widen_station_type_enum.php (weighbridge, grading,
 * cages-track, threshing, pressing, depricarping, kernel-plant, other).
 *
 * Same driver-guarded approach as the 2026-08-23 precedent: MySQL
 * (production, per arch-spec) needs an explicit ALTER TABLE ... MODIFY;
 * PostgreSQL's `$table->enum()` compiles to varchar + a named CHECK
 * constraint (`stations_type_check`) that must be dropped/re-added;
 * SQLite (local dev) needs no statement at all since `$table->enum()`
 * there compiles to a plain varchar with no CHECK constraint.
 */
return new class extends Migration
{
    private const OLD_TYPES = [
        'weighbridge', 'grading', 'cages-track', 'threshing', 'pressing',
        'depricarping', 'kernel-plant', 'other',
    ];

    private const NEW_TYPES = [
        'weighbridge', 'grading', 'cages-track', 'threshing', 'pressing',
        'depricarping', 'kernel-plant', 'solid-waste-disposal', 'process-water',
        'kernel-dispatch', 'cpo-dispatch', 'effluent-plant', 'storage-tank',
        'engine-room', 'boiler-room', 'clarification', 'process-quality-control',
        'other',
    ];

    public function up(): void
    {
        $this->applyCheck(self::NEW_TYPES);
    }

    public function down(): void
    {
        $this->applyCheck(self::OLD_TYPES);
    }

    private function applyCheck(array $types): void
    {
        $driver = DB::getDriverName();
        $quoted = "'".implode("', '", $types)."'";

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE stations MODIFY type ENUM({$quoted}) NOT NULL");

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE stations DROP CONSTRAINT IF EXISTS stations_type_check');
            DB::statement("ALTER TABLE stations ADD CONSTRAINT stations_type_check CHECK (type IN ({$quoted}))");

            return;
        }

        // sqlite (and any other driver): $table->enum() has no CHECK
        // constraint to widen — the column already accepts any string.
    }
};
