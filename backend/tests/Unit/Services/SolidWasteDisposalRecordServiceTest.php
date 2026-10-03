<?php

/**
 * SolidWasteDisposalRecordServiceTest — screen-091--data-browser-solid-waste-disposal-web
 * / screen-101--detail-solid-waste-disposal-web / screen-111--form-solid-waste-disposal-web.
 *
 * Mirrors CagesTrackRecordServiceTest.php's structure — TestCase +
 * RefreshDatabase (sqlite in-memory), fixture data via model factories.
 * Unlike Cages Track, this station has no grid/N-column concept and no
 * per-row time-slot ordering constraint — detail rows are a free event log.
 */

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Exceptions\CrossMillWriteDeniedException;
use App\Exceptions\ExportFailedException;
use App\Exceptions\InvalidDateRangeException;
use App\Exceptions\NoActiveSolidWasteDisposalStationException;
use App\Models\BusinessUnit;
use App\Models\ProductionLine;
use App\Models\SolidWasteDisposalDetail;
use App\Models\SolidWasteDisposalRecord;
use App\Models\Station;
use App\Models\User;
use App\Services\SolidWasteDisposalRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function solidWasteFormPayload(array $overrides = []): array
{
    return array_merge([
        'solid_waste_disposal_id' => 'SWD-'.Str::random(6),
        'date' => '2026-08-31',
        'note' => null,
        'details' => [],
    ], $overrides);
}

beforeEach(function () {
    $this->service = new SolidWasteDisposalRecordService();
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->solidWasteDisposal()->create();
    $this->creator = User::factory()->forBusinessUnit($this->businessUnit)->create();

    // Jalur BACA (listRecords/export/getDetail) sejak 2026-09-28 memakai
    // AKTOR TERAUTENTIKASI, bukan parameter — lihat bagian READ SIDE di
    // App\Support\Concerns\ScopesToActorMill. Test unit di berkas ini
    // ditulis untuk semantik baca TANPA cakupan mill, jadi aktornya Admin:
    // Admin memang tidak terikat mill, sehingga setiap asersi lama tetap
    // menguji hal yang persis sama. Cakupan mill untuk peran terikat diuji
    // di tests/Feature/Livewire/DataBrowser*Test.php dan Detail*Test.php.
    $this->actingAs(User::factory()->role(UserRole::Admin)->create());
    // Prasyarat kunci periode (usecase-141): ke-18 *RecordService menolak
    // penulisan data stasiun tanpa Periode Pelaporan yang TERBUKA untuk jenis
    // stasiun itu. Test di berkas ini menguji aturan stasiunnya sendiri, bukan
    // kunci periodenya, jadi prasyaratnya dipenuhi di sini. Kunci periodenya
    // diuji tersendiri di tests/Unit/Support/EnforcesPeriodLockTest.php.
    openPeriodFor($this->businessUnit->id, 'solid-waste-disposal');
});

it('throws InvalidDateRangeException when date_from is after date_to on listRecords()', function () {
    expect(fn () => $this->service->listRecords([
        'date_from' => '2026-02-10',
        'date_to' => '2026-02-01',
    ], 1, 20))->toThrow(InvalidDateRangeException::class);
});

it('returns an empty data list and meta.total = 0 when no records match the filter', function () {
    SolidWasteDisposalRecord::factory()->forStation($this->station)->onDate('2026-01-01')->create();

    $result = $this->service->listRecords([
        'date_from' => '2020-01-01',
        'date_to' => '2020-01-02',
    ], 1, 20);

    expect($result['data'])->toBe([]);
    expect($result['meta']['total'])->toBe(0);
});

