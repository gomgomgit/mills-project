<?php

use App\Enums\UserRole;
use App\Models\BoilerRoomDetail;
use App\Models\BoilerRoomRecord;
use App\Models\CagesTippedTime;
use App\Models\CagesTrackRecord;
use App\Models\ClarificationDetail;
use App\Models\ClarificationRecord;
use App\Models\CpoDispatchDetail;
use App\Models\CpoDispatchRecord;
use App\Models\DepricarpingDetail;
use App\Models\DepricarpingRecord;
use App\Models\EffluentPlantDetail;
use App\Models\EffluentPlantRecord;
use App\Models\EngineRoomDetail;
use App\Models\EngineRoomRecord;
use App\Models\GradingDetail;
use App\Models\GradingRecord;
use App\Models\KernelDispatchDetail;
use App\Models\KernelDispatchRecord;
use App\Models\KernelPlantDetail;
use App\Models\KernelPlantRecord;
use App\Models\PressingDetail;
use App\Models\PressingRecord;
use App\Models\ProcessQualityControlDetail;
use App\Models\ProcessQualityControlRecord;
use App\Models\ProcessWaterDetail;
use App\Models\ProcessWaterRecord;
use App\Models\SolidWasteDisposalDetail;
use App\Models\SolidWasteDisposalRecord;
use App\Models\SterilizerDetail;
use App\Models\SterilizerRecord;
use App\Models\StorageTankDetail;
use App\Models\StorageTankRecord;
use App\Models\ThreshingDetail;
use App\Models\ThreshingRecord;
use App\Models\User;
use App\Services\BoilerRoomRecordService;
use App\Services\CagesTrackRecordService;
use App\Services\ClarificationRecordService;
use App\Services\CpoDispatchRecordService;
use App\Services\DepricarpingRecordService;
use App\Services\EffluentPlantRecordService;
use App\Services\EngineRoomRecordService;
use App\Services\GradingRecordService;
use App\Services\KernelDispatchRecordService;
use App\Services\KernelPlantRecordService;
use App\Services\PressingRecordService;
use App\Services\ProcessQualityControlRecordService;
use App\Services\ProcessWaterRecordService;
use App\Services\SolidWasteDisposalRecordService;
use App\Services\SterilizerRecordService;
use App\Services\StorageTankRecordService;
use App\Services\ThreshingRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// Jalur BACA (export/listRecords/getDetail) sejak 2026-09-28 memakai AKTOR
// TERAUTENTIKASI — lihat bagian READ SIDE di
// App\Support\Concerns\ScopesToActorMill. Test di berkas ini menguji BENTUK
// baris ekspor, bukan cakupan mill, jadi aktornya Admin: Admin tidak terikat
// mill, sehingga setiap asersi tetap menguji hal yang persis sama.
beforeEach(function () {
    $this->actingAs(User::factory()->role(UserRole::Admin)->create());
});

/**
 * Cross-station guard for the detail-level CSV export (the "Urutan 0" work in
 * docs/katalog-laporan-per-stasiun.pdf): every station's export() must emit one
 * line per detail row, carrying the record's context columns on each line, so
 * the measured readings actually leave the system. Before this, 17 of the 18
 * stations exported header columns only — no readings at all.
 *
 * Each row is [service, record model, detail model, detail FK, one expected
 * detail-column header, context column count, detail column count].
 *
 * Context column counts gained one on 2026-09-28: every export now leads with
 * a Production Line column, read from the record's OWN `production_line_id`
 * (2026_09_28_000041) rather than through `station`, so an exported row still
 * names the line it was produced on after its station has been moved.
 */
