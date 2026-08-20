<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * screen-023--form-grading-web fallout: `vehicle_code` on `grading_records`
 * was created NOT NULL (2025_01_15_000008_create_grading_records_table.php)
 * but a prior session change made Vehicle Code optional on Form Grading
 * mobile (screen-011) — that change only relaxed client-side validation,
 * never touched the entity-catalog/DB schema. Building screen-023's web
 * create()/update() (which genuinely omits vehicle_code as optional, per
 * that same product decision) surfaced the gap: inserting/updating a
 * record without it violated this NOT NULL constraint.
 *
 * ->nullable()->change() requires doctrine/dbal (just added to
 * composer.json for this migration — no prior migration in this project
 * needed a column-type/nullability CHANGE, only ADD COLUMN, which doesn't
 * need it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grading_records', function (Blueprint $table) {
            $table->string('vehicle_code')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('grading_records', function (Blueprint $table) {
            $table->string('vehicle_code')->nullable(false)->change();
        });
    }
};