it('returns a paginated, filtered list with the shared pagination meta shape', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();
    $otherStation = Station::factory()->forBusinessUnit($otherBusinessUnit)->solidWasteDisposal()->create();

    SolidWasteDisposalRecord::factory()->forStation($this->station)->onDate('2026-02-05')->count(3)->create();
    SolidWasteDisposalRecord::factory()->forStation($this->station)->onDate('2026-03-01')->create();
    SolidWasteDisposalRecord::factory()->forStation($otherStation)->onDate('2026-02-05')->create();

    $result = $this->service->listRecords([
        'date_from' => '2026-02-01',
        'date_to' => '2026-02-10',
        'business_unit_id' => $this->businessUnit->id,
    ], 1, 2);

    expect($result['meta'])->toBe(['page' => 1, 'per_page' => 2, 'total' => 3, 'total_pages' => 2]);
    expect($result['data'])->toHaveCount(2);
    expect(array_keys($result['data'][0]))->toBe([
        'id', 'solid_waste_disposal_id', 'date', 'event_count', 'production_line_name', 'status',
    ]);
});

it('computes event_count as the number of related SolidWasteDisposalDetail rows', function () {
    $withTwo = SolidWasteDisposalRecord::factory()->forStation($this->station)->onDate('2026-02-05')->create();
    SolidWasteDisposalDetail::factory()->forRecord($withTwo)->count(2)->create();

    $withNone = SolidWasteDisposalRecord::factory()->forStation($this->station)->onDate('2026-02-05')->create();

    $result = $this->service->listRecords(['date_from' => '2026-02-01', 'date_to' => '2026-02-10'], 1, 20);
    $rows = collect($result['data'])->keyBy('id');

    expect($rows[$withTwo->id]['event_count'])->toBe(2);
    expect($rows[$withNone->id]['event_count'])->toBe(0);
});

it('throws InvalidDateRangeException when date_from is after date_to on export()', function () {
    expect(fn () => $this->service->export([
        'date_from' => '2026-02-10',
        'date_to' => '2026-02-01',
    ], 'csv'))->toThrow(InvalidDateRangeException::class);
});

it('throws ExportFailedException when the filtered dataset exceeds the export row limit', function () {
    $limit = SolidWasteDisposalRecordService::EXPORT_ROW_LIMIT;
    $total = $limit + 1;

    $now = now();
    $chunkSize = 2000;
    $inserted = 0;

    while ($inserted < $total) {
        $batch = min($chunkSize, $total - $inserted);
        $rows = [];

        for ($i = 0; $i < $batch; $i++) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'station_id' => $this->station->id,
                'production_line_id' => $this->station->production_line_id,
                'solid_waste_disposal_id' => 'SWD-BULK-'.($inserted + $i),
                'date' => $now->toDateString(),
                'note' => null,
                'checked_by' => null,
                'acknowledged_by' => null,
                'status' => RecordStatus::Saved->value,
                'created_by' => $this->creator->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('solid_waste_disposal_records')->insert($rows);
        $inserted += $batch;
    }

    expect(SolidWasteDisposalRecord::count())->toBe($total);

    expect(fn () => $this->service->export([], 'csv'))->toThrow(ExportFailedException::class);
});

