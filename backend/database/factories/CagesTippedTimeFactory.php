<?php

namespace Database\Factories;

use App\Models\CagesTippedTime;
use App\Models\CagesTrackRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CagesTippedTime>
 *
 * Test infrastructure — created by test-writer-agent for
 * screen-018--data-browser-cages-track-web. No factory existed prior to
 * this screen (see the module docblock note in
 * database/factories/CagesTrackRecordFactory.php: "no CagesTippedTimeFactory
 * exists yet" as of code-writer-agent's generate pass). Mirrors this
 * codebase's other simple child-row factories' structure/conventions
 * (e.g. CagesTrackRecordFactory itself) — CagesTippedTime has no `saving`
 * guard of its own (the constraint lives on CagesTrackRecord), so this
 * factory is a plain leaf factory: cages_track_record_id, tipped_hour,
 * checked_cage_numbers, total_cages, cages_remain (entity-catalog v3 —
 * full restructure of the old cage_number/tipped_time shape).
 *
 * Used by CagesTrackRecordServiceTest / DataBrowserCagesTrackTest (Api +
 * Livewire) wherever a test needs a CagesTrackRecord with a non-zero
 * tipped_time_count (the withCount('cagesTippedTimes') computed column
 * exercised by CagesTrackRecordService::listRecords()/export()).
 */
class CagesTippedTimeFactory extends Factory
{
    protected $model = CagesTippedTime::class;

    public function definition(): array
    {
        $totalCages = $this->faker->numberBetween(1, 5);
        $cageNumbers = $this->faker->randomElements(range(1, 20), $totalCages);
        sort($cageNumbers);

        return [
            'cages_track_record_id' => CagesTrackRecord::factory(),
            'tipped_hour' => $this->faker->unique()->numberBetween(0, 23),
            'checked_cage_numbers' => implode(',', $cageNumbers),
            'total_cages' => $totalCages,
            'cages_remain' => $this->faker->numberBetween(0, 20),
        ];
    }

    public function forRecord(CagesTrackRecord|string $record): self
    {
        return $this->state(fn () => [
            'cages_track_record_id' => $record instanceof CagesTrackRecord ? $record->id : $record,
        ]);
    }
}
