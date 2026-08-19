<?php

namespace App\Models;

use App\Enums\StationType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Station — belongs to a Business Unit, has many Machinery.
 * Production station of one of the log-sheet types: weighbridge, grading, cages-track, other.
 */
class Station extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'business_unit_id',
        'name',
        'type',
        'is_active',
    ];

    protected $casts = [
        'type' => StationType::class,
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function machinery(): HasMany
    {
        return $this->hasMany(Machinery::class);
    }
}
