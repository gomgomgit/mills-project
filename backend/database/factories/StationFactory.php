<?php

namespace Database\Factories;

use App\Enums\StationType;
use App\Models\BusinessUnit;
use App\Models\Station;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Station>
 *
 * Test infrastructure — created by test-writer-agent for
 * screen-016--data-browser-weighbridge-web (no prior screen needed a
 * Station factory; WeighbridgeRecord::station() is a required belongsTo,
 * so WeighbridgeRecordFactory needs this to exist).
 */
class StationFactory extends Factory
{
    protected $model = Station::class;

    public function definition(): array
    {
        return [
            'business_unit_id' => BusinessUnit::factory(),
            'name' => 'Weighbridge '.$this->faker->unique()->numerify('##'),
            'type' => StationType::Weighbridge,
            'is_active' => true,
        ];
    }

    public function weighbridge(): self
    {
        return $this->state(fn () => ['type' => StationType::Weighbridge]);
    }

    public function forBusinessUnit(BusinessUnit|string $businessUnit): self
    {
        return $this->state(fn () => [
            'business_unit_id' => $businessUnit instanceof BusinessUnit ? $businessUnit->id : $businessUnit,
        ]);
    }
}
