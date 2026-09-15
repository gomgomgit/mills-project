<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-mill switch for mobile write-through saving (product decision
 * 2026-09-14).
 *
 * Default FALSE — the existing behaviour is preserved on every mill that
 * does not opt in: the mobile app keeps writing to local SQLite and only
 * pushes when the operator taps "Sinkronisasi". Turning this on makes a
 * successful local save immediately push that record to the server as
 * well, so data stops waiting for a manual step.
 *
 * The local database is NOT bypassed either way — it stays the working
 * store so drafts and the Pause/resume flow keep working offline, and so
 * a save made without signal still lands somewhere instead of failing.
 * This is the deliberate difference from "remote-only": see the mobile
 * syncService header for the trade-off that was chosen here.
 *
 * Lives on mill_setting rather than an env var so an Admin can flip it per
 * mill without a mobile rebuild.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mill_settings', function (Blueprint $table) {
            $table->boolean('immediate_sync_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('mill_settings', function (Blueprint $table) {
            $table->dropColumn('immediate_sync_enabled');
        });
    }
};
