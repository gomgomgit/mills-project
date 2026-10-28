<?php

/**
 * LaporanCpoDispatchTest (API) — screen-166--laporan-cpo-dispatch-web (+ kembaran mobile screen-167), keempat endpoint
 * /api/cpo-dispatch-reports/*.
 *
 * BERKAS INI WAJIB ADA: laporan saudaranya masing-masing punya padanannya
 * (Laporan<Stasiun>Test) di folder ini, dan stasiun ini dikirim tanpa satu pun,
 * sehingga CpoDispatchReportController tercatat 0% pada run coverage 2026-10-28.
 *
 * Yang dibuktikan di sini BUKAN aturan agregasinya (itu di
 * tests/Unit/Services/CpoDispatchReportService*) dan bukan tampilannya, melainkan
 * KONTRAK HTTP-nya: kode status, BENTUK jawaban galatnya, siapa yang diterima
 * rute mana, dan bentuk berkas ekspornya.
 *
 * TIGA JAWABAN BERBEDA UNTUK OPERATOR, DAN KETIGANYA DISENGAJA:
 *   - /periods, /summary, /export: 200 (kembaran mobile dibangun dalam seri
 *     yang sama);
 *   - /business-units/options: 403, diangkat DARI DALAM service karena
 *     middleware prefix ini menerima EMPAT peran;
 *   - rute WEB /reports/cpo-dispatch: TIDAK diterima — dibuktikan secara struktural
 *     lewat uji 'daftar middleware' paling bawah.
 *
 * FIXTURE: TANGGAL SELALU DIPAKU DAN RELATIF. Factory men-default `date` ke
 * dateTimeBetween('-30 days','now'), dan StationFactory bersarang membuat
 * ProductionLine sendiri; setiap record di bawah memaku tanggalnya lewat
 * helper supaya tidak ada uji yang bergantung pada kebetulan tanggal.
 *
 * KETIADAAN DIASERSIKAN ATAS NAMA KUNCI, TIDAK PERNAH ATAS PROSA: payload
 * memuat kalimat manusia (`_note_*`), jadi memindai teks respons bisa lolos atau
 * memerah karena kata yang kebetulan sama. Untuk alasan yang sama tidak ada uji
 * di sini yang mencari angka acak di dalam isi respons — UUID, yang tak pernah
 * bisa dikuasai, bisa kebetulan memuatnya.
 */

use App\Enums\RecordStatus;
use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\CpoDispatchDetail;
use App\Models\CpoDispatchRecord;
use App\Models\Period;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use App\Services\CpoDispatchReportService;
use Illuminate\Support\Str;

/**
 * Batas ekspor yang diturunkan, supaya 422 'batas baris terlampaui' dapat
 * dibuktikan DI TINGKAT HTTP tanpa menyemai 50.001 baris. Hanya mungkin karena
 * service membaca batasnya lewat `static::`.
 */
class CpoDispatchReportApiTinyExportService extends CpoDispatchReportService
{
    public const EXPORT_ROW_LIMIT = 3;
}

function cdApiUrl(string $path, array $query = []): string
{
    return '/api/cpo-dispatch-reports/'.$path.($query === [] ? '' : '?'.http_build_query($query));
}

/** Satu record induk; TANGGALNYA argumen posisional. */
function cdApiRecord(Station $station, string $date, array $attributes = []): CpoDispatchRecord
{
    return CpoDispatchRecord::factory()
        ->forStation($station)
        ->onDate($date)
        ->create(array_merge([
            'cpo_dispatch_id' => 'CD-'.Str::upper(Str::random(8)),
            'status' => RecordStatus::Saved,
            'note' => null,
        ], $attributes));
}

/** Satu baris detail; SATU BARIS = SATU PENGIRIMAN (satu tanker). */
function cdApiDetail(CpoDispatchRecord $record, string $eventDate, array $values = []): CpoDispatchDetail
{
    return CpoDispatchDetail::factory()
        ->forRecord($record)
        ->create(array_merge([
            'event_date' => $eventDate,
            'net_weight_mt' => 1.0,
        ], $values));
}

