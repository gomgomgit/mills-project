<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data migration: promotes the 'Sterilizer' station (the LAST remaining
 * `other`-typed placeholder) to a fully active station on every EXISTING
 * `production_lines` row — ProductionLineService::DEFAULT_STATIONS only
 * governs NEW Production Lines created from this point forward; rows
 * already provisioned before this change keep `type = 'other'`,
 * `is_active = false` unless backfilled here.
 *
 * Matched by the OLD placeholder name + type (case-sensitive, exact
 * match — these were seeded verbatim from the old DEFAULT_STATIONS list,
 * so an exact match is safe and avoids touching any Admin-renamed
 * station):
 *   'Sterilizer' (type 'other') -> name unchanged, type 'sterilizer',
 *   is_active true
 *
 * This is a pure promotion — no new INSERTs needed, since every
 * Production Line already has a 'Sterilizer' row from its original
 * provisioning. Mirrors the exact
 * `$activations` UPDATE-loop pattern of
 * 2026_08_31_000022_activate_6_and_add_4_new_stations_on_existing_production_lines.php.
 *
 * This is the FINAL station promotion for this project — after this
 * migration runs, every existing Production Line has 18 active stations
 * and 0 placeholders.
 *
 * down() reverts the promoted rows back to their exact prior
 * name/type/is_active (safe — the up() mapping is a fixed, reversible 1:1
 * table).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('stations')
            ->where('name', 'Sterilizer')
            ->where('type', 'other')
            ->update([
                'type' => 'sterilizer',
                'is_active' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('stations')
            ->where('name', 'Sterilizer')
            ->where('type', 'sterilizer')
            ->update([
                'type' => 'other',
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }
};
