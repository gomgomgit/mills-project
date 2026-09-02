<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CPO Dispatch Detail — Baris log per-kejadian pengiriman CPO dalam satu CPO Dispatch Record.
 * Rows are added manually via 'Tambah baris' per event
 * (unbounded, not a pre-generated 24-row grid), mirroring
 * CagesTippedTime/CagesTrackRecord's pattern.
 */
class CpoDispatchDetail extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'cpo_dispatch_record_id',
        'event_date',
        'shift',
        'time_in',
        'time_out',
        'waybill_number',
        'tanker_plate_no',
        'transport_company',
        'driver_name',
        'storage_tank_source',
        'seal_no_top',
        'seal_no_bottom',
        'gross_weight_mt',
        'tare_weight_mt',
        'net_weight_mt',
        'ffa_percent',
        'moisture_percent',
        'impurities_percent',
        'dobi',
        'destination_buyer',
        'weighbridge_operator',
        'findings',
    ];

    protected $casts = [
        'event_date' => 'date',
        'gross_weight_mt' => 'float',
        'tare_weight_mt' => 'float',
        'net_weight_mt' => 'float',
        'ffa_percent' => 'float',
        'moisture_percent' => 'float',
        'impurities_percent' => 'float',
        'dobi' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function cpoDispatchRecord(): BelongsTo
    {
        return $this->belongsTo(CpoDispatchRecord::class);
    }
}
