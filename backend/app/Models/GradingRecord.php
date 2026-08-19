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
 * Grading Record — log-sheet entry for the grading station; has many Grading Details.
 *
 * Constraint: minimal satu grading-detail sebelum status=saved.
 * Enforced via a `saving` model event: a record cannot transition to
 * RecordStatus::Saved (or be created directly as Saved) without at least one
 * related GradingDetail already persisted.
 */
class GradingRecord extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'station_id',
        'grading_number',
        'date',
        'weighbridge_record_id',
        'license_plate_no',
        'vehicle_code',
        'estate_supplier',
        'division',
        'netto',
        'quantity',
        'note',
        'checked_by',
        'acknowledged_by',
        'status',
        'created_by',
    ];

    protected $casts = [
        'date' => 'datetime',
        'netto' => 'float',
        'quantity' => 'float',
        'status' => RecordStatus::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $record) {
            if ($record->status === RecordStatus::Saved) {
                $detailCount = $record->exists
                    ? $record->gradingDetails()->count()
                    : 0;

                if ($detailCount === 0) {
                    throw ValidationException::withMessages([
                        'status' => 'Grading record membutuhkan minimal satu grading detail sebelum status diubah menjadi saved.',
                    ]);
                }
            }
        });
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function weighbridgeRecord(): BelongsTo
    {
        return $this->belongsTo(WeighbridgeRecord::class);
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

    public function gradingDetails(): HasMany
    {
        return $this->hasMany(GradingDetail::class);
    }
}
