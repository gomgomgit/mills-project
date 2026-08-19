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
 *
 * `code` and `description` were added by screen-030--kelola-station's
 * entity-catalog v4 rework
 * (2026_08_19_000004_add_fields_to_stations_table.php) — the pre-existing
 * `business_unit_id`/`name`/`type`/`is_active` fields and the
 * `businessUnit()`/`machinery()` relationships below are completely
 * unchanged by that rework.
 */
class Station extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'business_unit_id',
        'name',
        'type',
        'is_active',
        'code',
        'description',
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

    /**
     * machineryGroups() — added by screen-030--kelola-station so
     * StationService::listStations()'s withCount('machineryGroups') and
     * StationService::delete()'s delete-guard (via
     * MachineryGroup::where('station_id', $id)->count(), not this
     * relationship directly, but conceptually the same edge) both have a
     * relationship to hang off of. `MachineryGroup` is currently a
     * minimal placeholder model (see App\Models\MachineryGroup's
     * docblock) — screen-033--kelola-machinery-group will expand it
     * later without needing to touch this relationship.
     */
    public function machineryGroups(): HasMany
    {
        return $this->hasMany(MachineryGroup::class);
    }
}
