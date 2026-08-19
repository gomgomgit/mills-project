<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates `machinery_tax_purchases` — a brand-new child entity of
 * `machinery` for screen-031--kelola-machinery / entity-catalog v4.
 * Managed directly inside the Machinery form with replace-all semantics
 * on update (see App\Services\MachineryService::update()'s docblock) — no
 * independent CRUD screen of its own. Mirrors
 * 2026_08_19_000008_create_machinery_insurances_table.php's structure and
 * cascadeOnDelete() reasoning exactly (see that migration's docblock).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machinery_tax_purchases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('machinery_id')->constrained('machinery')->cascadeOnDelete();
            $table->date('purchase_date')->nullable();
            $table->float('purchase_cost')->nullable();
            $table->string('policy_type')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_fax')->nullable();
            $table->string('contact_email')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machinery_tax_purchases');
    }
};
