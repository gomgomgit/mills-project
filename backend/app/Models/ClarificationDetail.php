<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Clarification Detail — Baris detail time-slot per jam dalam satu Clarification Record.
 * `time_slot` is stored as a native DB `time` column and left
 * uncast (plain string), consistent with ThreshingDetail — no existing
 * precedent forcing a Carbon time-only cast in this codebase.
 */
class ClarificationDetail extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'clarification_record_id',
        'time_slot',
        'clarification_tank_temp_c',
        'oil_tank_temperature_c',
        'sludge_tank_temp_c',
        'buffer_tank_level_percent',
        'pure_oil_production_rate_ton_hour',
        'downtime_mins',
        'findings',
    ];

    protected $casts = [
        'clarification_tank_temp_c' => 'float',
        'oil_tank_temperature_c' => 'float',
        'sludge_tank_temp_c' => 'float',
        'buffer_tank_level_percent' => 'float',
        'pure_oil_production_rate_ton_hour' => 'float',
        'downtime_mins' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function clarificationRecord(): BelongsTo
    {
        return $this->belongsTo(ClarificationRecord::class);
    }
}
