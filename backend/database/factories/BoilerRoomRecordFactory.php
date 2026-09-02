<?php

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\BoilerRoomRecord;
use App\Models\Station;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoilerRoomRecord>
 *
 * Test infrastructure for screen-098--data-browser-boiler-room-web /
 * screen-108--detail-boiler-room-web / screen-118--form-boiler-room-web.
 * Mirrors EngineRoomRecordFactory.php's exact structure/conventions.
 *
 * Default status is RecordStatus::Synced, deliberately NOT ::Saved: a
 * record created fresh via this factory (no related BoilerRoomDetail rows
 * exist yet) would fail a "minimal satu related row" saving guard on every
 * ->create() call if ::Saved were the default (defensive — mirrors
 * EngineRoomRecordFactory even though BoilerRoomRecord's own model
 * comment notes this guard is intentionally NOT implemented for this
 * entity).
 */
class BoilerRoomRecordFactory extends Factory
{
    protected $model = BoilerRoomRecord::class;

    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('-30 days', 'now');

        return [
            'station_id' => Station::factory(),
            'boiler_room_id' => 'BR-'.$this->faker->unique()->numerify('####'),
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
