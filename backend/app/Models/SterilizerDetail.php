<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sterilizer Detail — one row per sterilization cycle (Close Door s.d.
 * Open Door) belonging to one SterilizerRecord. Rows are added manually
 * via 'Tambah baris' per cycle (unbounded, not a pre-generated grid),
 * mirroring CpoDispatchDetail/CagesTippedTime's pattern.
 *
 * `duration_minutes` is ALWAYS computed server-side by
 * SterilizerRecordService (= open_door_time - close_door_time in
 * minutes), never trusted from client input even if sent.
 * `checked_by_spv` is a plain boolean checkbox — NOT a reference to a
 * Supervisor user (different from the header's `checked_by`).
 */
class SterilizerDetail extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'sterilizer_record_id',
        'sterilizer_no',
        'close_door_time',
        'peak_1_time',
        'exhaust_1_time',
        'peak_2_time',
        'exhaust_2_time',
        'peak_3_time',
        'exhaust_3_time',
        'open_door_time',
        'duration_minutes',
        'number_of_cages',
        'cages_status',
        'checked_by_spv',
        'remarks',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'number_of_cages' => 'integer',
        'checked_by_spv' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function sterilizerRecord(): BelongsTo
    {
        return $this->belongsTo(SterilizerRecord::class);
    }
}
