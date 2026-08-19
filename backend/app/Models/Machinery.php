<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Machinery — belongs to a Station.
 */
class Machinery extends Model
{
    use HasFactory, HasUuids;

    /**
     * "machinery" is an uncountable noun; Eloquent's pluralizer could infer
     * "machineries", so the table name is pinned explicitly to be safe.
     */
    protected $table = 'machinery';

    protected $fillable = [
        'station_id',
        'name',
        'picture',
        'notes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }
}
