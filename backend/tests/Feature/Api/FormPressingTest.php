<?php

/**
 * FormPressingTest (Feature/Api) — screen-058--form-pressing-web /
 * usecase-058--form-pressing-web.
 *
 * Integration tests for POST /api/pressing-records and PATCH
 * /api/pressing-records/{id}, mirroring
 * tests/Feature/Api/FormThreshingTest.php's structure. REVISED 2026-08-24
 * (entity-catalog v12): `details` is now a dynamic array of 1..24 rows
 * (unique + strictly ascending canonical time_slot order) rather than
 * always exactly 24 entries.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\PressingDetail;
use App\Models\PressingRecord;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->pressingStation = Station::factory()->forBusinessUnit($this->businessUnit)->pressing()->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnit)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnit)->create();
    // Prasyarat kunci periode (usecase-141): ke-18 *RecordService menolak
    // penulisan data stasiun tanpa Periode Pelaporan yang TERBUKA untuk jenis
    // stasiun itu. Test di berkas ini menguji aturan stasiunnya sendiri, bukan
    // kunci periodenya, jadi prasyaratnya dipenuhi di sini. Kunci periodenya
    // diuji tersendiri di tests/Unit/Support/EnforcesPeriodLockTest.php.
    openPeriodFor($this->businessUnit->id, 'pressing');
});

function pressingApiPayload(array $overrides = []): array
{
    return array_merge([
        'presser_id' => 'PR-API-001',
        'date' => '2026-08-24',
        'details' => [['time_slot' => '07:00', 'digester_temp_c' => 92]],
    ], $overrides);
}

it('berhasil: creates a new record with status=saved, resolved station_id, and only the given detail rows', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/pressing-records', pressingApiPayload([
        'production_line_id' => $this->pressingStation->production_line_id,
    ]));

    $response->assertCreated();
    $response->assertJsonFragment(['station_id' => $this->pressingStation->id, 'status' => 'saved']);
    expect(PressingRecord::where('presser_id', 'PR-API-001')->exists())->toBeTrue();
    expect($response->json('details'))->toHaveCount(1);
});

it('returns 422 VALIDATION_ERROR when a required field is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/pressing-records', pressingApiPayload([
        'production_line_id' => $this->pressingStation->production_line_id,
        'presser_id' => '',
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('presser_id');
});

it('returns 422 VALIDATION_ERROR when details is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/pressing-records', pressingApiPayload([
        'production_line_id' => $this->pressingStation->production_line_id,
        'details' => [],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when a detail row uses a time_slot outside the 24 canonical slots', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/pressing-records', pressingApiPayload([
        'production_line_id' => $this->pressingStation->production_line_id,
        'details' => [['time_slot' => '07:15', 'digester_temp_c' => 1]],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when time_slot is not ascending across detail rows', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/pressing-records', pressingApiPayload([
        'production_line_id' => $this->pressingStation->production_line_id,
        'details' => [
            ['time_slot' => '09:00', 'digester_temp_c' => 1],
            ['time_slot' => '07:00', 'digester_temp_c' => 2],
        ],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when zero rows have any reading filled', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/pressing-records', pressingApiPayload([
        'production_line_id' => $this->pressingStation->production_line_id,
        'details' => [['time_slot' => '07:00']], // no reading columns
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 when production_line_id has no active pressing station', function () {
    $otherProductionLine = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create();

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/pressing-records', pressingApiPayload([
        'production_line_id' => $otherProductionLine->id,
    ]));

    $response->assertStatus(422);
});

it('sets checked_by when checked=true and requester role=supervisor', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/pressing-records', pressingApiPayload([
        'production_line_id' => $this->pressingStation->production_line_id,
        'checked' => true,
    ]));

    $response->assertCreated();
    expect(PressingRecord::where('presser_id', 'PR-API-001')->first()->checked_by)->toBe($this->supervisor->id);
});

it('sets acknowledged_by when acknowledged=true and requester role=mill_management', function () {
    $response = $this->actingAs($this->millManagement, 'web')->postJson('/api/pressing-records', pressingApiPayload([
        'production_line_id' => $this->pressingStation->production_line_id,
        'acknowledged' => true,
    ]));

    $response->assertCreated();
    expect(PressingRecord::where('presser_id', 'PR-API-001')->first()->acknowledged_by)->toBe($this->millManagement->id);
});

it('allows the Operator role to create (mobile sync)', function () {
    $response = $this->actingAs($this->operator, 'web')->postJson('/api/pressing-records', pressingApiPayload([
        'production_line_id' => $this->pressingStation->production_line_id,
    ]));

    $response->assertCreated();
});

it('berhasil: updates an existing record and upserts its detail rows (insert new, update existing, delete removed)', function () {
    $record = PressingRecord::factory()->forStation($this->pressingStation)->create(['presser_id' => 'PR-OLD']);
    $keptDetail = PressingDetail::factory()->forRecord($record)->timeSlot('07:00')->filled()->create();
    $removedDetail = PressingDetail::factory()->forRecord($record)->timeSlot('12:00')->filled()->create();

    $payload = pressingApiPayload([
        'presser_id' => 'PR-NEW',
        'details' => [
            ['id' => $keptDetail->id, 'time_slot' => '07:00', 'digester_temp_c' => 99.9],
            ['time_slot' => '15:00', 'digester_temp_c' => 91],
        ],
    ]);
    unset($payload['production_line_id']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/pressing-records/{$record->id}", $payload);

    $response->assertOk();
    $response->assertJsonFragment(['presser_id' => 'PR-NEW']);
    expect($record->fresh()->presser_id)->toBe('PR-NEW');
    expect(PressingDetail::where('pressing_record_id', $record->id)->count())->toBe(2);
    expect(PressingDetail::find($removedDetail->id))->toBeNull();
});

it('does not change station_id even if production_line_id is sent on update', function () {
    $record = PressingRecord::factory()->forStation($this->pressingStation)->create();
    $existingDetail = PressingDetail::factory()->forRecord($record)->timeSlot('07:00')->filled()->create();
    $otherProductionLine = ProductionLine::factory()->create();
    Station::factory()->forProductionLine($otherProductionLine)->pressing()->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/pressing-records/{$record->id}", pressingApiPayload([
        'production_line_id' => $otherProductionLine->id,
        'details' => [
            ['id' => $existingDetail->id, 'time_slot' => $existingDetail->time_slot, 'digester_temp_c' => $existingDetail->digester_temp_c],
        ],
    ]));

    $response->assertOk();
    expect($record->fresh()->station_id)->toBe($this->pressingStation->id);
});

it('returns 404 RECORD_NOT_FOUND when updating a non-existent id', function () {
    $response = $this->actingAs($this->admin, 'web')->patchJson(
        '/api/pressing-records/00000000-0000-0000-0000-000000000000',
        pressingApiPayload()
    );

    $response->assertStatus(404);
});

it('rejects unauthenticated requests on create and update', function () {
    $this->postJson('/api/pressing-records', pressingApiPayload([
        'production_line_id' => $this->pressingStation->production_line_id,
    ]))->assertStatus(401);

    $record = PressingRecord::factory()->forStation($this->pressingStation)->create();
    $this->patchJson("/api/pressing-records/{$record->id}", pressingApiPayload())->assertStatus(401);
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
    $otherStation = Station::factory()->forBusinessUnit($otherMill)->pressing()->create();
    $recordsBefore = PressingRecord::count();

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/pressing-records', pressingApiPayload([
        'production_line_id' => $otherStation->production_line_id,
    ]));

    $response->assertStatus(403);
    $response->assertJsonPath('code', 'FORBIDDEN');
    expect(PressingRecord::count())->toBe($recordsBefore);
});

it('menolak 403 FORBIDDEN saat PATCH record milik mill lain, dan tidak mengubah satu kolom pun', function () {
    $otherMill = BusinessUnit::factory()->create();
    $otherStation = Station::factory()->forBusinessUnit($otherMill)->pressing()->create();
    $record = PressingRecord::factory()->forStation($otherStation)->create(['presser_id' => 'SCOPE-MILIK-MILL-B']);
    $before = $record->fresh()->getAttributes();

    $response = $this->actingAs($this->supervisor, 'web')->patchJson("/api/pressing-records/{$record->id}", pressingApiPayload([
        'presser_id' => 'SCOPE-HIJACKED',
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

function pressingPeriodLockPayload($test, string $date, string $id): array
{
    return pressingApiPayload([
        'presser_id' => $id,
        'date' => $date,
    ]);
}

it('kunci periode: menolak 422 PERIOD_CLOSED saat POST bertanggal di periode tertutup, tanpa menyimpan satu baris pun', function () {
    replacePrerequisiteWithClosedAndOpenPeriods($this->businessUnit->id, 'pressing');
    $recordsBefore = PressingRecord::count();

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/pressing-records', array_merge(
        pressingPeriodLockPayload($this, PERIOD_LOCK_CLOSED_DATE, 'PR-LOCK-CLOSED'),
        ['production_line_id' => $this->pressingStation->production_line_id],
    ));

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'PERIOD_CLOSED');
    expect(PressingRecord::count())->toBe($recordsBefore);
});

it('kunci periode: menolak 422 PERIOD_CLOSED saat PATCH record yang tanggal lamanya di periode tertutup walau tanggal barunya di periode terbuka, dan record tidak berubah', function () {
    replacePrerequisiteWithClosedAndOpenPeriods($this->businessUnit->id, 'pressing');
    // Record lama dibuat lewat factory (melewati service), tanggalnya di Juli yang tertutup.
    $record = PressingRecord::factory()->forStation($this->pressingStation)->create([
        'presser_id' => 'PR-LOCK-OLD',
        'date' => PERIOD_LOCK_CLOSED_DATE,
    ]);
    $before = $record->fresh()->getAttributes();

    // Tanggal baru jatuh di Agustus yang terbuka — tetap harus ditolak, karena
    // memindahkan record keluar dari periode tertutup sama saja mengubah isinya.
    $response = $this->actingAs($this->admin, 'web')->patchJson(
        "/api/pressing-records/{$record->id}",
        pressingPeriodLockPayload($this, PERIOD_LOCK_OPEN_MID, 'PR-LOCK-MOVED'),
    );

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'PERIOD_CLOSED');
    expect($record->fresh()->getAttributes())->toBe($before);
});

it('kunci periode: menerima 201 saat POST tepat pada tanggal awal dan tanggal akhir periode terbuka (inklusif)', function () {
    replacePrerequisiteWithClosedAndOpenPeriods($this->businessUnit->id, 'pressing');

    foreach ([PERIOD_LOCK_OPEN_START => 'PR-LOCK-START', PERIOD_LOCK_OPEN_END => 'PR-LOCK-END'] as $date => $id) {
        $this->actingAs($this->supervisor, 'web')->postJson('/api/pressing-records', array_merge(
            pressingPeriodLockPayload($this, $date, $id),
            ['production_line_id' => $this->pressingStation->production_line_id],
        ))->assertCreated();
    }

    expect(PressingRecord::whereIn('presser_id', ['PR-LOCK-START', 'PR-LOCK-END'])->count())->toBe(2);
});
