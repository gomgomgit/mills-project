<?php

namespace App\Models;

use App\Enums\Uom;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Grading Detail — line item (grading parameter + quantity/percentage)
 * belonging to a Grading Record.
 *
 * `uom` is a snapshot copy of the related GradingParameter's uom at
 * selection time (stored independently, not derived live via the
 * relationship) — so historical rows stay stable if the master parameter's
 * uom changes later.
 */
class GradingDetail extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'grading_record_id',
        'grading_parameter_id',
        'quantity',
        'uom',
        'percentage',
    ];

    protected $casts = [
        'quantity' => 'float',
        'uom' => Uom::class,
        'percentage' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function gradingRecord(): BelongsTo
    {
        return $this->belongsTo(GradingRecord::class);
    }

    public function gradingParameter(): BelongsTo
    {
        return $this->belongsTo(GradingParameter::class);
    }
}
