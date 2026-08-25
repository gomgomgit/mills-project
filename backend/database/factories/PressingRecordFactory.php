<?php

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\PressingRecord;
use App\Models\Station;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PressingRecord>
 *
 * Test infrastructure for screen-050--data-browser-pressing-web /
 * screen-054--detail-pressing-web / screen-058--form-pressing-web.
 * Mirrors ThreshingRecordFactory.php's exact structure/conventions —
 * PressingRecord shares the same "minimal satu related row before
 * status=saved" constraint shape (PressingRecord::booted()).
 *
 * Default status is RecordStatus::Synced, deliberately NOT ::Saved: a
 * record created fresh via this factory (no related PressingDetail rows
 * exist yet) would fail the `saving` guard on every ->create() call if
 * ::Saved were the default.
 */
class PressingRecordFactory extends Factory
{
    protected $model = PressingRecord::class;

    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('-30 days', 'now');

        return [
            'station_id' => Station::factory(),
            'presser_id' => 'PR-'.$this->faker->unique()->numerify('####'),
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
