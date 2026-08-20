<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MillSetting — screen-034--mills-setting / usecase-034--mills-setting.
 * 1:1 with BusinessUnit (unique `business_unit_id`) — app-facing branding
 * only (app_name, logo, home_page_image).
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
        'app_name',
        'logo',
        'home_page_image',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
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
