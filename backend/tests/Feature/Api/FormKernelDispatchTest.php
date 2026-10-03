<?php

/**
 * FormKernelDispatchTest (Feature/Api) —
 * screen-113--form-kernel-dispatch-web /
 * usecase-078--form-kernel-dispatch-web.
 *
 * Integration tests for POST /api/kernel-dispatch-records and
 * PATCH /api/kernel-dispatch-records/{id}. Mirrors
 * FormSolidWasteDisposalTest.php's structure, minus the grid/N-column and
 * time-slot-ordering concerns — Kernel Dispatch's detail rows are a free
 * event log.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\KernelDispatchRecord;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->kernelDispatchStation = Station::factory()->forBusinessUnit($this->businessUnit)->kernelDispatch()->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnit)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnit)->create();
    // Prasyarat kunci periode (usecase-141): ke-18 *RecordService menolak
    // penulisan data stasiun tanpa Periode Pelaporan yang TERBUKA untuk jenis
    // stasiun itu. Test di berkas ini menguji aturan stasiunnya sendiri, bukan
    // kunci periodenya, jadi prasyaratnya dipenuhi di sini. Kunci periodenya
    // diuji tersendiri di tests/Unit/Support/EnforcesPeriodLockTest.php.
    openPeriodFor($this->businessUnit->id, 'kernel-dispatch');
});

function kernelDispatchApiPayload(array $overrides = []): array
{
    return array_merge([
        'kernel_dispatch_id' => 'KD-API-001',
        'date' => '2026-08-31',
    ], $overrides);
}

// Scenario: "Buat Record Kernel Dispatch Baru — berhasil"
it('berhasil: creates a new record with status=saved, resolved station_id, and inserted details', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/kernel-dispatch-records', kernelDispatchApiPayload([
        'production_line_id' => $this->kernelDispatchStation->production_line_id,
        'details' => [['event_date' => '2026-08-31', 'gross_weight_mt' => 10, 'tare_weight_mt' => 2]],
    ]));

    $response->assertCreated();
    $response->assertJsonFragment(['station_id' => $this->kernelDispatchStation->id, 'status' => 'saved']);
    expect(KernelDispatchRecord::where('kernel_dispatch_id', 'KD-API-001')->exists())->toBeTrue();
});

it('computes net_weight_mt as gross_weight_mt minus tare_weight_mt', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/kernel-dispatch-records', kernelDispatchApiPayload([
        'production_line_id' => $this->kernelDispatchStation->production_line_id,
        'details' => [['event_date' => '2026-08-31', 'gross_weight_mt' => 10.5, 'tare_weight_mt' => 2.5]],
    ]));

    $response->assertCreated();
    $response->assertJsonFragment(['net_weight_mt' => 8.0]);
});

// Scenario: "Field Wajib Belum Lengkap"
it('returns 422 VALIDATION_ERROR when a required field is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/kernel-dispatch-records', kernelDispatchApiPayload([
        'production_line_id' => $this->kernelDispatchStation->production_line_id,
        'kernel_dispatch_id' => '',
        'details' => [['event_date' => '2026-08-31']],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('kernel_dispatch_id');
});

// Scenario: "Belum Ada Baris Log Valid"
it('returns 422 VALIDATION_ERROR when details array is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/kernel-dispatch-records', kernelDispatchApiPayload([
        'production_line_id' => $this->kernelDispatchStation->production_line_id,
        'details' => [],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when no detail row has an event_date', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/kernel-dispatch-records', kernelDispatchApiPayload([
        'production_line_id' => $this->kernelDispatchStation->production_line_id,
        'details' => [['shift' => 'Shift 1']],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

// Scenario: "Production Line Tanpa Station Kernel Dispatch Aktif"
it('returns 422 when production_line_id has no active kernel-dispatch station', function () {
    $otherProductionLine = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create();

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/kernel-dispatch-records', kernelDispatchApiPayload([
        'production_line_id' => $otherProductionLine->id,
        'details' => [['event_date' => '2026-08-31']],
    ]));

    $response->assertStatus(422);
});

it('sets checked_by when checked=true and requester role=supervisor', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/kernel-dispatch-records', kernelDispatchApiPayload([
        'production_line_id' => $this->kernelDispatchStation->production_line_id,
        'checked' => true,
        'details' => [['event_date' => '2026-08-31']],
    ]));

    $response->assertCreated();
    expect(KernelDispatchRecord::where('kernel_dispatch_id', 'KD-API-001')->first()->checked_by)->toBe($this->supervisor->id);
});

it('sets acknowledged_by when acknowledged=true and requester role=mill_management', function () {
    $response = $this->actingAs($this->millManagement, 'web')->postJson('/api/kernel-dispatch-records', kernelDispatchApiPayload([
        'production_line_id' => $this->kernelDispatchStation->production_line_id,
        'acknowledged' => true,
        'details' => [['event_date' => '2026-08-31']],
    ]));

    $response->assertCreated();
    expect(KernelDispatchRecord::where('kernel_dispatch_id', 'KD-API-001')->first()->acknowledged_by)->toBe($this->millManagement->id);
});

// Mobile sync allowance — same convention as FormSolidWasteDisposalTest.php/
// FormCagesTrackTest.php: Operator is allowed to POST for offline sync.
it('allows the Operator role to create (mobile sync)', function () {
    $response = $this->actingAs($this->operator, 'web')->postJson('/api/kernel-dispatch-records', kernelDispatchApiPayload([
        'production_line_id' => $this->kernelDispatchStation->production_line_id,
        'details' => [['event_date' => '2026-08-31']],
    ]));

    $response->assertCreated();
});

// Scenario: "Edit Record Kernel Dispatch — berhasil"
it('berhasil: updates an existing record and its details', function () {
    $record = KernelDispatchRecord::factory()->forStation($this->kernelDispatchStation)->create(['kernel_dispatch_id' => 'KD-OLD']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/kernel-dispatch-records/{$record->id}", kernelDispatchApiPayload([
        'kernel_dispatch_id' => 'KD-NEW',
        'details' => [['event_date' => '2026-08-31']],
    ]));

    $response->assertOk();
    $response->assertJsonFragment(['kernel_dispatch_id' => 'KD-NEW']);
    expect($record->fresh()->kernel_dispatch_id)->toBe('KD-NEW');
});

it('does not change station_id even if production_line_id is sent on update', function () {
    $record = KernelDispatchRecord::factory()->forStation($this->kernelDispatchStation)->create();
    $otherProductionLine = ProductionLine::factory()->create();
    Station::factory()->forProductionLine($otherProductionLine)->kernelDispatch()->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/kernel-dispatch-records/{$record->id}", kernelDispatchApiPayload([
        'production_line_id' => $otherProductionLine->id,
        'details' => [['event_date' => '2026-08-31']],
    ]));

    $response->assertOk();
    expect($record->fresh()->station_id)->toBe($this->kernelDispatchStation->id);
});

// Scenario: "Record Tidak Ditemukan (mode edit)"
it('returns 404 RECORD_NOT_FOUND when updating a non-existent id', function () {
    $response = $this->actingAs($this->admin, 'web')->patchJson(
        '/api/kernel-dispatch-records/00000000-0000-0000-0000-000000000000',
        kernelDispatchApiPayload(['details' => [['event_date' => '2026-08-31']]])
    );

    $response->assertStatus(404);
});

it('rejects unauthenticated requests on create and update', function () {
    $this->postJson('/api/kernel-dispatch-records', kernelDispatchApiPayload([
        'production_line_id' => $this->kernelDispatchStation->production_line_id,
        'details' => [['event_date' => '2026-08-31']],
    ]))->assertStatus(401);

    $record = KernelDispatchRecord::factory()->forStation($this->kernelDispatchStation)->create();
    $this->patchJson("/api/kernel-dispatch-records/{$record->id}", kernelDispatchApiPayload())->assertStatus(401);
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
    $otherStation = Station::factory()->forBusinessUnit($otherMill)->kernelDispatch()->create();
    $recordsBefore = KernelDispatchRecord::count();

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/kernel-dispatch-records', kernelDispatchApiPayload([
        'production_line_id' => $otherStation->production_line_id, 'details' => [['event_date' => '2026-08-31', 'gross_weight_mt' => 10, 'tare_weight_mt' => 2]],
    ]));

    $response->assertStatus(403);
    $response->assertJsonPath('code', 'FORBIDDEN');
    expect(KernelDispatchRecord::count())->toBe($recordsBefore);
});

it('menolak 403 FORBIDDEN saat PATCH record milik mill lain, dan tidak mengubah satu kolom pun', function () {
    $otherMill = BusinessUnit::factory()->create();
    $otherStation = Station::factory()->forBusinessUnit($otherMill)->kernelDispatch()->create();
    $record = KernelDispatchRecord::factory()->forStation($otherStation)->create(['kernel_dispatch_id' => 'SCOPE-MILIK-MILL-B']);
    $before = $record->fresh()->getAttributes();

    $response = $this->actingAs($this->supervisor, 'web')->patchJson("/api/kernel-dispatch-records/{$record->id}", kernelDispatchApiPayload([
        'kernel_dispatch_id' => 'SCOPE-HIJACKED', 'details' => [['event_date' => '2026-08-31', 'gross_weight_mt' => 10, 'tare_weight_mt' => 2]],
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

function kernelDispatchPeriodLockPayload($test, string $date, string $id): array
{
    return kernelDispatchApiPayload([
        'kernel_dispatch_id' => $id,
        'date' => $date,
        'details' => [['event_date' => $date, 'gross_weight_mt' => 10, 'tare_weight_mt' => 2]],
    ]);
}

it('kunci periode: menolak 422 PERIOD_CLOSED saat POST bertanggal di periode tertutup, tanpa menyimpan satu baris pun', function () {
    replacePrerequisiteWithClosedAndOpenPeriods($this->businessUnit->id, 'kernel-dispatch');
    $recordsBefore = KernelDispatchRecord::count();

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/kernel-dispatch-records', array_merge(
        kernelDispatchPeriodLockPayload($this, PERIOD_LOCK_CLOSED_DATE, 'KD-LOCK-CLOSED'),
        ['production_line_id' => $this->kernelDispatchStation->production_line_id],
    ));

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'PERIOD_CLOSED');
    expect(KernelDispatchRecord::count())->toBe($recordsBefore);
});

it('kunci periode: menolak 422 PERIOD_CLOSED saat PATCH record yang tanggal lamanya di periode tertutup walau tanggal barunya di periode terbuka, dan record tidak berubah', function () {
    replacePrerequisiteWithClosedAndOpenPeriods($this->businessUnit->id, 'kernel-dispatch');
    // Record lama dibuat lewat factory (melewati service), tanggalnya di Juli yang tertutup.
    $record = KernelDispatchRecord::factory()->forStation($this->kernelDispatchStation)->create([
        'kernel_dispatch_id' => 'KD-LOCK-OLD',
        'date' => PERIOD_LOCK_CLOSED_DATE,
    ]);
    $before = $record->fresh()->getAttributes();

    // Tanggal baru jatuh di Agustus yang terbuka — tetap harus ditolak, karena
    // memindahkan record keluar dari periode tertutup sama saja mengubah isinya.
    $response = $this->actingAs($this->admin, 'web')->patchJson(
        "/api/kernel-dispatch-records/{$record->id}",
        kernelDispatchPeriodLockPayload($this, PERIOD_LOCK_OPEN_MID, 'KD-LOCK-MOVED'),
    );

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'PERIOD_CLOSED');
    expect($record->fresh()->getAttributes())->toBe($before);
});

it('kunci periode: menerima 201 saat POST tepat pada tanggal awal dan tanggal akhir periode terbuka (inklusif)', function () {
    replacePrerequisiteWithClosedAndOpenPeriods($this->businessUnit->id, 'kernel-dispatch');

    foreach ([PERIOD_LOCK_OPEN_START => 'KD-LOCK-START', PERIOD_LOCK_OPEN_END => 'KD-LOCK-END'] as $date => $id) {
        $this->actingAs($this->supervisor, 'web')->postJson('/api/kernel-dispatch-records', array_merge(
            kernelDispatchPeriodLockPayload($this, $date, $id),
            ['production_line_id' => $this->kernelDispatchStation->production_line_id],
        ))->assertCreated();
    }

    expect(KernelDispatchRecord::whereIn('kernel_dispatch_id', ['KD-LOCK-START', 'KD-LOCK-END'])->count())->toBe(2);
});
