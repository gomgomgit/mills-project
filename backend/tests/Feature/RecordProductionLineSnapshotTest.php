<?php

/**
 * RecordProductionLineSnapshotTest — the guarantee that
 * 2026_09_28_000041/42/43 exists to create.
 *
 * WHY THIS FILE EXISTS
 * --------------------
 * Before `production_line_id` became a real column on the 18 `*_records`
 * tables, a record's production line could only be DERIVED at read time
 * through `station.production_line_id`. That made every record's line a
 * function of the station's CURRENT configuration: moving one station to
 * another production line silently REWROTE THE HISTORY of every record
 * that station had ever produced — retroactively, and with no trace that
 * the line had ever been different.
 *
 * The test named 'keeps a record pointing at the line it was produced on
 * after its station is moved' is the whole point of the stage. It asserts
 * something that was IMPOSSIBLE to assert before the column existed,
 * because there was nowhere for the old value to live. Everything else
 * here protects that guarantee's preconditions:
 *
 *   - the value is written on create() from the RESOLVED STATION (never
 *     from the request body, which carries `production_line_id` only in
 *     order to SELECT the station),
 *   - update() neither changes nor blanks it (a record's station never
 *     changes through update(), so neither may its line),
 *   - the database itself refuses a record with no line (NOT NULL) or with
 *     a line that does not exist (FK).
 *
 * All 18 station types are covered explicitly rather than by one
 * representative: the 18 services are hand-written siblings, and the
 * pre-2026-09-28 mill-scope bug this project just fixed existed precisely
 * because 17 of 18 siblings had drifted from the 18th.
 */

use App\Enums\Uom;
use App\Enums\UserRole;
use App\Models\BoilerRoomRecord;
use App\Models\BusinessUnit;
use App\Models\CagesTrackRecord;
use App\Models\ClarificationRecord;
use App\Models\CpoDispatchRecord;
use App\Models\DepricarpingRecord;
use App\Models\EffluentPlantRecord;
use App\Models\EngineRoomRecord;
use App\Models\GradingParameter;
use App\Models\GradingRecord;
use App\Models\KernelDispatchRecord;
use App\Models\KernelPlantRecord;
use App\Models\Machinery;
use App\Models\PressingRecord;
use App\Models\ProcessQualityControlRecord;
use App\Models\ProcessWaterRecord;
use App\Models\ProductionLine;
use App\Models\SolidWasteDisposalRecord;
use App\Models\Station;
use App\Models\SterilizerRecord;
use App\Models\StorageTankRecord;
use App\Models\ThreshingRecord;
use App\Models\User;
use App\Models\WeighbridgeRecord;
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
use App\Services\WeighbridgeRecordService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// TestCase + RefreshDatabase are applied to tests/Feature by tests/Pest.php.

/**
 * One entry per station type: the StationFactory state that produces the
 * station, the service whose create()/update() is exercised, the model, and
 * a `payload` closure that both seeds whatever prerequisites that service
 * needs and returns a complete, valid create() payload (minus
 * `production_line_id`, which the caller injects).
 *
 * @return array<string, array{state: string, service: class-string, model: class-string, payload: callable}>
 */
