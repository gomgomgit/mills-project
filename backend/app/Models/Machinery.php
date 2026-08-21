<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Machinery — belongs to a Station AND a MachineryGroup (and,
 * transitively, a Business Unit copied from the MachineryGroup). Has many
 * MachineryInsurance and MachineryTaxPurchase (child rows managed
 * directly inside the Machinery form, replace-all semantics — see
 * App\Services\MachineryService).
 *
 * Originally created as a MINIMAL PLACEHOLDER by screen-030--kelola-station
 * (station_id/name/picture/notes only) purely so Station::machinery() and
 * StationService::delete()'s delete-guard had a model to resolve against.
 * screen-033--kelola-machinery-group later added the `machinery_group_id`
 * column via migration but deliberately left it OFF $fillable, leaving
 * that — and the rest of this entity's full field set — for
 * screen-031--kelola-machinery (this screen) to claim.
 *
 * EXPANDED by screen-031--kelola-machinery via
 * 2026_08_19_000007_add_fields_to_machinery_table.php: claimed
 * `machinery_group_id` into $fillable, added `business_unit_id`,
 * `equipment_code` (globally unique), `description`, and 17 nullable
 * technical-spec fields. The pre-existing `station_id`/`name`/`picture`/
 * `notes` fields and `station()` relationship are UNCHANGED — this file
 * was extended, not rewritten, per screen-033's own docblock precedent.
 *
 * 2026-08-20 (entity-catalog v10): `business_unit_id` was RENAMED to
 * `production_line_id` — see App\Models\MachineryGroup's own docblock for
 * the full rationale (Production Line inserted between Business Unit and
 * Station). `businessUnit()` is replaced by `productionLine()` below.
 *
 * IMPORTANT — `station_id` and `production_line_id` are NEVER set from
 * user/request input. MachineryService::create()/::update() always
 * overwrite both server-side with the selected MachineryGroup's own
 * station_id/production_line_id, even though both are technically
 * mass-assignable here — this is a structural hierarchy-consistency
 * guarantee enforced at the service layer, not the model layer (mirrors
 * how App\Models\MachineryGroup's own production_line_id is handled).
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
        'machinery_group_id',
        'production_line_id',
        'equipment_code',
        'name',
        'description',
        'picture',
        'notes',
        'registration_no',
        'make',
        'model',
        'equipment_type',
        'part_no',
        'serial_no',
        'gearbox',
        'motor',
        'mounting',
        'rpm',
        'chain',
        'capacity',
        'brand',
        'year_made',
        'fixed_asset',
        'control_activity',
        'owner_ite',
    ];

    protected $casts = [
        'rpm' => 'float',
        'year_made' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function machineryGroup(): BelongsTo
    {
        return $this->belongsTo(MachineryGroup::class);
    }

    public function productionLine(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class);
    }

    public function insurances(): HasMany
    {
        return $this->hasMany(MachineryInsurance::class);
    }

    public function taxPurchases(): HasMany
    {
        return $this->hasMany(MachineryTaxPurchase::class);
    }
}
