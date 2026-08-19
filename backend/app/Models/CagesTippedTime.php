<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cages Tipped Time — one hour-slot checklist row belonging to a Cages Track
 * Record. Each row covers a single whole hour (`tipped_hour`, 0-23, unique
 * per cages_track_record_id) and records which cage numbers were checked in
 * that hour (`checked_cage_numbers`, client-computed CSV text), plus two
 * client-computed-then-persisted counters: `total_cages` (count of entries
 * in checked_cage_numbers) and `cages_remain` (parent record's cages_tipped
 * minus total_cages).
 */
class CagesTippedTime extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'cages_track_record_id',
        'tipped_hour',
        'checked_cage_numbers',
        'total_cages',
        'cages_remain',
    ];

    protected $casts = [
        'tipped_hour' => 'integer',
        'checked_cage_numbers' => 'string',
        'total_cages' => 'integer',
        'cages_remain' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function cagesTrackRecord(): BelongsTo
    {
        return $this->belongsTo(CagesTrackRecord::class);
    }
}
