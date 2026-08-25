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
 * Threshing Record — log-sheet header entry for the threshing station; has many Threshing Details
 * (one row per hourly time-slot).
 *
 * Constraint: minimal satu threshing-detail sebelum status=saved.
 * Enforced via a `saving` model event: a record cannot transition to
 * RecordStatus::Saved (or be created directly as Saved) without at least one
 * related ThreshingDetail already persisted.
 */
class ThreshingRecord extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'station_id',
        'thresher_id',
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
                    ? $record->threshingDetails()->count()
                    : 0;

                if ($detailCount === 0) {
                    throw ValidationException::withMessages([
                        'status' => 'Threshing record membutuhkan minimal satu threshing detail sebelum status diubah menjadi saved.',
                    ]);
                }
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

    public function threshingDetails(): HasMany
    {
        return $this->hasMany(ThreshingDetail::class);
    }
}
