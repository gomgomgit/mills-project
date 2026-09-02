<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kernel Dispatch Detail — Baris log per-kejadian pengiriman kernel dalam satu Kernel Dispatch Record.
 * Rows are added manually via 'Tambah baris' per event
 * (unbounded, not a pre-generated 24-row grid), mirroring
 * CagesTippedTime/CagesTrackRecord's pattern.
 */
class KernelDispatchDetail extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'kernel_dispatch_record_id',
        'event_date',
        'shift',
        'weighbridge_ticket_no',
        'waybill_number',
        'transporter_contractor',
        'vehicle_plate_no',
        'driver_name',
        'silo_source_id',
        'destination_buyer',
        'gross_weight_mt',
        'tare_weight_mt',
        'net_weight_mt',
        'kernel_moisture_percent',
        'dirt_impurities_percent',
        'ffa_percent',
        'broken_kernel_percent',
        'security_seal_no_top',
        'security_seal_no_bottom',
        'weighbridge_operator_id',
        'remarks_gate_status',
        'findings',
    ];

    protected $casts = [
        'event_date' => 'date',
        'gross_weight_mt' => 'float',
        'tare_weight_mt' => 'float',
        'net_weight_mt' => 'float',
        'kernel_moisture_percent' => 'float',
        'dirt_impurities_percent' => 'float',
        'ffa_percent' => 'float',
        'broken_kernel_percent' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function kernelDispatchRecord(): BelongsTo
    {
        return $this->belongsTo(KernelDispatchRecord::class);
    }
}
