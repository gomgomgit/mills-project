<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'username' => $this->faker->unique()->userName(),
            'password_hash' => Hash::make('Passw0rd!'),
            'name' => $this->faker->name(),
            // Default to a web-screen actor (this screen's actors are
            // admin/supervisor/mill_management — operator does not use the
            // web login screen).
            'role' => UserRole::Supervisor,
            'business_unit_id' => BusinessUnit::factory(),
            'is_active' => true,
        ];
    }

    /**
     * Set a known plain-text password (hashed) so tests can log in with it.
     */
    public function password(string $plain): self
    {
        return $this->state(fn () => [
            'password_hash' => Hash::make($plain),
        ]);
    }

    public function role(UserRole $role): self
    {
        return $this->state(fn () => [
            'role' => $role,
        ]);
    }

    public function inactive(): self
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function forBusinessUnit(BusinessUnit|string $businessUnit): self
    {
        return $this->state(fn () => [
            'business_unit_id' => $businessUnit instanceof BusinessUnit ? $businessUnit->id : $businessUnit,
        ]);
    }
}
