<?php

namespace Database\Factories;

use App\Models\BoilerRoomDetail;
use App\Models\BoilerRoomRecord;
use App\Services\BoilerRoomRecordService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoilerRoomDetail>
 *
 * Test infrastructure for screen-098--data-browser-boiler-room-web /
 * screen-108--detail-boiler-room-web / screen-118--form-boiler-room-web.
 * Mirrors EngineRoomDetailFactory.php's structure — BoilerRoomDetail has
 * no `saving` guard of its own (the constraint, if any, lives on
 * BoilerRoomRecord).
 *
 * `time_slot` defaults to a random one of the 24 canonical slots
 * (BoilerRoomRecordService::canonicalTimeSlots()) rather than an arbitrary
 * faker time string, so seeded rows always match the real app's grid shape.
 * `filled()` state sets a non-null steam_pressure_bar, for tests needing a
 * "filled" row (filled_slot_count / minimum-one-row checks). The 2 enum
 * columns (blowdown_executed, sootblowing_executed) default to null —
 * never an empty string, since the underlying columns have a SQLite CHECK
 * constraint that rejects ''.
 */
class BoilerRoomDetailFactory extends Factory
{
    protected $model = BoilerRoomDetail::class;

    public function definition(): array
    {
        $slots = BoilerRoomRecordService::canonicalTimeSlots();

        return [
            'boiler_room_record_id' => BoilerRoomRecord::factory(),
            'time_slot' => $this->faker->randomElement($slots),
            'steam_pressure_bar' => null,
            'steam_temp_c' => null,
            'feed_water_temp_c' => null,
            'feed_water_tank_level_percent' => null,
            'boiler_water_level_percent' => null,
            'water_tds_ppm' => null,
            'water_ph' => null,
            'fuel_feed_rate' => null,
            'id_fan_load' => null,
            'sa_fan_load' => null,
            'exhaust_gas_temp_c' => null,
            'dust_collector_differential_pressure_mmh2o' => null,
            'blowdown_executed' => null,
            'sootblowing_executed' => null,
            'findings' => null,
        ];
    }

    public function forRecord(BoilerRoomRecord|string $record): self
    {
        return $this->state(fn () => [
            'boiler_room_record_id' => $record instanceof BoilerRoomRecord ? $record->id : $record,
        ]);
    }

    public function timeSlot(string $timeSlot): self
    {
        return $this->state(fn () => ['time_slot' => $timeSlot]);
    }

    /**
     * A "filled" row (at least one reading column non-null) — for
     * filled_slot_count / minimum-one-row validation tests.
     */
    public function filled(): self
    {
        return $this->state(fn () => ['steam_pressure_bar' => $this->faker->randomFloat(2, 5, 20)]);
    }

    /**
     * A row "filled" only via an enum status column — for tests verifying
     * an enum-only row still counts as valid/filled.
     */
    public function filledViaEnum(): self
    {
        return $this->state(fn () => ['blowdown_executed' => 'y']);
    }
}
