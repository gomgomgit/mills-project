<?php

namespace Database\Factories;

use App\Models\DepricarpingDetail;
use App\Models\DepricarpingRecord;
use App\Services\DepricarpingRecordService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DepricarpingDetail>
 *
 * Test infrastructure for screen-051--data-browser-depricarping-web /
 * screen-055--detail-depricarping-web / screen-059--form-depricarping-web.
 * Mirrors PressingDetailFactory.php's/ThreshingDetailFactory.php's
 * structure — DepricarpingDetail has no `saving` guard of its own (the
 * constraint lives on DepricarpingRecord).
 *
 * `time_slot` defaults to a random one of the 24 canonical slots
 * (DepricarpingRecordService::canonicalTimeSlots()) rather than an
 * arbitrary faker time string, so seeded rows always match the real app's
 * grid shape. `filled()` state sets a non-null fan_static_pressure_mmh2o,
 * for tests needing a "filled" row (filled_slot_count / minimum-one-row
 * checks).
 */
class DepricarpingDetailFactory extends Factory
{
    protected $model = DepricarpingDetail::class;

    public function definition(): array
    {
        $slots = DepricarpingRecordService::canonicalTimeSlots();

        return [
            'depricarping_record_id' => DepricarpingRecord::factory(),
            'time_slot' => $this->faker->randomElement($slots),
            'fan_static_pressure_mmh2o' => null,
            'polishing_drum_speed_rpm' => null,
            'air_velocity_ms' => null,
            'fibre_moisture_percent' => null,
            'kernel_recovery_in_fibre_percent' => null,
            'nut_silo_1_temp_c' => null,
            'nut_silo_2_temp_c' => null,
            'downtime_minutes' => null,
            'findings' => null,
        ];
    }

    public function forRecord(DepricarpingRecord|string $record): self
    {
        return $this->state(fn () => [
            'depricarping_record_id' => $record instanceof DepricarpingRecord ? $record->id : $record,
        ]);
    }

    public function timeSlot(string $timeSlot): self
    {
        return $this->state(fn () => ['time_slot' => $timeSlot]);
    }

    /**
     * A "filled" row (at least one of the 8 reading columns non-null) —
     * for filled_slot_count / minimum-one-row validation tests.
     */
    public function filled(): self
    {
        return $this->state(fn () => ['fan_static_pressure_mmh2o' => $this->faker->randomFloat(2, 40, 50)]);
    }
}