it('returns a StreamedResponse with the correct content-type for csv and excel formats', function (string $format, string $expectedContentType) {
    $record = SolidWasteDisposalRecord::factory()->forStation($this->station)->onDate('2026-02-05')->create();
    SolidWasteDisposalDetail::factory()->forRecord($record)->create();

    SolidWasteDisposalRecord::factory()->forStation($this->station)->onDate('2026-02-05')->create();

    $response = $this->service->export(['date_from' => '2026-02-01', 'date_to' => '2026-02-10'], $format);

    expect($response)->toBeInstanceOf(StreamedResponse::class);
    expect($response->headers->get('Content-Type'))->toBe($expectedContentType);

    ob_start();
    $response->sendContent();
    $body = ob_get_clean();

    expect($body)->toContain('Solid Waste Disp. ID');
    expect(substr_count($body, "\n"))->toBeGreaterThanOrEqual(2);
})->with([
    'csv' => ['csv', 'text/csv'],
    'excel' => ['excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
]);

it('throws ModelNotFoundException when the id does not exist', function () {
    $this->service->getDetail((string) Str::uuid());
})->throws(ModelNotFoundException::class);

it('returns the full record with resolved station_name when id exists', function () {
    $record = SolidWasteDisposalRecord::factory()->forStation($this->station)->create();

    $result = $this->service->getDetail($record->id);

    expect($result['id'])->toBe($record->id);
    expect($result['station_name'])->toBe($this->station->name);
});

it('returns details array ordered by event_date', function () {
    $record = SolidWasteDisposalRecord::factory()->forStation($this->station)->create();
    SolidWasteDisposalDetail::factory()->forRecord($record)->create(['event_date' => '2026-08-15']);
    SolidWasteDisposalDetail::factory()->forRecord($record)->create(['event_date' => '2026-08-01']);
    SolidWasteDisposalDetail::factory()->forRecord($record)->create(['event_date' => '2026-08-10']);

    $result = $this->service->getDetail($record->id);

    expect(array_column($result['details'], 'event_date'))->toBe(['2026-08-01', '2026-08-10', '2026-08-15']);
});

it('returns null checked_by_name and acknowledged_by_name when not set', function () {
    $record = SolidWasteDisposalRecord::factory()->forStation($this->station)->create(['checked_by' => null, 'acknowledged_by' => null]);

    $result = $this->service->getDetail($record->id);

    expect($result['checked_by_name'])->toBeNull();
    expect($result['acknowledged_by_name'])->toBeNull();
});

it('resolves created_by_name, checked_by_name, acknowledged_by_name to user names when present', function () {
    $checker = User::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'Budi Checker']);
    $acknowledger = User::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'Siti Manager']);
    $record = SolidWasteDisposalRecord::factory()->forStation($this->station)->create([
        'created_by' => $this->creator->id,
        'checked_by' => $checker->id,
        'acknowledged_by' => $acknowledger->id,
    ]);

    $result = $this->service->getDetail($record->id);

    expect($result['created_by_name'])->toBe($this->creator->name);
    expect($result['checked_by_name'])->toBe('Budi Checker');
    expect($result['acknowledged_by_name'])->toBe('Siti Manager');
});

// screen-111--form-solid-waste-disposal-web: create()/update() tests below.

it('creates record with resolved station_id and inserted details when valid', function () {
    $result = $this->service->create(
        solidWasteFormPayload([
            'production_line_id' => $this->station->production_line_id,
            'details' => [['event_date' => '2026-08-31', 'gross_weight_mt' => 10, 'tare_weight_mt' => 2]],
        ]),
        $this->creator
    );

    expect($result['station_id'])->toBe($this->station->id);
    expect($result['status'])->toBe('saved');
    expect($result['details'])->toHaveCount(1);
});

// Field angka yang dikosongkan di form web datang sebagai '' (Livewire tidak
// melewati ConvertEmptyStringsToNull). Sampai 2026-10-03 nilai itu diteruskan
// apa adanya: PostgreSQL menolaknya (SQLSTATE 22P02 → 500) sementara SQLite di
// suite ini menerimanya diam-diam, dan `(float) ''` menyimpan berat kosong
// sebagai 0 sehingga Net ikut terhitung palsu. Asersinya pada nilai MENTAH di
// tabel, bukan atribut model yang sudah di-cast.
it('stores blank numeric detail fields as null, not empty string or 0', function () {
    $this->service->create(
        solidWasteFormPayload([
            'production_line_id' => $this->station->production_line_id,
            'details' => [['event_date' => '2026-08-31', 'gross_weight_mt' => '', 'tare_weight_mt' => '']],
        ]),
        $this->creator
    );

    $row = (array) DB::table('solid_waste_disposal_details')->select(['gross_weight_mt', 'tare_weight_mt', 'net_weight_mt'])->first();

    foreach ($row as $column => $value) {
        expect($value)->toBeNull("kolom {$column} seharusnya null");
    }
});

