<?php

namespace Database\Factories;

use App\Models\Corporate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Corporate>
 */
class CorporateFactory extends Factory
{
    protected $model = Corporate::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company(),
        ];
    }
}
