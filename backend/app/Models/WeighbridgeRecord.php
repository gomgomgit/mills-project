<?php

namespace App\Models;

use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Weighbridge Record — log-sheet entry for the weighbridge station.
 *
 * Constraint: net_weight = gross_weight - tare_weight jika keduanya terisi.
 * Enforced via a `saving` model event below, so the constraint holds
 * regardless of which layer (API, seeder, tinker, ...) persists the model.
 */
class WeighbridgeRecord extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'station_id',
        'wb_card_number',
        'weighbridge_type',
        'record_datetime',
        'vehicle_number',
        'driver_name',
        'estate_supplier',
        'destination',
        'division',
        'block',
        'gross_weight',
        'tare_weight',
        'net_weight',
        'quantity',
        'checked_by',
        'acknowledged_by',
        'status',
        'created_by',
    ];

    protected $casts = [
        'record_datetime' => 'datetime',
        'gross_weight' => 'float',
        'tare_weight' => 'float',
        'net_weight' => 'float',
        'quantity' => 'float',
        'status' => RecordStatus::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $record) {
            if ($record->gross_weight !== null && $record->tare_weight !== null) {
                $record->net_weight = $record->gross_weight - $record->tare_weight;
            }
        });
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
