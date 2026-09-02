<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Storage Tank Detail — Baris detail time-slot per jam dalam satu Storage Tank Record.
 * `time_slot` is stored as a native DB `time` column and left
 * uncast (plain string), consistent with ThreshingDetail — no existing
 * precedent forcing a Carbon time-only cast in this codebase.
 */
class StorageTankDetail extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'storage_tank_record_id',
        'time_slot',
        'cpo_sounding_depth_mm',
        'water_dip_bottom_depth_mm',
        'net_oil_depth_mm',
        'oil_temperature_top_c',
        'oil_temperature_middle_c',
        'oil_temperature_bottom_c',
        'average_temperature_c',
        'calculated_volume_m3',
        'calculated_weight_mt',
        'ffa_percent',
        'moisture_content_percent',
        'impurities_dirt_percent',
        'dobi_index',
        'steam_heating_valve_status',
        'tank_structural_condition',
        'inspector_name',
        'findings',
    ];

    protected $casts = [
        'cpo_sounding_depth_mm' => 'float',
        'water_dip_bottom_depth_mm' => 'float',
        'net_oil_depth_mm' => 'float',
        'oil_temperature_top_c' => 'float',
        'oil_temperature_middle_c' => 'float',
        'oil_temperature_bottom_c' => 'float',
        'average_temperature_c' => 'float',
        'calculated_volume_m3' => 'float',
        'calculated_weight_mt' => 'float',
        'ffa_percent' => 'float',
        'moisture_content_percent' => 'float',
        'impurities_dirt_percent' => 'float',
        'dobi_index' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function storageTankRecord(): BelongsTo
    {
        return $this->belongsTo(StorageTankRecord::class);
    }
}
