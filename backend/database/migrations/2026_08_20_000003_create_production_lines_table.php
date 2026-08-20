<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the `production_lines` table — screen-036--kelola-production-line
 * / usecase-036--kelola-production-line, entity-catalog v9's new
 * `production-line` entity, inserted into the hierarchy between
 * `business_units` and `stations` (Business Unit → Production Line →
 * Station → Machinery Group → Machinery).
 *
 * `code` is nullable+unique (same convention as `stations.code` — see
 * 2026_08_19_000004_add_fields_to_stations_table.php's own docblock:
 * multiple NULL `code`s never collide under a UNIQUE index).
 *
 * `created_by`/`updated_by` mirror `mill_settings`'s nullable FK-to-users
 * convention (2026_08_19_000011_create_mill_settings_table.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_unit_id')->constrained('business_units')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->string('description')->nullable();

            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_lines');
    }
};
