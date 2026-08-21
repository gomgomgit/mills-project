<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes `machinery_groups.production_line_id` and
 * `machinery.production_line_id` NOT NULL now that the previous
 * migration (...000009) has backfilled every existing row — matching
 * entity-catalog v10's `production_line_id` (`required: true`) on both
 * entities.
 *
 * ->change() requires doctrine/dbal (already a project dependency — see
 * 2026_08_20_000006_make_production_line_id_not_null_on_stations_table.php's
 * docblock for the precedent).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machinery_groups', function (Blueprint $table) {
            $table->foreignUuid('production_line_id')->nullable(false)->change();
        });

        Schema::table('machinery', function (Blueprint $table) {
            $table->foreignUuid('production_line_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('machinery', function (Blueprint $table) {
            $table->foreignUuid('production_line_id')->nullable()->change();
        });

        Schema::table('machinery_groups', function (Blueprint $table) {
            $table->foreignUuid('production_line_id')->nullable()->change();
        });
    }
};
