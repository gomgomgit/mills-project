<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Boiler Room Detail — Baris detail time-slot per jam dalam satu Boiler Room Record.
 * `time_slot` is stored as a native DB `time` column and left
 * uncast (plain string), consistent with ThreshingDetail — no existing
 * precedent forcing a Carbon time-only cast in this codebase.
 */
class BoilerRoomDetail extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'boiler_room_record_id',
        'time_slot',
        'steam_pressure_bar',
        'steam_temp_c',
        'feed_water_temp_c',
        'feed_water_tank_level_percent',
        'boiler_water_level_percent',
        'water_tds_ppm',
        'water_ph',
        'fuel_feed_rate',
        'id_fan_load',
        'sa_fan_load',
        'exhaust_gas_temp_c',
        'dust_collector_differential_pressure_mmh2o',
        'blowdown_executed',
        'sootblowing_executed',
        'findings',
    ];

    protected $casts = [
        'steam_pressure_bar' => 'float',
        'steam_temp_c' => 'float',
        'feed_water_temp_c' => 'float',
        'feed_water_tank_level_percent' => 'float',
        'boiler_water_level_percent' => 'float',
        'water_tds_ppm' => 'float',
        'water_ph' => 'float',
        'exhaust_gas_temp_c' => 'float',
        'dust_collector_differential_pressure_mmh2o' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function boilerRoomRecord(): BelongsTo
    {
        return $this->belongsTo(BoilerRoomRecord::class);
    }
}
