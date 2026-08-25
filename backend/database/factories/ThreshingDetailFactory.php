<?php

namespace Database\Factories;

use App\Models\ThreshingDetail;
use App\Models\ThreshingRecord;
use App\Services\ThreshingRecordService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ThreshingDetail>
 *
 * Test infrastructure for screen-049--data-browser-threshing-web /
 * screen-053--detail-threshing-web / screen-057--form-threshing-web.
 * Mirrors CagesTippedTimeFactory.php's structure — ThreshingDetail has no
 * `saving` guard of its own (the constraint lives on ThreshingRecord).
 *
 * `time_slot` defaults to a random one of the 24 canonical slots
 * (ThreshingRecordService::canonicalTimeSlots()) rather than an arbitrary
 * faker time string, so seeded rows always match the real app's grid
 * shape. `filled()` state sets a non-null ffb_throughput_mt_hour, for
 * tests needing a "filled" row (filled_slot_count / minimum-one-row
 * checks).
 */
class ThreshingDetailFactory extends Factory
{
    protected $model = ThreshingDetail::class;

    public function definition(): array
    {
        $slots = ThreshingRecordService::canonicalTimeSlots();

        return [
            'threshing_record_id' => ThreshingRecord::factory(),
            'time_slot' => $this->faker->randomElement($slots),
            'ffb_throughput_mt_hour' => null,
            'thresher_drum_speed_rpm' => null,
            'motor_current_amps' => null,
            'unstripped_bunch_count_percent' => null,
            'empty_bunch_oil_loss_percent' => null,
            'downtime_reason' => null,
        ];
    }

    public function forRecord(ThreshingRecord|string $record): self
    {
        return $this->state(fn () => [
            'threshing_record_id' => $record instanceof ThreshingRecord ? $record->id : $record,
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
        return $this->state(fn () => ['ffb_throughput_mt_hour' => $this->faker->randomFloat(2, 10, 60)]);
    }
}
