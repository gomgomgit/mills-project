<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates a NEW placeholder `machinery_groups` table.
 *
 * `MachineryGroup` does not exist yet as a fully-specified entity — it is
 * being built out fully by a later screen, screen-033--kelola-machinery-
 * group. This migration only lays the minimal FK shape screen-030--
 * kelola-station's delete-guard needs today
 * (StationService::delete()/StationHasMachineryException — a Station may
 * not be deleted while it still has related Machinery Group or Machinery
 * rows), applying this session's "new migration adds columns to an
 * existing table" convention (see 2026_08_19_000004_add_fields_to_
 * stations_table.php) to a table that doesn't exist yet at all: "minimal
 * placeholder table now, full field set added later by its own screen."
 *
 * screen-033--kelola-machinery-group will later ADD further columns to
 * this same table via its own new migration (never editing this one),
 * exactly mirroring how screen-027/028/029/030 each added their own
 * extra fields to corporates/companies/business_units/stations via a new
 * migration rather than editing the original create_*_table migration.
 *
 * Columns:
 *  - `id` — uuid primary key, same convention as every other entity in
 *    this schema (stations, machinery, business_units, ...).
 *  - `business_unit_id` — FK to business_units, cascade-deletes with its
 *    parent business unit (mirrors stations.business_unit_id's own
 *    cascadeOnDelete()).
 *  - `station_id` — FK to stations, cascade-deletes with its parent
 *    station (mirrors machinery.station_id's own cascadeOnDelete()) —
 *    this is the column StationService::delete()'s delete-guard counts
 *    against.
 *  - timestamps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machinery_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_unit_id')->constrained('business_units')->cascadeOnDelete();
            $table->foreignUuid('station_id')->constrained('stations')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machinery_groups');
    }
};