function recordProductionLineCases(): array
{
    $timeSlotDetail = fn (string $field, float|int $value) => [
        'details' => [['time_slot' => '07:00', $field => $value]],
    ];

    $eventDetail = [
        'details' => [['event_date' => '2026-08-31', 'gross_weight_mt' => 10, 'tare_weight_mt' => 2]],
    ];

    return [
        'weighbridge' => [
            'state' => 'weighbridge',
            'service' => WeighbridgeRecordService::class,
            'model' => WeighbridgeRecord::class,
            'payload' => fn (Station $station) => [
                'wb_card_number' => 'WB-'.Str::random(6),
                'weighbridge_type' => 'receive',
                'record_datetime' => '2026-08-20T08:00:00',
                'vehicle_number' => 'B 1234 XY',
                'driver_name' => 'Budi',
                'estate_supplier' => 'Estate A',
                'gross_weight' => 15000,
                'tare_weight' => 5000,
            ],
        ],
        'grading' => [
            'state' => 'grading',
            'service' => GradingRecordService::class,
            'model' => GradingRecord::class,
            'payload' => function (Station $station) {
                $weighbridgeStation = Station::factory()
                    ->forProductionLine($station->production_line_id)
                    ->weighbridge()
                    ->create();

                return [
                    'grading_number' => 'GR-'.Str::random(6),
                    'date' => '2026-08-20',
                    'license_plate_no' => 'B 1234 XY',
                    'estate_supplier' => 'Estate A',
                    'netto' => 1000,
                    'quantity' => 120,
                    'weighbridge_record_id' => WeighbridgeRecord::factory()->forStation($weighbridgeStation)->create()->id,
                    'details' => [[
                        'grading_parameter_id' => GradingParameter::factory()->create(['uom' => Uom::Kg])->id,
                        'quantity' => 250,
                    ]],
                ];
            },
        ],
        'cages-track' => [
            'state' => 'cagesTrack',
            'service' => CagesTrackRecordService::class,
            'model' => CagesTrackRecord::class,
            'payload' => function (Station $station) {
                // jumlah_cages is COUNT(machinery) on the resolved station.
                Machinery::factory()->count(10)->create(['station_id' => $station->id]);

                return [
                    'cages_track_number' => 'CT-'.Str::random(6),
                    'date' => '2026-08-20',
                    'tippler_start_time' => '2026-08-20T08:00:00Z',
                    'tippler_stop_time' => '2026-08-20T09:00:00Z',
                    'cages_out' => 12,
                    'cages_tipped' => 10,
                    'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1, 3, 5]]],
                ];
            },
        ],
        'threshing' => [
            'state' => 'threshing',
            'service' => ThreshingRecordService::class,
            'model' => ThreshingRecord::class,
            'payload' => fn (Station $station) => array_merge([
                'thresher_id' => 'TH-'.Str::random(6),
                'date' => '2026-08-24',
                'note' => null,
            ], $timeSlotDetail('ffb_throughput_mt_hour', 45.5)),
        ],
        'pressing' => [
            'state' => 'pressing',
            'service' => PressingRecordService::class,
            'model' => PressingRecord::class,
            'payload' => fn (Station $station) => array_merge([
                'presser_id' => 'PR-'.Str::random(6),
                'date' => '2026-08-24',
                'note' => null,
            ], $timeSlotDetail('digester_temp_c', 92)),
        ],
        'depricarping' => [
            'state' => 'depricarping',
            'service' => DepricarpingRecordService::class,
            'model' => DepricarpingRecord::class,
            'payload' => fn (Station $station) => array_merge([
                'presser_id' => 'DP-'.Str::random(6),
                'date' => '2026-08-24',
                'note' => null,
            ], $timeSlotDetail('fan_static_pressure_mmh2o', 45)),
        ],
        'kernel-plant' => [
            'state' => 'kernelPlant',
            'service' => KernelPlantRecordService::class,
            'model' => KernelPlantRecord::class,
            'payload' => fn (Station $station) => array_merge([
                'kernel_plant_id' => 'KP-'.Str::random(6),
                'date' => '2026-08-24',
                'note' => null,
            ], $timeSlotDetail('ripple_mill_1_amps', 22)),
        ],
        'solid-waste-disposal' => [
            'state' => 'solidWasteDisposal',
            'service' => SolidWasteDisposalRecordService::class,
            'model' => SolidWasteDisposalRecord::class,
            'payload' => fn (Station $station) => array_merge([
                'solid_waste_disposal_id' => 'SWD-'.Str::random(6),
                'date' => '2026-08-31',
                'note' => null,
            ], $eventDetail),
        ],
        'process-water' => [
            'state' => 'processWater',
            'service' => ProcessWaterRecordService::class,
            'model' => ProcessWaterRecord::class,
            'payload' => fn (Station $station) => array_merge([
                'process_water_id' => 'PW-'.Str::random(6),
                'date' => '2026-08-31',
                'note' => null,
            ], $timeSlotDetail('raw_water_flow_m3h', 45.5)),
        ],
        'kernel-dispatch' => [
            'state' => 'kernelDispatch',
            'service' => KernelDispatchRecordService::class,
            'model' => KernelDispatchRecord::class,
            'payload' => fn (Station $station) => array_merge([
                'kernel_dispatch_id' => 'KD-'.Str::random(6),
                'date' => '2026-08-31',
                'note' => null,
            ], $eventDetail),
        ],
        'cpo-dispatch' => [
            'state' => 'cpoDispatch',
            'service' => CpoDispatchRecordService::class,
            'model' => CpoDispatchRecord::class,
            'payload' => fn (Station $station) => array_merge([
                'cpo_dispatch_id' => 'CD-'.Str::random(6),
                'date' => '2026-08-31',
                'note' => null,
            ], $eventDetail),
        ],
        'effluent-plant' => [
            'state' => 'effluentPlant',
            'service' => EffluentPlantRecordService::class,
            'model' => EffluentPlantRecord::class,
            'payload' => fn (Station $station) => array_merge([
                'effluent_plant_id' => 'EP-'.Str::random(6),
                'date' => '2026-08-31',
                'note' => null,
            ], $timeSlotDetail('anaerobic_pond_1_ph', 45.5)),
        ],
        'storage-tank' => [
            'state' => 'storageTank',
            'service' => StorageTankRecordService::class,
            'model' => StorageTankRecord::class,
            'payload' => fn (Station $station) => array_merge([
                'storage_tank_id' => 'ST-'.Str::random(6),
                'date' => '2026-08-31',
                'note' => null,
            ], $timeSlotDetail('cpo_sounding_depth_mm', 1200.5)),
        ],
        'engine-room' => [
            'state' => 'engineRoom',
            'service' => EngineRoomRecordService::class,
            'model' => EngineRoomRecord::class,
            'payload' => fn (Station $station) => array_merge([
                'engine_room_id' => 'ER-'.Str::random(6),
                'date' => '2026-08-31',
                'note' => null,
            ], $timeSlotDetail('steam_turbine_inlet_pressure_bar', 12.5)),
        ],
        'boiler-room' => [
            'state' => 'boilerRoom',
            'service' => BoilerRoomRecordService::class,
            'model' => BoilerRoomRecord::class,
            'payload' => fn (Station $station) => array_merge([
                'boiler_room_id' => 'BR-'.Str::random(6),
                'date' => '2026-08-31',
                'note' => null,
            ], $timeSlotDetail('steam_pressure_bar', 12.5)),
        ],
        'clarification' => [
            'state' => 'clarification',
            'service' => ClarificationRecordService::class,
            'model' => ClarificationRecord::class,
            'payload' => fn (Station $station) => array_merge([
                'clarification_id' => 'CLR-'.Str::random(6),
                'date' => '2026-08-31',
                'note' => null,
            ], $timeSlotDetail('clarification_tank_temp_c', 65.5)),
        ],
        'process-quality-control' => [
            'state' => 'processQualityControl',
            'service' => ProcessQualityControlRecordService::class,
            'model' => ProcessQualityControlRecord::class,
            'payload' => fn (Station $station) => array_merge([
                'process_qc_id' => 'PQC-'.Str::random(6),
                'date' => '2026-08-31',
                'note' => null,
            ], $timeSlotDetail('fruit_press_oil_loss_in_sludge_percent', 0.85)),
        ],
        'sterilizer' => [
            'state' => 'sterilizer',
            'service' => SterilizerRecordService::class,
            'model' => SterilizerRecord::class,
            'payload' => fn (Station $station) => [
                'sterilizer_id' => 'STR-'.Str::random(6),
                'date' => '2026-08-31',
                'note' => null,
                'details' => [['close_door_time' => '07:00', 'open_door_time' => '08:10']],
            ],
        ],
    ];
}

