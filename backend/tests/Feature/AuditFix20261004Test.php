<?php

/**
 * Regresi temuan audit 2026-10-04 — area backend inti.
 *
 *  1. Zona waktu aplikasi WIB + normalisasi datetime ber-offset dari API.
 *  2. Kunci periode memeriksa event_date per baris detail (CPO/Kernel
 *     Dispatch, Solid Waste Disposal).
 *  3. Periode yang berisi data tidak bisa dihapus (409 PERIOD_HAS_RECORDS).
 *  4. UUID kiriman klien divalidasi sebelum menyentuh SQL; QueryException
 *     tidak pernah membocorkan teks SQL.
 *  5. Form Weighbridge mode edit menampilkan Business Unit dan Production Line
 *     yang benar.
 *  6. Penolakan verifikasi oleh kunci periode memakai kalimat verifikasi.
 *  7. Batas atas tanggal kejadian (besok, zona aplikasi).
 *  8. Label status, nama bulan, dan angka Indonesia di layar data web; Net
 *     Weight sebagai teks, bukan input disabled.
 *  +  checked_by_spv Sterilizer hanya bisa diset/diubah Supervisor.
 *
 * JAM DIBEKUKAN pada 2026-10-20 10:00 WIB untuk seluruh berkas, supaya tanggal
 * Oktober di bawah tidak "di masa depan" terhadap batas atas tanggal kejadian.
 */

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Exceptions\ApiExceptionHandler;
use App\Livewire\Data\DataBrowserWeighbridge;
use App\Livewire\Data\DetailWeighbridge;
use App\Livewire\Data\FormSterilizer;
use App\Livewire\Data\FormWeighbridge;
use App\Livewire\MasterData\KelolaPeriodePelaporan;
use App\Models\BusinessUnit;
use App\Models\CpoDispatchDetail;
use App\Models\CpoDispatchRecord;
use App\Models\GradingParameter;
use App\Models\Period;
use App\Models\Station;
use App\Models\SterilizerDetail;
use App\Models\User;
use App\Models\WeighbridgeRecord;
use App\Services\PeriodService;
use App\Support\Display;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00', 'Asia/Jakarta'));

    $this->bu = BusinessUnit::factory()->create(['name' => 'Mill Audit A']);
    $this->wbStation = Station::factory()->forBusinessUnit($this->bu)->create(['name' => 'Stasiun WB Audit']);
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->bu)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->bu)->create();
    $this->mm = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->bu)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

afterEach(function () {
    Carbon::setTestNow();
});

function auditWbPayload(string $lineId, array $overrides = []): array
{
    return array_merge([
        'production_line_id' => $lineId,
        'wb_card_number' => 'WB-AUDIT-1',
        'weighbridge_type' => 'receive',
        'record_datetime' => '2026-10-03T20:35:00',
        'vehicle_number' => 'B 1 AU',
        'driver_name' => 'Audit',
        'estate_supplier' => 'Estate Audit',
        'gross_weight' => 25432.75,
        'tare_weight' => 8000.25,
    ], $overrides);
}

/** Kumpulkan setiap binding query yang dijalankan selama $fn. */
function auditCaptureBindings(callable $fn): array
{
    $bindings = [];
    DB::listen(function ($query) use (&$bindings) {
        foreach ($query->bindings as $binding) {
            $bindings[] = is_scalar($binding) ? (string) $binding : null;
        }
    });

    $fn();

    return $bindings;
}

// ---------------------------------------------------------------------------
// 1. Zona waktu
// ---------------------------------------------------------------------------

it('[tz] zona waktu aplikasi adalah Asia/Jakarta', function () {
    expect(config('app.timezone'))->toBe('Asia/Jakarta');
    expect(now()->format('Y-m-d H:i'))->toBe('2026-10-20 10:00');
});

