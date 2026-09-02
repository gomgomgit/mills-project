<?php

namespace Database\Factories;

use App\Models\ClarificationDetail;
use App\Models\ClarificationRecord;
use App\Services\ClarificationRecordService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClarificationDetail>
 *
 * Test infrastructure for screen-099--data-browser-clarification-web /
 * screen-109--detail-clarification-web / screen-119--form-clarification-web.
 * Mirrors BoilerRoomDetailFactory.php's structure — ClarificationDetail
 * has no `saving` guard of its own (the constraint, if any, lives on
 * ClarificationRecord).
 *
 * `time_slot` defaults to a random one of the 24 canonical slots
 * (ClarificationRecordService::canonicalTimeSlots()) rather than an
 * arbitrary faker time string, so seeded rows always match the real app's
 * grid shape. `filled()` state sets a non-null clarification_tank_temp_c,
 * for tests needing a "filled" row (filled_slot_count / minimum-one-row
 * checks). Unlike Boiler Room/Engine Room, this station has NO enum
 * columns at all — every column here is either a nullable float or a
 * nullable string (findings).
 */
class ClarificationDetailFactory extends Factory
{
    protected $model = ClarificationDetail::class;

    public function definition(): array
    {
        $slots = ClarificationRecordService::canonicalTimeSlots();

        return [
            'clarification_record_id' => ClarificationRecord::factory(),
            'time_slot' => $this->faker->randomElement($slots),
            'clarification_tank_temp_c' => null,
            'oil_tank_temperature_c' => null,
            'sludge_tank_temp_c' => null,
            'buffer_tank_level_percent' => null,
            'pure_oil_production_rate_ton_hour' => null,
            'downtime_mins' => null,
            'findings' => null,
        ];
    }

    public function forRecord(ClarificationRecord|string $record): self
    {
        return $this->state(fn () => [
            'clarification_record_id' => $record instanceof ClarificationRecord ? $record->id : $record,
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
        return $this->state(fn () => ['clarification_tank_temp_c' => $this->faker->randomFloat(2, 60, 90)]);
    }
}