/**
 * Build the mill, the production line, the station of $state, and an
 * Operator bound to that mill — the realistic write actor.
 *
 * @return array{0: Station, 1: User, 2: BusinessUnit}
 */
function stationAndActorFor(string $state): array
{
    $businessUnit = BusinessUnit::factory()->create();
    $station = Station::factory()->forBusinessUnit($businessUnit)->{$state}()->create();
    $actor = User::factory()->forBusinessUnit($businessUnit)->role(UserRole::Operator)->create();

    // Prasyarat kunci periode (usecase-141): ke-18 *RecordService menolak
    // penulisan tanpa Periode Pelaporan yang TERBUKA untuk jenis stasiun itu.
    // Berkas ini menguji snapshot production_line_id, bukan kunci periodenya,
    // jadi prasyaratnya dipenuhi di sini — satu titik untuk seluruh dataset
    // 18 stasiun. Kunci periodenya diuji tersendiri di
    // tests/Unit/Support/EnforcesPeriodLockTest.php.
    openPeriodForStation($station);

    test()->actingAs($actor);

    return [$station, $actor, $businessUnit];
}

it('stores production_line_id equal to the resolved station\'s line on create', function (string $state, string $serviceClass, string $modelClass, callable $payloadFor) {
    [$station, $actor] = stationAndActorFor($state);

    $payload = $payloadFor($station);
    $payload['production_line_id'] = $station->production_line_id;

    $created = (new $serviceClass)->create($payload, $actor);

    $record = $modelClass::findOrFail($created['id']);

    expect($record->production_line_id)->not->toBeNull()
        ->and($record->production_line_id)->toBe($station->production_line_id)
        ->and($record->station_id)->toBe($station->id);
})->with(array_map(
    fn (array $case) => [$case['state'], $case['service'], $case['model'], $case['payload']],
    recordProductionLineCases()
));

