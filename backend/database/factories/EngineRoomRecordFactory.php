<?php

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\EngineRoomRecord;
use App\Models\Station;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EngineRoomRecord>
 *
 * Test infrastructure for screen-097--data-browser-engine-room-web /
 * screen-107--detail-engine-room-web / screen-117--form-engine-room-web.
 * Mirrors StorageTankRecordFactory.php's exact structure/conventions.
 *
 * Default status is RecordStatus::Synced, deliberately NOT ::Saved: a
 * record created fresh via this factory (no related EngineRoomDetail rows
 * exist yet) would fail a "minimal satu related row" saving guard on every
 * ->create() call if ::Saved were the default (defensive — mirrors
 * StorageTankRecordFactory even though EngineRoomRecord's own model
 * comment notes this guard is intentionally NOT implemented for this
 * entity).
 */
class EngineRoomRecordFactory extends Factory
{
    protected $model = EngineRoomRecord::class;

    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('-30 days', 'now');

        return [
            'station_id' => Station::factory(),
            'engine_room_id' => 'ER-'.$this->faker->unique()->numerify('####'),
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