dataset('stations', [
    'threshing' => [ThreshingRecordService::class, ThreshingRecord::class, ThreshingDetail::class, 'threshing_record_id', 'Drum Speed (RPM)', 7, 7],
    'pressing' => [PressingRecordService::class, PressingRecord::class, PressingDetail::class, 'pressing_record_id', 'Digester Temp (°C)', 7, 7],
    'depricarping' => [DepricarpingRecordService::class, DepricarpingRecord::class, DepricarpingDetail::class, 'depricarping_record_id', 'Kernel Recovery in Fibre (%)', 7, 10],
    'kernel plant' => [KernelPlantRecordService::class, KernelPlantRecord::class, KernelPlantDetail::class, 'kernel_plant_record_id', 'Shell Loss (%)', 7, 10],
    'clarification' => [ClarificationRecordService::class, ClarificationRecord::class, ClarificationDetail::class, 'clarification_record_id', 'Pure Oil Production Rate (Ton/Hour)', 7, 8],
    'storage tank' => [StorageTankRecordService::class, StorageTankRecord::class, StorageTankDetail::class, 'storage_tank_record_id', 'Calculated Weight (MT)', 7, 18],
    'boiler room' => [BoilerRoomRecordService::class, BoilerRoomRecord::class, BoilerRoomDetail::class, 'boiler_room_record_id', 'Water TDS (ppm)', 7, 16],
    'engine room' => [EngineRoomRecordService::class, EngineRoomRecord::class, EngineRoomDetail::class, 'engine_room_record_id', 'Electrical Sync Total Factory Load (kW)', 7, 28],
    'process water' => [ProcessWaterRecordService::class, ProcessWaterRecord::class, ProcessWaterDetail::class, 'process_water_record_id', 'Raw Water Flow (m³/h)', 7, 14],
    'effluent plant' => [EffluentPlantRecordService::class, EffluentPlantRecord::class, EffluentPlantDetail::class, 'effluent_plant_record_id', 'Final Discharge BOD (mg/L)', 7, 20],
    'process quality control' => [ProcessQualityControlRecordService::class, ProcessQualityControlRecord::class, ProcessQualityControlDetail::class, 'process_quality_control_record_id', 'Final Storage FFA (%)', 7, 17],
    'sterilizer' => [SterilizerRecordService::class, SterilizerRecord::class, SterilizerDetail::class, 'sterilizer_record_id', 'Duration (Minutes)', 8, 14],
    'cpo dispatch' => [CpoDispatchRecordService::class, CpoDispatchRecord::class, CpoDispatchDetail::class, 'cpo_dispatch_record_id', 'Net Weight (MT)', 8, 21],
    'kernel dispatch' => [KernelDispatchRecordService::class, KernelDispatchRecord::class, KernelDispatchDetail::class, 'kernel_dispatch_record_id', 'Net Weight (MT)', 8, 21],
    'solid waste disposal' => [SolidWasteDisposalRecordService::class, SolidWasteDisposalRecord::class, SolidWasteDisposalDetail::class, 'solid_waste_disposal_record_id', 'Net Weight (MT)', 8, 17],
    'grading' => [GradingRecordService::class, GradingRecord::class, GradingDetail::class, 'grading_record_id', 'Quality Parameter', 13, 4], // 13: tanpa 'Checked By' (temuan audit 2026-10-04 #8)
    'cages track' => [CagesTrackRecordService::class, CagesTrackRecord::class, CagesTippedTime::class, 'cages_track_record_id', 'Total Cages', 12, 4],
]);

/**
 * Streams the export to a string and parses it back as CSV, so a reading that
 * happens to contain a comma or a newline is still counted as one field.
 *
 * @return array<int, array<int, ?string>>
 */
function exportedRows(string $service, array $filters = []): array
{
    $response = app($service)->export($filters, 'csv');

    ob_start();
    $response->sendContent();
    $body = ob_get_clean();

    $handle = fopen('php://memory', 'r+');
    fwrite($handle, $body);
    rewind($handle);

    $rows = [];
    while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        $rows[] = $row;
    }
    fclose($handle);

    return $rows;
}

/**
 * Creates $count detail rows for one record. Stations whose detail table is
 * unique per slot/cycle get an explicit, distinct slot value — the factories
 * pick one at random, which collides once more than one row is seeded.
 */
