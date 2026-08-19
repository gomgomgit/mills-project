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
 * Cages Track Record — log-sheet entry for the cages-track station; has many
 * Cages Tipped Times.
 *
 * Constraint: minimal satu cages-tipped-time sebelum status=saved.
 * Enforced via a `saving` model event: a record cannot transition to
 * RecordStatus::Saved (or be created directly as Saved) without at least one
 * related CagesTippedTime already persisted.
 */
class CagesTrackRecord extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'station_id',
        'cages_track_number',
        'date',
        'tippler_start_time',
        'tippler_stop_time',
        'cages_out',
        'cages_tipped',
        'note',
        'checked_by',
        'acknowledged_by',
        'status',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'tippler_start_time' => 'datetime',
        'tippler_stop_time' => 'datetime',
        'cages_out' => 'integer',
        'cages_tipped' => 'integer',
        'status' => RecordStatus::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $record) {
            if ($record->status === RecordStatus::Saved) {
                $tippedTimeCount = $record->exists
                    ? $record->cagesTippedTimes()->count()
                    : 0;

                if ($tippedTimeCount === 0) {
                    throw ValidationException::withMessages([
                        'status' => 'Cages track record membutuhkan minimal satu cages tipped time sebelum status diubah menjadi saved.',
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

    public function cagesTippedTimes(): HasMany
    {
        return $this->hasMany(CagesTippedTime::class);
    }
}
