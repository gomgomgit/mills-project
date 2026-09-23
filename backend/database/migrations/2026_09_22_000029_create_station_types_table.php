<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * station_types — master data for the kinds of station a mill can have.
 *
 * WHY THIS TABLE EXISTS
 * Before this migration, the set of valid station types lived in two places
 * that both had to be edited by hand whenever a type was added:
 *   1. a CHECK constraint on `stations.type` (Laravel `$table->enum()`), and
 *   2. App\Enums\StationType.
 * That cost three migrations whose ONLY purpose was widening the constraint —
 * 2026_08_23_000001 (+4 types), 2026_08_31_000001 (+10), 2026_09_01_000026
 * (+sterilizer) — each having to drop and re-add the PostgreSQL CHECK, with
 * long comments about SQLite behaving differently. Adding a 20th station type
 * would have needed a fourth one, plus a second constraint on
 * `periods.station_type`.
 *
 * With this table, adding a station type is an INSERT — no migration.
 *
 * FK TARGETS `code`, NOT `id`
 * Every other FK in this schema points at a UUID `id`. This one deliberately
 * does not. `stations.type` already holds the string 'sterilizer' and is read
 * that way in 34 places across services, seeders, factories, tests and the
 * mobile app. Pointing the FK at `code` keeps every one of those working
 * untouched while still getting real referential integrity; pointing it at a
 * UUID would have meant rewriting all of them and migrating the column's data.
 * `id` is kept anyway so the table matches the shape of every other master
 * table (and so a future screen can reference a row without its code).
 */
return new class extends Migration
{
    /**
     * The 18 canonical types, in process order (TBS in → CPO out), copied
     * verbatim from ProductionLineService::DEFAULT_STATIONS, plus the
     * historical 'other' which predates the canonical set and is still
     * allowed by App\Enums\StationType.
     */
    private const SEED = [
        ['weighbridge', 'Weighbridge'],
        ['grading', 'Grading'],
        ['cages-track', 'Cages Track'],
        ['sterilizer', 'Sterilizer'],
        ['threshing', 'Threshing'],
        ['pressing', 'Pressing'],
        ['clarification', 'Clarification'],
        ['kernel-plant', 'Kernel Plant'],
        ['boiler-room', 'Boiler Room'],
        ['effluent-plant', 'Effluent Plant'],
        ['depricarping', 'Depricarping'],
        ['engine-room', 'Engine Room'],
        ['process-water', 'Process Water'],
        ['storage-tank', 'Storage Tank'],
        ['solid-waste-disposal', 'Solid Waste Disposal'],
        ['kernel-dispatch', 'Kernel Dispatch'],
        ['cpo-dispatch', 'CPO Dispatch'],
        ['process-quality-control', 'Process Quality Control'],
        ['other', 'Other'],
    ];

    public function up(): void
    {
        Schema::create('station_types', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // FK target — see the class docblock for why this, not `id`.
            $table->string('code')->unique();

            $table->string('name');

            // Display order for the station grids (mobile Station List,
            // web Production Process Activity). Follows the physical
            // process order rather than alphabetical.
            $table->integer('sort_order');

            // Lets a mill retire a type without deleting the row — existing
            // stations keep their FK, the type just stops being offered.
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        $now = now();
        $rows = [];
        foreach (self::SEED as $i => [$code, $name]) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'code' => $code,
                'name' => $name,
                'sort_order' => ($i + 1) * 10,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('station_types')->insert($rows);

        // Swap the CHECK constraint for a real FK.
        //
        // The constraint is named `stations_type_check` by Laravel's enum()
        // on PostgreSQL. SQLite (used by the test suite) compiles enum() to a
        // plain varchar with no named constraint, so the drop is guarded —
        // the same guard the three widening migrations already use.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE stations DROP CONSTRAINT IF EXISTS stations_type_check');
        }

        // Guard against a station row holding a type that is not in the seed
        // above. There should be none — the seed is a superset of the enum —
        // but failing loudly here beats a half-applied migration.
        $orphans = DB::table('stations')
            ->whereNotIn('type', array_column(self::SEED, 0))
            ->distinct()
            ->pluck('type')
            ->all();

        if ($orphans !== []) {
            throw new RuntimeException(
                'Cannot add the station_types FK: stations rows exist with types not present in '
                .'station_types ['.implode(', ', $orphans).']. Add them to this migration\'s SEED '
                .'constant (or fix the rows) and re-run.'
            );
        }

        Schema::table('stations', function (Blueprint $table) {
            $table->foreign('type')
                ->references('code')
                ->on('station_types')
                ->restrictOnDelete()   // a type in use cannot be deleted
                ->cascadeOnUpdate();   // renaming a code rewrites its stations
        });
    }

    public function down(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->dropForeign(['type']);
        });

        Schema::dropIfExists('station_types');

        // The original CHECK constraint is NOT restored here. Re-adding it
        // would re-create the exact maintenance problem this migration exists
        // to remove, and the three widening migrations that built it are still
        // in the history for anyone who needs the original list.
    }
};
