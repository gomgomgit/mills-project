<?php

namespace Database\Factories;

use App\Enums\Uom;
use App\Models\GradingParameter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradingParameter>
 *
 * Generates arbitrary test data — not the canonical 16 rows (see
 * database/seeders/GradingParameterSeeder.php for those).
 */
class GradingParameterFactory extends Factory
{
    protected $model = GradingParameter::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(3, true),
            'uom' => $this->faker->randomElement(Uom::cases()),
            'sort_order' => $this->faker->unique()->numberBetween(1, 1000),
        ];
    }
}
