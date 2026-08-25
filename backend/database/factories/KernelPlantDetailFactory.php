<?php

namespace Database\Factories;

use App\Models\KernelPlantDetail;
use App\Models\KernelPlantRecord;
use App\Services\KernelPlantRecordService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KernelPlantDetail>
 *
 * Test infrastructure for screen-052--data-browser-kernel-plant-web /
 * screen-056--detail-kernel-plant-web / screen-060--form-kernel-plant-web.
 * Mirrors DepricarpingDetailFactory.php's structure — KernelPlantDetail has
 * no `saving` guard of its own (the constraint lives on KernelPlantRecord).
 *
 * `time_slot` defaults to a random one of the 24 canonical slots
 * (KernelPlantRecordService::canonicalTimeSlots()) rather than an
 * arbitrary faker time string, so seeded rows always match the real app's
 * grid shape. `filled()` state sets a non-null ripple_mill_1_amps, for
 * tests needing a "filled" row (filled_slot_count / minimum-one-row
 * checks).
 */
class KernelPlantDetailFactory extends Factory
{
    protected $model = KernelPlantDetail::class;

    public function definition(): array
    {
        $slots = KernelPlantRecordService::canonicalTimeSlots();

        return [
            'kernel_plant_record_id' => KernelPlantRecord::factory(),
            'time_slot' => $this->faker->randomElement($slots),
            'ripple_mill_1_amps' => null,
            'ripple_mill_2_amps' => null,
            'claybath_hydro_sg' => null,
            'kernel_silo_1_temp_c' => null,
            'kernel_silo_2_temp_c' => null,
            'kernel_moisture_percent' => null,
            'shell_loss_percent' => null,
            'downtime_minutes' => null,
            'findings' => null,
        ];
    }

    public function forRecord(KernelPlantRecord|string $record): self
    {
        return $this->state(fn () => [
            'kernel_plant_record_id' => $record instanceof KernelPlantRecord ? $record->id : $record,
        ]);
    }

    public function timeSlot(string $timeSlot): self
    {
        return $this->state(fn () => ['time_slot' => $timeSlot]);
    }

    /**
     * A "filled" row (at least one of the 9 reading columns non-null) —
     * for filled_slot_count / minimum-one-row validation tests.
     */
    public function filled(): self
    {
        return $this->state(fn () => ['ripple_mill_1_amps' => $this->faker->randomFloat(2, 20, 25)]);
    }
}
