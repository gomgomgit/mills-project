<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Solid Waste Disposal Detail — Baris log per-kejadian pembuangan limbah padat dalam satu Solid Waste Disposal Record.
 * Rows are added manually via 'Tambah baris' per event
 * (unbounded, not a pre-generated 24-row grid), mirroring
 * CagesTippedTime/CagesTrackRecord's pattern.
 */
class SolidWasteDisposalDetail extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'solid_waste_disposal_record_id',
        'event_date',
        'shift',
        'weighbridge_ticket_no',
        'vehicle_no',
        'driver_name',
        'solid_waste_type',
        'source_station',
        'gross_weight_mt',
        'tare_weight_mt',
        'net_weight_mt',
        'disposal_utilization_site',
        'purpose_end_use',
        'gate_pass_no',
        'security_seal_no',
        'operator_id',
        'remarks',
        'findings',
    ];

    protected $casts = [
        'event_date' => 'date',
        'gross_weight_mt' => 'float',
        'tare_weight_mt' => 'float',
        'net_weight_mt' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function solidWasteDisposalRecord(): BelongsTo
    {
        return $this->belongsTo(SolidWasteDisposalRecord::class);
    }
}
