<?php

namespace Database\Factories;

use App\Models\EngineRoomDetail;
use App\Models\EngineRoomRecord;
use App\Services\EngineRoomRecordService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EngineRoomDetail>
 *
 * Test infrastructure for screen-097--data-browser-engine-room-web /
 * screen-107--detail-engine-room-web / screen-117--form-engine-room-web.
 * Mirrors StorageTankDetailFactory.php's structure — EngineRoomDetail has
 * no `saving` guard of its own (the constraint, if any, lives on
 * EngineRoomRecord).
 *
 * `time_slot` defaults to a random one of the 24 canonical slots
 * (EngineRoomRecordService::canonicalTimeSlots()) rather than an arbitrary
 * faker time string, so seeded rows always match the real app's grid shape.
 * `filled()` state sets a non-null steam_turbine_inlet_pressure_bar, for
 * tests needing a "filled" row (filled_slot_count / minimum-one-row
 * checks). The 2 enum columns (diesel_gen_1_status, diesel_gen_2_status)
 * default to null — never an empty string, since the underlying columns
 * have a SQLite CHECK constraint that rejects ''.
 */
class EngineRoomDetailFactory extends Factory
{
    protected $model = EngineRoomDetail::class;

    public function definition(): array
    {
        $slots = EngineRoomRecordService::canonicalTimeSlots();

        return [
            'engine_room_record_id' => EngineRoomRecord::factory(),
            'time_slot' => $this->faker->randomElement($slots),
            'steam_turbine_inlet_pressure_bar' => null,
            'steam_turbine_inlet_temp_c' => null,
            'steam_turbine_exhaust_pressure_bar' => null,
            'steam_turbine_rpm' => null,
            'steam_turbine_alternator_bearing_temp_1_c' => null,
            'steam_turbine_alternator_bearing_temp_2_c' => null,
            'diesel_gen_1_status' => null,
            'diesel_gen_1_load_kw' => null,
            'diesel_gen_1_amperage_a' => null,
            'diesel_gen_1_jacket_water_temp_c' => null,
            'diesel_gen_1_lube_oil_pressure_bar' => null,
            'diesel_gen_2_status' => null,
            'diesel_gen_2_load_kw' => null,
            'diesel_gen_2_amperage_a' => null,
            'diesel_gen_2_jacket_water_temp_c' => null,
            'diesel_gen_2_lube_oil_pressure_bar' => null,
            'electrical_sync_total_factory_load_kw' => null,
            'electrical_sync_system_frequency_hz' => null,
            'electrical_sync_power_factor' => null,
            'electrical_sync_busbar_voltage_v' => null,
            'air_compressor_1_pressure_bar' => null,
            'compressor_2_pressure_bar' => null,
            'battery_charger_ups_voltage_v' => null,
            'fuel_tank_level' => null,
            'daily_energy_export_kwh' => null,
            'action_taken_maintenance_remark' => null,
            'findings' => null,
        ];
    }

    public function forRecord(EngineRoomRecord|string $record): self
    {
        return $this->state(fn () => [
            'engine_room_record_id' => $record instanceof EngineRoomRecord ? $record->id : $record,
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
        return $this->state(fn () => ['steam_turbine_inlet_pressure_bar' => $this->faker->randomFloat(2, 5, 20)]);
    }

    /**
     * A row "filled" only via an enum status column — for tests verifying
     * an enum-only row still counts as valid/filled.
     */
    public function filledViaEnum(): self
    {
        return $this->state(fn () => ['diesel_gen_1_status' => 'run']);
    }
}
