<?php

/**
 * FormStorageTankTest (Feature/Api) — screen-116--form-storage-tank-web /
 * usecase-096--form-storage-tank-web.
 *
 * Integration tests for POST /api/storage-tank-records and PATCH
 * /api/storage-tank-records/{id}, mirroring
 * tests/Feature/Api/FormEffluentPlantTest.php's structure exactly.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\StorageTankDetail;
use App\Models\StorageTankRecord;
use App\Models\User;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->storageTankStation = Station::factory()->forBusinessUnit($this->businessUnit)->storageTank()->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnit)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnit)->create();
    // Prasyarat kunci periode (usecase-141): ke-18 *RecordService menolak
    // penulisan data stasiun tanpa Periode Pelaporan yang TERBUKA untuk jenis
    // stasiun itu. Test di berkas ini menguji aturan stasiunnya sendiri, bukan
    // kunci periodenya, jadi prasyaratnya dipenuhi di sini. Kunci periodenya
    // diuji tersendiri di tests/Unit/Support/EnforcesPeriodLockTest.php.
    openPeriodFor($this->businessUnit->id, 'storage-tank');
});

function storageTankApiPayload(array $overrides = []): array
{
    return array_merge([
        'storage_tank_id' => 'ST-API-001',
        'date' => '2026-08-31',
        'details' => [['time_slot' => '07:00', 'cpo_sounding_depth_mm' => 1200.5]],
    ], $overrides);
}

it('berhasil: creates a new record with status=saved, resolved station_id, and only the given detail rows', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/storage-tank-records', storageTankApiPayload([
        'production_line_id' => $this->storageTankStation->production_line_id,
    ]));

    $response->assertCreated();
    $response->assertJsonFragment(['station_id' => $this->storageTankStation->id, 'status' => 'saved']);
    expect(StorageTankRecord::where('storage_tank_id', 'ST-API-001')->exists())->toBeTrue();
    expect($response->json('details'))->toHaveCount(1);
});

it('returns 422 VALIDATION_ERROR when a required field is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/storage-tank-records', storageTankApiPayload([
        'production_line_id' => $this->storageTankStation->production_line_id,
        'storage_tank_id' => '',
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('storage_tank_id');
});

it('returns 422 VALIDATION_ERROR when details is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/storage-tank-records', storageTankApiPayload([
        'production_line_id' => $this->storageTankStation->production_line_id,
        'details' => [],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when a detail row uses a time_slot outside the 24 canonical slots', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/storage-tank-records', storageTankApiPayload([
        'production_line_id' => $this->storageTankStation->production_line_id,
        'details' => [['time_slot' => '07:15', 'cpo_sounding_depth_mm' => 1]],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when time_slot is not ascending across detail rows', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/storage-tank-records', storageTankApiPayload([
        'production_line_id' => $this->storageTankStation->production_line_id,
        'details' => [
            ['time_slot' => '09:00', 'cpo_sounding_depth_mm' => 1],
            ['time_slot' => '07:00', 'cpo_sounding_depth_mm' => 2],
        ],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when zero rows have any reading filled', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/storage-tank-records', storageTankApiPayload([
        'production_line_id' => $this->storageTankStation->production_line_id,
        'details' => [['time_slot' => '07:00']], // no reading columns
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 when production_line_id has no active storage-tank station', function () {
    $otherProductionLine = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create();

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/storage-tank-records', storageTankApiPayload([
        'production_line_id' => $otherProductionLine->id,
    ]));

    $response->assertStatus(422);
});

it('sets checked_by when checked=true and requester role=supervisor', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/storage-tank-records', storageTankApiPayload([
        'production_line_id' => $this->storageTankStation->production_line_id,
        'checked' => true,
    ]));

    $response->assertCreated();
    expect(StorageTankRecord::where('storage_tank_id', 'ST-API-001')->first()->checked_by)->toBe($this->supervisor->id);
});

it('sets acknowledged_by when acknowledged=true and requester role=mill_management', function () {
    $response = $this->actingAs($this->millManagement, 'web')->postJson('/api/storage-tank-records', storageTankApiPayload([
        'production_line_id' => $this->storageTankStation->production_line_id,
        'acknowledged' => true,
    ]));

    $response->assertCreated();
    expect(StorageTankRecord::where('storage_tank_id', 'ST-API-001')->first()->acknowledged_by)->toBe($this->millManagement->id);
});

it('allows the Operator role to create (mobile sync)', function () {
    $response = $this->actingAs($this->operator, 'web')->postJson('/api/storage-tank-records', storageTankApiPayload([
        'production_line_id' => $this->storageTankStation->production_line_id,
    ]));

    $response->assertCreated();
});

it('berhasil: updates an existing record and upserts its detail rows (insert new, update existing, delete removed)', function () {
    $record = StorageTankRecord::factory()->forStation($this->storageTankStation)->create(['storage_tank_id' => 'ST-OLD']);
    $keptDetail = StorageTankDetail::factory()->forRecord($record)->timeSlot('07:00')->filled()->create();
    $removedDetail = StorageTankDetail::factory()->forRecord($record)->timeSlot('12:00')->filled()->create();

    $payload = storageTankApiPayload([
        'storage_tank_id' => 'ST-NEW',
        'details' => [
            ['id' => $keptDetail->id, 'time_slot' => '07:00', 'cpo_sounding_depth_mm' => 99.9],
            ['time_slot' => '15:00', 'cpo_sounding_depth_mm' => 5],
        ],
    ]);
    unset($payload['production_line_id']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/storage-tank-records/{$record->id}", $payload);

    $response->assertOk();
    $response->assertJsonFragment(['storage_tank_id' => 'ST-NEW']);
    expect($record->fresh()->storage_tank_id)->toBe('ST-NEW');
    expect(StorageTankDetail::where('storage_tank_record_id', $record->id)->count())->toBe(2);
    expect(StorageTankDetail::find($removedDetail->id))->toBeNull();
});

it('does not change station_id even if production_line_id is sent on update', function () {
    $record = StorageTankRecord::factory()->forStation($this->storageTankStation)->create();
    $existingDetail = StorageTankDetail::factory()->forRecord($record)->timeSlot('07:00')->filled()->create();
    $otherProductionLine = ProductionLine::factory()->create();
    Station::factory()->forProductionLine($otherProductionLine)->storageTank()->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/storage-tank-records/{$record->id}", storageTankApiPayload([
        'production_line_id' => $otherProductionLine->id,
        'details' => [
            ['id' => $existingDetail->id, 'time_slot' => $existingDetail->time_slot, 'cpo_sounding_depth_mm' => $existingDetail->cpo_sounding_depth_mm],
        ],
    ]));

    $response->assertOk();
    expect($record->fresh()->station_id)->toBe($this->storageTankStation->id);
});

it('returns 404 RECORD_NOT_FOUND when updating a non-existent id', function () {
    $response = $this->actingAs($this->admin, 'web')->patchJson(
        '/api/storage-tank-records/00000000-0000-0000-0000-000000000000',
        storageTankApiPayload()
    );

    $response->assertStatus(404);
});

it('rejects unauthenticated requests on create and update', function () {
    $this->postJson('/api/storage-tank-records', storageTankApiPayload([
        'production_line_id' => $this->storageTankStation->production_line_id,
    ]))->assertStatus(401);

    $record = StorageTankRecord::factory()->forStation($this->storageTankStation)->create();
    $this->patchJson("/api/storage-tank-records/{$record->id}", storageTankApiPayload())->assertStatus(401);
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
    $otherStation = Station::factory()->forBusinessUnit($otherMill)->storageTank()->create();
    $recordsBefore = StorageTankRecord::count();

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/storage-tank-records', storageTankApiPayload([
        'production_line_id' => $otherStation->production_line_id,
    ]));

    $response->assertStatus(403);
    $response->assertJsonPath('code', 'FORBIDDEN');
    expect(StorageTankRecord::count())->toBe($recordsBefore);
});

it('menolak 403 FORBIDDEN saat PATCH record milik mill lain, dan tidak mengubah satu kolom pun', function () {
    $otherMill = BusinessUnit::factory()->create();
    $otherStation = Station::factory()->forBusinessUnit($otherMill)->storageTank()->create();
    $record = StorageTankRecord::factory()->forStation($otherStation)->create(['storage_tank_id' => 'SCOPE-MILIK-MILL-B']);
    $before = $record->fresh()->getAttributes();

    $response = $this->actingAs($this->supervisor, 'web')->patchJson("/api/storage-tank-records/{$record->id}", storageTankApiPayload([
        'storage_tank_id' => 'SCOPE-HIJACKED',
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

function storageTankPeriodLockPayload($test, string $date, string $id): array
{
    return storageTankApiPayload([
        'storage_tank_id' => $id,
        'date' => $date,
    ]);
}

it('kunci periode: menolak 422 PERIOD_CLOSED saat POST bertanggal di periode tertutup, tanpa menyimpan satu baris pun', function () {
    replacePrerequisiteWithClosedAndOpenPeriods($this->businessUnit->id, 'storage-tank');
    $recordsBefore = StorageTankRecord::count();

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/storage-tank-records', array_merge(
        storageTankPeriodLockPayload($this, PERIOD_LOCK_CLOSED_DATE, 'ST-LOCK-CLOSED'),
        ['production_line_id' => $this->storageTankStation->production_line_id],
    ));

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'PERIOD_CLOSED');
    expect(StorageTankRecord::count())->toBe($recordsBefore);
});

it('kunci periode: menolak 422 PERIOD_CLOSED saat PATCH record yang tanggal lamanya di periode tertutup walau tanggal barunya di periode terbuka, dan record tidak berubah', function () {
    replacePrerequisiteWithClosedAndOpenPeriods($this->businessUnit->id, 'storage-tank');
    // Record lama dibuat lewat factory (melewati service), tanggalnya di Juli yang tertutup.
    $record = StorageTankRecord::factory()->forStation($this->storageTankStation)->create([
        'storage_tank_id' => 'ST-LOCK-OLD',
        'date' => PERIOD_LOCK_CLOSED_DATE,
    ]);
    $before = $record->fresh()->getAttributes();

    // Tanggal baru jatuh di Agustus yang terbuka — tetap harus ditolak, karena
    // memindahkan record keluar dari periode tertutup sama saja mengubah isinya.
    $response = $this->actingAs($this->admin, 'web')->patchJson(
        "/api/storage-tank-records/{$record->id}",
        storageTankPeriodLockPayload($this, PERIOD_LOCK_OPEN_MID, 'ST-LOCK-MOVED'),
    );

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'PERIOD_CLOSED');
    expect($record->fresh()->getAttributes())->toBe($before);
});

it('kunci periode: menerima 201 saat POST tepat pada tanggal awal dan tanggal akhir periode terbuka (inklusif)', function () {
    replacePrerequisiteWithClosedAndOpenPeriods($this->businessUnit->id, 'storage-tank');

    foreach ([PERIOD_LOCK_OPEN_START => 'ST-LOCK-START', PERIOD_LOCK_OPEN_END => 'ST-LOCK-END'] as $date => $id) {
        $this->actingAs($this->supervisor, 'web')->postJson('/api/storage-tank-records', array_merge(
            storageTankPeriodLockPayload($this, $date, $id),
            ['production_line_id' => $this->storageTankStation->production_line_id],
        ))->assertCreated();
    }

    expect(StorageTankRecord::whereIn('storage_tank_id', ['ST-LOCK-START', 'ST-LOCK-END'])->count())->toBe(2);
});
