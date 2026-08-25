<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Threshing Detail — hourly time-slot row (up to 24/day, one of the
 * canonical 07:00–06:00-next-day slots, added dynamically by the user via
 * "Tambah baris" — no longer pre-created 24-at-a-time, see
 * ThreshingRecordService's header comment) belonging to a ThreshingRecord.
 * `time_slot` is stored as a native DB `time` column and left uncast (plain
 * string), consistent with no existing precedent forcing a Carbon
 * time-only cast in this codebase.
 */
class ThreshingDetail extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'threshing_record_id',
        'time_slot',
        'ffb_throughput_mt_hour',
        'thresher_drum_speed_rpm',
        'motor_current_amps',
        'unstripped_bunch_count_percent',
        'empty_bunch_oil_loss_percent',
        'downtime_reason',
    ];

    protected $casts = [
        'ffb_throughput_mt_hour' => 'float',
        'thresher_drum_speed_rpm' => 'float',
        'motor_current_amps' => 'float',
        'unstripped_bunch_count_percent' => 'float',
        'empty_bunch_oil_loss_percent' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function threshingRecord(): BelongsTo
    {
        return $this->belongsTo(ThreshingRecord::class);
    }
}