it('[tz] record_datetime ber-Z dari mobile disimpan sebagai jam WIB, bukan jam UTC', function () {
    openPeriodFor($this->bu->id, 'weighbridge');

    // 13:35Z = 20:35 WIB — persis kasus audit.
    $response = $this->actingAs($this->operator, 'web')->postJson('/api/weighbridge-records', auditWbPayload(
        $this->wbStation->production_line_id,
        ['record_datetime' => '2026-10-03T13:35:00.000Z'],
    ));

    $response->assertCreated();
    expect($response->json('record_datetime'))->toBe('2026-10-03T20:35:00+07:00');

    $raw = DB::table('weighbridge_records')->where('id', $response->json('id'))->value('record_datetime');
    expect(substr((string) $raw, 0, 16))->toBe('2026-10-03 20:35');
});

it('[tz] input naif (datetime-local web) dibiarkan sebagai jam WIB', function () {
    openPeriodFor($this->bu->id, 'weighbridge');

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/weighbridge-records', auditWbPayload(
        $this->wbStation->production_line_id,
        ['record_datetime' => '2026-10-03T20:35'],
    ));

    $response->assertCreated();
    expect($response->json('record_datetime'))->toBe('2026-10-03T20:35:00+07:00');
});

it('[tz] instan Z dipakai kunci periode menurut TANGGAL WIB-nya', function () {
    // Hanya Oktober yang terbuka. 2026-09-30T18:00Z = 1 Okt 01:00 WIB → diterima.
    Period::factory()->forBusinessUnit($this->bu)->stationType('weighbridge')->range('2026-10-01', '2026-10-31')->open()->create();

    $this->actingAs($this->operator, 'web')->postJson('/api/weighbridge-records', auditWbPayload(
        $this->wbStation->production_line_id,
        ['record_datetime' => '2026-09-30T18:00:00Z'],
    ))->assertCreated();
});

it('[tz] default Tanggal & Waktu Form Weighbridge web adalah jam WIB', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 23:30:00', 'UTC')); // = 4 Okt 06:30 WIB

    Livewire::actingAs($this->supervisor)
        ->test(FormWeighbridge::class)
        ->assertSet('form.record_datetime', '2026-10-04T06:30');
});

it('[tz] "Periode Terbuka Hari Ini" memakai tanggal WIB pada 00:00–07:00 WIB', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 18:00:00', 'UTC')); // = 4 Okt 01:00 WIB

    $result = app(PeriodService::class)->openPeriodsByBusinessUnit($this->bu->id);

    expect($result['meta']['today'])->toBe('2026-10-04');
});

it('[tz] kolom date-only tidak berubah oleh zona waktu', function () {
    $station = Station::factory()->forBusinessUnit($this->bu)->cpoDispatch()->create();
    openPeriodFor($this->bu->id, 'cpo-dispatch');

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/cpo-dispatch-records', [
        'production_line_id' => $station->production_line_id,
        'cpo_dispatch_id' => 'CD-TZ',
        'date' => '2026-10-01',
        'details' => [['event_date' => '2026-10-01']],
    ]);

    $response->assertCreated();
    expect($response->json('date'))->toBe('2026-10-01');
    expect($response->json('details.0.event_date'))->toStartWith('2026-10-01');
});

// ---------------------------------------------------------------------------
// 2. Kunci periode per baris detail
// ---------------------------------------------------------------------------

dataset('event log stations', [
    'cpo-dispatch' => ['cpo-dispatch', 'cpoDispatch', '/api/cpo-dispatch-records', 'cpo_dispatch_id'],
    'kernel-dispatch' => ['kernel-dispatch', 'kernelDispatch', '/api/kernel-dispatch-records', 'kernel_dispatch_id'],
    'solid-waste-disposal' => ['solid-waste-disposal', 'solidWasteDisposal', '/api/solid-waste-disposal-records', 'solid_waste_disposal_id'],
]);