it('computes net_weight_mt as gross_weight_mt minus tare_weight_mt', function () {
    $result = $this->service->create(
        solidWasteFormPayload([
            'production_line_id' => $this->station->production_line_id,
            'details' => [['event_date' => '2026-08-31', 'gross_weight_mt' => 10.5, 'tare_weight_mt' => 2.5]],
        ]),
        $this->creator
    );

    expect($result['details'][0]['net_weight_mt'])->toBe(8.0);
});

it('throws ValidationException when a required field is empty', function () {
    expect(fn () => $this->service->create(
        solidWasteFormPayload([
            'production_line_id' => $this->station->production_line_id,
            'solid_waste_disposal_id' => '',
            'details' => [['event_date' => '2026-08-31']],
        ]),
        $this->creator
    ))->toThrow(ValidationException::class);
});

it('throws ValidationException when details array is empty', function () {
    expect(fn () => $this->service->create(
        solidWasteFormPayload(['production_line_id' => $this->station->production_line_id, 'details' => []]),
        $this->creator
    ))->toThrow(ValidationException::class);
});

it('throws ValidationException when no detail row has an event_date', function () {
    expect(fn () => $this->service->create(
        solidWasteFormPayload([
            'production_line_id' => $this->station->production_line_id,
            'details' => [['event_date' => null, 'shift' => 'Shift 1']],
        ]),
        $this->creator
    ))->toThrow(ValidationException::class);
});

it('throws NoActiveSolidWasteDisposalStationException when production_line_id has no active station', function () {
    $otherProductionLine = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create();

    expect(fn () => $this->service->create(
        solidWasteFormPayload([
            'production_line_id' => $otherProductionLine->id,
            'details' => [['event_date' => '2026-08-31']],
        ]),
        $this->creator
    ))->toThrow(NoActiveSolidWasteDisposalStationException::class);
});

it('sets checked_by to requester id when checked=true and requester role=supervisor', function () {
    $supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();

    $result = $this->service->create(
        solidWasteFormPayload([
            'production_line_id' => $this->station->production_line_id,
            'checked' => true,
            'details' => [['event_date' => '2026-08-31']],
        ]),
        $supervisor
    );

    expect($result['checked_by_name'])->toBe($supervisor->name);
});

it('ignores checked=true when requester role is not supervisor', function () {
    $millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnit)->create();

    $result = $this->service->create(
        solidWasteFormPayload([
            'production_line_id' => $this->station->production_line_id,
            'checked' => true,
            'details' => [['event_date' => '2026-08-31']],
        ]),
        $millManagement
    );

    expect($result['checked_by_name'])->toBeNull();
});

it('sets acknowledged_by to requester id when acknowledged=true and requester role=mill_management', function () {
    $millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnit)->create();

    $result = $this->service->create(
        solidWasteFormPayload([
            'production_line_id' => $this->station->production_line_id,
            'acknowledged' => true,
            'details' => [['event_date' => '2026-08-31']],
        ]),
        $millManagement
    );

    expect($result['acknowledged_by_name'])->toBe($millManagement->name);
});

it('updates record and upserts details: inserts new row, updates existing row, deletes removed row', function () {
    $record = SolidWasteDisposalRecord::factory()->forStation($this->station)->create();
    $keptDetail = SolidWasteDisposalDetail::factory()->forRecord($record)->create(['event_date' => '2026-08-01', 'gross_weight_mt' => 5, 'tare_weight_mt' => 1]);
    SolidWasteDisposalDetail::factory()->forRecord($record)->create(['event_date' => '2026-08-05']);

    $result = $this->service->update(
        $record->id,
        solidWasteFormPayload([
            'details' => [
                ['id' => $keptDetail->id, 'event_date' => '2026-08-01', 'gross_weight_mt' => 8, 'tare_weight_mt' => 1],
                ['event_date' => '2026-08-10', 'gross_weight_mt' => 3, 'tare_weight_mt' => 1],
            ],
        ]),
        $this->creator
    );

    expect($result['details'])->toHaveCount(2);
    expect(SolidWasteDisposalDetail::where('solid_waste_disposal_record_id', $record->id)->count())->toBe(2);
    expect(SolidWasteDisposalDetail::find($keptDetail->id)->net_weight_mt)->toBe(7.0);
    expect(SolidWasteDisposalDetail::where('event_date', '2026-08-05')->exists())->toBeFalse();
});

