<?php

namespace App\Models;

use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

/**
 * Kernel Plant Record — log-sheet header entry for the kernel-plant station; has many Kernel Plant Details
 * (one row per hourly time-slot).
 *
 * Constraint: minimal satu kernel-plant-detail sebelum status=saved.
 * Enforced via a `saving` model event: a record cannot transition to
 * RecordStatus::Saved (or be created directly as Saved) without at least one
 * related KernelPlantDetail already persisted.
 */
class KernelPlantRecord extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'station_id',
        'production_line_id',
        'kernel_plant_id',
        'date',
        'note',
        'checked_by',
        'acknowledged_by',
        'status',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'status' => RecordStatus::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $record) {
            if ($record->status === RecordStatus::Saved) {
                $detailCount = $record->exists
                    ? $record->kernelPlantDetails()->count()
                    : 0;

                if ($detailCount === 0) {
                    throw ValidationException::withMessages([
                        'status' => 'Kernel Plant record membutuhkan minimal satu kernel plant detail sebelum status diubah menjadi saved.',
                    ]);
                }
            }
        });
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    /**
     * The production line this record was written on — a point-in-time
     * SNAPSHOT stored on the row (2026_09_28_000041), NOT a derivation of
     * `station->production_line_id`. If the station is later moved to
     * another line, this keeps pointing at the line the record was actually
     * produced on. Read it from here, never through `station`.
     */
    public function productionLine(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class);
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

    public function kernelPlantDetails(): HasMany
    {
        return $this->hasMany(KernelPlantDetail::class);
    }
}
