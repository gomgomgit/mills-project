<?php

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\CagesTrackRecord;
use App\Models\Station;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CagesTrackRecord>
 *
 * Test infrastructure — created by code-writer-agent for
 * screen-018--data-browser-cages-track-web (no factory existed prior to
 * this screen; mirrors database/factories/GradingRecordFactory.php's
 * exact structure/conventions, since CagesTrackRecord shares the same
 * "minimal satu related row before status=saved" constraint shape as
 * GradingRecord).
 *
 * Default status is RecordStatus::Synced, deliberately NOT ::Saved:
 * CagesTrackRecord::booted()'s `saving` model event throws a
 * ValidationException whenever a record is saved with status === Saved and
 * has zero persisted CagesTippedTime rows. A record created fresh via this
 * factory (no related CagesTippedTime rows exist yet) would fail that
 * check on every ->create() call if ::Saved were the default. ::Synced is
 * not gated by that check and still reads as realistic, non-draft fixture
 * data for this screen's list/export tests.
 *
 * Field values mirror entity-catalog's cages-track-record.test_fixture
 * shape (id "ctr-001", cages_track_number "CT-2024-0001", etc.) as the
 * realistic baseline referenced by this screen's implementation plan.
 */
class CagesTrackRecordFactory extends Factory
{
    protected $model = CagesTrackRecord::class;

    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('-30 days', 'now');
        $startTime = (clone $date)->setTime($this->faker->numberBetween(0, 12), $this->faker->numberBetween(0, 59));
        $stopTime = (clone $startTime)->modify('+'.$this->faker->numberBetween(1, 8).' hours');

        return [
            'station_id' => Station::factory(),
            'cages_track_number' => 'CT-'.$this->faker->unique()->numerify('####-####'),
            'date' => $date->format('Y-m-d'),
            'tippler_start_time' => $startTime,
            'tippler_stop_time' => $stopTime,
            'cages_out' => $this->faker->numberBetween(5, 20),
            'cages_tipped' => $this->faker->numberBetween(5, 20),
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

    /**
     * Date-scoping helper (mirrors GradingRecordFactory::onDate(), adapted
     * for CagesTrackRecord's plain `date` column) — useful for the
     * date-range filter tests.
     */
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
