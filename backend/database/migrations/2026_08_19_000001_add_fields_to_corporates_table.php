<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the full corporate field set introduced by the entity-catalog v4
 * rework (docs/MMS_Weighbridge_ERD_Operational_MVP_v3.1.mermaid) —
 * screen-027--kelola-corporate 3-tech-spec ver 2.
 *
 * Per this project's convention (see 2025_01_15_000001_create_corporates_
 * table.php's header comment history / CLAUDE.md notes), historical
 * migrations are never edited — new columns are always added via a new
 * migration file.
 *
 * `corporate_code` and `created_by` are added as nullable at the DB layer
 * even though the tech-spec marks them "required" — this is a deliberate,
 * ALTER-safe choice: this migration runs against `corporates`, a table
 * that may already contain rows (e.g. DemoAccountSeeder's
 * `Corporate::firstOrCreate(['name' => ...])`), and adding a NOT NULL
 * column with a UNIQUE index without a default would fail against any
 * pre-existing row. "Required" is enforced instead at the application
 * layer (CorporateService's Validator rules, mirroring how `name` is
 * already enforced as "required" primarily via Validator rather than
 * solely a DB constraint) for every write that goes through the service.
 * See CorporateService / KelolaCorporate.php's implementation_notes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corporates', function (Blueprint $table) {
            $table->string('corporate_code')->nullable()->unique()->after('id');

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

            $table->foreignUuid('created_by')->nullable()->after('logo')
                ->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->after('created_by')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('corporates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropConstrainedForeignId('created_by');

            // Drop the unique index explicitly before dropping its column
            // — SQLite's native ALTER TABLE ... DROP COLUMN (3.35+) leaves
            // a dangling index definition behind when the dropped column
            // still has a UNIQUE index on it, which then breaks schema
            // introspection for every subsequent statement in this same
            // migration. MySQL/Postgres drop the index automatically as
            // part of dropping the column, so this call is a no-op there.
            $table->dropUnique('corporates_corporate_code_unique');

            $table->dropColumn([
                'corporate_code',
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
            ]);
        });
    }
};
