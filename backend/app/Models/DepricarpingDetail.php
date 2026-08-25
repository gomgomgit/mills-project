<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Depricarping Detail — hourly time-slot row (24 rows/day, 07:00–06:00 next day)
 * belonging to a DepricarpingRecord. `time_slot` is stored as a native DB `time`
 * column and left uncast (plain string), consistent with no existing
 * precedent forcing a Carbon time-only cast in this codebase.
 */
class DepricarpingDetail extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'depricarping_record_id',
        'time_slot',
        'fan_static_pressure_mmh2o',
        'polishing_drum_speed_rpm',
        'air_velocity_ms',
        'fibre_moisture_percent',
        'kernel_recovery_in_fibre_percent',
        'nut_silo_1_temp_c',
        'nut_silo_2_temp_c',
        'downtime_minutes',
        'findings',
    ];

    protected $casts = [
        'fan_static_pressure_mmh2o' => 'float',
        'polishing_drum_speed_rpm' => 'float',
        'air_velocity_ms' => 'float',
        'fibre_moisture_percent' => 'float',
        'kernel_recovery_in_fibre_percent' => 'float',
        'nut_silo_1_temp_c' => 'float',
        'nut_silo_2_temp_c' => 'float',
        'downtime_minutes' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function depricarpingRecord(): BelongsTo
    {
        return $this->belongsTo(DepricarpingRecord::class);
    }
}
