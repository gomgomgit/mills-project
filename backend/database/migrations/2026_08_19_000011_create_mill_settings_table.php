<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the `mill_settings` table — screen-034--mills-setting /
 * usecase-034--mills-setting, entity-catalog v7's `mill-setting` entity
 * (1:1 with `business_units`, added alongside `station.icon` — see
 * 2026_08_19_000012_add_icon_to_stations_table.php).
 *
 * `business_unit_id` is `unique()` — enforces the "exactly one Mills
 * Setting per mill" invariant at the DB layer (application layer also
 * relies on this via MillSettingService's get-or-create-default logic,
 * which SELECTs by business_unit_id before ever attempting an insert).
 *
 * `app_name` is NOT NULL at the DB layer even though a row is always
 * created with a non-empty default (business_unit.name) — the
 * "required" tech-spec field genuinely never has an empty value in
 * practice, unlike other entities' migrations in this codebase that
 * leave a "required" field nullable at the DB layer for ALTER-safety on
 * an already-populated table (this is a brand new table, no such
 * concern applies here).
 *
 * `logo`/`home_page_image` — nullable string paths, same convention as
 * business_units.logo / corporates.logo (Laravel Filesystem local disk,
 * not binary-in-DB).
 *
 * `jumlah_cages` — NOT NULL, application-layer enforces > 0
 * (MillSettingService's Validator rule) rather than a DB CHECK
 * constraint, matching this codebase's convention of application-layer
 * validation over DB constraints for business rules (see e.g.
 * StationService's is_active/type cross-field rule).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mill_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_unit_id')->unique()->constrained('business_units')->cascadeOnDelete();
            $table->string('app_name');
            $table->string('logo')->nullable();
            $table->string('home_page_image')->nullable();
            $table->unsignedInteger('jumlah_cages')->default(1);

            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mill_settings');
    }
};
