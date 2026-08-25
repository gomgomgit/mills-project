<?php

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\KernelPlantRecord;
use App\Models\Station;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KernelPlantRecord>
 *
 * Test infrastructure for screen-052--data-browser-kernel-plant-web /
 * screen-056--detail-kernel-plant-web / screen-060--form-kernel-plant-web.
 * Mirrors DepricarpingRecordFactory.php's exact structure/conventions —
 * KernelPlantRecord shares the same "minimal satu related row before
 * status=saved" constraint shape (KernelPlantRecord::booted()).
 *
 * Default status is RecordStatus::Synced, deliberately NOT ::Saved: a
 * record created fresh via this factory (no related KernelPlantDetail rows
 * exist yet) would fail the `saving` guard on every ->create() call if
 * ::Saved were the default.
 */
class KernelPlantRecordFactory extends Factory
{
    protected $model = KernelPlantRecord::class;

    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('-30 days', 'now');

        return [
            'station_id' => Station::factory(),
            'kernel_plant_id' => 'KP-'.$this->faker->unique()->numerify('####'),
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
