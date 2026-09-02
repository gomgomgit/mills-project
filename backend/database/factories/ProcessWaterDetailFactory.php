<?php

namespace Database\Factories;

use App\Models\ProcessWaterDetail;
use App\Models\ProcessWaterRecord;
use App\Services\ProcessWaterRecordService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcessWaterDetail>
 *
 * Test infrastructure for screen-092--data-browser-process-water-web /
 * screen-102--detail-process-water-web / screen-112--form-process-water-web.
 * Mirrors ThreshingDetailFactory.php's structure — ProcessWaterDetail has no
 * `saving` guard of its own (the constraint, if any, lives on
 * ProcessWaterRecord).
 *
 * `time_slot` defaults to a random one of the 24 canonical slots
 * (ProcessWaterRecordService::canonicalTimeSlots()) rather than an
 * arbitrary faker time string, so seeded rows always match the real app's
 * grid shape. `filled()` state sets a non-null raw_water_flow_m3h, for
 * tests needing a "filled" row (filled_slot_count / minimum-one-row
 * checks).
 */
class ProcessWaterDetailFactory extends Factory
{
    protected $model = ProcessWaterDetail::class;

    public function definition(): array
    {
        $slots = ProcessWaterRecordService::canonicalTimeSlots();

        return [
            'process_water_record_id' => ProcessWaterRecord::factory(),
            'time_slot' => $this->faker->randomElement($slots),
            'shift' => null,
            'inspector_id' => null,
            'raw_water_flow_m3h' => null,
            'clarified_water_flow_m3h' => null,
            'softener_inlet_ph' => null,
            'softener_outlet_hardness_ppm' => null,
            'alum_dosing_kgh' => null,
            'polymer_dosing_gh' => null,
            'boiler_feed_tank_temp_c' => null,
            'boiler_feed_water_ph' => null,
            'boiler_feed_tds_ppm' => null,
            'action_taken_status' => null,
            'findings' => null,
        ];
    }

    public function forRecord(ProcessWaterRecord|string $record): self
    {
        return $this->state(fn () => [
            'process_water_record_id' => $record instanceof ProcessWaterRecord ? $record->id : $record,
        ]);
    }

    public function timeSlot(string $timeSlot): self
    {
        return $this->state(fn () => ['time_slot' => $timeSlot]);
    }

    /**
     * A "filled" row (at least one reading column non-null) — for
     * filled_slot_count / minimum-one-row validation tests.
     */
    public function filled(): self
    {
        return $this->state(fn () => ['raw_water_flow_m3h' => $this->faker->randomFloat(2, 10, 60)]);
    }
}
