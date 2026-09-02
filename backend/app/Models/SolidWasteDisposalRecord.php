<?php

namespace App\Models;

use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Solid Waste Disposal Record — log-sheet header entry for the SolidWasteDisposal station; has many SolidWasteDisposalDetail.
 *
 * Note: the "minimal satu baris detail sebelum status=saved" constraint from
 * the entity-catalog is intentionally NOT enforced here via a static::saving()
 * hook (unlike ThreshingRecord/CagesTrackRecord) — that validation is left to
 * the Phase 4 screen-implementation pass for this entity, per
 * entity-models-agent scope (schema/model structure only).
 */
class SolidWasteDisposalRecord extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'station_id',
        'solid_waste_disposal_id',
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

    public function solidWasteDisposalDetails(): HasMany
    {
        return $this->hasMany(SolidWasteDisposalDetail::class);
    }
}
