<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops `business_unit_id` from `machinery_groups` and `machinery` —
 * final step of the entity-catalog v10 rename to `production_line_id`
 * (...000008 added the new column, ...000009 backfilled it, ...000010
 * made it NOT NULL). Every call site (services, factories, seeders,
 * tests) has been repointed to `production_line_id` in this same change.
 *
 * `station.business_unit_id` is NOT touched here — that column is a
 * different, still-correct denormalization (from
 * `production_line.business_unit_id`) per entity-catalog v9/v10's
 * `station` entity docblock; it is out of scope for this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machinery', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_unit_id');
        });

        Schema::table('machinery_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('machinery_groups', function (Blueprint $table) {
            $table->foreignUuid('business_unit_id')->nullable()->after('id')->constrained('business_units')->cascadeOnDelete();
        });

        Schema::table('machinery', function (Blueprint $table) {
            $table->foreignUuid('business_unit_id')->nullable()->after('machinery_group_id')->constrained('business_units')->nullOnDelete();
        });
    }
};
