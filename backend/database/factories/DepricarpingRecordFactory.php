<?php

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\DepricarpingRecord;
use App\Models\Station;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DepricarpingRecord>
 *
 * Test infrastructure for screen-051--data-browser-depricarping-web /
 * screen-055--detail-depricarping-web / screen-059--form-depricarping-web.
 * Mirrors PressingRecordFactory.php's/ThreshingRecordFactory.php's exact
 * structure/conventions — DepricarpingRecord shares the same "minimal satu
 * related row before status=saved" constraint shape
 * (DepricarpingRecord::booted()).
 *
 * Default status is RecordStatus::Synced, deliberately NOT ::Saved: a
 * record created fresh via this factory (no related DepricarpingDetail rows
 * exist yet) would fail the `saving` guard on every ->create() call if
 * ::Saved were the default.
 */
class DepricarpingRecordFactory extends Factory
{
    protected $model = DepricarpingRecord::class;

    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('-30 days', 'now');

        return [
            'station_id' => Station::factory(),
            'presser_id' => 'DP-'.$this->faker->unique()->numerify('####'),
            'date' => $date->format('Y-m-d'),
            'note' => $this->faker->optional()->sentence(),
            'checked_by' => null,
            'acknowledged_by' => null,
            'status' => RecordStatus::Synced,
            'created_by' => User::factory(),
        ];
    }

    public function forStation(Station|string $station): self
    {
        return $this->state(fn () => [
            'station_id' => $station instanceof Station ? $station->id : $station,
        ]);
    }

    public function onDate(\DateTimeInterface|string $date): self
    {
        $value = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : $date;

        return $this->state(fn () => ['date' => $value]);
    }

    public function status(RecordStatus $status): self
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
