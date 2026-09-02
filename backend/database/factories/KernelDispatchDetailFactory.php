<?php

namespace Database\Factories;

use App\Models\KernelDispatchDetail;
use App\Models\KernelDispatchRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KernelDispatchDetail>
 *
 * Test infrastructure for screen-093--data-browser-kernel-dispatch-web.
 * Mirrors SolidWasteDisposalDetailFactory's structure — a plain leaf
 * factory, no `saving` guard of its own (that constraint, if any, lives on
 * KernelDispatchRecord).
 */
class KernelDispatchDetailFactory extends Factory
{
    protected $model = KernelDispatchDetail::class;

    public function definition(): array
    {
        $gross = $this->faker->randomFloat(2, 5, 20);
        $tare = $this->faker->randomFloat(2, 1, 4);

        return [
            'kernel_dispatch_record_id' => KernelDispatchRecord::factory(),
            'event_date' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'shift' => $this->faker->randomElement(['Shift 1', 'Shift 2', 'Shift 3']),
            'weighbridge_ticket_no' => 'WT-'.$this->faker->unique()->numerify('#####'),
            'waybill_number' => 'WB-'.$this->faker->unique()->numerify('#####'),
            'transporter_contractor' => $this->faker->company(),
            'vehicle_plate_no' => strtoupper($this->faker->bothify('B ####??')),
            'driver_name' => $this->faker->name(),
            'silo_source_id' => $this->faker->numerify('SILO-##'),
            'destination_buyer' => $this->faker->company(),
            'gross_weight_mt' => $gross,
            'tare_weight_mt' => $tare,
            'net_weight_mt' => $gross - $tare,
            'kernel_moisture_percent' => $this->faker->randomFloat(2, 5, 8),
            'dirt_impurities_percent' => $this->faker->randomFloat(2, 0, 2),
            'ffa_percent' => $this->faker->randomFloat(2, 1, 5),
            'broken_kernel_percent' => $this->faker->randomFloat(2, 0, 15),
            'security_seal_no_top' => 'SST-'.$this->faker->unique()->numerify('#####'),
            'security_seal_no_bottom' => 'SSB-'.$this->faker->unique()->numerify('#####'),
            'weighbridge_operator_id' => $this->faker->numerify('OP-###'),
            'remarks_gate_status' => $this->faker->randomElement(['released', 'not_released']),
            'findings' => $this->faker->optional()->sentence(),
        ];
    }

    public function forRecord(KernelDispatchRecord|string $record): self
    {
        return $this->state(fn () => [
            'kernel_dispatch_record_id' => $record instanceof KernelDispatchRecord ? $record->id : $record,
        ]);
    }
}
