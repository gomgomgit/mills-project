<?php

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\GradingRecord;
use App\Models\Station;
use App\Models\User;
use App\Models\WeighbridgeRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradingRecord>
 *
 * Test infrastructure — created by test-writer-agent for
 * screen-017--data-browser-grading-web (no factory existed prior to this
 * screen; mirrors database/factories/WeighbridgeRecordFactory.php's
 * structure/conventions).
 *
 * GradingRecord references a WeighbridgeRecord (weighbridge_record_id) and
 * has grading_number/date/license_plate_no/vehicle_code/estate_supplier/
 * division/netto/quantity/note, plus checked_by/acknowledged_by (nullable
 * FKs to users) and status.
 *
 * Default status is RecordStatus::Synced, deliberately NOT ::Saved:
 * GradingRecord::booted()'s `saving` model event throws a
 * ValidationException whenever a record is saved with status === Saved and
 * has zero persisted GradingDetail rows. A record created fresh via this
 * factory (no related GradingDetail rows exist yet) would fail that check
 * on every ->create() call if ::Saved were the default. ::Synced is not
 * gated by that check and still reads as realistic, non-draft fixture data
 * for this screen's list/export tests.
 */
class GradingRecordFactory extends Factory
{
    protected $model = GradingRecord::class;

    public function definition(): array
    {
        return [
            'station_id' => Station::factory(),
            'grading_number' => 'GR-'.$this->faker->unique()->numerify('######'),
            'date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'weighbridge_record_id' => WeighbridgeRecord::factory(),
            'license_plate_no' => strtoupper($this->faker->bothify('B ####??')),
            'vehicle_code' => strtoupper($this->faker->bothify('TR-##')),
            'estate_supplier' => $this->faker->company(),
            'division' => $this->faker->word(),
            'netto' => $this->faker->randomFloat(2, 5000, 20000),
            'quantity' => $this->faker->randomFloat(2, 50, 200),
            'note' => $this->faker->optional()->sentence(),
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
     * Date-scoping helper (mirrors WeighbridgeRecordFactory::arrivedAt())
     * — useful for the date-range filter tests. GradingRecord's `date`
     * column is a timestamp; a date-only string still round-trips cleanly
     * through the 'datetime' cast and whereDate() filtering.
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
