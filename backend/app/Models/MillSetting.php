<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MillSetting — screen-034--mills-setting / usecase-034--mills-setting.
 * 1:1 with BusinessUnit (unique `business_unit_id`) — per-mill app settings
 * that the mobile app actually reads: `home_page_image` (Home hero image),
 * `immediate_sync_enabled` (write-through saving), plus the station icon
 * overrides stored on `station` itself.
 *
 * `app_name` and `logo` were REMOVED 2026-10-12 — no screen read either one.
 * Both were stored, synced to the device, and then affected nothing: the
 * mobile Home header uses a FIXED product name, deliberately separate from a
 * per-mill app name (business_rules screen-005 v5). A setting that changes no
 * screen cannot be told apart from a setting that is broken, so screen-034's
 * own rule ("every setting here must have a screen that reads it") retired
 * them instead of leaving them in place. See
 * 2026_10_12_000001_remove_app_name_and_logo_from_mill_settings_table.php.
 *
 * `jumlah_cages` was REMOVED 2026-08-20 (entity-catalog v9) — the Cages
 * Tipped Time grid's checklist column count is now derived dynamically as
 * COUNT(machinery WHERE station_id = the Cages Track station), not a
 * manually-configured number (see
 * 2026_08_20_000007_remove_jumlah_cages_from_mill_settings_table.php and
 * App\Services\CagesTrackRecordService).
 *
 * A row is created lazily (MillSettingService::getOrCreate()) rather than
 * at BusinessUnit-creation time, so pre-existing Business Units (created
 * before this feature) are automatically compatible without a backfill
 * migration.
 */
class MillSetting extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'business_unit_id',
        'home_page_image',
        'immediate_sync_enabled',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'immediate_sync_enabled' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
