<?php

namespace Database\Factories;

use App\Models\BusinessUnit;
use App\Models\Machinery;
use App\Models\MachineryGroup;
use App\Models\Station;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Machinery>
 *
 * Test infrastructure — created by test-writer-agent for
 * screen-030--kelola-station (no prior screen needed a Machinery
 * factory; StationServiceTest/KelolaStationTest's delete-guard tests
 * need this to exist so they can seed a Machinery row pointing at a
 * given Station and prove StationService::delete()'s 409
 * STATION_HAS_MACHINERY guard fires independently of MachineryGroup).
 *
 * EXPANDED by screen-031--kelola-machinery's test suite (entity-catalog
 * v4 — `App\Models\Machinery` is no longer a minimal placeholder, see that
 * model's own docblock): added a default `equipment_code` (globally
 * unique, mirrors MachineryGroupFactory's `group_code` default
 * precedent), and `forMachineryGroup()` now sets `machinery_group_id` via
 * a plain ->state() mass-assignment (that column is fillable as of this
 * screen) rather than the previous forceFill()->save() workaround —
 * behaviorally identical for every pre-existing caller
 * (MachineryGroupServiceTest / KelolaMachineryGroupTest, Api and
 * Livewire), just simpler now that the column is mass-assignable.
 *
 * `picture`/`notes` remain nullable and left unset by default since most
 * callers never assert on them.
 */
class MachineryFactory extends Factory
{
    protected $model = Machinery::class;

    public function definition(): array
    {
        return [
            'station_id' => Station::factory(),
            'name' => 'Machine '.$this->faker->unique()->numerify('##'),
            'equipment_code' => 'EQ-'.$this->faker->unique()->numerify('######'),
        ];
    }

    public function forMachineryGroup(MachineryGroup|string $machineryGroup): self
    {
        $machineryGroupId = $machineryGroup instanceof MachineryGroup ? $machineryGroup->id : $machineryGroup;

        return $this->state(fn () => ['machinery_group_id' => $machineryGroupId]);
    }

    /**
     * Sets machinery_group_id/station_id/business_unit_id consistently
     * from a given (or newly created) MachineryGroup — mirrors what
     * App\Services\MachineryService::create() derives server-side, for
     * tests that need a fully hierarchy-consistent row without going
     * through the service (e.g. seeding fixtures for list/detail
     * endpoint tests).
     */
    public function forFullMachineryGroup(?MachineryGroup $machineryGroup = null): self
    {
        $machineryGroup ??= MachineryGroup::factory()->create();

        return $this->state(fn () => [
            'machinery_group_id' => $machineryGroup->id,
            'station_id' => $machineryGroup->station_id,
            'business_unit_id' => $machineryGroup->business_unit_id,
        ]);
    }

    /**
     * Sets an explicit `equipment_code` — used for uniqueness-conflict
     * fixtures, mirrors MachineryGroupFactory::withGroupCode().
     */
    public function withEquipmentCode(string $equipmentCode): self
    {
        return $this->state(fn () => ['equipment_code' => $equipmentCode]);
    }
}