it('[detail-lock] menolak baris detail bertanggal di luar periode terbuka (create)', function (string $type, string $factoryState, string $url, string $idField) {
    $station = Station::factory()->forBusinessUnit($this->bu)->{$factoryState}()->create();
    Period::factory()->forBusinessUnit($this->bu)->stationType($type)->range('2026-10-01', '2026-10-31')->open()->create();

    $response = $this->actingAs($this->supervisor, 'web')->postJson($url, [
        'production_line_id' => $station->production_line_id,
        $idField => 'LOG-1',
        'date' => '2026-10-03',
        'details' => [['event_date' => '2026-10-03'], ['event_date' => '2026-09-15']],
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'PERIOD_CLOSED');
    expect($response->json('message'))->toContain('15/09/2026');
})->with('event log stations');

it('[detail-lock] menerima baris detail yang seluruhnya di periode terbuka', function (string $type, string $factoryState, string $url, string $idField) {
    $station = Station::factory()->forBusinessUnit($this->bu)->{$factoryState}()->create();
    Period::factory()->forBusinessUnit($this->bu)->stationType($type)->range('2026-10-01', '2026-10-31')->open()->create();

    $this->actingAs($this->supervisor, 'web')->postJson($url, [
        'production_line_id' => $station->production_line_id,
        $idField => 'LOG-2',
        'date' => '2026-10-03',
        'details' => [['event_date' => '2026-10-03'], ['event_date' => '2026-10-04']],
    ])->assertCreated();
})->with('event log stations');

it('[detail-lock] update: tanggal baris BARU dan tanggal baris LAMA sama-sama diperiksa', function () {
    $station = Station::factory()->forBusinessUnit($this->bu)->cpoDispatch()->create();
    Period::factory()->forBusinessUnit($this->bu)->stationType('cpo-dispatch')->range('2026-10-01', '2026-10-31')->open()->create();
    Period::factory()->forBusinessUnit($this->bu)->stationType('cpo-dispatch')->range('2026-09-01', '2026-09-30')->closed()->create();

    $record = CpoDispatchRecord::factory()->forStation($station)->onDate('2026-10-02')->create();
    $septemberRow = CpoDispatchDetail::factory()->create(['cpo_dispatch_record_id' => $record->id, 'event_date' => '2026-09-28']);

    // (a) Menghapus baris September (periode tertutup) → ditolak.
    $removal = $this->actingAs($this->supervisor, 'web')->patchJson("/api/cpo-dispatch-records/{$record->id}", [
        'cpo_dispatch_id' => $record->cpo_dispatch_id,
        'date' => '2026-10-02',
        'details' => [['event_date' => '2026-10-02']],
    ]);
    $removal->assertStatus(422)->assertJsonPath('code', 'PERIOD_CLOSED');
    expect($removal->json('message'))->toContain('28/09/2026');
    expect(CpoDispatchDetail::whereKey($septemberRow->id)->exists())->toBeTrue();

    // (b) Menambah baris bertanggal di luar periode terbuka → ditolak.
    $septemberRow->delete();
    $this->actingAs($this->supervisor, 'web')->patchJson("/api/cpo-dispatch-records/{$record->id}", [
        'cpo_dispatch_id' => $record->cpo_dispatch_id,
        'date' => '2026-10-02',
        'details' => [['event_date' => '2026-10-02'], ['event_date' => '2026-09-10']],
    ])->assertStatus(422)->assertJsonPath('code', 'PERIOD_CLOSED');
});

// ---------------------------------------------------------------------------
// 3. Hapus periode yang berisi data
// ---------------------------------------------------------------------------

it('[period-delete] menolak 409 menghapus periode yang berisi record stasiun', function () {
    $period = Period::factory()->forBusinessUnit($this->bu)->named('AUDIT-Daily Okt')->stationType('weighbridge')->range('2026-10-01', '2026-10-31')->open()->create();
    WeighbridgeRecord::factory()->forStation($this->wbStation)->arrivedAt('2026-10-03 08:00:00')->create();

    $response = $this->actingAs($this->admin, 'web')->deleteJson("/api/periods/{$period->id}");

    $response->assertStatus(409);
    $response->assertJsonPath('code', 'PERIOD_HAS_RECORDS');
    expect($response->json('message'))->toContain('tidak dapat dihapus')->toContain('Weighbridge');
    expect(Period::whereKey($period->id)->exists())->toBeTrue();
});

it('[period-delete] menolak juga bila hanya event_date baris detail yang jatuh di dalam rentang', function () {
    $station = Station::factory()->forBusinessUnit($this->bu)->cpoDispatch()->create();
    $period = Period::factory()->forBusinessUnit($this->bu)->stationType('cpo-dispatch')->range('2026-09-01', '2026-09-30')->open()->create();
    $record = CpoDispatchRecord::factory()->forStation($station)->onDate('2026-10-02')->create();
    CpoDispatchDetail::factory()->create(['cpo_dispatch_record_id' => $record->id, 'event_date' => '2026-09-15']);

    $this->actingAs($this->admin, 'web')->deleteJson("/api/periods/{$period->id}")->assertStatus(409);
});

it('[period-delete] periode kosong, data di luar rentang, dan data mill lain tidak menghalangi hapus', function () {
    $period = Period::factory()->forBusinessUnit($this->bu)->stationType('weighbridge')->range('2026-10-01', '2026-10-31')->open()->create();
    WeighbridgeRecord::factory()->forStation($this->wbStation)->arrivedAt('2026-11-01 00:30:00')->create();
    $otherMill = BusinessUnit::factory()->create();
    $otherStation = Station::factory()->forBusinessUnit($otherMill)->create();
    WeighbridgeRecord::factory()->forStation($otherStation)->arrivedAt('2026-10-03 08:00:00')->create();

    $this->actingAs($this->admin, 'web')->deleteJson("/api/periods/{$period->id}")->assertOk();
    expect(Period::whereKey($period->id)->exists())->toBeFalse();
});

it('[period-delete] layar Kelola Periode menampilkan penolakannya inline', function () {
    $period = Period::factory()->forBusinessUnit($this->bu)->stationType('weighbridge')->range('2026-10-01', '2026-10-31')->open()->create();
    WeighbridgeRecord::factory()->forStation($this->wbStation)->arrivedAt('2026-10-03 08:00:00')->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('askDelete', $period->id)
        ->call('confirmDelete')
        ->assertSet('deleteErrorMessage', fn ($message) => str_contains((string) $message, 'tidak dapat dihapus'));

    expect(Period::whereKey($period->id)->exists())->toBeTrue();
});

// ---------------------------------------------------------------------------
// 4. UUID kiriman klien tidak sampai ke SQL
// ---------------------------------------------------------------------------

it('[uuid] grading_parameter_id dan weighbridge_record_id bukan-UUID ditolak 422 tanpa query berisi nilai itu', function () {
    $grading = Station::factory()->forBusinessUnit($this->bu)->grading()->create();
    openPeriodFor($this->bu->id, 'grading');
    $wb = WeighbridgeRecord::factory()->forStation($this->wbStation)->create();
    $parameter = GradingParameter::factory()->create();

    $bindings = auditCaptureBindings(function () use ($grading, $wb) {
        $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/grading-records', [
            'production_line_id' => $grading->production_line_id,
            'grading_number' => 'GR-UUID',
            'date' => '2026-10-03',
            'weighbridge_record_id' => $wb->id,
            'license_plate_no' => 'B 1',
            'estate_supplier' => 'E',
            'netto' => 100,
            'quantity' => 10,
            'details' => [['grading_parameter_id' => 'bukan-uuid-param', 'quantity' => 1]],
        ]);
        $response->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
        expect($response->json('message'))->not->toContain('SQLSTATE');

        $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/grading-records', [
            'production_line_id' => $grading->production_line_id,
            'grading_number' => 'GR-UUID',
            'date' => '2026-10-03',
            'weighbridge_record_id' => 'bukan-uuid-wb',
            'license_plate_no' => 'B 1',
            'estate_supplier' => 'E',
            'netto' => 100,
            'quantity' => 10,
            'details' => [['grading_parameter_id' => 'bukan-uuid-param', 'quantity' => 1]],
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors('weighbridge_record_id');
    });

    // Di PostgreSQL, binding seperti ini = SQLSTATE 22P02 → 500 berisi teks SQL.
    expect($bindings)->not->toContain('bukan-uuid-param');
    expect($bindings)->not->toContain('bukan-uuid-wb');
});

