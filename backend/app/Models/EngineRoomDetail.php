<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Engine Room Detail — Baris detail time-slot per jam dalam satu Engine Room Record.
 * `time_slot` is stored as a native DB `time` column and left
 * uncast (plain string), consistent with ThreshingDetail — no existing
 * precedent forcing a Carbon time-only cast in this codebase.
 */
class EngineRoomDetail extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'engine_room_record_id',
        'time_slot',
        'steam_turbine_inlet_pressure_bar',
        'steam_turbine_inlet_temp_c',
        'steam_turbine_exhaust_pressure_bar',
        'steam_turbine_rpm',
        'steam_turbine_alternator_bearing_temp_1_c',
        'steam_turbine_alternator_bearing_temp_2_c',
        'diesel_gen_1_status',
        'diesel_gen_1_load_kw',
        'diesel_gen_1_amperage_a',
        'diesel_gen_1_jacket_water_temp_c',
        'diesel_gen_1_lube_oil_pressure_bar',
        'diesel_gen_2_status',
        'diesel_gen_2_load_kw',
        'diesel_gen_2_amperage_a',
        'diesel_gen_2_jacket_water_temp_c',
        'diesel_gen_2_lube_oil_pressure_bar',
        'electrical_sync_total_factory_load_kw',
        'electrical_sync_system_frequency_hz',
        'electrical_sync_power_factor',
        'electrical_sync_busbar_voltage_v',
        'air_compressor_1_pressure_bar',
        'compressor_2_pressure_bar',
        'battery_charger_ups_voltage_v',
        'fuel_tank_level',
        'daily_energy_export_kwh',
        'action_taken_maintenance_remark',
        'findings',
    ];

    protected $casts = [
        'steam_turbine_inlet_pressure_bar' => 'float',
        'steam_turbine_inlet_temp_c' => 'float',
        'steam_turbine_exhaust_pressure_bar' => 'float',
        'steam_turbine_rpm' => 'float',
        'steam_turbine_alternator_bearing_temp_1_c' => 'float',
        'steam_turbine_alternator_bearing_temp_2_c' => 'float',
        'diesel_gen_1_load_kw' => 'float',
        'diesel_gen_1_amperage_a' => 'float',
        'diesel_gen_1_jacket_water_temp_c' => 'float',
        'diesel_gen_1_lube_oil_pressure_bar' => 'float',
        'diesel_gen_2_load_kw' => 'float',
        'diesel_gen_2_amperage_a' => 'float',
        'diesel_gen_2_jacket_water_temp_c' => 'float',
        'diesel_gen_2_lube_oil_pressure_bar' => 'float',
        'electrical_sync_total_factory_load_kw' => 'float',
        'electrical_sync_system_frequency_hz' => 'float',
        'electrical_sync_power_factor' => 'float',
        'electrical_sync_busbar_voltage_v' => 'float',
        'air_compressor_1_pressure_bar' => 'float',
        'compressor_2_pressure_bar' => 'float',
        'battery_charger_ups_voltage_v' => 'float',
        'fuel_tank_level' => 'float',
        'daily_energy_export_kwh' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function engineRoomRecord(): BelongsTo
    {
        return $this->belongsTo(EngineRoomRecord::class);
    }
}
