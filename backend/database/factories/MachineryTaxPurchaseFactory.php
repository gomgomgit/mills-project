<?php

namespace Database\Factories;

use App\Models\Machinery;
use App\Models\MachineryTaxPurchase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MachineryTaxPurchase>
 *
 * Test infrastructure for screen-031--kelola-machinery's child-row
 * ("tax_purchases") coverage — MachineryTaxPurchase is a brand-new
 * entity, no prior screen needed this factory.
 */
class MachineryTaxPurchaseFactory extends Factory
{
    protected $model = MachineryTaxPurchase::class;

    public function definition(): array
    {
        return [
            'machinery_id' => Machinery::factory(),
            'purchase_date' => $this->faker->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'purchase_cost' => $this->faker->randomFloat(2, 1000, 500000),
            'policy_type' => $this->faker->randomElement(['Cash', 'Credit', 'Leasing']),
            'contact_name' => $this->faker->name(),
            'contact_phone' => $this->faker->phoneNumber(),
            'contact_fax' => $this->faker->phoneNumber(),
            'contact_email' => $this->faker->safeEmail(),
        ];
    }

    public function forMachinery(Machinery|string $machinery): self
    {
        return $this->state(fn () => [
            'machinery_id' => $machinery instanceof Machinery ? $machinery->id : $machinery,
        ]);
    }
}
