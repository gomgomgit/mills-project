<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Process Water Detail — Baris detail time-slot per jam dalam satu Process Water Record.
 * `time_slot` is stored as a native DB `time` column and left
 * uncast (plain string), consistent with ThreshingDetail — no existing
 * precedent forcing a Carbon time-only cast in this codebase.
 */
class ProcessWaterDetail extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'process_water_record_id',
        'time_slot',
        'shift',
        'inspector_id',
        'raw_water_flow_m3h',
        'clarified_water_flow_m3h',
        'softener_inlet_ph',
        'softener_outlet_hardness_ppm',
        'alum_dosing_kgh',
        'polymer_dosing_gh',
        'boiler_feed_tank_temp_c',
        'boiler_feed_water_ph',
        'boiler_feed_tds_ppm',
        'action_taken_status',
        'findings',
    ];

    protected $casts = [
        'raw_water_flow_m3h' => 'float',
        'clarified_water_flow_m3h' => 'float',
        'softener_inlet_ph' => 'float',
        'softener_outlet_hardness_ppm' => 'float',
        'alum_dosing_kgh' => 'float',
        'polymer_dosing_gh' => 'float',
        'boiler_feed_tank_temp_c' => 'float',
        'boiler_feed_water_ph' => 'float',
        'boiler_feed_tds_ppm' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function processWaterRecord(): BelongsTo
    {
        return $this->belongsTo(ProcessWaterRecord::class);
    }
}