function seedDetailRows(string $detailModel, string $fk, string $recordId, int $count): void
{
    $fillable = (new $detailModel)->getFillable();
    $slots = ['07:00', '08:00', '09:00', '10:00', '11:00'];

    for ($i = 0; $i < $count; $i++) {
        $attributes = [$fk => $recordId];

        if (in_array('time_slot', $fillable, true)) {
            $attributes['time_slot'] = $slots[$i];
        }

        if (in_array('tipped_hour', $fillable, true)) {
            $attributes['tipped_hour'] = 7 + $i;
        }

        if (in_array('sterilizer_no', $fillable, true)) {
            $attributes['sterilizer_no'] = (string) ($i + 1);
        }

        $detailModel::factory()->create($attributes);
    }
}

it('writes one CSV line per detail row, repeating the record context on each line', function (
    string $service,
    string $recordModel,
    string $detailModel,
    string $fk,
    string $expectedDetailHeader,
    int $contextColumns,
    int $detailColumns
) {
    $record = $recordModel::factory()->create();
    seedDetailRows($detailModel, $fk, $record->id, 3);

    $rows = exportedRows($service);

    [$header] = $rows;
    $dataRows = array_slice($rows, 1);

    expect($header)->toHaveCount($contextColumns + $detailColumns);
    expect($header)->toContain($expectedDetailHeader);

    // One line per detail row — not one line per record.
    expect($dataRows)->toHaveCount(3);

    foreach ($dataRows as $row) {
        expect($row)->toHaveCount($contextColumns + $detailColumns);
        // Context columns are identical on every line (they come from the
        // one shared record), which is what makes the file pivot in Excel.
        expect(array_slice($row, 0, $contextColumns))
            ->toBe(array_slice($dataRows[0], 0, $contextColumns));
    }

    // At least one detail column carries a value — the whole point of the
    // change is that readings are no longer dropped.
    $detailValues = array_merge(...array_map(
        fn ($row) => array_slice($row, $contextColumns),
        $dataRows
    ));
    expect(array_filter($detailValues, fn ($value) => $value !== null && $value !== ''))->not->toBeEmpty();
})->with('stations');

it('still writes one line for a record that has no detail rows, with the detail columns blank', function (
    string $service,
    string $recordModel,
    string $detailModel,
    string $fk,
    string $expectedDetailHeader,
    int $contextColumns,
    int $detailColumns
) {
    $recordModel::factory()->create();

    $rows = exportedRows($service);
    $dataRows = array_slice($rows, 1);

    expect($dataRows)->toHaveCount(1);
    expect(array_slice($dataRows[0], $contextColumns))
        ->toBe(array_fill(0, $detailColumns, ''));
})->with('stations');

/**
 * Guards the Grading header columns specifically: the previous export wrote
 * vehicle_number / driver_name / block, none of which exist on
 * grading_records (entity-catalog v2 renamed them), so those columns were
 * always blank — while netto and quantity, the two numbers the station is
 * recorded for, were missing entirely.
 */
it('carries the Grading record netto and quantity into the export', function () {
    $record = GradingRecord::factory()->create([
        'netto' => 12345.67,
        'quantity' => 89.5,
    ]);
    GradingDetail::factory()->create(['grading_record_id' => $record->id]);

    $rows = exportedRows(GradingRecordService::class);
    [$header] = $rows;
    $row = $rows[1];

    expect($header)->toContain('Netto', 'Quantity', 'License Plate No', 'Vehicle Code');
    expect($header)->not->toContain('Vehicle Number', 'Driver Name', 'Block');

    expect($row[array_search('Netto', $header, true)])->toBe('12345.67');
    expect($row[array_search('Quantity', $header, true)])->toBe('89.5');
    expect($row[array_search('License Plate No', $header, true)])->toBe($record->license_plate_no);
});
