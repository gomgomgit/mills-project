<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MachineryGroup — belongs to a Station (and, transitively, a Business
 * Unit copied from that Station), has many Machinery.
 *
 * Originally created by screen-030--kelola-station as a MINIMAL
 * PLACEHOLDER (see git history / that screen's implementation_notes) —
 * only `business_unit_id`/`station_id` — purely so
 * Station::machineryGroups() and StationService::delete()'s delete-guard
 * had a model to resolve against.
 *
 * EXPANDED by screen-033--kelola-machinery-group (this screen) via
 * 2026_08_19_000006_add_fields_to_machinery_groups_table.php: added
 * `group_code` (globally unique), `description`, `unit`,
 * `workshop_factor`, `cost_per_equipment` to $fillable/$casts, and the
 * `machinery()` relationship below. The pre-existing
 * `business_unit_id`/`station_id` fields and `station()`/`businessUnit()`
 * relationships are UNCHANGED — this file was extended, not rewritten,
 * per that screen's own docblock precedent.
 *
 * IMPORTANT — `business_unit_id` is NEVER set from user/request input.
 * MachineryGroupService::create()/::update() always overwrite it
 * server-side with the selected Station's own business_unit_id, even
 * though it is technically mass-assignable here — this is a structural
 * hierarchy-consistency guarantee enforced at the service layer, not the
 * model layer (mirrors how `code`/`is_active` etc. on sibling entities are
 * mass-assignable but still fully validated by their own Service class).
 */
class MachineryGroup extends Model
{
    use HasFactory, HasUuids;

    /**
     * Explicit table name — same defensive convention as
     * App\Models\Machinery's own $table declaration (kept explicit for
     * consistency/safety across sibling models, even though the default
     * plural inference ("machinery_groups") would already be correct
     * here).
     */
    protected $table = 'machinery_groups';

    protected $fillable = [
        'business_unit_id',
        'station_id',
        'group_code',
        'description',
        'unit',
        'workshop_factor',
        'cost_per_equipment',
    ];

    protected $casts = [
        'workshop_factor' => 'float',
        'cost_per_equipment' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /**
     * machinery() — added by screen-033--kelola-machinery-group so
     * MachineryGroupService::listMachineryGroups()'s withCount('machinery')
     * and MachineryGroupService::delete()'s delete-guard (via
     * Machinery::where('machinery_group_id', $id)->count(), not this
     * relationship directly, but conceptually the same edge — mirrors
     * Station::machineryGroups()'s equivalent precedent) both have a
     * relationship to hang off of.
     */
    public function machinery(): HasMany
    {
        return $this->hasMany(Machinery::class);
    }
}