/** Isi sebuah StreamedResponse, ditangkap. ALIRKAN SEKALI SAJA per response. */
function cdApiStreamed($response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

/** Body yang sudah ditangkap, dipecah menjadi baris non-kosong. */
function cdApiCsvLines(string $body): array
{
    return array_values(array_filter(explode("\n", trim($body)), fn ($line) => trim($line) !== ''));
}

/**
 * Seluruh nama KUNCI di mana pun dalam struktur, serapa pun dalamnya.
 *
 * @return list<string>
 */
function cdApiAllKeys(mixed $value): array
{
    if (! is_array($value)) {
        return [];
    }

    $keys = [];

    foreach ($value as $key => $child) {
        if (is_string($key)) {
            $keys[] = $key;
        }

        $keys = array_merge($keys, cdApiAllKeys($child));
    }

    return array_values(array_unique($keys));
}

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->cpoDispatch()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->cpoDispatch()->create();

    $this->lineA = (string) $this->stationA->production_line_id;
    $this->lineB = (string) $this->stationB->production_line_id;

    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    // PERIODE YANG SUDAH SELESAI, 31 hari.
    $this->periodStart = now()->subDays(40)->toDateString();
    $this->periodEnd = now()->subDays(10)->toDateString();

    $this->day1 = now()->subDays(39)->toDateString();
    $this->day2 = now()->subDays(38)->toDateString();
    $this->day3 = now()->subDays(37)->toDateString();
    $this->dayBeforePeriod = now()->subDays(41)->toDateString();
    $this->dayAfterPeriod = now()->subDays(9)->toDateString();

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::CpoDispatch->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Alpha')
        ->create();
});

it('Supervisor dan Mill Management: periods + summary + export mill sendiri', function () {
    cdApiDetail(cdApiRecord($this->stationA, $this->day2), $this->day2, ['net_weight_mt' => 2.0]);

    foreach ([$this->supervisor, $this->millManagement] as $user) {
        $periods = $this->actingAs($user, 'web')->getJson(cdApiUrl('periods'));
        $periods->assertOk();

        expect(collect($periods->json('data'))->pluck('id')->all())->toContain((string) $this->periodA->id);

        $summary = $this->actingAs($user, 'web')->getJson(cdApiUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]));

        $summary->assertOk();
        expect($summary->json('business_unit.id'))->toBe((string) $this->businessUnitA->id);
        expect($summary->json('production_line.id'))->toBe($this->lineA);
        expect($summary->json('period.id'))->toBe((string) $this->periodA->id);
        expect(array_keys($summary->json()))->toContain('hourly', 'turnaround');

        $export = $this->actingAs($user, 'web')->get(cdApiUrl('export', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
            'format' => 'csv',
        ]));

        $export->assertOk();
        $this->assertStringContainsString('text/csv', (string) $export->headers->get('content-type'));
        // Satu baris judul + SATU baris untuk satu detail.
        expect(cdApiCsvLines(cdApiStreamed($export->baseResponse)))->toHaveCount(2);
    }
});

it('Operator: 200 pada ketiga rute DATA, 403 pada /business-units/options', function () {
    cdApiDetail(cdApiRecord($this->stationA, $this->day2), $this->day2, ['net_weight_mt' => 2.0]);

    $periods = $this->actingAs($this->operator, 'web')->getJson(cdApiUrl('periods'));
    $periods->assertOk();

    $periodId = collect($periods->json('data'))->pluck('id')->first();

    expect($periodId)->toBe((string) $this->periodA->id);

    $asOperator = $this->actingAs($this->operator, 'web')->getJson(cdApiUrl('summary', [
        'period_id' => $periodId,
        'production_line_id' => $this->lineA,
    ]));
    $asOperator->assertOk();

    $asSupervisor = $this->actingAs($this->supervisor, 'web')->getJson(cdApiUrl('summary', [
        'period_id' => $periodId,
        'production_line_id' => $this->lineA,
    ]));

    // Operator berada di cabang TERIKAT-MILL, bukan cabang Admin: payload
    // IDENTIK dengan yang dilihat Supervisor.
    expect($asOperator->json())->toEqual($asSupervisor->json());

    $this->actingAs($this->operator, 'web')
        ->get(cdApiUrl('export', [
            'period_id' => $periodId,
            'production_line_id' => $this->lineA,
            'format' => 'csv',
        ]))
        ->assertOk();

    $forbidden = $this->actingAs($this->operator, 'web')->getJson(cdApiUrl('business-units/options'));

    $forbidden->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
    expect(array_keys($forbidden->json()))->toBe(['message', 'code']);
});

