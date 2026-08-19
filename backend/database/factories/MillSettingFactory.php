<?php

namespace Database\Factories;

use App\Models\BusinessUnit;
use App\Models\MillSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MillSetting>
 *
 * screen-034--mills-setting test infrastructure.
 */
class MillSettingFactory extends Factory
{
    protected $model = MillSetting::class;

    public function definition(): array
    {
        return [
            'business_unit_id' => BusinessUnit::factory(),
            'app_name' => $this->faker->company(),
            'jumlah_cages' => 5,
        ];
    }

    public function forBusinessUnit(BusinessUnit|string $businessUnit): self
    {
        return $this->state(fn () => [
            'business_unit_id' => $businessUnit instanceof BusinessUnit ? $businessUnit->id : $businessUnit,
        ]);
    }

    public function withJumlahCages(int $jumlahCages): self
    {
        return $this->state(fn () => ['jumlah_cages' => $jumlahCages]);
    }
}
