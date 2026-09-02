<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Effluent Plant Detail — Baris detail time-slot per jam dalam satu Effluent Plant Record.
 * `time_slot` is stored as a native DB `time` column and left
 * uncast (plain string), consistent with ThreshingDetail — no existing
 * precedent forcing a Carbon time-only cast in this codebase.
 */
class EffluentPlantDetail extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'effluent_plant_record_id',
        'time_slot',
        'anaerobic_pond_1_ph',
        'anaerobic_pond_1_temp_c',
        'anaerobic_pond_2_ph',
        'anaerobic_pond_2_temp_c',
        'cooling_pond_ph',
        'cooling_pond_temp_c',
        'biogas_flare_status',
        'biogas_flow_rate_m3h',
        'raw_pome_feed_rate_m3h',
        'effluent_discharge_flow_rate_m3h',
        'final_discharge_ph',
        'final_discharge_bod_mgl_lab',
        'final_discharge_cod_mgl_lab',
        'final_discharge_tss_mgl_lab',
        'dosing_pump_1_status',
        'chemical_consumed_kgl',
        'sludge_dewatering_status',
        'remarks_maintenance_actions',
        'findings',
    ];

    protected $casts = [
        'anaerobic_pond_1_ph' => 'float',
        'anaerobic_pond_1_temp_c' => 'float',
        'anaerobic_pond_2_ph' => 'float',
        'anaerobic_pond_2_temp_c' => 'float',
        'cooling_pond_ph' => 'float',
        'cooling_pond_temp_c' => 'float',
        'biogas_flow_rate_m3h' => 'float',
        'raw_pome_feed_rate_m3h' => 'float',
        'effluent_discharge_flow_rate_m3h' => 'float',
        'final_discharge_ph' => 'float',
        'final_discharge_bod_mgl_lab' => 'float',
        'final_discharge_cod_mgl_lab' => 'float',
        'final_discharge_tss_mgl_lab' => 'float',
        'chemical_consumed_kgl' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function effluentPlantRecord(): BelongsTo
    {
        return $this->belongsTo(EffluentPlantRecord::class);
    }
}
