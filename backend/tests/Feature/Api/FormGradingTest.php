<?php

/**
 * FormGradingTest (Feature/Api) — screen-023--form-grading-web /
 * usecase-023--form-grading-web.
 *
 * Integration tests for POST /api/grading-records and
 * PATCH /api/grading-records/{id} (App\Http\Controllers\Api\
 * GradingRecordController::store()/update()), one per test_scenarios'
 * api_test step(s). Mirrors FormWeighbridgeTest.php's setup/conventions
 * (screen-022), with GradingRecord's extra details[] array + weighbridge
 * reference layered on top.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\GradingParameter;
use App\Models\GradingRecord;
use App\Models\Station;
use App\Models\User;
use App\Models\WeighbridgeRecord;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->weighbridgeStation = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->gradingStation = Station::factory()->forBusinessUnit($this->businessUnit)->grading()->create();
    $this->weighbridgeRecord = WeighbridgeRecord::factory()->forStation($this->weighbridgeStation)->create();
    $this->gradingParameter = GradingParameter::factory()->create(['uom' => \App\Enums\Uom::Kg]);
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnit)->create();
    // Prasyarat kunci periode (usecase-141): ke-18 *RecordService menolak
    // penulisan data stasiun tanpa Periode Pelaporan yang TERBUKA untuk jenis
    // stasiun itu. Test di berkas ini menguji aturan stasiunnya sendiri, bukan
    // kunci periodenya, jadi prasyaratnya dipenuhi di sini. Kunci periodenya
    // diuji tersendiri di tests/Unit/Support/EnforcesPeriodLockTest.php.
    openPeriodFor($this->businessUnit->id, 'grading');
});

function gradingApiPayload(array $overrides = []): array
{
    return array_merge([
        'grading_number' => 'GR-API-001',
        'date' => '2026-08-20',
        'license_plate_no' => 'B 1234 XY',
        'estate_supplier' => 'Estate A',
        'netto' => 1000,
        'quantity' => 120,
    ], $overrides);
}

// Scenario: "Buat Record Grading Baru — berhasil"
it('berhasil: creates a new record with status=saved, resolved station_id, and inserted details', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/grading-records', gradingApiPayload([
        'production_line_id' => $this->gradingStation->production_line_id,
        'weighbridge_record_id' => $this->weighbridgeRecord->id,
        'details' => [['grading_parameter_id' => $this->gradingParameter->id, 'quantity' => 250]],
    ]));

    $response->assertCreated();
    $response->assertJsonFragment(['station_id' => $this->gradingStation->id, 'status' => 'saved']);
    expect(GradingRecord::where('grading_number', 'GR-API-001')->exists())->toBeTrue();
});

// Scenario: "Field Wajib Belum Lengkap"
it('returns 422 VALIDATION_ERROR when a required field is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/grading-records', gradingApiPayload([
        'production_line_id' => $this->gradingStation->production_line_id,
        'weighbridge_record_id' => $this->weighbridgeRecord->id,
        'grading_number' => '',
        'details' => [['grading_parameter_id' => $this->gradingParameter->id, 'quantity' => 5]],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('grading_number');
});

// Scenario: "Belum Ada Baris Grading Detail Valid"
it('returns 422 VALIDATION_ERROR when details array is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/grading-records', gradingApiPayload([
        'production_line_id' => $this->gradingStation->production_line_id,
        'weighbridge_record_id' => $this->weighbridgeRecord->id,
        'details' => [],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

// Scenario: "Quality Parameter Tidak Bisa Duplikat Antar Baris"
it('returns 422 VALIDATION_ERROR when two detail rows share the same grading_parameter_id', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/grading-records', gradingApiPayload([
        'production_line_id' => $this->gradingStation->production_line_id,
        'weighbridge_record_id' => $this->weighbridgeRecord->id,
        'details' => [
            ['grading_parameter_id' => $this->gradingParameter->id, 'quantity' => 5],
            ['grading_parameter_id' => $this->gradingParameter->id, 'quantity' => 3],
        ],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

// Scenario: "Business Unit Tanpa Station Grading Aktif"
it('returns 422 when production_line_id has no active grading station', function () {
    $otherProductionLine = \App\Models\ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create();

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/grading-records', gradingApiPayload([
        'production_line_id' => $otherProductionLine->id,
        'weighbridge_record_id' => $this->weighbridgeRecord->id,
        'details' => [['grading_parameter_id' => $this->gradingParameter->id, 'quantity' => 5]],
    ]));

    $response->assertStatus(422);
});

// TEMPORARY (2026-08-20, mobile syncService.ts): Operator is now allowed
// on this route (was 403) — see FormWeighbridgeTest.php's matching test
// for the full rationale.
it('allows the Operator role to create (mobile sync)', function () {
    $response = $this->actingAs($this->operator, 'web')->postJson('/api/grading-records', gradingApiPayload([
        'production_line_id' => $this->gradingStation->production_line_id,
        'weighbridge_record_id' => $this->weighbridgeRecord->id,
        'details' => [['grading_parameter_id' => $this->gradingParameter->id, 'quantity' => 5]],
    ]));

    $response->assertCreated();
});

// Scenario: "Edit Record Grading — berhasil"
it('berhasil: updates an existing record and its details', function () {
    $record = GradingRecord::factory()->forStation($this->gradingStation)->create(['grading_number' => 'GR-OLD']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/grading-records/{$record->id}", gradingApiPayload([
        'weighbridge_record_id' => $this->weighbridgeRecord->id,
        'grading_number' => 'GR-NEW',
        'details' => [['grading_parameter_id' => $this->gradingParameter->id, 'quantity' => 5]],
    ]));

    $response->assertOk();
    $response->assertJsonFragment(['grading_number' => 'GR-NEW']);
    expect($record->fresh()->grading_number)->toBe('GR-NEW');
});

it('does not change station_id even if production_line_id is sent on update', function () {
    $record = GradingRecord::factory()->forStation($this->gradingStation)->create();
    $otherProductionLine = \App\Models\ProductionLine::factory()->create();
    Station::factory()->forProductionLine($otherProductionLine)->grading()->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/grading-records/{$record->id}", gradingApiPayload([
        'production_line_id' => $otherProductionLine->id,
        'weighbridge_record_id' => $this->weighbridgeRecord->id,
        'details' => [['grading_parameter_id' => $this->gradingParameter->id, 'quantity' => 5]],
    ]));

    $response->assertOk();
    expect($record->fresh()->station_id)->toBe($this->gradingStation->id);
});

// Scenario: "Record Tidak Ditemukan (mode edit)"
it('returns 404 RECORD_NOT_FOUND when updating a non-existent id', function () {
    $response = $this->actingAs($this->admin, 'web')->patchJson(
        '/api/grading-records/00000000-0000-0000-0000-000000000000',
        gradingApiPayload([
            'weighbridge_record_id' => $this->weighbridgeRecord->id,
            'details' => [['grading_parameter_id' => $this->gradingParameter->id, 'quantity' => 5]],
        ])
    );

    $response->assertStatus(404);
});

it('rejects unauthenticated requests on create and update', function () {
    $this->postJson('/api/grading-records', gradingApiPayload([
        'production_line_id' => $this->gradingStation->production_line_id,
        'weighbridge_record_id' => $this->weighbridgeRecord->id,
        'details' => [['grading_parameter_id' => $this->gradingParameter->id, 'quantity' => 5]],
    ]))->assertStatus(401);

    $record = GradingRecord::factory()->forStation($this->gradingStation)->create();
    $this->patchJson("/api/grading-records/{$record->id}", gradingApiPayload())->assertStatus(401);
});

/*
|--------------------------------------------------------------------------
| Cross-mill write guard — HTTP contract (2026-09-28)
|--------------------------------------------------------------------------
| 403 FORBIDDEN (not 422): payload well-formed, target row real, actor simply
| has no access to that mill. See App\Exceptions\CrossMillWriteDeniedException.
*/