it('never lets the client choose the stored line — the value always comes from the station', function (string $state, string $serviceClass, string $modelClass, callable $payloadFor) {
    [$station, $actor, $businessUnit] = stationAndActorFor($state);

    // A second line in the SAME mill, carrying a station of the same type.
    // The client's `production_line_id` selects THAT station, so the stored
    // line must be that station's line — not the first one, and not
    // anything the client could name independently.
    $otherLine = ProductionLine::factory()->create(['business_unit_id' => $businessUnit->id]);
    $otherStation = Station::factory()->forProductionLine($otherLine)->{$state}()->create();

    $payload = $payloadFor($otherStation);
    $payload['production_line_id'] = $otherLine->id;

    $created = (new $serviceClass)->create($payload, $actor);
    $record = $modelClass::findOrFail($created['id']);

    expect($record->station_id)->toBe($otherStation->id)
        ->and($record->production_line_id)->toBe($otherStation->production_line_id)
        ->and($record->production_line_id)->toBe($otherLine->id)
        ->and($record->production_line_id)->not->toBe($station->production_line_id);
})->with(array_map(
    fn (array $case) => [$case['state'], $case['service'], $case['model'], $case['payload']],
    recordProductionLineCases()
));

it('leaves production_line_id untouched on update, even when a different line is sent', function (string $state, string $serviceClass, string $modelClass, callable $payloadFor) {
    [$station, $actor, $businessUnit] = stationAndActorFor($state);

    $payload = $payloadFor($station);
    $payload['production_line_id'] = $station->production_line_id;

    $created = (new $serviceClass)->create($payload, $actor);

    $otherLine = ProductionLine::factory()->create(['business_unit_id' => $businessUnit->id]);

    $updatePayload = $payload;
    $updatePayload['production_line_id'] = $otherLine->id;

    (new $serviceClass)->update($created['id'], $updatePayload, $actor);

    $record = $modelClass::findOrFail($created['id']);

    // Neither moved to $otherLine nor blanked out.
    expect($record->production_line_id)->toBe($station->production_line_id)
        ->and($record->production_line_id)->not->toBeNull()
        ->and($record->station_id)->toBe($station->id);
})->with(array_map(
    fn (array $case) => [$case['state'], $case['service'], $case['model'], $case['payload']],
    recordProductionLineCases()
));

/*
|--------------------------------------------------------------------------
| THE REASON THIS WHOLE STAGE EXISTS
|--------------------------------------------------------------------------
*/

