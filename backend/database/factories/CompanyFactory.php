<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Corporate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'corporate_id' => Corporate::factory(),
            'name' => $this->faker->companySuffix() . ' ' . $this->faker->company(),
        ];
    }
}
