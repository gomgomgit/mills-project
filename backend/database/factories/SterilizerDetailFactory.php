<?php

namespace Database\Factories;

use App\Models\SterilizerDetail;
use App\Models\SterilizerRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SterilizerDetail>
 *
 * Test infrastructure for screen-124--data-browser-sterilizer-web.
 * Mirrors CpoDispatchDetailFactory's structure — a plain leaf factory, no
 * `saving` guard of its own (that constraint, if any, lives on
 * SterilizerRecord). `duration_minutes` is computed here the same way
 * SterilizerRecordService computes it (close/open door time diff) so
 * factory-made rows stay internally consistent, even though the service
 * is the only source of truth when going through the real create/update
 * flow.
 */
class SterilizerDetailFactory extends Factory
{
    protected $model = SterilizerDetail::class;

    public function definition(): array
    {
        $closeDoorTime = $this->faker->time('H:i', '10:00');
        $durationMinutes = $this->faker->numberBetween(60, 120);
        $openDoorTime = date('H:i', strtotime($closeDoorTime) + $durationMinutes * 60);

        return [
            'sterilizer_record_id' => SterilizerRecord::factory(),
            'sterilizer_no' => (string) $this->faker->numberBetween(1, 6),
            'close_door_time' => $closeDoorTime,
            'peak_1_time' => date('H:i', strtotime($closeDoorTime) + 15 * 60),
            'exhaust_1_time' => date('H:i', strtotime($closeDoorTime) + 20 * 60),
            'peak_2_time' => date('H:i', strtotime($closeDoorTime) + 35 * 60),
            'exhaust_2_time' => date('H:i', strtotime($closeDoorTime) + 40 * 60),
            'peak_3_time' => date('H:i', strtotime($closeDoorTime) + 55 * 60),
            'exhaust_3_time' => date('H:i', strtotime($closeDoorTime) + 60 * 60),
            'open_door_time' => $openDoorTime,
            'duration_minutes' => $durationMinutes,
            'number_of_cages' => $this->faker->numberBetween(1, 12),
            'cages_status' => $this->faker->randomElement(['Baik', 'Perlu Perbaikan', '']),
            'checked_by_spv' => $this->faker->boolean(),
            'remarks' => $this->faker->optional()->sentence(),
        ];
    }

    public function forRecord(SterilizerRecord|string $record): self
    {
        return $this->state(fn () => [
            'sterilizer_record_id' => $record instanceof SterilizerRecord ? $record->id : $record,
        ]);
    }
}
