<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expands the `machinery_groups` placeholder table (created by
 * 2026_08_19_000005_create_machinery_groups_table.php purely for
 * screen-030--kelola-station's delete-guard) into a fully-specified
 * entity for screen-033--kelola-machinery-group / entity-catalog v4.
 *
 * Per this project's convention (see 2026_08_19_000001-000004's
 * precedent), the original create_machinery_groups_table migration is
 * never edited — new columns are always added via a new migration file.
 * `id`, `business_unit_id`, `station_id`, and timestamps are PRE-EXISTING
 * (laid down by 2026_08_19_000005) and UNCHANGED here.
 *
 * Adds to `machinery_groups`:
 *  - `group_code` — globally unique. "Required" at the application layer
 *    (MachineryGroupService::validate()) but nullable+unique at the DB
 *    layer — same ALTER-safe reasoning as company_code/corporate_code/
 *    business_units.code before it (see
 *    2026_08_19_000002_add_fields_to_companies_table.php's docblock):
 *    this ALTER runs against a table that may already contain rows
 *    seeded by screen-030, and a NOT NULL column with a UNIQUE index but
 *    no default would fail against any pre-existing row.
 *  - `description`, `unit` — nullable free-text.
 *  - `workshop_factor`, `cost_per_equipment` — nullable float.
 *
 * Also adds `machinery_group_id` to the PRE-EXISTING `machinery` table
 * (2025_01_15_000005_create_machinery_table.php — never edited either).
 * This is needed so THIS screen's own delete-guard
 * (Machinery::where('machinery_group_id', $id)->count(), in
 * MachineryGroupService::delete()) has a column to query against.
 * Nullable (existing Machinery rows predate this FK) + FK to
 * machinery_groups, nullOnDelete() (mirrors companies.created_by's
 * nullOnDelete() precedent — this FK is a soft link, not an ownership
 * edge that should cascade-delete Machinery rows). Machinery's own
 * `$fillable`/model fields are deliberately NOT expanded by this
 * migration or by App\Models\Machinery — full Machinery field coverage
 * is screen-031--kelola-machinery's job, not this screen's; this
 * migration only lays the FK column this screen's delete-guard needs.
 * (database/factories/MachineryFactory.php gains a forMachineryGroup()
 * state that sets this column via forceFill(), bypassing $fillable,
 * exactly because it is deliberately not fillable yet.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machinery_groups', function (Blueprint $table) {
            $table->string('group_code')->nullable()->unique()->after('station_id');
            $table->string('description')->nullable()->after('group_code');
            $table->string('unit')->nullable()->after('description');
            $table->float('workshop_factor')->nullable()->after('unit');
            $table->float('cost_per_equipment')->nullable()->after('workshop_factor');
        });

        if (! Schema::hasColumn('machinery', 'machinery_group_id')) {
            Schema::table('machinery', function (Blueprint $table) {
                $table->foreignUuid('machinery_group_id')->nullable()->after('station_id')
                    ->constrained('machinery_groups')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('machinery', 'machinery_group_id')) {
            Schema::table('machinery', function (Blueprint $table) {
                $table->dropConstrainedForeignId('machinery_group_id');
            });
        }

        Schema::table('machinery_groups', function (Blueprint $table) {
            // Drop the unique index explicitly before dropping its column
            // — SQLite's native ALTER TABLE ... DROP COLUMN (3.35+) leaves
            // a dangling index definition behind when the dropped column
            // still has a UNIQUE index on it, which then breaks schema
            // introspection for every subsequent statement in this same
            // migration. MySQL/Postgres drop the index automatically as
            // part of dropping the column, so this call is a no-op there.
            // Mirrors 2026_08_19_000001_add_fields_to_corporates_table.php's
            // down() precedent exactly.
            $table->dropUnique('machinery_groups_group_code_unique');

            $table->dropColumn(['group_code', 'description', 'unit', 'workshop_factor', 'cost_per_equipment']);
        });
    }
};
