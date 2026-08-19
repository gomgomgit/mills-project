<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the full business unit field set introduced by the entity-catalog
 * v4 rework (docs/MMS_Weighbridge_ERD_Operational_MVP_v3.1.mermaid) —
 * screen-029--kelola-business-unit 3-tech-spec ver 2. Mirrors
 * 2026_08_19_000001_add_fields_to_corporates_table.php /
 * 2026_08_19_000002_add_fields_to_companies_table.php's structure/style
 * exactly, with one divergence specific to `business_units`:
 *
 *  - `code` is already unique GLOBALLY on the ORIGINAL table
 *    (2025_01_15_000003_create_business_units_table.php's
 *    `$table->string('code')->unique()`) — this migration does not touch
 *    that column or its index at all, so `down()` has no unique index to
 *    drop (unlike the corporate/company migrations, which introduce a
 *    brand new unique column here).
 *  - `name` has NO uniqueness rule at all on this entity — a deliberate
 *    divergence from both Corporate (`name` globally unique) and Company
 *    (`name` unique within `corporate_id`). See
 *    App\Services\BusinessUnitService::validate() for the corresponding
 *    application-layer rule (just `required|string|max:255`, no
 *    Rule::unique at all).
 *
 * Per this project's convention, historical migrations are never edited —
 * new columns are always added via a new migration file.
 *
 * `created_by` is added as nullable at the DB layer even though the
 * tech-spec marks it "required" — same ALTER-safe reasoning as the
 * Corporate/Company migrations: `business_units` may already contain rows
 * (e.g. DemoAccountSeeder), and "required" is enforced instead at the
 * application layer (BusinessUnitService's Validator rules) for every
 * write that goes through the service.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_units', function (Blueprint $table) {
            $table->string('business_unit_type_code')->nullable()->after('code');
            $table->string('short_name')->nullable()->after('business_unit_type_code');
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
        Schema::table('business_units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropConstrainedForeignId('created_by');

            $table->dropColumn([
                'business_unit_type_code',
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
