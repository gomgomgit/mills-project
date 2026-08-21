<?php

namespace Database\Factories;

use App\Models\MachineryGroup;
use App\Models\Station;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MachineryGroup>
 *
 * Test infrastructure — created by test-writer-agent for
 * screen-030--kelola-station (no prior screen needed a MachineryGroup
 * factory; StationServiceTest/KelolaStationTest's delete-guard tests need
 * this to exist so they can seed a MachineryGroup row pointing at a given
 * Station and prove StationService::delete()'s 409 STATION_HAS_MACHINERY
 * guard fires).
 *
 * EXPANDED by screen-033--kelola-machinery-group's test suite: added a
 * default `group_code` (App\Models\MachineryGroup is no longer a minimal
 * placeholder — see that model's own docblock) and a `withGroupCode()`
 * state for uniqueness-conflict fixtures, mirroring
 * database/factories/StationFactory.php's `withCode()` precedent exactly.
 * `forStation()` is UNCHANGED.
 *
 * 2026-08-20 (entity-catalog v10): `business_unit_id` was RENAMED to
 * `production_line_id` — `definition()` derives it from the auto-created
 * Station's own `production_line_id`, mirroring
 * StationFactory::definition()'s identical
 * business_unit_id-derived-from-production_line_id closure pattern one
 * level down.
 */
class MachineryGroupFactory extends Factory
{
    protected $model = MachineryGroup::class;

    public function definition(): array
    {
        return [
            'station_id' => Station::factory(),
            'production_line_id' => function (array $attributes) {
                return Station::find($attributes['station_id'])?->production_line_id;
            },
            'group_code' => 'MG-'.$this->faker->unique()->numerify('####'),
        ];
    }

    public function forStation(Station|string $station): self
    {
        return $this->state(fn () => [
            'station_id' => $station instanceof Station ? $station->id : $station,
        ]);
    }

    /**
     * Sets an explicit `group_code` — used by
     * screen-033--kelola-machinery-group's test suite for
     * uniqueness-conflict fixtures.
     */
    public function withGroupCode(string $groupCode): self
    {
        return $this->state(fn () => ['group_code' => $groupCode]);
    }
}
