<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates `machinery_insurances` — a brand-new child entity of `machinery`
 * for screen-031--kelola-machinery / entity-catalog v4. Managed directly
 * inside the Machinery form with replace-all semantics on update (see
 * App\Services\MachineryService::update()'s docblock) — no independent
 * CRUD screen of its own.
 *
 * `machinery_id` uses cascadeOnDelete() (unlike machinery_groups.
 * station_id's restrictOnDelete()-style guard elsewhere in this codebase)
 * — this is Machinery's actual delete mechanism: entity-catalog v4 gives
 * this one master-data screen NO delete-guard at all, deletion of a
 * Machinery row is expected to cascade to its child rows (see
 * MachineryService::delete()'s docblock). Mirrors
 * 2025_01_15_000009_create_grading_details_table.php's
 * grading_record_id->cascadeOnDelete() precedent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machinery_insurances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('machinery_id')->constrained('machinery')->cascadeOnDelete();
            $table->string('ownership')->nullable();
            $table->string('insurance_policy_no')->nullable();
            $table->string('insurance_company')->nullable();
            $table->date('insurance_expiry_date')->nullable();
            $table->float('premium')->nullable();
            $table->float('amount_insured')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machinery_insurances');
    }
};