it('tamu — 401 UNAUTHENTICATED pada keempat rute, dengan BENTUK yang sama', function () {
    // Uji terpisah dengan sengaja: actingAs() bertahan sepanjang satu test,
    // jadi 'tanpa sesi' di dalam test yang sudah masuk sebagai Operator akan
    // mengukur 403-nya Operator.
    foreach ([
        cdApiUrl('business-units/options'),
        cdApiUrl('periods'),
        cdApiUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]),
        cdApiUrl('export', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]),
    ] as $url) {
        $response = $this->getJson($url);

        $response->assertStatus(401)->assertJsonPath('code', 'UNAUTHENTICATED');
        expect(array_keys($response->json()))->toBe(['message', 'code']);
    }
});

it('Admin memilih mill lebih dulu: options 200, periods tanpa mill 422, lalu 200', function () {
    $options = $this->actingAs($this->admin, 'web')->getJson(cdApiUrl('business-units/options'));
    $options->assertOk();

    expect(collect($options->json('data'))->pluck('id')->map(fn ($id) => (string) $id)->all())
        ->toContain((string) $this->businessUnitA->id, (string) $this->businessUnitB->id);

    // Admin TANPA mill: 422, bukan daftar periode lintas mill.
    $this->actingAs($this->admin, 'web')
        ->getJson(cdApiUrl('periods'))
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR');

    $this->actingAs($this->admin, 'web')
        ->getJson(cdApiUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]))
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR');

    $periods = $this->actingAs($this->admin, 'web')->getJson(cdApiUrl('periods', [
        'business_unit_id' => (string) $this->businessUnitA->id,
    ]));
    $periods->assertOk();
    expect(collect($periods->json('data'))->pluck('id')->all())->toContain((string) $this->periodA->id);

    $summary = $this->actingAs($this->admin, 'web')->getJson(cdApiUrl('summary', [
        'business_unit_id' => (string) $this->businessUnitA->id,
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();
    expect($summary->json('business_unit.id'))->toBe((string) $this->businessUnitA->id);

    // Ekspor Admin juga lewat jalur yang sama.
    $this->actingAs($this->admin, 'web')
        ->get(cdApiUrl('export', [
            'business_unit_id' => (string) $this->businessUnitA->id,
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]))
        ->assertOk();
});

it('business_unit_id kiriman DIABAIKAN bagi peran terikat mill: 200 berisi mill akun, SENGAJA bukan 403', function () {
    cdApiDetail(cdApiRecord($this->stationA, $this->day2), $this->day2, ['net_weight_mt' => 2.0]);

    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::CpoDispatch->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->create();

    // Peran terikat mill meminta mill LAIN lewat query: diabaikan. Jawaban 403
    // akan memastikan mill lain itu ada.
    $periods = $this->actingAs($this->supervisor, 'web')->getJson(cdApiUrl('periods', [
        'business_unit_id' => (string) $this->businessUnitB->id,
    ]));
    $periods->assertOk();

    $ids = collect($periods->json('data'))->pluck('id')->all();

    expect($ids)->toContain((string) $this->periodA->id);
    expect($ids)->not->toContain((string) $periodB->id);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(cdApiUrl('summary', [
        'business_unit_id' => (string) $this->businessUnitB->id,
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();
    expect($summary->json('business_unit.id'))->toBe((string) $this->businessUnitA->id);
});

it('akun terikat mill tanpa business_unit_id: 422', function () {
    $orphan = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $response = $this->actingAs($orphan, 'web')->getJson(cdApiUrl('periods'));

    $response->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
    // Daftar seluruh mill TIDAK ditawarkan kepadanya.
    expect(cdApiAllKeys($response->json()))->not->toContain('data');
});

it("Production Line belum dipilih: 422 pada summary DAN export, nol angka, tanpa 'line pertama'", function () {
    $lineKedua = Station::factory()->forBusinessUnit($this->businessUnitA)->cpoDispatch()->create();

    cdApiDetail(cdApiRecord($this->stationA, $this->day2), $this->day2, ['net_weight_mt' => 2.0]);
    cdApiDetail(cdApiRecord($lineKedua, $this->day2), $this->day2, ['net_weight_mt' => 90.0]);

    foreach (['summary', 'export'] as $path) {
        $response = $this->actingAs($this->supervisor, 'web')->getJson(cdApiUrl($path, [
            'period_id' => (string) $this->periodA->id,
        ]));

        $response->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
        $response->assertJsonPath(
            'errors.production_line_id.0',
            'Production Line wajib dipilih untuk menampilkan laporan.',
        );
        // NOL ANGKA: tidak ada blok laporan yang terbit sebagian.
        expect(array_keys($response->json()))->toBe(['message', 'code', 'errors']);
    }

    // KOSONG-SETELAH-TRIM diperlakukan sama dengan absen — hanya terlihat dari HTTP.
    foreach (['', '  '] as $kosong) {
        $this->actingAs($this->supervisor, 'web')
            ->getJson(cdApiUrl('summary', [
                'period_id' => (string) $this->periodA->id,
                'production_line_id' => $kosong,
            ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.production_line_id.0', 'Production Line wajib dipilih untuk menampilkan laporan.');
    }
});

it('period_id belum dipilih: 422 pada summary DAN export, bukan 404 untuk string kosong', function () {
    foreach (['summary', 'export'] as $path) {
        foreach ([[], ['period_id' => ''], ['period_id' => '   ']] as $query) {
            $response = $this->actingAs($this->supervisor, 'web')->getJson(cdApiUrl(
                $path,
                array_merge(['production_line_id' => $this->lineA], $query),
            ));

            $response->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
            $response->assertJsonPath('errors.period_id.0', 'Periode Pelaporan wajib dipilih.');
        }
    }
});

it('mill belum punya periode yang mencakup stasiun ini: data [] dengan 200', function () {
    $this->periodA->stations()->delete();
    $this->periodA->delete();

    $lain = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::Weighbridge->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Weighbridge')
        ->create();

    $response = $this->actingAs($this->supervisor, 'web')->getJson(cdApiUrl('periods'));

    // Array KOSONG dengan 200 — bukan 404 dan bukan exception.
    $response->assertOk();
    expect($response->json('data'))->toBe([]);
    expect(array_column($response->json('data'), 'id'))->not->toContain((string) $lain->id);
});

it('periode tanpa data: kerangka penuh dengan 200, bukan 404, dan ekspor hanya berisi baris judul', function () {
    $summary = $this->actingAs($this->supervisor, 'web')->getJson(cdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    expect($summary->json('period.id'))->toBe((string) $this->periodA->id);
    expect(array_keys($summary->json()))->toContain('hourly', 'turnaround');

    $export = $this->actingAs($this->supervisor, 'web')->get(cdApiUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $export->assertOk();
    expect(cdApiCsvLines(cdApiStreamed($export->baseResponse)))->toHaveCount(1);
});

it('ekspor: rentang periode inklusif di kedua ujung, dan record di luarnya tidak ikut', function () {
    foreach ([
        $this->dayBeforePeriod => 'luar-awal',
        $this->periodStart => 'tepi-awal',
        $this->periodEnd => 'tepi-akhir',
        $this->dayAfterPeriod => 'luar-akhir',
    ] as $date => $label) {
        cdApiDetail(cdApiRecord($this->stationA, $date), $date, ['net_weight_mt' => 2.0]);
    }

    $export = $this->actingAs($this->supervisor, 'web')->get(cdApiUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $export->assertOk();
    // Judul + DUA baris: kedua ujung ikut, kedua sisi luar tidak.
    expect(cdApiCsvLines(cdApiStreamed($export->baseResponse)))->toHaveCount(3);
});

it('lingkup line: record line lain dan stasiun jenis lain tidak pernah bocor ke ekspor', function () {
    $lineKedua = Station::factory()->forBusinessUnit($this->businessUnitA)->cpoDispatch()->create();

    cdApiDetail(cdApiRecord($this->stationA, $this->day2), $this->day2, ['net_weight_mt' => 2.0]);
    cdApiDetail(cdApiRecord($lineKedua, $this->day2), $this->day2, ['net_weight_mt' => 90.0]);
    cdApiDetail(cdApiRecord($lineKedua, $this->day3), $this->day3, ['net_weight_mt' => 90.0]);

    $export = $this->actingAs($this->supervisor, 'web')->get(cdApiUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $export->assertOk();
    expect(cdApiCsvLines(cdApiStreamed($export->baseResponse)))->toHaveCount(2);

    $other = $this->actingAs($this->supervisor, 'web')->get(cdApiUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => (string) $lineKedua->production_line_id,
    ]));

    $other->assertOk();
    expect(cdApiCsvLines(cdApiStreamed($other->baseResponse)))->toHaveCount(3);
});

it('format — TRIM + DEFAULT ditetapkan CONTROLLER, dan hanya terlihat dari HTTP', function () {
    cdApiDetail(cdApiRecord($this->stationA, $this->day2), $this->day2, ['net_weight_mt' => 2.0]);

    $base = [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ];

    // Absen, kosong, dan spasi semua berarti default 'csv'.
    foreach ([[], ['format' => ''], ['format' => '   '], ['format' => ' csv ']] as $query) {
        $response = $this->actingAs($this->supervisor, 'web')->get(cdApiUrl('export', array_merge($base, $query)));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
    }

    $xlsx = $this->actingAs($this->supervisor, 'web')->get(cdApiUrl('export', array_merge($base, ['format' => 'xlsx'])));

    $xlsx->assertOk();
    $this->assertStringContainsString('spreadsheetml', (string) $xlsx->headers->get('content-type'));

    // Daftar tertutup {csv, xlsx}: selebihnya 422 SEBELUM aliran dimulai.
    $bad = $this->actingAs($this->supervisor, 'web')->getJson(cdApiUrl('export', array_merge($base, ['format' => 'pdf'])));

    $bad->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
    $this->assertNull($bad->headers->get('content-disposition'));
});

it('ekspor: batas baris terlampaui adalah 422 SEBELUM aliran dimulai, dan batasnya INKLUSIF', function () {
    expect(CpoDispatchReportService::EXPORT_ROW_LIMIT)->toBe(50000);

    $this->app->bind(
        CpoDispatchReportService::class,
        fn () => new CpoDispatchReportApiTinyExportService,
    );

    // EMPAT baris pada DUA record induk: batas menghitung BARIS YANG
    // DITERBITKAN, bukan record.
    $r1 = cdApiRecord($this->stationA, $this->day2);
    $r2 = cdApiRecord($this->stationA, $this->day3);

    foreach (range(1, 2) as $i) {
        cdApiDetail($r1, $this->day2, ['net_weight_mt' => 2.0]);
        cdApiDetail($r2, $this->day3, ['net_weight_mt' => 2.0]);
    }

    expect(CpoDispatchRecord::query()->count())->toBe(2);
    expect(CpoDispatchDetail::query()->count())->toBe(4);

    $base = [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ];

    $lewat = $this->actingAs($this->supervisor, 'web')->getJson(cdApiUrl('export', $base));

    $lewat->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
    expect($lewat->json('message'))->toBeString()->not->toBe('');
    $this->assertNull($lewat->headers->get('content-disposition'));
    $this->assertStringNotContainsString('text/csv', (string) $lewat->headers->get('content-type'));

    // TEPAT DI BATAS: beraliran — perbandingannya strictly-greater.
    CpoDispatchDetail::query()->orderBy('id')->limit(1)->get()->each->delete();

    expect(CpoDispatchDetail::query()->count())->toBe(3);

    $tepat = $this->actingAs($this->supervisor, 'web')->get(cdApiUrl('export', $base));

    $tepat->assertOk();
    expect(cdApiCsvLines(cdApiStreamed($tepat->baseResponse)))->toHaveCount(4);
});

it('period_id tidak ada → 404 NOT_FOUND; period_id mill lain → 403 FORBIDDEN, dan keduanya BERBENTUK sama', function () {
    $periodeB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::CpoDispatch->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->create();

    $hilang = $this->actingAs($this->supervisor, 'web')->getJson(cdApiUrl('summary', [
        'period_id' => (string) Str::uuid(),
        'production_line_id' => $this->lineA,
    ]));

    $hilang->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
    expect(array_keys($hilang->json()))->toBe(['message', 'code']);

    // ORACLE LINTAS MILL DITUTUP: periode mill lain menjawab 403, TIDAK PERNAH 404.
    foreach (['summary', 'export'] as $path) {
        $asing = $this->actingAs($this->supervisor, 'web')->getJson(cdApiUrl($path, [
            'period_id' => (string) $periodeB->id,
            'production_line_id' => $this->lineA,
        ]));

        $asing->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        expect(array_keys($asing->json()))->toBe(['message', 'code']);
    }
});

it('production_line_id milik mill LAIN: 403 pada summary DAN pada export, nol angka', function () {
    cdApiDetail(cdApiRecord($this->stationB, $this->day2), $this->day2, ['net_weight_mt' => 90.0]);

    foreach (['summary', 'export'] as $path) {
        $response = $this->actingAs($this->supervisor, 'web')->getJson(cdApiUrl($path, [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineB,
        ]));

        $response->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        expect(array_keys($response->json()))->toBe(['message', 'code']);
        // Nama line mill lain tidak pernah terbit.
        $this->assertStringNotContainsString(
            (string) ProductionLine::find($this->lineB)->name,
            (string) $response->getContent(),
        );
    }
});

it('layar hanya membaca: prefix API ini tidak punya satu rute tulis pun', function () {
    $rutes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/cpo-dispatch-reports'));

    expect($rutes)->toHaveCount(4);

    foreach ($rutes as $route) {
        expect(array_values(array_diff($route->methods(), ['HEAD'])))->toBe(['GET']);
    }

    expect($rutes->map(fn ($route) => $route->uri())->sort()->values()->all())->toBe([
        'api/cpo-dispatch-reports/business-units/options',
        'api/cpo-dispatch-reports/export',
        'api/cpo-dispatch-reports/periods',
        'api/cpo-dispatch-reports/summary',
    ]);
});

it('daftar middleware: prefix API menerima Operator sementara rute WEB laporan ini TIDAK', function () {
    $apiRoute = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($route) => $route->uri() === 'api/cpo-dispatch-reports/summary');

    $webRoute = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($route) => $route->uri() === 'reports/cpo-dispatch');

    $this->assertNotNull($apiRoute);
    $this->assertNotNull($webRoute);

    // Daftar peran diurai menjadi array, bukan dicocokkan sebagai substring.
    $peranDari = function ($route): array {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'role:')) {
                return explode(',', substr($middleware, strlen('role:')));
            }
        }

        return [];
    };

    $apiRoles = $peranDari($apiRoute);
    $webRoles = $peranDari($webRoute);

    $this->assertContains('operator', $apiRoles, 'rute API tidak menerima Operator');
    $this->assertNotContains('operator', $webRoles, 'rute WEB menerima Operator');

    foreach (['supervisor', 'mill_management', 'admin'] as $role) {
        $this->assertContains($role, $apiRoles, "rute API tidak menerima {$role}");
        $this->assertContains($role, $webRoles, "rute WEB tidak menerima {$role}");
    }

    // Perbedaannya TEPAT satu peran.
    expect(array_values(array_diff($apiRoles, $webRoles)))->toBe(['operator']);
    expect(array_values(array_diff($webRoles, $apiRoles)))->toBe([]);

    expect($webRoute->getName())->toBe('reports.cpo-dispatch');
});
