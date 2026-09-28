<?php

namespace App\Models;

use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Process Quality Control Record — log-sheet header entry for the ProcessQualityControl station; has many ProcessQualityControlDetail.
 *
 * Note: the "minimal satu baris detail sebelum status=saved" constraint from
 * the entity-catalog is intentionally NOT enforced here via a static::saving()
 * hook (unlike ThreshingRecord/CagesTrackRecord) — that validation is left to
 * the Phase 4 screen-implementation pass for this entity, per
 * entity-models-agent scope (schema/model structure only).
 */
class ProcessQualityControlRecord extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'station_id',
        'production_line_id',
        'process_qc_id',
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

    public function processQualityControlDetails(): HasMany
    {
        return $this->hasMany(ProcessQualityControlDetail::class);
    }
}
