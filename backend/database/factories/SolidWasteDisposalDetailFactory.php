<?php

namespace Database\Factories;

use App\Models\SolidWasteDisposalDetail;
use App\Models\SolidWasteDisposalRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SolidWasteDisposalDetail>
 *
 * Test infrastructure for screen-091--data-browser-solid-waste-disposal-web.
 * Mirrors CagesTippedTimeFactory's structure — a plain leaf factory, no
 * `saving` guard of its own (that constraint, if any, lives on
 * SolidWasteDisposalRecord).
 */
class SolidWasteDisposalDetailFactory extends Factory
{
    protected $model = SolidWasteDisposalDetail::class;

    public function definition(): array
    {
        $gross = $this->faker->randomFloat(2, 5, 20);
        $tare = $this->faker->randomFloat(2, 1, 4);

        return [
            'solid_waste_disposal_record_id' => SolidWasteDisposalRecord::factory(),
            'event_date' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'shift' => $this->faker->randomElement(['Shift 1', 'Shift 2', 'Shift 3']),
            'weighbridge_ticket_no' => 'WT-'.$this->faker->unique()->numerify('#####'),
            'vehicle_no' => strtoupper($this->faker->bothify('B ####??')),
            'driver_name' => $this->faker->name(),
            'solid_waste_type' => $this->faker->randomElement(['Empty Bunch', 'Fibre', 'Shell', 'Ash']),
            'source_station' => $this->faker->randomElement(['Threshing', 'Pressing', 'Boiler Room']),
            'gross_weight_mt' => $gross,
            'tare_weight_mt' => $tare,
            'net_weight_mt' => $gross - $tare,
            'disposal_utilization_site' => $this->faker->company(),
            'purpose_end_use' => $this->faker->randomElement(['Composting', 'Landfill', 'Fuel']),
            'gate_pass_no' => 'GP-'.$this->faker->unique()->numerify('#####'),
            'security_seal_no' => 'SS-'.$this->faker->unique()->numerify('#####'),
            'operator_id' => $this->faker->numerify('OP-###'),
            'remarks' => $this->faker->optional()->sentence(),
            'findings' => $this->faker->optional()->sentence(),
        ];
    }

    public function forRecord(SolidWasteDisposalRecord|string $record): self
    {
        return $this->state(fn () => [
            'solid_waste_disposal_record_id' => $record instanceof SolidWasteDisposalRecord ? $record->id : $record,
        ]);
    }
}
