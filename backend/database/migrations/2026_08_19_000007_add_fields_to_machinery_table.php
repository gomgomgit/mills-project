<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expands the `machinery` placeholder table (created by
 * 2025_01_15_000005_create_machinery_table.php — station_id/name/picture/
 * notes only, plus `machinery_group_id` added later by
 * 2026_08_19_000006_add_fields_to_machinery_groups_table.php purely for
 * screen-033--kelola-machinery-group's delete-guard) into a
 * fully-specified entity for screen-031--kelola-machinery /
 * entity-catalog v4.
 *
 * Per this project's convention (see 2026_08_19_000001-000006's
 * precedent), the original create_machinery_table migration is never
 * edited — new columns are always added via a new migration file. `id`,
 * `station_id`, `machinery_group_id`, `name`, `picture`, `notes`, and
 * timestamps are PRE-EXISTING and UNCHANGED here.
 *
 * Adds to `machinery`:
 *  - `business_unit_id` — NOT NULL at the app layer (MachineryService
 *    always derives it server-side from the selected MachineryGroup's own
 *    business_unit_id — see MachineryService::create()/::update()) but
 *    nullable at the DB layer, same ALTER-safe reasoning as every prior
 *    *_id/*_code column added via this migration-numbering series: this
 *    ALTER runs against a table that may already contain rows (seeded by
 *    screen-030's Machinery factory usage), and a NOT NULL column with no
 *    default would fail against any pre-existing row.
 *  - `equipment_code` — nullable+unique at the DB layer, "required" at
 *    the application layer (MachineryService::validate()) — same
 *    ALTER-safe reasoning as machinery_groups.group_code before it.
 *  - `description` — nullable free-text (distinct from the pre-existing
 *    `notes` column, which this migration leaves untouched).
 *  - 17 nullable technical-spec fields: registration_no, make, model,
 *    equipment_type, part_no, serial_no, gearbox, motor, mounting, rpm
 *    (float), chain, capacity, brand, year_made (integer), fixed_asset,
 *    control_activity, owner_ite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machinery', function (Blueprint $table) {
            $table->foreignUuid('business_unit_id')->nullable()->after('machinery_group_id')
                ->constrained('business_units')->nullOnDelete();
            $table->string('equipment_code')->nullable()->unique()->after('business_unit_id');
            $table->string('description')->nullable()->after('name');
            $table->string('registration_no')->nullable()->after('picture');
            $table->string('make')->nullable()->after('registration_no');
            $table->string('model')->nullable()->after('make');
            $table->string('equipment_type')->nullable()->after('model');
            $table->string('part_no')->nullable()->after('equipment_type');
            $table->string('serial_no')->nullable()->after('part_no');
            $table->string('gearbox')->nullable()->after('serial_no');
            $table->string('motor')->nullable()->after('gearbox');
            $table->string('mounting')->nullable()->after('motor');
            $table->float('rpm')->nullable()->after('mounting');
            $table->string('chain')->nullable()->after('rpm');
            $table->string('capacity')->nullable()->after('chain');
            $table->string('brand')->nullable()->after('capacity');
            $table->integer('year_made')->nullable()->after('brand');
            $table->string('fixed_asset')->nullable()->after('year_made');
            $table->string('control_activity')->nullable()->after('fixed_asset');
            $table->string('owner_ite')->nullable()->after('control_activity');
        });
    }

    public function down(): void
    {
        Schema::table('machinery', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_unit_id');

            // Drop the unique index explicitly before dropping its column
            // — SQLite's native ALTER TABLE ... DROP COLUMN (3.35+) leaves
            // a dangling index definition behind when the dropped column
            // still has a UNIQUE index on it, which then breaks schema
            // introspection for every subsequent statement in this same
            // migration. MySQL/Postgres drop the index automatically as
            // part of dropping the column, so this call is a no-op there.
            // Mirrors 2026_08_19_000001_add_fields_to_corporates_table.php's
            // down() precedent exactly.
            $table->dropUnique('machinery_equipment_code_unique');

            $table->dropColumn([
                'equipment_code',
                'description',
                'registration_no',
                'make',
                'model',
                'equipment_type',
                'part_no',
                'serial_no',
                'gearbox',
                'motor',
                'mounting',
                'rpm',
                'chain',
                'capacity',
                'brand',
                'year_made',
                'fixed_asset',
                'control_activity',
                'owner_ite',
            ]);
        });
    }
};
