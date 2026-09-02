<?php

namespace Database\Factories;

use App\Models\CpoDispatchDetail;
use App\Models\CpoDispatchRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CpoDispatchDetail>
 *
 * Test infrastructure for screen-094--data-browser-cpo-dispatch-web.
 * Mirrors KernelDispatchDetailFactory's structure — a plain leaf factory,
 * no `saving` guard of its own (that constraint, if any, lives on
 * CpoDispatchRecord).
 */
class CpoDispatchDetailFactory extends Factory
{
    protected $model = CpoDispatchDetail::class;

    public function definition(): array
    {
        $gross = $this->faker->randomFloat(2, 5, 20);
        $tare = $this->faker->randomFloat(2, 1, 4);

        return [
            'cpo_dispatch_record_id' => CpoDispatchRecord::factory(),
            'event_date' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'shift' => $this->faker->randomElement(['Shift 1', 'Shift 2', 'Shift 3']),
            'time_in' => $this->faker->time('H:i'),
            'time_out' => $this->faker->time('H:i'),
            'waybill_number' => 'WB-'.$this->faker->unique()->numerify('#####'),
            'tanker_plate_no' => strtoupper($this->faker->bothify('B ####??')),
            'transport_company' => $this->faker->company(),
            'driver_name' => $this->faker->name(),
            'storage_tank_source' => $this->faker->numerify('TANK-##'),
            'seal_no_top' => 'ST-'.$this->faker->unique()->numerify('#####'),
            'seal_no_bottom' => 'SB-'.$this->faker->unique()->numerify('#####'),
            'gross_weight_mt' => $gross,
            'tare_weight_mt' => $tare,
            'net_weight_mt' => $gross - $tare,
            'ffa_percent' => $this->faker->randomFloat(2, 1, 5),
            'moisture_percent' => $this->faker->randomFloat(2, 0, 1),
            'impurities_percent' => $this->faker->randomFloat(2, 0, 1),
            'dobi' => $this->faker->randomFloat(2, 2, 3),
            'destination_buyer' => $this->faker->company(),
            'weighbridge_operator' => $this->faker->name(),
            'findings' => $this->faker->optional()->sentence(),
        ];
    }

    public function forRecord(CpoDispatchRecord|string $record): self
    {
        return $this->state(fn () => [
            'cpo_dispatch_record_id' => $record instanceof CpoDispatchRecord ? $record->id : $record,
        ]);
    }
}
