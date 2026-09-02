<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Process Quality Control Detail — Baris detail time-slot dalam satu Process Quality Control Record.
 * `time_slot` is stored as a native DB `time` column and left
 * uncast (plain string), consistent with ThreshingDetail — no existing
 * precedent forcing a Carbon time-only cast in this codebase.
 */
class ProcessQualityControlDetail extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'process_quality_control_record_id',
        'time_slot',
        'shift',
        'fruit_press_oil_loss_in_sludge_percent',
        'fruit_press_oil_loss_in_fibre_percent',
        'purifier_clarification_balance_inlet_temp_c',
        'purifier_clarification_balance_backpressure_bar',
        'vacuum_drying_station_drier_temp_c',
        'vacuum_drying_station_vacuum_pressure_bar',
        'decanter_centrifuge_feed_rate_mth',
        'decanter_centrifuge_oil_loss_in_cake_percent',
        'final_storage_ffa_percent',
        'final_storage_moisture_content_percent',
        'final_storage_impurities_dirt_percent',
        'final_storage_dobi_index',
        'qc_inspector_id',
        'qc_engineering_corrective_actions',
        'findings',
    ];

    protected $casts = [
        'fruit_press_oil_loss_in_sludge_percent' => 'float',
        'fruit_press_oil_loss_in_fibre_percent' => 'float',
        'purifier_clarification_balance_inlet_temp_c' => 'float',
        'purifier_clarification_balance_backpressure_bar' => 'float',
        'vacuum_drying_station_drier_temp_c' => 'float',
        'vacuum_drying_station_vacuum_pressure_bar' => 'float',
        'decanter_centrifuge_feed_rate_mth' => 'float',
        'decanter_centrifuge_oil_loss_in_cake_percent' => 'float',
        'final_storage_ffa_percent' => 'float',
        'final_storage_moisture_content_percent' => 'float',
        'final_storage_impurities_dirt_percent' => 'float',
        'final_storage_dobi_index' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function processQualityControlRecord(): BelongsTo
    {
        return $this->belongsTo(ProcessQualityControlRecord::class);
    }
}
