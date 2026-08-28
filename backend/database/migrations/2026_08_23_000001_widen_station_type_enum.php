<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Widens stations.type to accept the 4 new MVP station types (threshing,
 * pressing, depricarping, kernel-plant) alongside the existing
 * weighbridge/grading/cages-track/other set.
 *
 * MySQL (production, per arch-spec) stores this column as a real ENUM and
 * needs an explicit ALTER TABLE ... MODIFY to widen it. PostgreSQL has no
 * native ENUM type either — Laravel's `$table->enum()` schema builder
 * compiles it to `varchar` + a CHECK constraint there (named
 * `stations_type_check` by Laravel's/Postgres' default convention), so it
 * needs its own DROP/ADD CONSTRAINT statements (found the hard way: this
 * branch was originally missing entirely, so any deployment actually
 * running Postgres kept the OLD 4-value check constraint forever and
 * failed with SQLSTATE[23514] on every insert of the 4 new station
 * types — SQLite never caught this because `$table->enum()` on SQLite
 * compiles to a plain `varchar` with NO check constraint at all, so the
 * column already silently accepted any string value there). SQLite
 * (local dev — see backend/.env's DB_CONNECTION) needs no statement at
 * all for that same reason. Guarded by driver name so every environment
 * migrates cleanly.
 */
return new class extends Migration
{
    private const OLD_TYPES = ['weighbridge', 'grading', 'cages-track', 'other'];

    private const NEW_TYPES = ['weighbridge', 'grading', 'cages-track', 'threshing', 'pressing', 'depricarping', 'kernel-plant', 'other'];

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
