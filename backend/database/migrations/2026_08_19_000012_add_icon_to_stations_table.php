<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `icon` to `stations` — screen-034--mills-setting /
 * usecase-034--mills-setting, entity-catalog v7's `station.icon` field.
 *
 * Nullable string holding a Lucide icon name (e.g. 'truck', 'gauge') —
 * NOT a file upload (entity-catalog v6's `station.image` field was
 * replaced by this `icon` field before ever being implemented, per the
 * user's REVISI at the screen-034 checkpoint — no data migration from an
 * `image` column is needed since that column was never created).
 * `null` means "use the type-default icon" (Gauge/Layers/Package per
 * uiux-spec component_patterns 'station-tile'), enforced at the
 * application layer against a fixed allow-list
 * (MillSettingService::SUPPORTED_ICONS), not a DB CHECK constraint —
 * same convention as mill_settings.jumlah_cages's >0 rule.
 *
 * Per this project's convention, historical migrations are never edited —
 * new columns are always added via a new migration file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->string('icon')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