it('[uuid] production_line_id bukan-UUID ditolak 422 tanpa query berisi nilai itu', function () {
    $bindings = auditCaptureBindings(function () {
        $this->actingAs($this->supervisor, 'web')->postJson('/api/weighbridge-records', auditWbPayload('bukan-uuid-line'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('production_line_id');
    });

    expect($bindings)->not->toContain('bukan-uuid-line');
});

it('[uuid] id baris detail bukan-UUID diperlakukan sebagai baris baru tanpa query berisi nilai itu', function () {
    $station = Station::factory()->forBusinessUnit($this->bu)->cpoDispatch()->create();
    openPeriodFor($this->bu->id, 'cpo-dispatch');
    $record = CpoDispatchRecord::factory()->forStation($station)->onDate('2026-10-02')->create();

    $bindings = auditCaptureBindings(function () use ($record) {
        $this->actingAs($this->supervisor, 'web')->patchJson("/api/cpo-dispatch-records/{$record->id}", [
            'cpo_dispatch_id' => $record->cpo_dispatch_id,
            'date' => '2026-10-02',
            'details' => [['id' => 'bukan-uuid-detail', 'event_date' => '2026-10-02']],
        ])->assertOk();
    });

    expect($bindings)->not->toContain('bukan-uuid-detail');
    expect(CpoDispatchDetail::where('cpo_dispatch_record_id', $record->id)->count())->toBe(1);
});

it('[uuid] QueryException tidak pernah membocorkan teks SQL; 22P02 menjadi 422', function () {
    config(['app.debug' => true]);

    $pdo = new class('SQLSTATE[22P02]: Invalid text representation: invalid input syntax for type uuid: "x"') extends PDOException
    {
        protected $code = '22P02';
    };
    $exception = new QueryException('pgsql', 'select * from "grading_parameters" where "id" = ?', ['x'], $pdo);

    $request = Request::create('/api/grading-records', 'POST');
    $request->headers->set('Accept', 'application/json');

    $response = ApiExceptionHandler::render($request, $exception);

    expect($response->getStatusCode())->toBe(422);
    expect($response->getContent())->not->toContain('SQLSTATE')->not->toContain('select');

    $other = new QueryException('pgsql', 'insert into "x"', [], new PDOException('SQLSTATE[23505]: Unique violation'));
    $response = ApiExceptionHandler::render($request, $other);
    expect($response->getStatusCode())->toBe(500);
    expect($response->getContent())->not->toContain('SQLSTATE')->not->toContain('insert');
});

// ---------------------------------------------------------------------------
// 5. Form Weighbridge mode edit: Business Unit & Production Line
// ---------------------------------------------------------------------------

it('[wb-edit] mode edit menampilkan nama Business Unit dan Production Line, bukan nama stasiun', function () {
    $record = WeighbridgeRecord::factory()->forStation($this->wbStation)->create();
    $lineName = $this->wbStation->productionLine->name;

    Livewire::actingAs($this->supervisor)
        ->test(FormWeighbridge::class, ['id' => $record->id])
        ->assertSet('businessUnitName', 'Mill Audit A')
        ->assertSet('productionLineName', $lineName)
        ->assertSeeHtml('data-testid="production-line-readonly"');
});

// ---------------------------------------------------------------------------
// 6. Kalimat penolakan verifikasi
// ---------------------------------------------------------------------------

it('[verify-msg] verifikasi yang ditolak kunci periode memakai kalimat verifikasi', function () {
    Period::factory()->forBusinessUnit($this->bu)->named('Okt Tutup')->stationType('weighbridge')->range('2026-10-01', '2026-10-31')->closed()->create();
    $record = WeighbridgeRecord::factory()->forStation($this->wbStation)->arrivedAt('2026-10-03 08:00:00')->create();

    $response = $this->actingAs($this->supervisor, 'web')
        ->patchJson("/api/records/weighbridge/{$record->id}/verification", ['level' => 'checked', 'value' => true]);

    $response->assertStatus(422)->assertJsonPath('code', 'PERIOD_CLOSED');
    expect($response->json('message'))->toContain('verifikasi')->not->toContain('disimpan atau diubah');
});

it('[verify-msg] verifikasi record log-kejadian ikut dikunci oleh tanggal baris detailnya', function () {
    $station = Station::factory()->forBusinessUnit($this->bu)->cpoDispatch()->create();
    Period::factory()->forBusinessUnit($this->bu)->stationType('cpo-dispatch')->range('2026-10-01', '2026-10-31')->open()->create();
    Period::factory()->forBusinessUnit($this->bu)->stationType('cpo-dispatch')->range('2026-09-01', '2026-09-30')->closed()->create();
    $record = CpoDispatchRecord::factory()->forStation($station)->onDate('2026-10-02')->create();
    CpoDispatchDetail::factory()->create(['cpo_dispatch_record_id' => $record->id, 'event_date' => '2026-09-28']);

    $this->actingAs($this->supervisor, 'web')
        ->patchJson("/api/records/cpo-dispatch/{$record->id}/verification", ['level' => 'checked', 'value' => true])
        ->assertStatus(422)
        ->assertJsonPath('code', 'PERIOD_CLOSED');
});

// ---------------------------------------------------------------------------
// 7. Batas atas tanggal kejadian
// ---------------------------------------------------------------------------

it('[future] tanggal kejadian lebih dari besok ditolak 422; besok masih diterima', function () {
    openPeriodFor($this->bu->id, 'weighbridge');

    $this->actingAs($this->supervisor, 'web')->postJson('/api/weighbridge-records', auditWbPayload(
        $this->wbStation->production_line_id,
        ['record_datetime' => '7278-03-01T08:00'],
    ))->assertStatus(422)->assertJsonValidationErrors('record_datetime');

    $this->actingAs($this->supervisor, 'web')->postJson('/api/weighbridge-records', auditWbPayload(
        $this->wbStation->production_line_id,
        ['record_datetime' => '2026-10-22T08:00'],
    ))->assertStatus(422)->assertJsonValidationErrors('record_datetime');

    $this->actingAs($this->supervisor, 'web')->postJson('/api/weighbridge-records', auditWbPayload(
        $this->wbStation->production_line_id,
        ['record_datetime' => '2026-10-21T23:00'],
    ))->assertCreated();
});

it('[future] batas atas berlaku juga untuk stasiun bertanggal date dan untuk event_date detail', function () {
    $station = Station::factory()->forBusinessUnit($this->bu)->cpoDispatch()->create();
    openPeriodFor($this->bu->id, 'cpo-dispatch');

    $this->actingAs($this->supervisor, 'web')->postJson('/api/cpo-dispatch-records', [
        'production_line_id' => $station->production_line_id,
        'cpo_dispatch_id' => 'CD-F1',
        'date' => '7278-01-01',
        'details' => [['event_date' => '2026-10-20']],
    ])->assertStatus(422)->assertJsonValidationErrors('date');

    $this->actingAs($this->supervisor, 'web')->postJson('/api/cpo-dispatch-records', [
        'production_line_id' => $station->production_line_id,
        'cpo_dispatch_id' => 'CD-F2',
        'date' => '2026-10-20',
        'details' => [['event_date' => '7278-01-01']],
    ])->assertStatus(422)->assertJsonValidationErrors('details');

    $this->actingAs($this->supervisor, 'web')->postJson('/api/cpo-dispatch-records', [
        'production_line_id' => $station->production_line_id,
        'cpo_dispatch_id' => 'CD-F3',
        'date' => '2026-10-20',
        'details' => [['event_date' => 'bukan-tanggal']],
    ])->assertStatus(422)->assertJsonValidationErrors('details');
});

// ---------------------------------------------------------------------------
// 8. Tampilan Indonesia di layar data web
// ---------------------------------------------------------------------------

it('[display] helper status, bulan, dan angka berformat Indonesia', function () {
    expect(Display::status('draft_ongoing'))->toBe('Draft');
    expect(Display::status('draft_paused'))->toBe('Dijeda');
    expect(Display::status(RecordStatus::Saved))->toBe('Tersimpan');
    expect(Display::status('synced'))->toBe('Tersinkron');
    expect(Display::date('2026-10-03'))->toBe('03 Okt 2026');
    expect(Display::dateTime('2026-10-03T13:35:00Z'))->toBe('03 Okt 2026 20:35');
    expect(Display::number(17432.5, 2))->toBe('17.432,50');
    expect(Display::number(25432.75))->toBe('25.432,75');
    expect(Display::value(null))->toBe('-');
    expect(Display::value('007'))->toBe('007');
});

it('[display] Data Browser dan Detail Weighbridge memakai label, bulan, dan angka Indonesia', function () {
    $record = WeighbridgeRecord::factory()->forStation($this->wbStation)->arrivedAt('2026-10-03 20:35:00')
        ->create(['gross_weight' => 25432.75, 'tare_weight' => 8000.25, 'status' => RecordStatus::Saved]);

    $browser = Livewire::actingAs($this->supervisor)->test(DataBrowserWeighbridge::class)->html();
    expect($browser)->toContain('>Tersimpan</span>')->not->toContain('>saved</span>');
    expect($browser)->toContain('17.432,50');

    $detail = Livewire::actingAs($this->supervisor)->test(DetailWeighbridge::class, ['id' => $record->id])->html();
    expect($detail)->toContain('03 Okt 2026 20:35')->toContain('25.432,75')->toContain('Tersimpan');
    expect($detail)->not->toContain('Oct 2026');
});

it('[display] Net Weight Form Weighbridge dirender sebagai teks, bukan input disabled', function () {
    $html = Livewire::actingAs($this->supervisor)
        ->test(FormWeighbridge::class)
        ->set('form.gross_weight', '25432.75')
        ->set('form.tare_weight', '8000.25')
        ->html();

    expect($html)->toMatch('/<span[^>]*data-testid="net-weight-preview"[^>]*>\s*17\.432,50\s*<\/span>/');
    expect($html)->not->toMatch('/<input[^>]*net_weight_preview/');
});

// ---------------------------------------------------------------------------
// + Sterilizer checked_by_spv hanya Supervisor
// ---------------------------------------------------------------------------

it('[spv] Operator tidak bisa menyetel checked_by_spv: baris baru dipaksa false, baris lama dipertahankan', function () {
    $station = Station::factory()->forBusinessUnit($this->bu)->sterilizer()->create();
    openPeriodFor($this->bu->id, 'sterilizer');

    $created = $this->actingAs($this->operator, 'web')->postJson('/api/sterilizer-records', [
        'production_line_id' => $station->production_line_id,
        'sterilizer_id' => 'STR-SPV',
        'date' => '2026-10-03',
        'details' => [['close_door_time' => '07:00', 'open_door_time' => '08:00', 'checked_by_spv' => true]],
    ]);
    $created->assertCreated();
    $recordId = $created->json('id');
    expect(SterilizerDetail::where('sterilizer_record_id', $recordId)->value('checked_by_spv'))->toBeFalse();

    // Supervisor mencentangnya.
    $detailId = SterilizerDetail::where('sterilizer_record_id', $recordId)->value('id');
    $this->actingAs($this->supervisor, 'web')->patchJson("/api/sterilizer-records/{$recordId}", [
        'sterilizer_id' => 'STR-SPV',
        'date' => '2026-10-03',
        'details' => [['id' => $detailId, 'close_door_time' => '07:00', 'open_door_time' => '08:00', 'checked_by_spv' => true]],
    ])->assertOk();
    expect((bool) SterilizerDetail::whereKey($detailId)->value('checked_by_spv'))->toBeTrue();

    // Operator mengirim false untuk baris lama dan true untuk baris baru:
    // keduanya diabaikan, simpan tetap berhasil.
    $this->actingAs($this->operator, 'web')->patchJson("/api/sterilizer-records/{$recordId}", [
        'sterilizer_id' => 'STR-SPV',
        'date' => '2026-10-03',
        'details' => [
            ['id' => $detailId, 'close_door_time' => '07:00', 'open_door_time' => '08:00', 'checked_by_spv' => false],
            ['close_door_time' => '09:00', 'open_door_time' => '10:00', 'checked_by_spv' => true],
        ],
    ])->assertOk();

    expect((bool) SterilizerDetail::whereKey($detailId)->value('checked_by_spv'))->toBeTrue();
    expect((bool) SterilizerDetail::where('sterilizer_record_id', $recordId)->where('id', '!=', $detailId)->value('checked_by_spv'))->toBeFalse();
});

it('[spv] Supervisor bisa menyetel checked_by_spv saat create', function () {
    $station = Station::factory()->forBusinessUnit($this->bu)->sterilizer()->create();
    openPeriodFor($this->bu->id, 'sterilizer');

    $created = $this->actingAs($this->supervisor, 'web')->postJson('/api/sterilizer-records', [
        'production_line_id' => $station->production_line_id,
        'sterilizer_id' => 'STR-SPV2',
        'date' => '2026-10-03',
        'details' => [['close_door_time' => '07:00', 'open_door_time' => '08:00', 'checked_by_spv' => true]],
    ])->assertCreated();

    expect((bool) SterilizerDetail::where('sterilizer_record_id', $created->json('id'))->value('checked_by_spv'))->toBeTrue();
});

it('[spv] Form Sterilizer web: hanya Supervisor mendapat kotak centang Checked By SPV', function () {
    Station::factory()->forBusinessUnit($this->bu)->sterilizer()->create();

    $operatorHtml = Livewire::actingAs($this->mm)->test(FormSterilizer::class)->call('addDetailRow')->html();
    expect($operatorHtml)->not->toContain('data-testid="detail-checked-by-spv-0"');
    expect($operatorHtml)->toContain('data-testid="detail-checked-by-spv-text-0"');

    $supervisorHtml = Livewire::actingAs($this->supervisor)->test(FormSterilizer::class)->call('addDetailRow')->html();
    expect($supervisorHtml)->toContain('data-testid="detail-checked-by-spv-0"');
});

it('[future] batas atas bisa dimatikan lewat konfigurasi (lingkungan uji ber-tahun jauh)', function () {
    openPeriodFor($this->bu->id, 'weighbridge');
    config(['app.event_date_max_days_ahead' => null]);

    $this->actingAs($this->supervisor, 'web')->postJson('/api/weighbridge-records', auditWbPayload(
        $this->wbStation->production_line_id,
        ['record_datetime' => '2650-03-01T08:00'],
    ))->assertCreated();
});
