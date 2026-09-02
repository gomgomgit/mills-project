<?php

namespace App\Models;

use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sterilizer Record — log-sheet header entry for the Sterilizer station;
 * has many SterilizerDetail. Mirrors CpoDispatchRecord's structure exactly
 * — event-log pattern, unbounded detail rows added manually per
 * sterilization cycle.
 *
 * Note: the "minimal satu baris detail sebelum status=saved" constraint
 * from the entity-catalog is intentionally NOT enforced here via a
 * static::saving() hook (same as CpoDispatchRecord) — that validation is
 * left to the Phase 4 screen-implementation pass for this entity.
 */
class SterilizerRecord extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'station_id',
        'sterilizer_id',
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

    public function sterilizerDetails(): HasMany
    {
        return $this->hasMany(SterilizerDetail::class);
    }
}