it('keeps a record pointing at the line it was produced on after its station is moved', function (string $modelClass, string $state) {
    $businessUnit = BusinessUnit::factory()->create();
    $station = Station::factory()->forBusinessUnit($businessUnit)->{$state}()->create();
    $lineItWasProducedOn = $station->production_line_id;

    $record = $modelClass::factory()->forStation($station)->create();

    expect($record->production_line_id)->toBe($lineItWasProducedOn);

    // The station is re-assigned to a different line in the same mill —
    // a legitimate configuration change, not a data fix.
    $newLine = ProductionLine::factory()->create(['business_unit_id' => $businessUnit->id]);
    $station->update(['production_line_id' => $newLine->id]);

    $record->refresh();
    $record->load('station');

    // BEFORE 2026_09_28_000041 this assertion could not be written: the
    // record's line WAS `station.production_line_id`, so the move rewrote
    // history and yesterday's log sheet started claiming it belonged to the
    // new line. The stored snapshot is what makes the past immutable.
    expect($record->production_line_id)->toBe($lineItWasProducedOn)
        ->and($record->station->production_line_id)->toBe($newLine->id)
        ->and($record->production_line_id)->not->toBe($record->station->production_line_id);
})->with(array_map(
    fn (array $case) => [$case['model'], $case['state']],
    recordProductionLineCases()
));

/*
|--------------------------------------------------------------------------
| DATABASE-LEVEL ENFORCEMENT
|--------------------------------------------------------------------------
*/

it('declares production_line_id as a non-nullable column on all 18 record tables', function () {
    $tables = [
        'boiler_room_records', 'cages_track_records', 'clarification_records',
        'cpo_dispatch_records', 'depricarping_records', 'effluent_plant_records',
        'engine_room_records', 'grading_records', 'kernel_dispatch_records',
        'kernel_plant_records', 'pressing_records', 'process_quality_control_records',
        'process_water_records', 'solid_waste_disposal_records', 'sterilizer_records',
        'storage_tank_records', 'threshing_records', 'weighbridge_records',
    ];

    expect($tables)->toHaveCount(18);

    foreach ($tables as $table) {
        $column = collect(Schema::getColumns($table))->firstWhere('name', 'production_line_id');

        expect($column)->not->toBeNull("$table is missing production_line_id");
        expect($column['nullable'])->toBeFalse("$table.production_line_id must be NOT NULL");
    }
});

it('refuses an insert with no production_line_id at the database level', function () {
    $station = Station::factory()->sterilizer()->create();
    $creator = User::factory()->create();

    expect(fn () => DB::table('sterilizer_records')->insert([
        'id' => (string) Str::uuid(),
        'station_id' => $station->id,
        'production_line_id' => null,
        'sterilizer_id' => 'STR-NULL-PROBE',
        'date' => '2026-09-28',
        'status' => 'saved',
        'created_by' => $creator->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('refuses an insert whose production_line_id is not a real production line', function () {
    $station = Station::factory()->sterilizer()->create();
    $creator = User::factory()->create();

    expect(fn () => DB::table('sterilizer_records')->insert([
        'id' => (string) Str::uuid(),
        'station_id' => $station->id,
        // Well-formed UUID, absent from `production_lines`.
        'production_line_id' => '00000000-0000-0000-0000-000000000000',
        'sterilizer_id' => 'STR-FK-PROBE',
        'date' => '2026-09-28',
        'status' => 'saved',
        'created_by' => $creator->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('protects production history by refusing to delete a production line that has records', function () {
    $station = Station::factory()->sterilizer()->create();
    SterilizerRecord::factory()->forStation($station)->create();

    // RESTRICT, not CASCADE — deleting a production line must never erase
    // the operational history produced on it. (Deliberately different from
    // the `cascadeOnDelete()` the same column uses on `machinery`, which is
    // configuration rather than history.)
    expect(fn () => DB::table('production_lines')->where('id', $station->production_line_id)->delete())
        ->toThrow(QueryException::class);
});