it('menolak 403 FORBIDDEN saat POST memakai production_line_id mill lain, tanpa menulis satu baris pun', function () {
    $otherMill = BusinessUnit::factory()->create();
    $otherStation = Station::factory()->forBusinessUnit($otherMill)->grading()->create();
    $recordsBefore = GradingRecord::count();

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/grading-records', gradingApiPayload([
        'production_line_id' => $otherStation->production_line_id, 'weighbridge_record_id' => $this->weighbridgeRecord->id, 'details' => [['grading_parameter_id' => $this->gradingParameter->id, 'quantity' => 250]],
    ]));

    $response->assertStatus(403);
    $response->assertJsonPath('code', 'FORBIDDEN');
    expect(GradingRecord::count())->toBe($recordsBefore);
});

it('menolak 403 FORBIDDEN saat PATCH record milik mill lain, dan tidak mengubah satu kolom pun', function () {
    $otherMill = BusinessUnit::factory()->create();
    $otherStation = Station::factory()->forBusinessUnit($otherMill)->grading()->create();
    $record = GradingRecord::factory()->forStation($otherStation)->create(['grading_number' => 'SCOPE-MILIK-MILL-B']);
    $before = $record->fresh()->getAttributes();

    $response = $this->actingAs($this->supervisor, 'web')->patchJson("/api/grading-records/{$record->id}", gradingApiPayload([
        'grading_number' => 'SCOPE-HIJACKED', 'weighbridge_record_id' => $this->weighbridgeRecord->id, 'details' => [['grading_parameter_id' => $this->gradingParameter->id, 'quantity' => 250]],
    ]));

    $response->assertStatus(403);
    $response->assertJsonPath('code', 'FORBIDDEN');
    expect($record->fresh()->getAttributes())->toBe($before);
});

