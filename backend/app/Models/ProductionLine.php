<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ProductionLine — belongs to a Business Unit, has many Station.
 *
 * entity-catalog v9 (2026-08-20): new hierarchy level inserted between
 * Business Unit and Station (Business Unit → Production Line → Station →
 * Machinery Group → Machinery) — one Business Unit/mill can have several
 * Production Lines, and each Production Line has its own full set of
 * stations (the 15 canonical stations are now auto-provisioned per
 * Production Line, not per Business Unit — see ProductionLineService).
 */
class ProductionLine extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'business_unit_id',
        'name',
        'code',
        'description',
        'created_by',
        'updated_by',
    ];

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function stations(): HasMany
    {
        return $this->hasMany(Station::class);
    }
}
