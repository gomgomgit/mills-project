<?php

namespace Database\Factories;

use App\Models\BusinessUnit;
use App\Models\ProductionLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionLine>
 *
 * Test infrastructure — added 2026-08-20 alongside ProductionLine itself
 * (entity-catalog v9, Production Line inserted between Business Unit and
 * Station in the hierarchy). StationFactory::definition() depends on this
 * factory for its own required `production_line_id` — see that class.
 */
class ProductionLineFactory extends Factory
{
    protected $model = ProductionLine::class;

    public function definition(): array
    {
        return [
            'business_unit_id' => BusinessUnit::factory(),
            'name' => 'Line '.$this->faker->unique()->numerify('##'),
            'code' => null,
            'description' => null,
        ];
    }

    public function forBusinessUnit(BusinessUnit|string $businessUnit): self
    {
        return $this->state(fn () => [
            'business_unit_id' => $businessUnit instanceof BusinessUnit ? $businessUnit->id : $businessUnit,
        ]);
    }

    public function withCode(string $code): self
    {
        return $this->state(fn () => ['code' => $code]);
    }
}
