<?php

/**
 * FormCpoDispatchTest (Feature/Api) —
 * screen-114--form-cpo-dispatch-web /
 * usecase-084--form-cpo-dispatch-web.
 *
 * Integration tests for POST /api/cpo-dispatch-records and
 * PATCH /api/cpo-dispatch-records/{id}. Mirrors
 * FormKernelDispatchTest.php's structure, minus the grid/N-column and
 * time-slot-ordering concerns — CPO Dispatch's detail rows are a free
 * event log.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\CpoDispatchRecord;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->cpoDispatchStation = Station::factory()->forBusinessUnit($this->businessUnit)->cpoDispatch()->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnit)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnit)->create();
    // Prasyarat kunci periode (usecase-141): ke-18 *RecordService menolak
    // penulisan data stasiun tanpa Periode Pelaporan yang TERBUKA untuk jenis
    // stasiun itu. Test di berkas ini menguji aturan stasiunnya sendiri, bukan
    // kunci periodenya, jadi prasyaratnya dipenuhi di sini. Kunci periodenya
    // diuji tersendiri di tests/Unit/Support/EnforcesPeriodLockTest.php.
    openPeriodFor($this->businessUnit->id, 'cpo-dispatch');
});

function cpoDispatchApiPayload(array $overrides = []): array
{
    return array_merge([
        'cpo_dispatch_id' => 'CD-API-001',
        'date' => '2026-08-31',
    ], $overrides);
}

// Scenario: "Buat Record CPO Dispatch Baru — berhasil"
it('berhasil: creates a new record with status=saved, resolved station_id, and inserted details', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/cpo-dispatch-records', cpoDispatchApiPayload([
        'production_line_id' => $this->cpoDispatchStation->production_line_id,
        'details' => [['event_date' => '2026-08-31', 'gross_weight_mt' => 10, 'tare_weight_mt' => 2]],
    ]));

    $response->assertCreated();
    $response->assertJsonFragment(['station_id' => $this->cpoDispatchStation->id, 'status' => 'saved']);
    expect(CpoDispatchRecord::where('cpo_dispatch_id', 'CD-API-001')->exists())->toBeTrue();
});

it('computes net_weight_mt as gross_weight_mt minus tare_weight_mt', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/cpo-dispatch-records', cpoDispatchApiPayload([
        'production_line_id' => $this->cpoDispatchStation->production_line_id,
        'details' => [['event_date' => '2026-08-31', 'gross_weight_mt' => 10.5, 'tare_weight_mt' => 2.5]],
    ]));

    $response->assertCreated();
    $response->assertJsonFragment(['net_weight_mt' => 8.0]);
});

// Scenario: "Field Wajib Belum Lengkap"
it('returns 422 VALIDATION_ERROR when a required field is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/cpo-dispatch-records', cpoDispatchApiPayload([
        'production_line_id' => $this->cpoDispatchStation->production_line_id,
        'cpo_dispatch_id' => '',
        'details' => [['event_date' => '2026-08-31']],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('cpo_dispatch_id');
});

// Scenario: "Belum Ada Baris Log Valid"
it('returns 422 VALIDATION_ERROR when details array is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/cpo-dispatch-records', cpoDispatchApiPayload([
        'production_line_id' => $this->cpoDispatchStation->production_line_id,
        'details' => [],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when no detail row has an event_date', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/cpo-dispatch-records', cpoDispatchApiPayload([
        'production_line_id' => $this->cpoDispatchStation->production_line_id,
        'details' => [['shift' => 'Shift 1']],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

// Scenario: "Production Line Tanpa Station CPO Dispatch Aktif"
it('returns 422 when production_line_id has no active cpo-dispatch station', function () {
    $otherProductionLine = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create();

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/cpo-dispatch-records', cpoDispatchApiPayload([
        'production_line_id' => $otherProductionLine->id,
        'details' => [['event_date' => '2026-08-31']],
    ]));

    $response->assertStatus(422);
});

it('sets checked_by when checked=true and requester role=supervisor', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/cpo-dispatch-records', cpoDispatchApiPayload([
        'production_line_id' => $this->cpoDispatchStation->production_line_id,
        'checked' => true,
        'details' => [['event_date' => '2026-08-31']],
    ]));

    $response->assertCreated();
    expect(CpoDispatchRecord::where('cpo_dispatch_id', 'CD-API-001')->first()->checked_by)->toBe($this->supervisor->id);
});

it('sets acknowledged_by when acknowledged=true and requester role=mill_management', function () {
    $response = $this->actingAs($this->millManagement, 'web')->postJson('/api/cpo-dispatch-records', cpoDispatchApiPayload([
        'production_line_id' => $this->cpoDispatchStation->production_line_id,
        'acknowledged' => true,
        'details' => [['event_date' => '2026-08-31']],
    ]));

    $response->assertCreated();
    expect(CpoDispatchRecord::where('cpo_dispatch_id', 'CD-API-001')->first()->acknowledged_by)->toBe($this->millManagement->id);
});

// Mobile sync allowance — same convention as FormKernelDispatchTest.php/
// FormSolidWasteDisposalTest.php: Operator is allowed to POST for offline sync.
it('allows the Operator role to create (mobile sync)', function () {
    $response = $this->actingAs($this->operator, 'web')->postJson('/api/cpo-dispatch-records', cpoDispatchApiPayload([
        'production_line_id' => $this->cpoDispatchStation->production_line_id,
        'details' => [['event_date' => '2026-08-31']],
    ]));

    $response->assertCreated();
});

// Scenario: "Edit Record CPO Dispatch — berhasil"
it('berhasil: updates an existing record and its details', function () {
    $record = CpoDispatchRecord::factory()->forStation($this->cpoDispatchStation)->create(['cpo_dispatch_id' => 'CD-OLD']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/cpo-dispatch-records/{$record->id}", cpoDispatchApiPayload([
        'cpo_dispatch_id' => 'CD-NEW',
        'details' => [['event_date' => '2026-08-31']],
    ]));

    $response->assertOk();
    $response->assertJsonFragment(['cpo_dispatch_id' => 'CD-NEW']);
    expect($record->fresh()->cpo_dispatch_id)->toBe('CD-NEW');
});

it('does not change station_id even if production_line_id is sent on update', function () {
    $record = CpoDispatchRecord::factory()->forStation($this->cpoDispatchStation)->create();
    $otherProductionLine = ProductionLine::factory()->create();
    Station::factory()->forProductionLine($otherProductionLine)->cpoDispatch()->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/cpo-dispatch-records/{$record->id}", cpoDispatchApiPayload([
        'production_line_id' => $otherProductionLine->id,
        'details' => [['event_date' => '2026-08-31']],
    ]));

    $response->assertOk();
    expect($record->fresh()->station_id)->toBe($this->cpoDispatchStation->id);
});

// Scenario: "Record Tidak Ditemukan (mode edit)"
it('returns 404 RECORD_NOT_FOUND when updating a non-existent id', function () {
    $response = $this->actingAs($this->admin, 'web')->patchJson(
        '/api/cpo-dispatch-records/00000000-0000-0000-0000-000000000000',
        cpoDispatchApiPayload(['details' => [['event_date' => '2026-08-31']]])
    );

    $response->assertStatus(404);
});

it('rejects unauthenticated requests on create and update', function () {
    $this->postJson('/api/cpo-dispatch-records', cpoDispatchApiPayload([
        'production_line_id' => $this->cpoDispatchStation->production_line_id,
        'details' => [['event_date' => '2026-08-31']],
    ]))->assertStatus(401);

    $record = CpoDispatchRecord::factory()->forStation($this->cpoDispatchStation)->create();
    $this->patchJson("/api/cpo-dispatch-records/{$record->id}", cpoDispatchApiPayload())->assertStatus(401);
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
    $otherStation = Station::factory()->forBusinessUnit($otherMill)->cpoDispatch()->create();
    $recordsBefore = CpoDispatchRecord::count();

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/cpo-dispatch-records', cpoDispatchApiPayload([
        'production_line_id' => $otherStation->production_line_id, 'details' => [['event_date' => '2026-08-31', 'gross_weight_mt' => 10, 'tare_weight_mt' => 2]],
    ]));

    $response->assertStatus(403);
    $response->assertJsonPath('code', 'FORBIDDEN');
    expect(CpoDispatchRecord::count())->toBe($recordsBefore);
});

it('menolak 403 FORBIDDEN saat PATCH record milik mill lain, dan tidak mengubah satu kolom pun', function () {
    $otherMill = BusinessUnit::factory()->create();
    $otherStation = Station::factory()->forBusinessUnit($otherMill)->cpoDispatch()->create();
    $record = CpoDispatchRecord::factory()->forStation($otherStation)->create(['cpo_dispatch_id' => 'SCOPE-MILIK-MILL-B']);
    $before = $record->fresh()->getAttributes();

    $response = $this->actingAs($this->supervisor, 'web')->patchJson("/api/cpo-dispatch-records/{$record->id}", cpoDispatchApiPayload([
        'cpo_dispatch_id' => 'SCOPE-HIJACKED', 'details' => [['event_date' => '2026-08-31', 'gross_weight_mt' => 10, 'tare_weight_mt' => 2]],
    ]));

    $response->assertStatus(403);
    $response->assertJsonPath('code', 'FORBIDDEN');
    expect($record->fresh()->getAttributes())->toBe($before);
});
