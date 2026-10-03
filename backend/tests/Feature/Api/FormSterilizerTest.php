<?php

/**
 * FormSterilizerTest (Feature/Api) —
 * screen-126--form-sterilizer-web /
 * usecase-126--form-sterilizer-web.
 *
 * Integration tests for POST /api/sterilizer-records and
 * PATCH /api/sterilizer-records/{id}. Mirrors FormCpoDispatchTest.php's
 * structure, minus the grid/N-column and time-slot-ordering concerns —
 * Sterilizer's detail rows are a free event log. This is the FINAL
 * station of this project.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\SterilizerRecord;
use App\Models\User;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->sterilizerStation = Station::factory()->forBusinessUnit($this->businessUnit)->sterilizer()->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnit)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnit)->create();
    // Prasyarat kunci periode (usecase-141): ke-18 *RecordService menolak
    // penulisan data stasiun tanpa Periode Pelaporan yang TERBUKA untuk jenis
    // stasiun itu. Test di berkas ini menguji aturan stasiunnya sendiri, bukan
    // kunci periodenya, jadi prasyaratnya dipenuhi di sini. Kunci periodenya
    // diuji tersendiri di tests/Unit/Support/EnforcesPeriodLockTest.php.
    openPeriodFor($this->businessUnit->id, 'sterilizer');
});

function sterilizerApiPayload(array $overrides = []): array
{
    return array_merge([
        'sterilizer_id' => 'STR-API-001',
        'date' => '2026-08-31',
    ], $overrides);
}

// Scenario: "Buat Record Sterilizer Baru — berhasil"
it('berhasil: creates a new record with status=saved, resolved station_id, and inserted details', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/sterilizer-records', sterilizerApiPayload([
        'production_line_id' => $this->sterilizerStation->production_line_id,
        'details' => [['close_door_time' => '07:00', 'open_door_time' => '08:10']],
    ]));

    $response->assertCreated();
    $response->assertJsonFragment(['station_id' => $this->sterilizerStation->id, 'status' => 'saved']);
    expect(SterilizerRecord::where('sterilizer_id', 'STR-API-001')->exists())->toBeTrue();
});

it('computes duration_minutes server-side and ignores any client-supplied value', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/sterilizer-records', sterilizerApiPayload([
        'production_line_id' => $this->sterilizerStation->production_line_id,
        'details' => [[
            'close_door_time' => '07:00',
            'open_door_time' => '08:10',
            'duration_minutes' => 999999,
        ]],
    ]));

    $response->assertCreated();
    $response->assertJsonFragment(['duration_minutes' => 70]);
});

// Scenario: "Field Wajib Belum Lengkap"
it('returns 422 VALIDATION_ERROR when a required field is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/sterilizer-records', sterilizerApiPayload([
        'production_line_id' => $this->sterilizerStation->production_line_id,
        'sterilizer_id' => '',
        'details' => [['close_door_time' => '07:00']],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('sterilizer_id');
});

// Scenario: "Belum Ada Baris Log Valid"
it('returns 422 VALIDATION_ERROR when details array is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/sterilizer-records', sterilizerApiPayload([
        'production_line_id' => $this->sterilizerStation->production_line_id,
        'details' => [],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when no detail row has a close_door_time', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/sterilizer-records', sterilizerApiPayload([
        'production_line_id' => $this->sterilizerStation->production_line_id,
        'details' => [['sterilizer_no' => '1']],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

// Scenario: "Production Line Tanpa Station Sterilizer Aktif"
it('returns 422 when production_line_id has no active sterilizer station', function () {
    $otherProductionLine = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create();

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/sterilizer-records', sterilizerApiPayload([
        'production_line_id' => $otherProductionLine->id,
        'details' => [['close_door_time' => '07:00']],
    ]));

    $response->assertStatus(422);
});

it('sets checked_by when checked=true and requester role=supervisor', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/sterilizer-records', sterilizerApiPayload([
        'production_line_id' => $this->sterilizerStation->production_line_id,
        'checked' => true,
        'details' => [['close_door_time' => '07:00']],
    ]));

    $response->assertCreated();
    expect(SterilizerRecord::where('sterilizer_id', 'STR-API-001')->first()->checked_by)->toBe($this->supervisor->id);
});

it('sets acknowledged_by when acknowledged=true and requester role=mill_management', function () {
    $response = $this->actingAs($this->millManagement, 'web')->postJson('/api/sterilizer-records', sterilizerApiPayload([
        'production_line_id' => $this->sterilizerStation->production_line_id,
        'acknowledged' => true,
        'details' => [['close_door_time' => '07:00']],
    ]));

    $response->assertCreated();
    expect(SterilizerRecord::where('sterilizer_id', 'STR-API-001')->first()->acknowledged_by)->toBe($this->millManagement->id);
});

// Mobile sync allowance — same convention as FormCpoDispatchTest.php:
// Operator is allowed to POST for offline sync.
it('allows the Operator role to create (mobile sync)', function () {
    $response = $this->actingAs($this->operator, 'web')->postJson('/api/sterilizer-records', sterilizerApiPayload([
        'production_line_id' => $this->sterilizerStation->production_line_id,
        'details' => [['close_door_time' => '07:00']],
    ]));

    $response->assertCreated();
});

// Scenario: "Edit Record Sterilizer — berhasil"
it('berhasil: updates an existing record and its details', function () {
    $record = SterilizerRecord::factory()->forStation($this->sterilizerStation)->create(['sterilizer_id' => 'STR-OLD']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/sterilizer-records/{$record->id}", sterilizerApiPayload([
        'sterilizer_id' => 'STR-NEW',
        'details' => [['close_door_time' => '07:00']],
    ]));

    $response->assertOk();
    $response->assertJsonFragment(['sterilizer_id' => 'STR-NEW']);
    expect($record->fresh()->sterilizer_id)->toBe('STR-NEW');
});

it('does not change station_id even if production_line_id is sent on update', function () {
    $record = SterilizerRecord::factory()->forStation($this->sterilizerStation)->create();
    $otherProductionLine = ProductionLine::factory()->create();
    Station::factory()->forProductionLine($otherProductionLine)->sterilizer()->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/sterilizer-records/{$record->id}", sterilizerApiPayload([
        'production_line_id' => $otherProductionLine->id,
        'details' => [['close_door_time' => '07:00']],
    ]));

    $response->assertOk();
    expect($record->fresh()->station_id)->toBe($this->sterilizerStation->id);
});

// Scenario: "Record Tidak Ditemukan (mode edit)"
it('returns 404 RECORD_NOT_FOUND when updating a non-existent id', function () {
    $response = $this->actingAs($this->admin, 'web')->patchJson(
        '/api/sterilizer-records/00000000-0000-0000-0000-000000000000',
        sterilizerApiPayload(['details' => [['close_door_time' => '07:00']]])
    );

    $response->assertStatus(404);
});

it('rejects unauthenticated requests on create and update', function () {
    $this->postJson('/api/sterilizer-records', sterilizerApiPayload([
        'production_line_id' => $this->sterilizerStation->production_line_id,
        'details' => [['close_door_time' => '07:00']],
    ]))->assertStatus(401);

    $record = SterilizerRecord::factory()->forStation($this->sterilizerStation)->create();
    $this->patchJson("/api/sterilizer-records/{$record->id}", sterilizerApiPayload())->assertStatus(401);
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
    $otherStation = Station::factory()->forBusinessUnit($otherMill)->sterilizer()->create();
    $recordsBefore = SterilizerRecord::count();

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/sterilizer-records', sterilizerApiPayload([
        'production_line_id' => $otherStation->production_line_id, 'details' => [['close_door_time' => '07:00', 'open_door_time' => '08:10']],
    ]));

    $response->assertStatus(403);
    $response->assertJsonPath('code', 'FORBIDDEN');
    expect(SterilizerRecord::count())->toBe($recordsBefore);
});

it('menolak 403 FORBIDDEN saat PATCH record milik mill lain, dan tidak mengubah satu kolom pun', function () {
    $otherMill = BusinessUnit::factory()->create();
    $otherStation = Station::factory()->forBusinessUnit($otherMill)->sterilizer()->create();
    $record = SterilizerRecord::factory()->forStation($otherStation)->create(['sterilizer_id' => 'SCOPE-MILIK-MILL-B']);
    $before = $record->fresh()->getAttributes();

    $response = $this->actingAs($this->supervisor, 'web')->patchJson("/api/sterilizer-records/{$record->id}", sterilizerApiPayload([
        'sterilizer_id' => 'SCOPE-HIJACKED', 'details' => [['close_door_time' => '07:00', 'open_door_time' => '08:10']],
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

function sterilizerPeriodLockPayload($test, string $date, string $id): array
{
    return sterilizerApiPayload([
        'sterilizer_id' => $id,
        'date' => $date,
        'details' => [['close_door_time' => '07:00', 'open_door_time' => '08:10']],
    ]);
}

it('kunci periode: menolak 422 PERIOD_CLOSED saat POST bertanggal di periode tertutup, tanpa menyimpan satu baris pun', function () {
    replacePrerequisiteWithClosedAndOpenPeriods($this->businessUnit->id, 'sterilizer');
    $recordsBefore = SterilizerRecord::count();

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/sterilizer-records', array_merge(
        sterilizerPeriodLockPayload($this, PERIOD_LOCK_CLOSED_DATE, 'STR-LOCK-CLOSED'),
        ['production_line_id' => $this->sterilizerStation->production_line_id],
    ));

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'PERIOD_CLOSED');
    expect(SterilizerRecord::count())->toBe($recordsBefore);
});

it('kunci periode: menolak 422 PERIOD_CLOSED saat PATCH record yang tanggal lamanya di periode tertutup walau tanggal barunya di periode terbuka, dan record tidak berubah', function () {
    replacePrerequisiteWithClosedAndOpenPeriods($this->businessUnit->id, 'sterilizer');
    // Record lama dibuat lewat factory (melewati service), tanggalnya di Juli yang tertutup.
    $record = SterilizerRecord::factory()->forStation($this->sterilizerStation)->create([
        'sterilizer_id' => 'STR-LOCK-OLD',
        'date' => PERIOD_LOCK_CLOSED_DATE,
    ]);
    $before = $record->fresh()->getAttributes();

    // Tanggal baru jatuh di Agustus yang terbuka — tetap harus ditolak, karena
    // memindahkan record keluar dari periode tertutup sama saja mengubah isinya.
    $response = $this->actingAs($this->admin, 'web')->patchJson(
        "/api/sterilizer-records/{$record->id}",
        sterilizerPeriodLockPayload($this, PERIOD_LOCK_OPEN_MID, 'STR-LOCK-MOVED'),
    );

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'PERIOD_CLOSED');
    expect($record->fresh()->getAttributes())->toBe($before);
});

it('kunci periode: menerima 201 saat POST tepat pada tanggal awal dan tanggal akhir periode terbuka (inklusif)', function () {
    replacePrerequisiteWithClosedAndOpenPeriods($this->businessUnit->id, 'sterilizer');

    foreach ([PERIOD_LOCK_OPEN_START => 'STR-LOCK-START', PERIOD_LOCK_OPEN_END => 'STR-LOCK-END'] as $date => $id) {
        $this->actingAs($this->supervisor, 'web')->postJson('/api/sterilizer-records', array_merge(
            sterilizerPeriodLockPayload($this, $date, $id),
            ['production_line_id' => $this->sterilizerStation->production_line_id],
        ))->assertCreated();
    }

    expect(SterilizerRecord::whereIn('sterilizer_id', ['STR-LOCK-START', 'STR-LOCK-END'])->count())->toBe(2);
});
