<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the full company field set introduced by the entity-catalog v4
 * rework (docs/MMS_Weighbridge_ERD_Operational_MVP_v3.1.mermaid) —
 * screen-028--kelola-company 3-tech-spec ver 2. Mirrors
 * 2026_08_19_000001_add_fields_to_corporates_table.php's structure/style
 * exactly, with two divergences specific to `company`:
 *
 *  - `company_code` (this entity's equivalent of `corporate_code`) is
 *    added as a plain nullable+unique column, exactly like
 *    `corporate_code` — globally unique across the whole table.
 *  - `name`'s existing uniqueness rule is UNCHANGED: still scoped to
 *    `corporate_id` (composite unique index `companies.(corporate_id,
 *    name)`, created by 2025_01_15_000002_create_companies_table.php) —
 *    this migration does not touch that index at all. `company_code`'s
 *    new unique index is therefore a second, independent uniqueness rule
 *    coexisting alongside the pre-existing per-corporate `name` rule —
 *    see CompanyService::validate()/validateCompany() for how both are
 *    enforced together at the application layer.
 *  - `last_update` (nullable date) is new to `company` — has no Corporate
 *    equivalent; a separate business-meaning date (e.g. "last reported to
 *    head office"), distinct from the technical `updated_at` timestamp
 *    column.
 *
 * Per this project's convention, historical migrations are never edited —
 * new columns are always added via a new migration file.
 *
 * `company_code` and `created_by` are added as nullable at the DB layer
 * even though the tech-spec marks them "required" — same ALTER-safe
 * reasoning as the Corporate migration: `companies` may already contain
 * rows, and adding a NOT NULL column with a UNIQUE index without a
 * default would fail against any pre-existing row. "Required" is enforced
 * instead at the application layer (CompanyService's Validator rules) for
 * every write that goes through the service.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('company_code')->nullable()->unique()->after('id');

            $table->string('short_name')->nullable()->after('name');
            $table->string('leader_name')->nullable()->after('short_name');
            $table->string('lawyer_name')->nullable()->after('leader_name');
            $table->string('address')->nullable()->after('lawyer_name');
            $table->string('telephone_no')->nullable()->after('address');
            $table->string('fax_no')->nullable()->after('telephone_no');
            $table->string('contact_no')->nullable()->after('fax_no');
            $table->string('extension_no')->nullable()->after('contact_no');
            $table->string('email')->nullable()->after('extension_no');
            $table->string('website')->nullable()->after('email');
            $table->string('map')->nullable()->after('website');
            $table->string('tax_register_no')->nullable()->after('map');
            $table->string('insurance_no')->nullable()->after('tax_register_no');
            $table->string('epf_employer')->nullable()->after('insurance_no');
            $table->string('socso_employer')->nullable()->after('epf_employer');
            $table->string('labor_union')->nullable()->after('socso_employer');
            $table->string('logo')->nullable()->after('labor_union');
            $table->date('last_update')->nullable()->after('logo');

            $table->foreignUuid('created_by')->nullable()->after('last_update')
                ->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->after('created_by')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropConstrainedForeignId('created_by');

            // Drop the unique index explicitly before dropping its column
            // — SQLite's native ALTER TABLE ... DROP COLUMN (3.35+) leaves
            // a dangling index definition behind when the dropped column
            // still has a UNIQUE index on it, which then breaks schema
            // introspection for every subsequent statement in this same
            // migration. MySQL/Postgres drop the index automatically as
            // part of dropping the column, so this call is a no-op there.
            $table->dropUnique('companies_company_code_unique');

            $table->dropColumn([
                'company_code',
                'short_name',
                'leader_name',
                'lawyer_name',
                'address',
                'telephone_no',
                'fax_no',
                'contact_no',
                'extension_no',
                'email',
                'website',
                'map',
                'tax_register_no',
                'insurance_no',
                'epf_employer',
                'socso_employer',
                'labor_union',
                'logo',
                'last_update',
            ]);
        });
    }
};
