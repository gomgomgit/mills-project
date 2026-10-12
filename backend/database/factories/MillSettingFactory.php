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
        // app_name dicabut 2026-10-12; tidak ada kolom lain yang wajib
        // diisi, jadi baris dapat dibuat hanya dengan relasinya.
        return [
            'business_unit_id' => BusinessUnit::factory(),
        ];
    }

    public function forBusinessUnit(BusinessUnit|string $businessUnit): self
    {
        return $this->state(fn () => [
            'business_unit_id' => $businessUnit instanceof BusinessUnit ? $businessUnit->id : $businessUnit,
        ]);
    }
}
