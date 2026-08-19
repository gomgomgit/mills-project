<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MillSetting — screen-034--mills-setting / usecase-034--mills-setting.
 * 1:1 with BusinessUnit (unique `business_unit_id`) — app-facing branding
 * (app_name, logo, home_page_image) and the mill's physical cage count
 * (jumlah_cages), which drives the Cages Tipped Time grid's checklist
 * column count (see App\Models\CagesTrackRecord's docblock — out of
 * scope for this migration/model, that wiring lives in screen-012's own
 * Phase 4).
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
        'jumlah_cages',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'jumlah_cages' => 'integer',
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
