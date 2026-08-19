<?php

namespace Database\Factories;

use App\Models\Machinery;
use App\Models\MachineryInsurance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MachineryInsurance>
 *
 * Test infrastructure for screen-031--kelola-machinery's child-row
 * ("insurances") coverage — MachineryInsurance is a brand-new entity, no
 * prior screen needed this factory.
 */
class MachineryInsuranceFactory extends Factory
{
    protected $model = MachineryInsurance::class;

    public function definition(): array
    {
        return [
            'machinery_id' => Machinery::factory(),
            'ownership' => $this->faker->company(),
            'insurance_policy_no' => 'POL-'.$this->faker->unique()->numerify('######'),
            'insurance_company' => $this->faker->company(),
            'insurance_expiry_date' => $this->faker->dateTimeBetween('now', '+2 years')->format('Y-m-d'),
            'premium' => $this->faker->randomFloat(2, 100, 5000),
            'amount_insured' => $this->faker->randomFloat(2, 1000, 500000),
        ];
    }

    public function forMachinery(Machinery|string $machinery): self
    {
        return $this->state(fn () => [
            'machinery_id' => $machinery instanceof Machinery ? $machinery->id : $machinery,
        ]);
    }
}
