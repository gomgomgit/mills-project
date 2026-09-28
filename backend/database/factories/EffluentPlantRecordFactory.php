<?php

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\EffluentPlantRecord;
use App\Models\Station;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EffluentPlantRecord>
 *
 * Test infrastructure for screen-095--data-browser-effluent-plant-web /
 * screen-105--detail-effluent-plant-web / screen-115--form-effluent-plant-web.
 * Mirrors ProcessWaterRecordFactory.php's exact structure/conventions.
 *
 * Default status is RecordStatus::Synced, deliberately NOT ::Saved: a
 * record created fresh via this factory (no related EffluentPlantDetail
 * rows exist yet) would fail a "minimal satu related row" saving guard on
 * every ->create() call if ::Saved were the default (defensive — mirrors
 * ProcessWaterRecordFactory even though EffluentPlantRecord's own model
 * comment notes this guard is intentionally NOT implemented for this
 * entity).
 */
class EffluentPlantRecordFactory extends Factory
{
    protected $model = EffluentPlantRecord::class;

    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('-30 days', 'now');

        return [
            'station_id' => Station::factory(),
            // NOT NULL since 2026_09_28_000043, and deliberately derived
            // from the station rather than faked independently: the same
            // closure pattern as MachineryFactory::definition(), so any
            // caller that sets only `station_id` (e.g. ->forStation()) still
            // gets the CONSISTENT line, while a caller that sets both
            // explicitly is honoured as-is.
            'production_line_id' => fn (array $attributes) => Station::find($attributes['station_id'])?->production_line_id,
            'effluent_plant_id' => 'EP-'.$this->faker->unique()->numerify('####'),
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