/*
|--------------------------------------------------------------------------
| Kunci Periode Pelaporan — kontrak HTTP (usecase-141)
|--------------------------------------------------------------------------
| Dulu hanya Sterilizer yang punya test HTTP penolakan kunci periode
| (KelolaPeriodePelaporanTest). Blok ini — seragam di ke-18 berkas Form*Test —
| membuktikan route stasiun ini benar-benar menolak 422 PERIOD_CLOSED. Periode
| prasyarat yang lebar dari beforeEach diganti dengan dua periode sempit lewat
| replacePrerequisiteWithClosedAndOpenPeriods() (tests/Pest.php): Juli 2026
| TERTUTUP, Agustus 2026 TERBUKA. Tanggal kejadian stasiun ini: kolom `date`.
*/

function gradingPeriodLockPayload($test, string $date, string $id): array
{
    return gradingApiPayload([
        'grading_number' => $id,
        'date' => $date,
        'weighbridge_record_id' => $test->weighbridgeRecord->id,
        'details' => [['grading_parameter_id' => $test->gradingParameter->id, 'quantity' => 250]],
    ]);
}

it('kunci periode: menolak 422 PERIOD_CLOSED saat POST bertanggal di periode tertutup, tanpa menyimpan satu baris pun', function () {
    replacePrerequisiteWithClosedAndOpenPeriods($this->businessUnit->id, 'grading');
    $recordsBefore = GradingRecord::count();

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/grading-records', array_merge(
        gradingPeriodLockPayload($this, PERIOD_LOCK_CLOSED_DATE, 'GR-LOCK-CLOSED'),
        ['production_line_id' => $this->gradingStation->production_line_id],
    ));

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'PERIOD_CLOSED');
    expect(GradingRecord::count())->toBe($recordsBefore);
});

it('kunci periode: menolak 422 PERIOD_CLOSED saat PATCH record yang tanggal lamanya di periode tertutup walau tanggal barunya di periode terbuka, dan record tidak berubah', function () {
    replacePrerequisiteWithClosedAndOpenPeriods($this->businessUnit->id, 'grading');
    // Record lama dibuat lewat factory (melewati service), tanggalnya di Juli yang tertutup.
    $record = GradingRecord::factory()->forStation($this->gradingStation)->create([
        'grading_number' => 'GR-LOCK-OLD',
        'date' => PERIOD_LOCK_CLOSED_DATE,
    ]);
    $before = $record->fresh()->getAttributes();

    // Tanggal baru jatuh di Agustus yang terbuka — tetap harus ditolak, karena
    // memindahkan record keluar dari periode tertutup sama saja mengubah isinya.
    $response = $this->actingAs($this->admin, 'web')->patchJson(
        "/api/grading-records/{$record->id}",
        gradingPeriodLockPayload($this, PERIOD_LOCK_OPEN_MID, 'GR-LOCK-MOVED'),
    );

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'PERIOD_CLOSED');
    expect($record->fresh()->getAttributes())->toBe($before);
});

it('kunci periode: menerima 201 saat POST tepat pada tanggal awal dan tanggal akhir periode terbuka (inklusif)', function () {
    replacePrerequisiteWithClosedAndOpenPeriods($this->businessUnit->id, 'grading');

    foreach ([PERIOD_LOCK_OPEN_START => 'GR-LOCK-START', PERIOD_LOCK_OPEN_END => 'GR-LOCK-END'] as $date => $id) {
        $this->actingAs($this->supervisor, 'web')->postJson('/api/grading-records', array_merge(
            gradingPeriodLockPayload($this, $date, $id),
            ['production_line_id' => $this->gradingStation->production_line_id],
        ))->assertCreated();
    }

    expect(GradingRecord::whereIn('grading_number', ['GR-LOCK-START', 'GR-LOCK-END'])->count())->toBe(2);
});
