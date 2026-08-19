<?php

namespace Database\Factories;

use App\Models\Corporate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Corporate>
 *
 * Entity-catalog v4 rework (screen-027--kelola-corporate 3-tech-spec ver 2):
 * generates the full corporate field set (not just `name`) so factory-created
 * rows exercise realistically in tests that assert on any of the new columns
 * (e.g. logo_url presence, corporate_code uniqueness). `logo`, `created_by`,
 * and `updated_by` are deliberately left null by default — no fake file is
 * stored on disk just from instantiating a factory, and there is no
 * authenticated-user context to attribute audit columns to at factory time;
 * tests that care about those set them explicitly.
 */
class CorporateFactory extends Factory
{
    protected $model = Corporate::class;

    public function definition(): array
    {
        return [
            'corporate_code' => $this->faker->unique()->numerify('CORP-#####'),
            'name' => $this->faker->unique()->company(),
            'short_name' => $this->faker->companySuffix(),
            'leader_name' => $this->faker->name(),
            'lawyer_name' => $this->faker->name(),
            'address' => $this->faker->address(),
            'telephone_no' => $this->faker->phoneNumber(),
            'fax_no' => $this->faker->phoneNumber(),
            'contact_no' => $this->faker->phoneNumber(),
            'extension_no' => (string) $this->faker->numberBetween(100, 999),
            'email' => $this->faker->unique()->safeEmail(),
            'website' => $this->faker->url(),
            'map' => $this->faker->url(),
            'tax_register_no' => $this->faker->numerify('TAX-########'),
            'insurance_no' => $this->faker->numerify('INS-########'),
            'epf_employer' => $this->faker->numerify('EPF-########'),
            'socso_employer' => $this->faker->numerify('SOCSO-########'),
            'labor_union' => $this->faker->boolean() ? $this->faker->company().' Labor Union' : null,
            'logo' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