it('updates record without accepting a production_line_id change', function () {
    $otherProductionLine = ProductionLine::factory()->create();
    $record = SolidWasteDisposalRecord::factory()->forStation($this->station)->create();
    SolidWasteDisposalDetail::factory()->forRecord($record)->create();

    $result = $this->service->update(
        $record->id,
        solidWasteFormPayload([
            'production_line_id' => $otherProductionLine->id,
            'solid_waste_disposal_id' => 'SWD-EDITED',
            'details' => [['event_date' => '2026-08-31']],
        ]),
        $this->creator
    );

    expect($result['station_id'])->toBe($this->station->id);
    expect($result['solid_waste_disposal_id'])->toBe('SWD-EDITED');
});

it('throws ModelNotFoundException when updating a non-existent id', function () {
    expect(fn () => $this->service->update(
        (string) Str::uuid(),
        solidWasteFormPayload(['details' => [['event_date' => '2026-08-31']]]),
        $this->creator
    ))->toThrow(ModelNotFoundException::class);
});

/*
|--------------------------------------------------------------------------
| Cross-mill write guard (SolidWasteDisposal) — 2026-09-28
|--------------------------------------------------------------------------
| A Production Line is a CHOSEN CONTEXT, not an account binding: the rule
| under test is "the chosen line must sit inside the ACTOR'S mill"
| (users.business_unit_id), with Admin recognised BY ROLE and therefore not
| mill-bound at all. See App\Support\Concerns\ScopesToActorMill.
|
| Before 2026-09-28 create() took `production_line_id` from the client and
| never looked at the actor's mill, and update() never checked ownership at
| all — an Operator of Mill A could write, and PATCH, Mill B's log sheet.
*/

it('menolak create() ke production line mill lain, tanpa menulis satu baris pun', function (UserRole $role) {
    $otherMill = BusinessUnit::factory()->create();
    $otherStation = Station::factory()->forBusinessUnit($otherMill)->solidWasteDisposal()->create();
    $actor = User::factory()->role($role)->forBusinessUnit($this->businessUnit)->create();

    $recordsBefore = SolidWasteDisposalRecord::count();
    $detailsBefore = SolidWasteDisposalDetail::count();

    expect(fn () => $this->service->create(solidWasteFormPayload(['production_line_id' => $otherStation->production_line_id, 'details' => [['event_date' => '2026-08-31', 'gross_weight_mt' => 10, 'tare_weight_mt' => 2]]]), $actor))
        ->toThrow(CrossMillWriteDeniedException::class);

    expect(SolidWasteDisposalRecord::count())->toBe($recordsBefore);
    expect(SolidWasteDisposalDetail::count())->toBe($detailsBefore);
})->with([
    'operator' => UserRole::Operator,
    'supervisor' => UserRole::Supervisor,
    'mill management' => UserRole::MillManagement,
]);

it('menolak update() record milik mill lain, dan tidak mengubah satu kolom pun', function () {
    $otherMill = BusinessUnit::factory()->create();
    $otherStation = Station::factory()->forBusinessUnit($otherMill)->solidWasteDisposal()->create();
    $record = SolidWasteDisposalRecord::factory()->forStation($otherStation)->create(['solid_waste_disposal_id' => 'SCOPE-MILIK-MILL-B']);
    $before = $record->fresh()->getAttributes();

    expect(fn () => $this->service->update($record->id, solidWasteFormPayload(['solid_waste_disposal_id' => 'SCOPE-HIJACKED', 'details' => [['event_date' => '2026-08-31', 'gross_weight_mt' => 10, 'tare_weight_mt' => 2]]]), $this->creator))
        ->toThrow(CrossMillWriteDeniedException::class);

    expect($record->fresh()->getAttributes())->toBe($before);
});

