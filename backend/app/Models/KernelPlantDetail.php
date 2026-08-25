<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kernel Plant Detail — hourly time-slot row (24 rows/day, 07:00–06:00 next day)
 * belonging to a KernelPlantRecord. `time_slot` is stored as a native DB `time`
 * column and left uncast (plain string), consistent with no existing
 * precedent forcing a Carbon time-only cast in this codebase.
 */
class KernelPlantDetail extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'kernel_plant_record_id',
        'time_slot',
        'ripple_mill_1_amps',
        'ripple_mill_2_amps',
        'claybath_hydro_sg',
        'kernel_silo_1_temp_c',
        'kernel_silo_2_temp_c',
        'kernel_moisture_percent',
        'shell_loss_percent',
        'downtime_minutes',
        'findings',
    ];

    protected $casts = [
        'ripple_mill_1_amps' => 'float',
        'ripple_mill_2_amps' => 'float',
        'claybath_hydro_sg' => 'float',
        'kernel_silo_1_temp_c' => 'float',
        'kernel_silo_2_temp_c' => 'float',
        'kernel_moisture_percent' => 'float',
        'shell_loss_percent' => 'float',
        'downtime_minutes' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function kernelPlantRecord(): BelongsTo
    {
        return $this->belongsTo(KernelPlantRecord::class);
    }
}
