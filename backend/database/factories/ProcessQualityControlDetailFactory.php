<?php

namespace Database\Factories;

use App\Models\ProcessQualityControlDetail;
use App\Models\ProcessQualityControlRecord;
use App\Services\ProcessQualityControlRecordService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcessQualityControlDetail>
 *
 * Test infrastructure for screen-100--data-browser-process-quality-control-web /
 * screen-110--detail-process-quality-control-web / screen-120--form-process-quality-control-web.
 * Mirrors ClarificationDetailFactory.php's structure — ProcessQualityControlDetail
 * has no `saving` guard of its own (the constraint, if any, lives on
 * ProcessQualityControlRecord).
 *
 * `time_slot` defaults to a random one of the 24 canonical slots
 * (ProcessQualityControlRecordService::canonicalTimeSlots()) rather than an
 * arbitrary faker time string, so seeded rows always match the real app's
 * grid shape. `filled()` state sets a non-null
 * fruit_press_oil_loss_in_sludge_percent, for tests needing a "filled" row
 * (filled_slot_count / minimum-one-row checks). This station has NO enum
 * columns at all — every column here is either a nullable float or a
 * nullable string (shift, qc_inspector_id, qc_engineering_corrective_actions,
 * findings).
 */
class ProcessQualityControlDetailFactory extends Factory
{
    protected $model = ProcessQualityControlDetail::class;

    public function definition(): array
    {
        $slots = ProcessQualityControlRecordService::canonicalTimeSlots();

        return [
            'process_quality_control_record_id' => ProcessQualityControlRecord::factory(),
            'time_slot' => $this->faker->randomElement($slots),
            'shift' => null,
            'fruit_press_oil_loss_in_sludge_percent' => null,
            'fruit_press_oil_loss_in_fibre_percent' => null,
            'purifier_clarification_balance_inlet_temp_c' => null,
            'purifier_clarification_balance_backpressure_bar' => null,
            'vacuum_drying_station_drier_temp_c' => null,
            'vacuum_drying_station_vacuum_pressure_bar' => null,
            'decanter_centrifuge_feed_rate_mth' => null,
            'decanter_centrifuge_oil_loss_in_cake_percent' => null,
            'final_storage_ffa_percent' => null,
            'final_storage_moisture_content_percent' => null,
            'final_storage_impurities_dirt_percent' => null,
            'final_storage_dobi_index' => null,
            'qc_inspector_id' => null,
            'qc_engineering_corrective_actions' => null,
            'findings' => null,
        ];
    }

    public function forRecord(ProcessQualityControlRecord|string $record): self
    {
        return $this->state(fn () => [
            'process_quality_control_record_id' => $record instanceof ProcessQualityControlRecord ? $record->id : $record,
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
        return $this->state(fn () => ['fruit_press_oil_loss_in_sludge_percent' => $this->faker->randomFloat(2, 0, 5)]);
    }
}