it('mengizinkan Admin menulis ke line mill mana pun (dibuktikan dengan dua mill berbeda)', function () {
    $millB = BusinessUnit::factory()->create();
    $stationB = Station::factory()->forBusinessUnit($millB)->solidWasteDisposal()->create();

    // Mill KEDUA butuh periode terbukanya sendiri: kunci periode (usecase-141)
    // dinilai per mill, dan periode milik mill pertama tidak pernah membuka pintu
    // bagi mill ini. Tanpa baris ini test ini gagal karena ALASAN YANG BENAR,
    // yaitu tepat aturan yang diuji terpisah di EnforcesPeriodLockTest.
    openPeriodForStation($stationB);
    // Admin dinilai dari PERAN: business_unit_id-nya sengaja diisi (19 dari 21
    // Admin di dev punya kolom ini terisi) dan harus diabaikan.
    $admin = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->businessUnit)->create();

    $inOwnMill = $this->service->create(solidWasteFormPayload(['production_line_id' => $this->station->production_line_id, 'solid_waste_disposal_id' => 'SCOPE-MILL-A', 'details' => [['event_date' => '2026-08-31', 'gross_weight_mt' => 10, 'tare_weight_mt' => 2]]]), $admin);
    $inOtherMill = $this->service->create(solidWasteFormPayload(['production_line_id' => $stationB->production_line_id, 'solid_waste_disposal_id' => 'SCOPE-MILL-B', 'details' => [['event_date' => '2026-08-31', 'gross_weight_mt' => 10, 'tare_weight_mt' => 2]]]), $admin);

    expect(SolidWasteDisposalRecord::find($inOwnMill['id'])->station_id)->toBe($this->station->id);
    expect(SolidWasteDisposalRecord::find($inOtherMill['id'])->station_id)->toBe($stationB->id);
});

it('gagal tertutup dengan pesan actionable ketika akun aktor belum terhubung ke mill', function () {
    $actor = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);
    $recordsBefore = SolidWasteDisposalRecord::count();

    try {
        $this->service->create(solidWasteFormPayload(['production_line_id' => $this->station->production_line_id, 'details' => [['event_date' => '2026-08-31', 'gross_weight_mt' => 10, 'tare_weight_mt' => 2]]]), $actor);
        $this->fail('create() seharusnya ditolak untuk aktor tanpa business_unit_id.');
    } catch (ValidationException $e) {
        expect($e->errors()['production_line_id'][0])->toBe('Akun Anda belum terhubung ke mill. Hubungi Admin.');
    }

    expect(SolidWasteDisposalRecord::count())->toBe($recordsBefore);
});

it('tetap mengizinkan create() dan update() pada line mill sendiri', function () {
    $created = $this->service->create(solidWasteFormPayload(['production_line_id' => $this->station->production_line_id, 'solid_waste_disposal_id' => 'SCOPE-OWN-1', 'details' => [['event_date' => '2026-08-31', 'gross_weight_mt' => 10, 'tare_weight_mt' => 2]]]), $this->creator);

    expect(SolidWasteDisposalRecord::find($created['id'])->station_id)->toBe($this->station->id);

    $this->service->update($created['id'], solidWasteFormPayload(['solid_waste_disposal_id' => 'SCOPE-OWN-2', 'details' => [['event_date' => '2026-08-31', 'gross_weight_mt' => 10, 'tare_weight_mt' => 2]]]), $this->creator);

    expect(SolidWasteDisposalRecord::find($created['id'])->solid_waste_disposal_id)->toBe('SCOPE-OWN-2');
});
