<?php

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Station;
use App\Models\User;
use App\Models\WeighbridgeRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeighbridgeRecord>
 *
 * Test infrastructure — created by test-writer-agent for
 * screen-016--data-browser-weighbridge-web (no factory existed prior to
 * this screen). gross_weight/tare_weight/net_weight kept internally
 * consistent (net = gross - tare) even though WeighbridgeRecord::booted()
 * already enforces this on save() — makes definition() self-documenting.
 */
class WeighbridgeRecordFactory extends Factory
{
    protected $model = WeighbridgeRecord::class;

    public function definition(): array
    {
        $gross = $this->faker->randomFloat(2, 5000, 20000);
        $tare = $this->faker->randomFloat(2, 1000, 4000);

        $arrival = $this->faker->dateTimeBetween('-30 days', 'now');

        return [
            'station_id' => Station::factory(),
            'wb_card_number' => 'WB-'.$this->faker->unique()->numerify('######'),
            'arrival_datetime' => $arrival,
            'dispatch_datetime' => (clone $arrival)->modify('+1 hour'),
            'vehicle_number' => strtoupper($this->faker->bothify('B ####??')),
            'driver_name' => $this->faker->name(),
            'estate_supplier' => $this->faker->company(),
            'division' => $this->faker->word(),
            'block' => $this->faker->bothify('Blok ##'),
            'gross_weight' => $gross,
            'tare_weight' => $tare,
            'net_weight' => $gross - $tare,
            'quantity' => $this->faker->randomFloat(2, 1, 20),
            'status' => RecordStatus::Saved,
            'created_by' => User::factory(),
        ];
    }

    public function forStation(Station|string $station): self
    {
        return $this->state(fn () => [
            'station_id' => $station instanceof Station ? $station->id : $station,
        ]);
    }

    public function arrivedAt(\DateTimeInterface|string $datetime): self
    {
        return $this->state(fn () => ['arrival_datetime' => $datetime]);
    }

    public function status(RecordStatus $status): self
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
