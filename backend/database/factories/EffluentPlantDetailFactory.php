<?php

namespace Database\Factories;

use App\Models\EffluentPlantDetail;
use App\Models\EffluentPlantRecord;
use App\Services\EffluentPlantRecordService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EffluentPlantDetail>
 *
 * Test infrastructure for screen-095--data-browser-effluent-plant-web /
 * screen-105--detail-effluent-plant-web / screen-115--form-effluent-plant-web.
 * Mirrors ProcessWaterDetailFactory.php's structure — EffluentPlantDetail has
 * no `saving` guard of its own (the constraint, if any, lives on
 * EffluentPlantRecord).
 *
 * `time_slot` defaults to a random one of the 24 canonical slots
 * (EffluentPlantRecordService::canonicalTimeSlots()) rather than an
 * arbitrary faker time string, so seeded rows always match the real app's
 * grid shape. `filled()` state sets a non-null anaerobic_pond_1_ph, for
 * tests needing a "filled" row (filled_slot_count / minimum-one-row
 * checks). The 3 enum columns (biogas_flare_status, dosing_pump_1_status,
 * sludge_dewatering_status) default to null — never an empty string, since
 * the underlying column has a SQLite CHECK constraint that rejects ''.
 */
class EffluentPlantDetailFactory extends Factory
{
    protected $model = EffluentPlantDetail::class;

    public function definition(): array
    {
        $slots = EffluentPlantRecordService::canonicalTimeSlots();

        return [
            'effluent_plant_record_id' => EffluentPlantRecord::factory(),
            'time_slot' => $this->faker->randomElement($slots),
            'anaerobic_pond_1_ph' => null,
            'anaerobic_pond_1_temp_c' => null,
            'anaerobic_pond_2_ph' => null,
            'anaerobic_pond_2_temp_c' => null,
            'cooling_pond_ph' => null,
            'cooling_pond_temp_c' => null,
            'biogas_flare_status' => null,
            'biogas_flow_rate_m3h' => null,
            'raw_pome_feed_rate_m3h' => null,
            'effluent_discharge_flow_rate_m3h' => null,
            'final_discharge_ph' => null,
            'final_discharge_bod_mgl_lab' => null,
            'final_discharge_cod_mgl_lab' => null,
            'final_discharge_tss_mgl_lab' => null,
            'dosing_pump_1_status' => null,
            'chemical_consumed_kgl' => null,
            'sludge_dewatering_status' => null,
            'remarks_maintenance_actions' => null,
            'findings' => null,
        ];
    }

    public function forRecord(EffluentPlantRecord|string $record): self
    {
        return $this->state(fn () => [
            'effluent_plant_record_id' => $record instanceof EffluentPlantRecord ? $record->id : $record,
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
        return $this->state(fn () => ['anaerobic_pond_1_ph' => $this->faker->randomFloat(2, 6, 8)]);
    }

    /**
     * A row "filled" only via one of the 3 enum status columns — for tests
     * verifying an enum-only row still counts as valid/filled.
     */
    public function filledViaEnum(): self
    {
        return $this->state(fn () => ['biogas_flare_status' => 'fault']);
    }
}
