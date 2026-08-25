<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Widens stations.type to accept the 4 new MVP station types (threshing,
 * pressing, depricarping, kernel-plant) alongside the existing
 * weighbridge/grading/cages-track/other set.
 *
 * MySQL (production, per arch-spec) stores this column as a real ENUM and
 * needs an explicit ALTER TABLE ... MODIFY to widen it. SQLite (local dev
 * — see backend/.env's DB_CONNECTION) has no native ENUM type: Laravel's
 * `$table->enum()` schema builder call that originally created this column
 * compiles to a plain `varchar` with no CHECK constraint on SQLite (see
 * `stations` table's actual schema — `"type" varchar not null`, no
 * constraint at all), so the column already accepts any string value and
 * the MySQL-only `MODIFY ... ENUM(...)` statement is both unsupported
 * syntax AND unnecessary there — running it unconditionally previously
 * broke `php artisan migrate` on SQLite with a syntax error. Guarded by
 * driver name so both environments migrate cleanly.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE stations MODIFY type ENUM('weighbridge', 'grading', 'cages-track', 'threshing', 'pressing', 'depricarping', 'kernel-plant', 'other') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE stations MODIFY type ENUM('weighbridge', 'grading', 'cages-track', 'other') NOT NULL");
    }
};
