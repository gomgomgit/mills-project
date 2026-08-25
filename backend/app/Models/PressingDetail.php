<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pressing Detail — hourly time-slot row (24 rows/day, 07:00–06:00 next day)
 * belonging to a PressingRecord. `time_slot` is stored as a native DB `time`
 * column and left uncast (plain string), consistent with no existing
 * precedent forcing a Carbon time-only cast in this codebase.
 */
class PressingDetail extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'pressing_record_id',
        'time_slot',
        'digester_temp_c',
        'digester_level_percent',
        'press_motor_current_amps',
        'cone_hydraulic_pressure_bar',
        'dilution_water_temp_c',
        'downtime_reason',
    ];

    protected $casts = [
        'digester_temp_c' => 'float',
        'digester_level_percent' => 'float',
        'press_motor_current_amps' => 'float',
        'cone_hydraulic_pressure_bar' => 'float',
        'dilution_water_temp_c' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function pressingRecord(): BelongsTo
    {
        return $this->belongsTo(PressingRecord::class);
    }
}
