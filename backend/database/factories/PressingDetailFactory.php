<?php

namespace Database\Factories;

use App\Models\PressingDetail;
use App\Models\PressingRecord;
use App\Services\PressingRecordService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PressingDetail>
 *
 * Test infrastructure for screen-050--data-browser-pressing-web /
 * screen-054--detail-pressing-web / screen-058--form-pressing-web.
 * Mirrors ThreshingDetailFactory.php's structure — PressingDetail has no
 * `saving` guard of its own (the constraint lives on PressingRecord).
 *
 * `time_slot` defaults to a random one of the 24 canonical slots
 * (PressingRecordService::canonicalTimeSlots()) rather than an arbitrary
 * faker time string, so seeded rows always match the real app's grid
 * shape. `filled()` state sets a non-null digester_temp_c, for tests
 * needing a "filled" row (filled_slot_count / minimum-one-row checks).
 */
class PressingDetailFactory extends Factory
{
    protected $model = PressingDetail::class;

    public function definition(): array
    {
        $slots = PressingRecordService::canonicalTimeSlots();

        return [
            'pressing_record_id' => PressingRecord::factory(),
            'time_slot' => $this->faker->randomElement($slots),
            'digester_temp_c' => null,
            'digester_level_percent' => null,
            'press_motor_current_amps' => null,
            'cone_hydraulic_pressure_bar' => null,
            'dilution_water_temp_c' => null,
            'downtime_reason' => null,
        ];
    }

    public function forRecord(PressingRecord|string $record): self
    {
        return $this->state(fn () => [
            'pressing_record_id' => $record instanceof PressingRecord ? $record->id : $record,
        ]);
    }

    public function timeSlot(string $timeSlot): self
    {
        return $this->state(fn () => ['time_slot' => $timeSlot]);
    }

    /**
     * A "filled" row (at least one of the 6 reading columns non-null) —
     * for filled_slot_count / minimum-one-row validation tests.
     */
    public function filled(): self
    {
        return $this->state(fn () => ['digester_temp_c' => $this->faker->randomFloat(2, 85, 95)]);
    }
}
