<?php

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\CpoDispatchRecord;
use App\Models\Station;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CpoDispatchRecord>
 *
 * Test infrastructure for screen-094--data-browser-cpo-dispatch-web.
 * Mirrors KernelDispatchRecordFactory's structure. Unlike CagesTrackRecord,
 * CpoDispatchRecord has no `saving` guard hook (see the model's own
 * docblock), so ::Saved is a safe default even with zero related detail
 * rows.
 */
class CpoDispatchRecordFactory extends Factory
{
    protected $model = CpoDispatchRecord::class;

    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('-30 days', 'now');

        return [
            'station_id' => Station::factory()->cpoDispatch(),
            'cpo_dispatch_id' => 'CD-'.$this->faker->unique()->numerify('####-####'),
            'date' => $date->format('Y-m-d'),
            'note' => $this->faker->optional()->sentence(),
            'checked_by' => null,
            'acknowledged_by' => null,
            'status' => RecordStatus::Saved,
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
