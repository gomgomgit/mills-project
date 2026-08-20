<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes `stations.production_line_id` NOT NULL now that the previous
 * migration (2026_08_20_000005) has backfilled every existing row — every
 * station has a real production line from this point forward, matching
 * entity-catalog v9's `station.production_line_id` (`required: true`).
 *
 * ->change() requires doctrine/dbal (already added to composer.json for
 * 2026_08_20_000001_make_vehicle_code_nullable_on_grading_records_table.php
 * — same mechanism, opposite direction).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->foreignUuid('production_line_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->foreignUuid('production_line_id')->nullable()->change();
        });
    }
};
