<?php

namespace Database\Factories;

use App\Models\StorageTankDetail;
use App\Models\StorageTankRecord;
use App\Services\StorageTankRecordService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StorageTankDetail>
 *
 * Test infrastructure for screen-096--data-browser-storage-tank-web /
 * screen-106--detail-storage-tank-web / screen-116--form-storage-tank-web.
 * Mirrors EffluentPlantDetailFactory.php's structure — StorageTankDetail has
 * no `saving` guard of its own (the constraint, if any, lives on
 * StorageTankRecord).
 *
 * `time_slot` defaults to a random one of the 24 canonical slots
 * (StorageTankRecordService::canonicalTimeSlots()) rather than an arbitrary
 * faker time string, so seeded rows always match the real app's grid shape.
 * `filled()` state sets a non-null cpo_sounding_depth_mm, for tests needing
 * a "filled" row (filled_slot_count / minimum-one-row checks). The 1 enum
 * column (steam_heating_valve_status) defaults to null — never an empty
 * string, since the underlying column has a SQLite CHECK constraint that
 * rejects ''.
 */
class StorageTankDetailFactory extends Factory
{
    protected $model = StorageTankDetail::class;

    public function definition(): array
    {
        $slots = StorageTankRecordService::canonicalTimeSlots();

        return [
            'storage_tank_record_id' => StorageTankRecord::factory(),
            'time_slot' => $this->faker->randomElement($slots),
            'cpo_sounding_depth_mm' => null,
            'water_dip_bottom_depth_mm' => null,
            'net_oil_depth_mm' => null,
            'oil_temperature_top_c' => null,
            'oil_temperature_middle_c' => null,
            'oil_temperature_bottom_c' => null,
            'average_temperature_c' => null,
            'calculated_volume_m3' => null,
            'calculated_weight_mt' => null,
            'ffa_percent' => null,
            'moisture_content_percent' => null,
            'impurities_dirt_percent' => null,
            'dobi_index' => null,
            'steam_heating_valve_status' => null,
            'tank_structural_condition' => null,
            'inspector_name' => null,
            'findings' => null,
        ];
    }

    public function forRecord(StorageTankRecord|string $record): self
    {
        return $this->state(fn () => [
            'storage_tank_record_id' => $record instanceof StorageTankRecord ? $record->id : $record,
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
        return $this->state(fn () => ['cpo_sounding_depth_mm' => $this->faker->randomFloat(2, 500, 2000)]);
    }

    /**
     * A row "filled" only via the enum status column — for tests verifying
     * an enum-only row still counts as valid/filled.
     */
    public function filledViaEnum(): self
    {
        return $this->state(fn () => ['steam_heating_valve_status' => 'open_1_4']);
    }
}
