<?php

namespace Database\Factories;

use App\Models\BusinessUnit;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessUnit>
 */
class BusinessUnitFactory extends Factory
{
    protected $model = BusinessUnit::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => $this->faker->unique()->city() . ' Mill',
            'code' => strtoupper($this->faker->unique()->lexify('BU-????')),
        ];
    }
}
