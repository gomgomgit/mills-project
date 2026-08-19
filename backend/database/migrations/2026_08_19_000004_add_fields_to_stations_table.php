<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the two extra Station fields introduced by the entity-catalog v4
 * rework (docs/MMS_Weighbridge_ERD_Operational_MVP_v3.1.mermaid) —
 * screen-030--kelola-station 3-tech-spec.
 *
 * Per this project's convention (see 2026_08_19_000001_add_fields_to_
 * corporates_table.php / ..._000002_add_fields_to_companies_table.php /
 * ..._000003_add_fields_to_business_units_table.php's precedent),
 * historical migrations are never edited — new columns are always added
 * via a new migration file, and existing columns are left completely
 * alone.
 *
 * IMPORTANT — `business_unit_id`, `name`, `type`, and `is_active` are
 * PRE-EXISTING columns from 2025_01_15_000004_create_stations_table.php
 * and are UNCHANGED by this migration. This migration only adds:
 *
 *  - `code` — nullable, globally unique (mirrors Corporate's
 *    `corporate_code` / Company's `company_code` / Business Unit's
 *    `code`), but unlike those three siblings, this one is OPTIONAL
 *    (nullable) rather than required — a Station may legitimately have
 *    no code, per screen-030's tech-spec, hence no NOT NULL constraint
 *    even at the application/Validator layer (StationService::validate()
 *    uses `nullable` + `Rule::unique(...)`, not `required` + unique, for
 *    this field — see that class's docblock).
 *  - `description` — nullable free-text field, no uniqueness rule.
 *
 * Both are added nullable at the DB layer regardless, following the
 * same ALTER-safe reasoning as the three sibling migrations above: this
 * migration runs against `stations`, a table that may already contain
 * rows (weighbridge/grading/cages-track stations created by earlier
 * screens/seeders), so adding a NOT NULL column without a default would
 * fail against any pre-existing row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->after('is_active');
            $table->string('description')->nullable()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            // Drop the unique index explicitly before dropping its column
            // — SQLite's native ALTER TABLE ... DROP COLUMN (3.35+) leaves
            // a dangling index definition behind when the dropped column
            // still has a UNIQUE index on it, which then breaks schema
            // introspection for every subsequent statement in this same
            // migration. MySQL/Postgres drop the index automatically as
            // part of dropping the column, so this call is a no-op there.
            // Mirrors 2026_08_19_000001_add_fields_to_corporates_table.php's
            // down() precedent exactly.
            $table->dropUnique('stations_code_unique');

            $table->dropColumn(['code', 'description']);
        });
    }
};
