<?php

/**
 * LaporanSolidWasteDisposalTest (API) — screen-162--laporan-solid-waste-disposal-web
 * (+ kembaran mobile screen-163), keempat endpoint
 * /api/solid-waste-disposal-reports/*.
 *
 * Satu uji per entri `test_scenarios[].api_test` pada tech spec screen-162
 * (29 skenario). Aturan agregasinya sendiri dibuktikan di
 * tests/Unit/Services/SolidWasteDisposalReportServiceTest.php terhadap
 * service-nya, dan tampilannya di tests/Feature/Livewire/LaporanSolidWasteDisposalTest.php;
 * yang dibuktikan berkas ini adalah KONTRAK HTTP-nya — kode status, BENTUK
 * jawaban galatnya, siapa yang diterima rute mana, apa yang benar-benar dibawa
 * payload-nya, dan bagaimana bentuk berkas ekspornya.
 *
 * ────────────────────────────────────────────────────────────────────────
 * TIGA JAWABAN YANG BERBEDA UNTUK OPERATOR, DAN KETIGANYA DISENGAJA
 * ────────────────────────────────────────────────────────────────────────
 *   - /periods, /summary, /export: 200 SEJAK COMMIT PERTAMA (preseden
 *     Grading / Kernel Plant / Engine Room / Process Water — kembaran mobile
 *     screen-163 dibangun dalam seri yang SAMA, jadi pemanggilnya sudah ada).
 *   - /business-units/options: 403, diangkat DARI DALAM service justru karena
 *     middleware prefix ini menerima EMPAT peran demi ketiga rute data,
 *     sehingga penjaga yang ditempatkan di middleware tidak akan menutup rute
 *     itu sama sekali.
 *   - rute WEB /reports/solid-waste-disposal: 403 — dibuktikan di
 *     tests/Feature/Livewire/LaporanSolidWasteDisposalTest.php (skenario 2),
 *     dan TIDAK diulang di sini. Yang diasersikan di sini adalah bentuk
 *     struktural dari perbedaan itu: DAFTAR MIDDLEWARE kedua kelompok rute
 *     (uji 'daftar middleware' di bagian paling bawah), karena perbedaan itulah
 *     yang menjaga admisi API tidak pernah merembes menjadi admisi layar web.
 *
 * ────────────────────────────────────────────────────────────────────────
 * KETIADAAN DIASERSIKAN ATAS NAMA KUNCI, TIDAK PERNAH ATAS PROSA
 * ────────────────────────────────────────────────────────────────────────
 * Payload /summary memuat TIGA KALIMAT MANUSIA — `_note_no_hourly`,
 * `_note_shift_not_published` dan `_note_absences` — dan ketiganya MEMUAT kata
 * 'jam', 'shift', 'downtime' dan 'ambang' justru karena tugasnya MENYATAKAN
 * ketiadaan hal-hal itu. Memindai teks respons karena itu menjawab pertanyaan
 * yang salah: ia bisa memerahkan implementasi yang benar, atau lulus semata
 * karena implementasinya memilih kata lain. Setiap asersi KETIADAAN di berkas
 * ini berjalan atas NAMA KUNCI lewat swdApiAllKeys().
 *
 * ────────────────────────────────────────────────────────────────────────
 * TRIM + DEFAULT `format` HANYA TERLIHAT DARI HTTP
 * ────────────────────────────────────────────────────────────────────────
 * SolidWasteDisposalReportController::queryString() memulangkan null untuk
 * nilai yang kosong SETELAH trim, dan export() menetapkan default 'csv' ATAS
 * NILAI YANG SUDAH DI-TRIM itu. Di tingkat SERVICE daftar tertutup {csv,xlsx}
 * menolak '', '  ' dan ' csv ' dengan 422 — jadi separuh perilaku ini TIDAK
 * DAPAT dilihat dari uji unit sama sekali. Uji 'format — trim + default'
 * di bawah adalah satu-satunya tempat ia dibuktikan.
 *
 * ────────────────────────────────────────────────────────────────────────
 * FIXTURE: TANGGAL SELALU DIPAKU, DAN SELALU RELATIF
 * ────────────────────────────────────────────────────────────────────────
 * SolidWasteDisposalRecordFactory men-default `date` ke
 * dateTimeBetween('-30 days','now') dan SolidWasteDisposalDetailFactory
 * men-default `event_date` dengan cara yang sama; StationFactory bersarang pun
 * membuat ProductionLine sendiri. Asersi 'tepat N muatan' yang menumpang
 * default itu gagal sesekali tanpa ada hubungannya dengan layar ini (flake
 * pabrik tersarang bertanggal acak). SETIAP record dan SETIAP muatan di bawah
 * memaku tanggalnya lewat helper. Tanggalnya relatif (now()->subDays(...))
 * supaya `$this->periodA` SELALU sudah selesai dan days_counted-nya
 * deterministik.
 *
 * `event_date` NOT NULL DI SKEMA (migrasi 2026_08_31_000003, tanpa
 * ->nullable() dan tanpa migrasi pengubah sesudahnya), jadi
 * `undated_load_count` SELALU 0 terhadap skema yang sebenarnya. Tidak ada satu
 * fixture pun di bawah yang mencoba menyemai event_date null — penolakan
 * skemanya sudah dipaku di uji unit (case 56), dan cabang agregasinya diuji di
 * sana lewat koleksi di memori. Yang diasersikan di sini: kuncinya SELALU
 * diterbitkan, bernilai 0.
 */

use App\Enums\RecordStatus;
use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\ProductionLine;
use App\Models\SolidWasteDisposalDetail;
use App\Models\SolidWasteDisposalRecord;
use App\Models\Station;
use App\Models\User;
use App\Services\SolidWasteDisposalReportService;
use App\Support\SheetWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * Batas ekspor yang diturunkan, supaya 422 'batas baris terlampaui' dapat
 * dibuktikan DI TINGKAT HTTP tanpa menyemai 50.001 baris.
 *
 * Hanya mungkin karena service membaca batasnya lewat `static::`, bukan
 * `self::`. Diikat ke container di uji yang memakainya, sehingga controller
 * yang sama persis me-resolve subclass ini — bukan service dipanggil langsung.
 */
class SolidWasteDisposalReportApiTinyExportService extends SolidWasteDisposalReportService
{
    public const EXPORT_ROW_LIMIT = 5;
}

function swdApiUrl(string $path, array $query = []): string
{
    return '/api/solid-waste-disposal-reports/'.$path.($query === [] ? '' : '?'.http_build_query($query));
}

/**
 * Satu record induk. TANGGALNYA argumen posisional karena ia yang menentukan
 * keanggotaan periode (recordQueryFor() menyaring atas
 * solid_waste_disposal_records.date) dan tidak boleh pernah diserahkan pada
 * default acak factory.
 */
function swdApiRecord(Station $station, string $date, array $attributes = []): SolidWasteDisposalRecord
{
    return SolidWasteDisposalRecord::factory()
        ->forStation($station)
        ->onDate($date)
        ->create(array_merge([
            'solid_waste_disposal_id' => 'SWD-'.Str::upper(Str::random(8)),
            'status' => RecordStatus::Saved,
            'note' => null,
        ], $attributes));
}

/**
 * Satu baris detail = SATU MUATAN KELUAR.
 *
 * SELURUH kolom yang berarti bagi laporan di-default NULL di sini, dan itu
 * disengaja: SolidWasteDisposalDetailFactory mengisi keempat kolom kategoris,
 * kedua nomor dokumen, shift, remarks dan findings dengan nilai faker ACAK,
 * sehingga fixture yang menumpangnya akan mengasersikan rekap yang isinya
 * berubah tiap jalan. Setiap uji di bawah menyebut TEPAT kolom yang memang jadi
 * subjeknya.
 *
 * `net_weight_mt` di-default 1.0 sementara bruto/tara di-default NULL — bentuk
 * yang sama dengan kedua baris fixture e2e hari ini. Laporan ini MEMBACA
 * net_weight_mt apa adanya.
 */
function swdApiLoad(
    SolidWasteDisposalRecord $record,
    string $eventDate,
    array $values = [],
): SolidWasteDisposalDetail {
    return SolidWasteDisposalDetail::factory()
        ->forRecord($record)
        ->create(array_merge([
            'event_date' => $eventDate,
            'shift' => null,
            'solid_waste_type' => null,
            'source_station' => null,
            'disposal_utilization_site' => null,
            'purpose_end_use' => null,
            'gate_pass_no' => null,
            'security_seal_no' => null,
            'operator_id' => null,
            'remarks' => null,
            'findings' => null,
            'gross_weight_mt' => null,
            'tare_weight_mt' => null,
            'net_weight_mt' => 1.0,
        ], $values));
}

/** Isi sebuah StreamedResponse, ditangkap. ALIRKAN SEKALI SAJA per response. */
function swdApiStreamed($response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

/** Body yang sudah ditangkap, dipecah menjadi baris non-kosong. */
function swdApiCsvLines(string $body): array
{
    return array_values(array_filter(explode("\n", trim($body)), fn ($line) => trim($line) !== ''));
}

/** Satu baris CSV, diurai. */
function swdApiCsvRow(string $line): array
{
    return str_getcsv(trim($line, "\r"), ',', '"', '\\');
}

/**
 * Seluruh nama KUNCI di mana pun dalam struktur, serapa pun dalamnya.
 *
 * DIPAKAI ALIH-ALIH MEMINDAI TEKS: ketiga `_note_*` pada payload ini adalah
 * kalimat manusia yang MEMUAT kata 'jam', 'shift', 'downtime' dan 'ambang'.
 * Yang ditanyakan adalah apakah sebuah KUNCI ada.
 *
 * @return list<string>
 */
function swdApiAllKeys(mixed $value): array
{
    if (! is_array($value)) {
        return [];
    }

    $keys = [];

    foreach ($value as $key => $child) {
        if (is_string($key)) {
            $keys[] = $key;
        }

        $keys = array_merge($keys, swdApiAllKeys($child));
    }

    return array_values(array_unique($keys));
}

/**
 * Nama kunci yang TIDAK BOLEH ADA di mana pun pada payload /summary.
 *
 * Empat rumpun, masing-masing dengan sebabnya sendiri: sumbu waktu per jam
 * (tabel detailnya hanya punya event_date), dimensi giliran kerja (kolom shift
 * teks bebas), downtime (stasiun ini tidak mengukurnya), penandaan di luar
 * batas (tidak ada master target), dan pengelompokan per unit (pembaca membaca
 * satu angka per line).
 *
 * @return list<string>
 */
function swdApiForbiddenKeys(): array
{
    return [
        // Sumbu waktu per jam.
        'hourly', 'hourly_distribution', 'by_hour', 'per_hour', 'hour', 'hours',
        'busiest_hour', 'empty_hour_count', 'time_slot', 'time_slots', 'slots',
        'filled_slots', 'expected_slots',
        // Giliran kerja.
        'shift', 'shifts', 'by_shift', 'per_shift', 'shift_distribution',
        // Downtime.
        'downtime', 'downtime_minutes', 'downtime_events', 'total_minutes',
        'stop_events', 'stoppages',
        // Penandaan di luar batas.
        'out_of_range', 'is_out_of_range', 'threshold', 'thresholds', 'target',
        'targets', 'violation', 'violations', 'severity', 'flag', 'flags',
        'warning', 'warnings', 'breach', 'exceeds', 'critical_limit', 'nominal',
        'safe_range', 'anomaly', 'anomalies', 'operational_target',
        // Pengelompokan per unit stasiun.
        'by_unit', 'units', 'by_station', 'stations',
        // Dua ember 'dibuang vs dimanfaatkan' — kontradiksi spec yang sudah
        // diselesaikan: rekapnya PER NILAI apa adanya.
        'disposed', 'utilized', 'disposed_count', 'utilized_count',
        'is_utilized', 'disposal_vs_utilization', 'buckets',
    ];
}

/** Satu baris rekap untuk satu nilai (null = kelompok 'tidak tercatat'). */
function swdApiEntry(array $rows, ?string $value): ?array
{
    foreach ($rows as $row) {
        if ($row['value'] === $value) {
            return $row;
        }
    }

    return null;
}

/** Posisi baris 'tidak tercatat' di dalam satu rekap, atau -1. */
function swdApiNullIndex(array $rows): int
{
    foreach ($rows as $index => $row) {
        if ($row['value'] === null) {
            return $index;
        }
    }

    return -1;
}

/** Kunci tingkat atas payload /summary, dalam urutan terbitnya. */
function swdApiSummaryKeys(): array
{
    return [
        'business_unit', 'production_line', 'period', 'loads',
        'by_waste_type', 'by_source_station', 'by_disposal_site',
        'by_purpose_end_use', 'compliance', 'draft_load_count',
        'undated_load_count', 'daily', 'daily_total', 'completeness',
        'text_entries', '_note_no_hourly', '_note_shift_not_published',
        '_note_absences',
    ];
}

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->solidWasteDisposal()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->solidWasteDisposal()->create();

    // PRODUCTION LINE ADALAH KONTEKS YANG DIPILIH, BUKAN IKATAN AKUN — dan di
    // layar ini memilihnya WAJIB, jadi hampir setiap uji di bawah menyebutnya.
    $this->lineA = (string) $this->stationA->production_line_id;
    $this->lineB = (string) $this->stationB->production_line_id;

    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    // Admin satu-satunya peran yang tidak terikat mill — justru itu sebab
    // pemilih mill ada, dan sebab /business-units/options hanya miliknya.
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    // PERIODE YANG SUDAH SELESAI, 31 hari: days_counted === days_in_period ===
    // 31 selamanya, apa pun tanggal kalender saat suite dijalankan.
    $this->periodStart = now()->subDays(40)->toDateString();
    $this->periodEnd = now()->subDays(10)->toDateString();

    $this->day1 = now()->subDays(39)->toDateString();
    $this->day2 = now()->subDays(38)->toDateString();
    $this->day3 = now()->subDays(37)->toDateString();
    $this->dayBeforePeriod = now()->subDays(41)->toDateString();
    $this->dayAfterPeriod = now()->subDays(9)->toDateString();

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::SolidWasteDisposal->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Maret Alpha')
        ->create();
});

// =====================================================================
// Skenario 1-5: siapa yang diterima, dan mill mana yang ia lihat
// =====================================================================

it('skenario 1 — Supervisor dan Mill Management: periods + summary + export mill sendiri', function () {
    $record = swdApiRecord($this->stationA, $this->day2);

    // Fixture SENGAJA TIDAK RATA: netto terisi pada 2 muatan dari 3, gate pass
    // hilang pada 1 dari 3, dan event_date-nya dua hari BERBEDA — penyebut yang
    // seragam tidak dapat lolos tanpa terlihat.
    swdApiLoad($record, $this->day2, [
        'net_weight_mt' => 2.0,
        'solid_waste_type' => 'Fibre',
        'source_station' => 'Pressing',
        'disposal_utilization_site' => 'TPA Utara',
        'purpose_end_use' => 'Landfill',
        'gate_pass_no' => 'GP-0001',
        'security_seal_no' => 'SS-0001',
    ]);
    swdApiLoad($record, $this->day2, [
        'net_weight_mt' => 4.0,
        'solid_waste_type' => 'Shell',
        'source_station' => 'Threshing',
        'disposal_utilization_site' => 'TPA Utara',
        'purpose_end_use' => 'Co-firing Boiler',
        'gate_pass_no' => null,
        'security_seal_no' => 'SS-0002',
    ]);
    swdApiLoad($record, $this->day3, [
        'net_weight_mt' => null,
        'solid_waste_type' => 'Fibre',
        'source_station' => 'Pressing',
        'disposal_utilization_site' => 'Zinc Scrap',
        'purpose_end_use' => 'Landfill',
        'gate_pass_no' => 'GP-0003',
        'security_seal_no' => 'SS-0003',
    ]);

    foreach ([$this->supervisor, $this->millManagement] as $user) {
        $periods = $this->actingAs($user, 'web')->getJson(swdApiUrl('periods'));
        $periods->assertOk();

        expect(collect($periods->json('data'))->pluck('id'))->toContain((string) $this->periodA->id);
        // BERTANDA HUBUNG: kunci snake_case akan ter-compile, tampak benar, dan
        // tidak pernah cocok dengan satu baris period_stations pun.
        expect(collect($periods->json('data'))->pluck('station_type'))->toContain('solid-waste-disposal');

        $summary = $this->actingAs($user, 'web')->getJson(swdApiUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]));
        $summary->assertOk();

        // BENTUK PAYLOAD-NYA, diasersikan sebagai daftar kunci yang LENGKAP:
        // sebuah blok yang hilang tidak boleh lolos hanya karena tak satu asersi
        // pun menyebutnya.
        expect(array_keys($summary->json()))->toBe(swdApiSummaryKeys());

        expect($summary->json('business_unit.name'))->toBe('Mill Alpha');
        expect($summary->json('production_line.id'))->toBe($this->lineA);
        expect($summary->json('period.id'))->toBe((string) $this->periodA->id);
        expect($summary->json('period.status'))->toBe('open');

        // SATUAN HITUNGNYA BARIS DETAIL, BUKAN RECORD: satu record induk
        // membawa tiga muatan, dan 1 di sini adalah cacat yang dicari.
        expect($summary->json('loads.load_count'))->toBe(3);
        expect($summary->json('loads.net_weight_load_count'))->toBe(2);
        expect($summary->json('loads.missing_net_weight_load_count'))->toBe(1);
        expect($summary->json('loads.net_weight_total'))->toEqual(6.0);
        expect($summary->json('loads.net_weight_avg'))->toEqual(3.0);

        // ANGKA YANG WAJIB DAPAT DIREKONSILIASI PEMBACA.
        expect($summary->json('loads.net_weight_load_count') + $summary->json('loads.missing_net_weight_load_count'))
            ->toBe($summary->json('loads.load_count'));

        // Keempat rekap: penjumlahannya SAMA DENGAN load_count.
        foreach (['by_waste_type', 'by_source_station', 'by_disposal_site', 'by_purpose_end_use'] as $recap) {
            expect(collect($summary->json($recap))->sum('load_count'))
                ->toBe($summary->json('loads.load_count'));
        }

        // Jejak kepatuhan: blok TERSENDIRI, dua penghitung saja.
        expect($summary->json('compliance.missing_gate_pass_count'))->toBe(1);
        expect($summary->json('compliance.missing_seal_count'))->toBe(0);

        expect($summary->json('draft_load_count'))->toBe(0);
        expect($summary->json('undated_load_count'))->toBe(0);

        // SATU ENTRI PER HARI BERMUATAN — bukan 31 baris nol.
        expect(collect($summary->json('daily'))->pluck('date')->all())->toBe([$this->day2, $this->day3]);
        expect(collect($summary->json('daily'))->sum('load_count'))
            ->toBe($summary->json('loads.load_count') - $summary->json('undated_load_count'));

        expect($summary->json('completeness.days_in_period'))->toBe(31);
        expect($summary->json('completeness.days_counted'))->toBe(31);
        expect($summary->json('completeness.days_with_load'))->toBe(2);

        // Ketiga prosa ketiadaan DITERBITKAN UTUH.
        expect($summary->json('_note_no_hourly'))->toBe(SolidWasteDisposalReportService::NOTE_NO_HOURLY);
        expect($summary->json('_note_shift_not_published'))->toBe(SolidWasteDisposalReportService::NOTE_SHIFT_NOT_PUBLISHED);
        expect($summary->json('_note_absences'))->toBe(SolidWasteDisposalReportService::NOTE_ABSENCES);

        // DAN NOL kunci terlarang di seluruh payload — atas NAMA KUNCI.
        $keys = swdApiAllKeys($summary->json());

        foreach (swdApiForbiddenKeys() as $forbidden) {
            $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
        }
    }

    $export = $this->actingAs($this->supervisor, 'web')->get(swdApiUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));

    $export->assertOk();
    $this->assertStringContainsString('text/csv', (string) $export->headers->get('content-type'));
});

it('skenario 2 — Operator: 200 pada ketiga rute DATA, 403 pada /business-units/options', function () {
    $record = swdApiRecord($this->stationA, $this->day2);
    swdApiLoad($record, $this->day2, ['net_weight_mt' => 3.0, 'solid_waste_type' => 'Shell']);

    // Langkah 2 — /periods: 200.
    $periods = $this->actingAs($this->operator, 'web')->getJson(swdApiUrl('periods'));
    $periods->assertOk();

    $periodId = collect($periods->json('data'))->pluck('id')->first();

    expect($periodId)->toBe((string) $this->periodA->id);

    // Langkah 3 — /summary: 200, dengan payload yang IDENTIK dengan yang
    // dilihat Supervisor. Operator berada di cabang TERIKAT MILL
    // resolveBusinessUnit(), bukan di cabang Admin.
    $asOperator = $this->actingAs($this->operator, 'web')->getJson(swdApiUrl('summary', [
        'period_id' => $periodId,
        'production_line_id' => $this->lineA,
    ]));
    $asOperator->assertOk();

    $asSupervisor = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('summary', [
        'period_id' => $periodId,
        'production_line_id' => $this->lineA,
    ]));
    $asSupervisor->assertOk();

    expect($asOperator->json())->toEqual($asSupervisor->json());
    expect($asOperator->json('business_unit.id'))->toBe((string) $this->businessUnitA->id);

    // Langkah 4 — /export: 200 juga.
    $this->actingAs($this->operator, 'web')
        ->get(swdApiUrl('export', [
            'period_id' => $periodId,
            'production_line_id' => $this->lineA,
            'format' => 'csv',
        ]))
        ->assertOk();

    // Langkah 5 — /business-units/options: 403, diangkat DARI DALAM service
    // justru karena middleware prefix ini menerima KEEMPAT peran. BENTUK
    // jawabannya ikut diasersikan: dua kunci, bukan tiga.
    $forbidden = $this->actingAs($this->operator, 'web')->getJson(swdApiUrl('business-units/options'));

    $forbidden->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
    expect(array_keys($forbidden->json()))->toBe(['message', 'code']);
});

it('tamu — 401 UNAUTHENTICATED pada keempat rute, dengan BENTUK yang sama', function () {
    // BERKAS/UJI TERPISAH DENGAN SENGAJA: $this->actingAs() bertahan sepanjang
    // satu test, jadi memeriksa 'tanpa sesi' di dalam test yang sudah masuk
    // sebagai Operator justru akan mengukur 403-nya Operator dan lulus sebagai
    // 'ditolak' tanpa membuktikan apa pun tentang 401.
    foreach ([
        swdApiUrl('business-units/options'),
        swdApiUrl('periods'),
        swdApiUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]),
        swdApiUrl('export', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]),
    ] as $url) {
        $response = $this->getJson($url);

        $response->assertStatus(401)->assertJsonPath('code', 'UNAUTHENTICATED');
        // BENTUKNYA, bukan hanya kodenya: tepat dua kunci, tanpa `errors`, dan
        // tanpa satu angka laporan pun.
        expect(array_keys($response->json()))->toBe(['message', 'code']);
        expect($response->json('message'))->toBe('Unauthenticated.');
    }
});

it('skenario 3 — Admin memilih mill lebih dulu: options 200, periods tanpa mill 422, lalu 200', function () {
    $record = swdApiRecord($this->stationA, $this->day2);
    swdApiLoad($record, $this->day2, ['net_weight_mt' => 7.0]);

    // Langkah 1 — pemilih mill.
    $options = $this->actingAs($this->admin, 'web')->getJson(swdApiUrl('business-units/options'));
    $options->assertOk();

    $ids = collect($options->json('data'))->pluck('id');

    expect($ids)->toContain((string) $this->businessUnitA->id);
    expect($ids)->toContain((string) $this->businessUnitB->id);

    // Langkah 2 — tanpa business_unit_id: 422, BUKAN mill pertama dan BUKAN
    // daftar kosong yang akan terbaca sebagai 'mill ini tidak punya data'.
    $this->actingAs($this->admin, 'web')
        ->getJson(swdApiUrl('periods'))
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonValidationErrors('business_unit_id');

    // Langkah 3 — dengan business_unit_id: 200.
    $periods = $this->actingAs($this->admin, 'web')->getJson(swdApiUrl('periods', [
        'business_unit_id' => (string) $this->businessUnitA->id,
    ]));
    $periods->assertOk();
    expect(collect($periods->json('data'))->pluck('id')->all())->toBe([(string) $this->periodA->id]);

    // Langkah 4 — summary: 200, dan cabang Admin MENGHORMATI business_unit_id
    // kiriman.
    $summary = $this->actingAs($this->admin, 'web')->getJson(swdApiUrl('summary', [
        'business_unit_id' => (string) $this->businessUnitA->id,
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    expect($summary->json('business_unit.name'))->toBe('Mill Alpha');
    expect($summary->json('loads.net_weight_total'))->toEqual(7.0);
});

it('skenario 4 — Admin mengganti mill: pilihan periode/line BASI ditolak server dengan 403', function () {
    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::SolidWasteDisposal->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Maret Beta')
        ->create();

    swdApiLoad(swdApiRecord($this->stationA, $this->day2), $this->day2, ['net_weight_mt' => 5.0]);

    // Langkah 1 — mill-A + periode mill-A + line mill-A: 200.
    $this->actingAs($this->admin, 'web')
        ->getJson(swdApiUrl('summary', [
            'business_unit_id' => (string) $this->businessUnitA->id,
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]))
        ->assertOk();

    // Langkah 2 — mill-B DIGANTI tetapi periode dan line masih milik mill-A:
    // 403. Membuktikan pilihan basi memang DITOLAK SERVER, bukan hanya
    // dikosongkan di UI. Ditolak di resolveProductionLine(), sebelum satu kueri
    // record pun berjalan.
    $stale = $this->actingAs($this->admin, 'web')->getJson(swdApiUrl('summary', [
        'business_unit_id' => (string) $this->businessUnitB->id,
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $stale->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
    expect(array_keys($stale->json()))->toBe(['message', 'code']);
    // NOL angka laporan ikut terbawa pada jawaban galat.
    expect($stale->json())->not->toHaveKey('loads');
    expect($stale->json())->not->toHaveKey('daily');

    // Langkah 3 — daftar periode mengikuti mill yang BARU, dan kedua daftar
    // tidak beririsan sama sekali.
    $periods = $this->actingAs($this->admin, 'web')->getJson(swdApiUrl('periods', [
        'business_unit_id' => (string) $this->businessUnitB->id,
    ]));
    $periods->assertOk();

    expect(collect($periods->json('data'))->pluck('id')->all())->toBe([(string) $periodB->id]);
    expect(collect($periods->json('data'))->pluck('id'))->not->toContain((string) $this->periodA->id);
});

it('skenario 5 — akun terikat mill tanpa business_unit_id: 422, dan daftar mill TIDAK ditawarkan', function () {
    $supervisorNoMill = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $periods = $this->actingAs($supervisorNoMill, 'web')->getJson(swdApiUrl('periods'));

    $periods->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonValidationErrors('business_unit_id');
    // BENTUK 422: tiga kunci, `errors` di antaranya.
    expect(array_keys($periods->json()))->toBe(['message', 'code', 'errors']);

    // Berlaku pada /summary dan /export juga — bukan hanya pada pemilih.
    foreach (['summary', 'export'] as $path) {
        $this->actingAs($supervisorNoMill, 'web')
            ->getJson(swdApiUrl($path, [
                'period_id' => (string) $this->periodA->id,
                'production_line_id' => $this->lineA,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('business_unit_id');
    }

    // GAGAL TERTUTUP: /business-units/options tetap 403 baginya, jadi daftar
    // seluruh mill tidak pernah ditawarkan sebagai jalan keluar.
    $this->actingAs($supervisorNoMill, 'web')
        ->getJson(swdApiUrl('business-units/options'))
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');
});

// =====================================================================
// Skenario 6-7: prasyarat pemilihan, dan daftar periode
// =====================================================================

it('skenario 6 — Production Line belum dipilih: 422 pada summary DAN export, nol angka', function () {
    // Mill punya DUA line yang KEDUANYA bermuatan — itulah yang membuat
    // 'default ke seluruh line' menghasilkan angka, dan karena itu diam-diam
    // melebarkan cakupan laporan. Kebocoran cakupan yang sudah tiga kali
    // terjadi di proyek ini.
    $lineKedua = ProductionLine::factory()->forBusinessUnit($this->businessUnitA)->create();
    $stationKedua = Station::factory()->forProductionLine($lineKedua)->solidWasteDisposal()->create();

    swdApiLoad(swdApiRecord($this->stationA, $this->day2), $this->day2, ['net_weight_mt' => 3.0]);
    swdApiLoad(swdApiRecord($stationKedua, $this->day2), $this->day2, ['net_weight_mt' => 9.0]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
    ]));

    $summary->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonValidationErrors('production_line_id');

    // TIDAK PERNAH di-default ke 'seluruh line mill ini': payload galat tidak
    // membawa satu angka laporan pun.
    foreach (['loads', 'by_waste_type', 'daily', 'completeness', 'compliance'] as $block) {
        expect($summary->json())->not->toHaveKey($block);
    }

    // Dan ekspornya menolak dengan alasan yang SAMA — bukan berkas separuh
    // berisi header.
    $export = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'format' => 'csv',
    ]));

    $export->assertStatus(422)->assertJsonValidationErrors('production_line_id');
    expect($export->headers->get('content-disposition'))->toBeNull();

    // production_line_id yang HANYA berisi spasi diperlakukan sama dengan absen
    // — queryString() men-trim-nya menjadi null.
    $this->actingAs($this->supervisor, 'web')
        ->getJson(swdApiUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => '   ',
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('production_line_id');
});

it('period_id belum dipilih: 422 dari requirePeriodId pada summary DAN export, bukan 404', function () {
    swdApiLoad(swdApiRecord($this->stationA, $this->day2), $this->day2, ['net_weight_mt' => 3.0]);

    foreach (['summary', 'export'] as $path) {
        // Absen sama sekali.
        $absent = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl($path, [
            'production_line_id' => $this->lineA,
        ]));

        $absent->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors('period_id');

        // Hanya spasi: queryString() men-trim-nya menjadi null, jadi 422 — BUKAN
        // 404 untuk string kosong, dan BUKAN laporan kosong diam-diam.
        $this->actingAs($this->supervisor, 'web')
            ->getJson(swdApiUrl($path, [
                'period_id' => '  ',
                'production_line_id' => $this->lineA,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('period_id');

        expect($absent->json())->not->toHaveKey('loads');
    }
});

it('skenario 7 — mill belum punya periode yang mencakup Solid Waste Disposal: data [] dengan 200', function () {
    Period::query()->delete();

    // DUA periode, keduanya hanya mendaftarkan stasiun LAIN.
    foreach ([StationTypeEnum::EngineRoom->value, StationTypeEnum::ProcessWater->value] as $index => $stationType) {
        Period::factory()
            ->forBusinessUnit($this->businessUnitA)
            ->stationType($stationType)
            ->range($this->periodStart, $this->periodEnd)
            ->open()
            ->named('Periode Stasiun Lain '.$index)
            ->create();
    }

    $periods = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('periods'));

    // [] dengan HTTP 200 — pemilih kosong plus arahan ke Kelola Periode
    // Pelaporan, bukan 404.
    $periods->assertOk();
    expect($periods->json('data'))->toBe([]);
});

// =====================================================================
// Skenario 8-10, 20-21: muatan, penyebut, dan netto yang dibaca apa adanya
// =====================================================================

it('skenario 8 — periode tanpa data: kerangka penuh dengan nol dan null, bukan 404', function () {
    $empty = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::SolidWasteDisposal->value)
        ->range(now()->subDays(80)->toDateString(), now()->subDays(50)->toDateString())
        ->open()
        ->named('Periode Tanpa Data')
        ->create();

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('summary', [
        'period_id' => (string) $empty->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    // SELURUH blok TETAP diterbitkan: kerangka laporan adalah ISI-nya, bukan
    // metadata yang boleh hilang ketika angkanya nol.
    expect(array_keys($summary->json()))->toBe(swdApiSummaryKeys());

    expect($summary->json('loads.load_count'))->toBe(0);
    // NULL, BUKAN 0: nol akan mengaku-akui total terukur yang tidak ada.
    expect($summary->json('loads.net_weight_total'))->toBeNull();
    expect($summary->json('loads.net_weight_avg'))->toBeNull();
    expect($summary->json('loads.net_weight_load_count'))->toBe(0);
    expect($summary->json('loads.missing_net_weight_load_count'))->toBe(0);

    foreach (['by_waste_type', 'by_source_station', 'by_disposal_site', 'by_purpose_end_use'] as $recap) {
        expect($summary->json($recap))->toBe([]);
    }

    expect($summary->json('daily'))->toBe([]);
    expect($summary->json('daily_total.load_count'))->toBe(0);
    expect($summary->json('daily_total.net_weight_total'))->toBeNull();
    expect($summary->json('compliance.missing_gate_pass_count'))->toBe(0);
    expect($summary->json('compliance.missing_seal_count'))->toBe(0);
    expect($summary->json('completeness.days_with_load'))->toBe(0);
    expect($summary->json('completeness.days_in_period'))->toBe(31);
    expect($summary->json('completeness.percent'))->toEqual(0.0);
    expect($summary->json('text_entries.findings'))->toBe([]);
    expect($summary->json('text_entries.remarks'))->toBe([]);
});

it('skenario 9 — seluruh muatan belum selesai ditimbang: total dan rata-rata NULL, bukan 0', function () {
    $record = swdApiRecord($this->stationA, $this->day2);

    foreach (range(1, 6) as $index) {
        swdApiLoad($record, $this->day2, ['net_weight_mt' => null]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    // ENAM MUATAN TETAP ENAM MUATAN: penimbangan yang belum selesai bukan
    // alasan membuang muatannya dari hitungan.
    expect($summary->json('loads.load_count'))->toBe(6);
    expect($summary->json('loads.net_weight_load_count'))->toBe(0);
    expect($summary->json('loads.missing_net_weight_load_count'))->toBe(6);
    expect($summary->json('loads.net_weight_total'))->toBeNull();
    expect($summary->json('loads.net_weight_avg'))->toBeNull();

    // Dan baris hariannya pun null, bukan 0,00 — aturan yang sama berlaku di
    // SETIAP tingkat.
    expect($summary->json('daily'))->toHaveCount(1);
    expect($summary->json('daily.0.load_count'))->toBe(6);
    expect($summary->json('daily.0.net_weight_total'))->toBeNull();
    expect($summary->json('daily.0.net_weight_avg'))->toBeNull();
    expect($summary->json('daily.0.net_weight_load_count'))->toBe(0);
});

it('skenario 10 / 21 — penyebut rata-rata adalah muatan ber-netto terisi, dan ia DITERBITKAN', function () {
    $record = swdApiRecord($this->stationA, $this->day2);

    foreach (range(1, 6) as $index) {
        swdApiLoad($record, $this->day2, ['net_weight_mt' => 5.0]);
    }

    // Empat yang belum selesai: sebagian hanya bruto, sebagian hanya tara.
    swdApiLoad($record, $this->day2, ['net_weight_mt' => null, 'gross_weight_mt' => 12.0]);
    swdApiLoad($record, $this->day2, ['net_weight_mt' => null, 'gross_weight_mt' => 13.0]);
    swdApiLoad($record, $this->day2, ['net_weight_mt' => null, 'tare_weight_mt' => 3.0]);
    swdApiLoad($record, $this->day2, ['net_weight_mt' => null, 'tare_weight_mt' => 4.0]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    expect($summary->json('loads.load_count'))->toBe(10);
    expect($summary->json('loads.net_weight_total'))->toEqual(30.0);
    // RATA-RATA ATAS 6, BUKAN ATAS 10: 3.0 adalah cacat yang dicari.
    expect($summary->json('loads.net_weight_avg'))->toEqual(5.0);
    expect($summary->json('loads.net_weight_avg'))->not->toEqual(3.0);
    // PENYEBUTNYA, diterbitkan berdampingan dengan rata-rata yang memilikinya.
    expect($summary->json('loads.net_weight_load_count'))->toBe(6);
    expect($summary->json('loads.missing_net_weight_load_count'))->toBe(4);
});

it('skenario 20 — net_weight_mt DIBACA APA ADANYA, tidak pernah dihitung ulang dari bruto − tara', function () {
    $record = swdApiRecord($this->stationA, $this->day2);

    // Bruto DAN tara terisi, tetapi netto tersimpan BERBEDA dari selisihnya:
    // implementasi yang menghitung ulang memulangkan 7.0 dan MEMERAH di sini.
    swdApiLoad($record, $this->day2, ['gross_weight_mt' => 10.0, 'tare_weight_mt' => 3.0, 'net_weight_mt' => 5.0]);
    // Hanya bruto; nettonya tetap dibaca.
    swdApiLoad($record, $this->day2, ['gross_weight_mt' => 8.0, 'tare_weight_mt' => null, 'net_weight_mt' => 2.0]);
    // BENTUK FIXTURE E2E HARI INI: netto terisi sementara bruto DAN tara null.
    swdApiLoad($record, $this->day2, ['gross_weight_mt' => null, 'tare_weight_mt' => null, 'net_weight_mt' => 5.0]);
    // Bruto DAN tara terisi tetapi netto NULL: tetap 'belum selesai ditimbang'.
    swdApiLoad($record, $this->day2, ['gross_weight_mt' => 9.0, 'tare_weight_mt' => 2.0, 'net_weight_mt' => null]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    expect($summary->json('loads.load_count'))->toBe(4);
    expect($summary->json('loads.net_weight_load_count'))->toBe(3);
    expect($summary->json('loads.net_weight_total'))->toEqual(12.0);
    // 7+5+7 = 19 (menghitung ulang) dan 7+7 = 14 (hanya yang lengkap) sama-sama
    // bukan jawabannya.
    expect($summary->json('loads.net_weight_total'))->not->toEqual(19.0);
    expect($summary->json('loads.net_weight_total'))->not->toEqual(14.0);
    expect($summary->json('loads.net_weight_avg'))->toEqual(4.0);
    // Muatan ber-bruto DAN tara lengkap tetapi netto null TETAP terhitung
    // sebagai belum selesai.
    expect($summary->json('loads.missing_net_weight_load_count'))->toBe(1);
});

// =====================================================================
// Skenario 11-12, 22-23, 26: keempat rekap kategoris
// =====================================================================

it('skenario 11 — kolom pengelompokan kosong pada seluruh muatan: satu baris null HARFIAH', function () {
    $record = swdApiRecord($this->stationA, $this->day2);

    foreach ([null, null, '', '   '] as $value) {
        swdApiLoad($record, $this->day2, [
            'purpose_end_use' => $value,
            'solid_waste_type' => 'Fibre',
            'source_station' => 'Pressing',
            'disposal_utilization_site' => 'TPA Utara',
        ]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    $recap = $summary->json('by_purpose_end_use');

    // SATU baris, bukan dua: '' dan '   ' di-trim menjadi null di satu tempat,
    // sehingga satu keadaan tidak terbelah menjadi dua baris.
    expect($recap)->toHaveCount(1);
    expect($recap[0]['value'])->toBeNull();
    expect($recap[0]['value'])->not->toBe('');
    expect($recap[0]['load_count'])->toBe(4);
    // Dan penjumlahannya TETAP sama dengan load_count — baris null tidak pernah
    // dibuang.
    expect(collect($recap)->sum('load_count'))->toBe($summary->json('loads.load_count'));

    // Rekap LAIN tetap berisi nilainya.
    expect(collect($summary->json('by_waste_type'))->pluck('value')->all())->toBe(['Fibre']);
});

it('skenario 12 — nilai langka dan yang hanya berbeda besar-kecil huruf: tiap nilai barisnya SENDIRI', function () {
    $record = swdApiRecord($this->stationA, $this->day2);

    // 3x 'tpa utara' @2.0 = 6.0 | 2x 'TPA Utara' + 1x ' TPA Utara ' @1.0 = 3.0
    // | 1x 'Zinc Scrap' @0.5.
    foreach (range(1, 3) as $index) {
        swdApiLoad($record, $this->day2, ['disposal_utilization_site' => 'tpa utara', 'net_weight_mt' => 2.0]);
    }

    foreach (range(1, 2) as $index) {
        swdApiLoad($record, $this->day2, ['disposal_utilization_site' => 'TPA Utara', 'net_weight_mt' => 1.0]);
    }

    swdApiLoad($record, $this->day2, ['disposal_utilization_site' => ' TPA Utara ', 'net_weight_mt' => 1.0]);
    swdApiLoad($record, $this->day2, ['disposal_utilization_site' => 'Zinc Scrap', 'net_weight_mt' => 0.5]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    $recap = $summary->json('by_disposal_site');

    // TIGA baris: spasi ujung di-trim (jadi ' TPA Utara ' bergabung), tetapi
    // besar-kecil huruf TIDAK dinormalkan — gejala kolom teks bebas yang perlu
    // data master harus TERLIHAT, bukan disembunyikan.
    expect($recap)->toHaveCount(3);
    expect(collect($recap)->pluck('value')->all())->toBe(['tpa utara', 'TPA Utara', 'Zinc Scrap']);

    expect(swdApiEntry($recap, 'TPA Utara')['load_count'])->toBe(3);
    expect(swdApiEntry($recap, 'tpa utara')['load_count'])->toBe(3);
    // NILAI YANG MUNCUL SEKALI TETAP MENDAPAT BARISNYA SENDIRI — tidak pernah
    // dipangkas dan tidak pernah dilipat ke kelompok 'lainnya'.
    expect(swdApiEntry($recap, 'Zinc Scrap')['load_count'])->toBe(1);
    $this->assertNotContains('Lainnya', collect($recap)->pluck('value')->all());

    expect(collect($recap)->sum('load_count'))->toBe($summary->json('loads.load_count'));
});

it('skenario 22 / 23 — rekap tujuan penggunaan akhir PER NILAI apa adanya, bukan dua ember', function () {
    $record = swdApiRecord($this->stationA, $this->day2);

    // Empat nilai teks bebas yang belum pernah ada di master mana pun.
    foreach ([
        'Landfill' => 8.0,
        'Co-firing Boiler' => 6.0,
        'Dijual ke pihak ketiga' => 4.0,
        'Kompos' => 2.0,
    ] as $value => $weight) {
        swdApiLoad($record, $this->day2, ['purpose_end_use' => $value, 'net_weight_mt' => $weight]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    $recap = $summary->json('by_purpose_end_use');

    // EMPAT baris, bukan dua: 'dibuang vs dimanfaatkan' adalah kontradiksi spec
    // yang sudah diselesaikan — memetakannya berarti mengarang pemetaan yang
    // tidak pernah ada di basis data.
    expect($recap)->toHaveCount(4);
    expect($recap)->not->toHaveCount(2);
    expect(collect($recap)->pluck('value')->all())
        ->toBe(['Landfill', 'Co-firing Boiler', 'Dijual ke pihak ketiga', 'Kompos']);

    // Setiap baris membawa angka totalnya BERDAMPINGAN dengan penyebutnya —
    // lima kunci, tidak kurang.
    foreach ($recap as $row) {
        expect(array_keys($row))
            ->toBe(['value', 'load_count', 'net_weight_total', 'net_weight_load_count', 'net_weight_avg']);
    }

    expect(collect($recap)->sum('load_count'))->toBe($summary->json('loads.load_count'));
    expect(collect($recap)->sum('net_weight_total'))->toEqual($summary->json('loads.net_weight_total'));

    // Dan tidak ada satu kunci pun yang mengubahnya menjadi dua ember.
    $keys = swdApiAllKeys($summary->json());

    foreach (['disposed', 'utilized', 'disposed_count', 'utilized_count', 'is_utilized', 'buckets'] as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
    }
});

it('skenario 26 — keempat rekap: penjumlahan menjumlah, urutan menurun, baris null di posisinya', function () {
    $record = swdApiRecord($this->stationA, $this->day2);

    $columns = ['solid_waste_type', 'source_station', 'disposal_utilization_site', 'purpose_end_use'];

    // KELOMPOK NULL DISEMAI BERTONASE PALING BESAR (3 x 10.0 = 30.0), supaya
    // POSISI barisnya diuji dan bukan kebetulan: ia harus PALING ATAS karena
    // urutannya menurun atas tonase, BUKAN karena null selalu terakhir.
    foreach (range(1, 3) as $index) {
        swdApiLoad($record, $this->day2, array_merge(
            array_fill_keys($columns, null),
            ['net_weight_mt' => 10.0],
        ));
    }

    swdApiLoad($record, $this->day2, array_merge(array_fill_keys($columns, 'Alpha'), ['net_weight_mt' => 5.0]));
    swdApiLoad($record, $this->day2, array_merge(array_fill_keys($columns, 'Alpha'), ['net_weight_mt' => 1.0]));
    swdApiLoad($record, $this->day2, array_merge(array_fill_keys($columns, 'Beta'), ['net_weight_mt' => 4.0]));

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    expect($summary->json('loads.load_count'))->toBe(6);

    foreach (['by_waste_type', 'by_source_station', 'by_disposal_site', 'by_purpose_end_use'] as $recap) {
        $rows = $summary->json($recap);

        expect($rows)->toHaveCount(3);
        // Urut MENURUN atas tonase: null (30.0), Alpha (6.0), Beta (4.0).
        expect(collect($rows)->pluck('value')->all())->toBe([null, 'Alpha', 'Beta']);
        expect(swdApiNullIndex($rows))->toBe(0);
        expect(swdApiEntry($rows, null)['load_count'])->toBe(3);
        expect(swdApiEntry($rows, 'Alpha')['load_count'])->toBe(2);
        expect(swdApiEntry($rows, 'Alpha')['net_weight_total'])->toEqual(6.0);
        expect(swdApiEntry($rows, 'Alpha')['net_weight_avg'])->toEqual(3.0);
        // SATU DEFINISI, SATU JAWABAN — dan asersi inilah yang menangkap baris
        // null yang dibuang.
        expect(collect($rows)->sum('load_count'))->toBe($summary->json('loads.load_count'));
    }
});

// =====================================================================
// Skenario 13-14, 25: jejak kepatuhan dan dua daftar teks bebas
// =====================================================================

it('skenario 13 / 25 — jejak kepatuhan: dua penghitung kehadiran, nol ukuran, nomornya tidak diterbitkan', function () {
    $record = swdApiRecord($this->stationA, $this->day2);

    // gate_pass_no: 3 null + 2 '' + 1 '  ' + 2 terisi → 6 hilang.
    // security_seal_no: 5 null + 3 terisi → 5 hilang.
    $gatePasses = [null, null, null, '', '', '  ', 'GP-UNIK-9001', 'GP-UNIK-9002'];
    $seals = [null, null, null, null, null, 'SS-UNIK-7001', 'SS-UNIK-7002', 'SS-UNIK-7003'];

    foreach ($gatePasses as $index => $gatePass) {
        swdApiLoad($record, $this->day2, [
            'gate_pass_no' => $gatePass,
            'security_seal_no' => $seals[$index],
        ]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    expect($summary->json('loads.load_count'))->toBe(8);
    expect($summary->json('compliance.missing_gate_pass_count'))->toBe(6);
    expect($summary->json('compliance.missing_seal_count'))->toBe(5);

    // TEPAT TIGA KUNCI: dua penghitung dan prosanya. Tidak ada rata-rata, tidak
    // ada urutan, tidak ada grafik — sebuah nomor segel tidak punya besaran.
    expect(array_keys($summary->json('compliance')))
        ->toBe(['missing_gate_pass_count', 'missing_seal_count', '_note']);

    foreach (['avg', 'min', 'max', 'sum', 'total', 'sorted', 'series', 'numbers'] as $forbidden) {
        $this->assertNotContains(
            $forbidden,
            swdApiAllKeys($summary->json('compliance')),
            "blok compliance tidak boleh punya kunci {$forbidden}",
        );
    }

    // NOMOR-NOMORNYA SENDIRI TIDAK DITERBITKAN DI PAYLOAD; ia hanya ada di
    // ekspor. Diasersikan atas token yang kami semai sendiri — bukan atas prosa.
    $body = (string) $summary->getContent();

    $this->assertStringNotContainsString('GP-UNIK-9001', $body);
    $this->assertStringNotContainsString('SS-UNIK-7001', $body);

    // PERIODE YANG LENGKAP SELURUHNYA: nol dan nol, dan nol itu TETAP
    // diterbitkan — nol itulah yang memberi tahu pembaca pemeriksaannya berjalan.
    $periodLengkap = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::SolidWasteDisposal->value)
        ->range(now()->subDays(80)->toDateString(), now()->subDays(50)->toDateString())
        ->open()
        ->named('Periode Kepatuhan Lengkap')
        ->create();

    $recordLengkap = swdApiRecord($this->stationA, now()->subDays(70)->toDateString());

    foreach (range(1, 8) as $index) {
        swdApiLoad($recordLengkap, now()->subDays(70)->toDateString(), [
            'gate_pass_no' => 'GP-LENGKAP-'.$index,
            'security_seal_no' => 'SS-LENGKAP-'.$index,
        ]);
    }

    $lengkap = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('summary', [
        'period_id' => (string) $periodLengkap->id,
        'production_line_id' => $this->lineA,
    ]));

    $lengkap->assertOk();
    expect($lengkap->json('loads.load_count'))->toBe(8);
    expect($lengkap->json('compliance'))->toHaveKey('missing_gate_pass_count');
    expect($lengkap->json('compliance.missing_gate_pass_count'))->toBe(0);
    expect($lengkap->json('compliance.missing_seal_count'))->toBe(0);
});

it('skenario 14 — temuan dan catatan: DUA daftar terpisah, kosong maupun terisi keduanya', function () {
    // Periode-1 — findings dan remarks kosong/whitespace seluruhnya.
    $kosong = swdApiRecord($this->stationA, $this->day1);

    foreach ([[null, null], ['', '   '], ['   ', '']] as [$findings, $remarks]) {
        swdApiLoad($kosong, $this->day1, ['findings' => $findings, 'remarks' => $remarks]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    // DUA kunci terpisah yang TETAP ada meski keduanya kosong — layar harus
    // dapat MENYATAKAN keadaannya, bukan merender container yang hilang.
    expect(array_keys($summary->json('text_entries')))->toBe(['findings', 'remarks']);
    expect($summary->json('text_entries.findings'))->toBe([]);
    expect($summary->json('text_entries.remarks'))->toBe([]);

    // Periode-2 — satu muatan mengisi findings DAN remarks sekaligus.
    $periodeDua = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::SolidWasteDisposal->value)
        ->range(now()->subDays(80)->toDateString(), now()->subDays(50)->toDateString())
        ->open()
        ->named('Periode Teks Bebas')
        ->create();

    $tanggal = now()->subDays(70)->toDateString();
    $record = swdApiRecord($this->stationA, $tanggal);

    swdApiLoad($record, $tanggal, [
        'findings' => 'Ceceran fibre di jalur keluar',
        'remarks' => 'Disapu ulang sebelum gerbang',
    ]);

    $kedua = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('summary', [
        'period_id' => (string) $periodeDua->id,
        'production_line_id' => $this->lineA,
    ]));

    $kedua->assertOk();

    // MUNCUL SEKALI DI MASING-MASING daftar — bukan penghitungan ganda, dan
    // bukan satu daftar gabungan yang justru menampilkannya dua kali.
    expect($kedua->json('text_entries.findings'))->toHaveCount(1);
    expect($kedua->json('text_entries.remarks'))->toHaveCount(1);
    expect($kedua->json('text_entries.findings.0'))
        ->toBe(['date' => $tanggal, 'value' => 'Ceceran fibre di jalur keluar']);
    expect($kedua->json('text_entries.remarks.0'))
        ->toBe(['date' => $tanggal, 'value' => 'Disapu ulang sebelum gerbang']);
    expect($kedua->json('loads.load_count'))->toBe(1);

    foreach (['text_entries_combined', 'notes', 'all_texts', 'entries'] as $forbidden) {
        $this->assertNotContains($forbidden, swdApiAllKeys($kedua->json('text_entries')));
    }
});

// =====================================================================
// Skenario 15-19, 24, 28: lingkup, kelengkapan, draft, rekap harian
// =====================================================================

it('skenario 15 — line punya lebih dari satu unit Solid Waste Disposal: digabung TEPAT SEKALI', function () {
    $unitKedua = Station::factory()
        ->forProductionLine($this->stationA->production_line_id)
        ->solidWasteDisposal()
        ->create();

    $r1 = swdApiRecord($this->stationA, $this->day1);

    foreach (range(1, 3) as $index) {
        swdApiLoad($r1, $this->day1, ['net_weight_mt' => 2.0]);
    }

    $r2 = swdApiRecord($unitKedua, $this->day2);

    foreach (range(1, 2) as $index) {
        swdApiLoad($r2, $this->day2, ['net_weight_mt' => 3.0]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    // 5 — bukan 3 (unit terlewat) dan bukan 10 (join ganda).
    expect($summary->json('loads.load_count'))->toBe(5);
    expect($summary->json('loads.load_count'))->not->toBe(3);
    expect($summary->json('loads.load_count'))->not->toBe(10);
    expect($summary->json('loads.net_weight_total'))->toEqual(12.0);

    // NOL pengelompokan per unit: pembaca membaca SATU angka per line.
    $keys = swdApiAllKeys($summary->json());

    foreach (['by_unit', 'units', 'by_station', 'stations'] as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
    }
});

it('skenario 16 — periode masih berjalan atau belum mulai: days_counted berhenti di hari ini', function () {
    // BERJALAN: mulai 9 hari lalu, berakhir 20 hari lagi → 30 hari periode,
    // 10 hari yang sudah lewat.
    $berjalan = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::SolidWasteDisposal->value)
        ->range(now()->subDays(9)->toDateString(), now()->addDays(20)->toDateString())
        ->open()
        ->named('Periode Berjalan')
        ->create();

    foreach ([8, 7, 6] as $offset) {
        $tanggal = now()->subDays($offset)->toDateString();
        swdApiLoad(swdApiRecord($this->stationA, $tanggal), $tanggal, ['net_weight_mt' => 2.0]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('summary', [
        'period_id' => (string) $berjalan->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    expect($summary->json('completeness.days_in_period'))->toBe(30);
    // BERHENTI DI HARI INI: menghukum periode berjalan atas hari yang belum
    // datang akan memberi 10%, bukan 30%.
    expect($summary->json('completeness.days_counted'))->toBe(10);
    expect($summary->json('completeness.days_with_load'))->toBe(3);
    expect($summary->json('completeness.percent'))->toEqual(30.0);
    expect($summary->json('completeness.percent'))->not->toEqual(10.0);

    // BELUM MULAI: percent NULL — bukan 0.0 — dan layar mencetak '—'. Nol persen
    // berarti ada hari yang diharapkan dan tak satu pun terisi.
    $belumMulai = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::SolidWasteDisposal->value)
        ->range(now()->addDays(40)->toDateString(), now()->addDays(60)->toDateString())
        ->open()
        ->named('Periode Belum Mulai')
        ->create();

    $belum = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('summary', [
        'period_id' => (string) $belumMulai->id,
        'production_line_id' => $this->lineA,
    ]));

    $belum->assertOk();

    expect($belum->json('completeness.days_counted'))->toBe(0);
    expect($belum->json('completeness.days_with_load'))->toBe(0);
    expect($belum->json('completeness.percent'))->toBeNull();
    expect($belum->json('daily'))->toBe([]);
});

it('skenario 17 — periode TERTUTUP: tetap terdaftar, tetap dapat dibaca, tetap dapat diekspor', function () {
    $closed = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::SolidWasteDisposal->value)
        ->range(now()->subDays(80)->toDateString(), now()->subDays(50)->toDateString())
        ->closed($this->admin)
        ->named('Periode Tertutup')
        ->create();

    $tanggal = now()->subDays(70)->toDateString();
    $record = swdApiRecord($this->stationA, $tanggal);
    swdApiLoad($record, $tanggal, ['net_weight_mt' => 6.0, 'solid_waste_type' => 'Ash']);

    // Langkah 1 — STATUS TIDAK PERNAH MENYARING daftar periode: kunci periode
    // mengatur PENULISAN data, bukan pembacaan laporan.
    $periods = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('periods'));
    $periods->assertOk();
    expect(collect($periods->json('data'))->pluck('id'))->toContain((string) $closed->id);
    expect(collect($periods->json('data'))->firstWhere('id', (string) $closed->id)['status'])->toBe('closed');

    // Langkah 2 — summary 200, PENUH.
    $summary = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('summary', [
        'period_id' => (string) $closed->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    expect($summary->json('period.status'))->toBe('closed');
    expect($summary->json('loads.load_count'))->toBe(1);
    expect($summary->json('loads.net_weight_total'))->toEqual(6.0);

    // Langkah 3 — export 200, persis seperti periode terbuka.
    $export = $this->actingAs($this->supervisor, 'web')->get(swdApiUrl('export', [
        'period_id' => (string) $closed->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));

    $export->assertOk();

    $lines = swdApiCsvLines(swdApiStreamed($export->baseResponse));

    expect($lines)->toHaveCount(2);
    expect(swdApiCsvRow($lines[1])[0])->toBe('Periode Tertutup');
});

it('skenario 18 / 28 — muatan draft IKUT terhitung, jumlahnya dinyatakan, dan ia ada di ekspor', function () {
    $draft = swdApiRecord($this->stationA, $this->day1, ['status' => RecordStatus::DraftOngoing]);

    foreach (range(1, 3) as $index) {
        swdApiLoad($draft, $this->day1, ['net_weight_mt' => 2.0]);
    }

    $final = swdApiRecord($this->stationA, $this->day2, ['status' => RecordStatus::Saved]);

    foreach (range(1, 5) as $index) {
        swdApiLoad($final, $this->day2, ['net_weight_mt' => 4.0]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    // DRAFT IKUT DI SELURUH ANGKA; draft_load_count adalah PERNYATAANNYA,
    // bukan penyaringnya.
    expect($summary->json('loads.load_count'))->toBe(8);
    expect($summary->json('loads.net_weight_total'))->toEqual(26.0);
    expect($summary->json('draft_load_count'))->toBe(3);

    // Dan ekspornya membawa kedelapan baris beserta kolom statusnya.
    $export = $this->actingAs($this->supervisor, 'web')->get(swdApiUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));

    $export->assertOk();

    $lines = swdApiCsvLines(swdApiStreamed($export->baseResponse));

    expect($lines)->toHaveCount(9);

    $statuses = [];

    foreach (array_slice($lines, 1) as $line) {
        $statuses[] = swdApiCsvRow($line)[4];
    }

    // LABEL INDONESIA lewat ExportValue::status() — ini status RECORD, dan
    // status()-lah helper-nya.
    expect(array_count_values($statuses))->toBe(['Draft' => 3, 'Tersimpan' => 5]);
});

it('skenario 19 — rekap harian panjang: satu baris per HARI BERMUATAN, urut naik, kakinya menjumlah', function () {
    // Sembilan hari bermuatan dari 31 — tanggalnya DIPAKU satu per satu.
    $offsets = range(39, 31);

    foreach ($offsets as $offset) {
        $tanggal = now()->subDays($offset)->toDateString();
        swdApiLoad(swdApiRecord($this->stationA, $tanggal), $tanggal, ['net_weight_mt' => 3.0]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    $daily = $summary->json('daily');

    // SEMBILAN baris, bukan 31: hari tanpa pengangkutan sengaja TIDAK dibuatkan
    // baris nol — mencetak 22 baris nol akan mengubur sembilan yang berarti.
    expect($daily)->toHaveCount(9);
    expect($daily)->not->toHaveCount(31);

    // range(39, 31) MENURUN, jadi offset terbesar adalah tanggal TERAWAL:
    // daftar ini sudah urut naik tanpa dibalik.
    $expectedDates = array_map(fn ($offset) => now()->subDays($offset)->toDateString(), $offsets);

    // URUT NAIK, dinyatakan ksort() — bukan diwarisi dari urutan kueri, yang
    // tidak dijanjikan PostgreSQL.
    expect(collect($daily)->pluck('date')->all())->toBe($expectedDates);

    foreach ($daily as $row) {
        expect(array_keys($row))
            ->toBe(['date', 'load_count', 'net_weight_total', 'net_weight_load_count', 'net_weight_avg']);
    }

    // KAKINYA DIJUMLAHKAN dari daily[], bukan disalin dari blok loads.
    expect($summary->json('daily_total.load_count'))->toBe(9);
    expect($summary->json('daily_total.net_weight_total'))->toEqual(27.0);
    expect($summary->json('daily_total.net_weight_load_count'))->toBe(9);
    expect($summary->json('daily_total.net_weight_avg'))->toEqual(3.0);
    expect($summary->json('daily_total'))->toHaveKey('_note');

    expect($summary->json('completeness.days_with_load'))->toBe(9);
    expect($summary->json('completeness.days_counted'))->toBe(31);
    expect($summary->json('completeness.percent'))->toEqual(29.0);

    // SATU DEFINISI, SATU JAWABAN.
    expect(collect($daily)->sum('load_count'))
        ->toBe($summary->json('loads.load_count') - $summary->json('undated_load_count'));
});

it('skenario 24 — sebaran per jam TIDAK ADA, dan ketiadaannya dinyatakan UTUH', function () {
    foreach ([$this->day1, $this->day2, $this->day3] as $tanggal) {
        $record = swdApiRecord($this->stationA, $tanggal);

        foreach (range(1, 4) as $index) {
            swdApiLoad($record, $tanggal, ['net_weight_mt' => 1.5, 'shift' => 'Shift '.$index]);
        }
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    expect($summary->json('loads.load_count'))->toBe(12);

    // DIASERSIKAN ATAS NAMA KUNCI: `_note_no_hourly` adalah kalimat manusia
    // yang MEMUAT kata 'jam', jadi memindai teks respons akan cocok dengan
    // kalimat yang justru MENYATAKAN ketiadaannya.
    $keys = swdApiAllKeys($summary->json());

    foreach ([
        'hourly', 'hourly_distribution', 'by_hour', 'per_hour', 'hour', 'hours',
        'busiest_hour', 'empty_hour_count', 'time_slot', 'time_slots', 'slots',
    ] as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
    }

    // SATU-SATUNYA sumbu waktu adalah TANGGAL.
    expect($summary->json('daily'))->toHaveCount(3);
    expect(array_keys($summary->json('daily.0')))->toContain('date');

    // Dan prosanya diterbitkan UTUH, bukan dipotong.
    expect($summary->json('_note_no_hourly'))->toBe(SolidWasteDisposalReportService::NOTE_NO_HOURLY);
    $this->assertStringContainsString('BUKAN fitur yang ditunda', $summary->json('_note_no_hourly'));
});

it('undated_load_count SELALU diterbitkan, dan terhadap skema yang sebenarnya ia 0', function () {
    $record = swdApiRecord($this->stationA, $this->day2);

    foreach (range(1, 3) as $index) {
        swdApiLoad($record, $this->day2, ['net_weight_mt' => 2.0]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    // KUNCINYA SELALU ADA — blade-nya yang hanya merender kotak keterangan
    // ketika angkanya > 0, dan itu dibuktikan di berkas Livewire. `event_date`
    // NOT NULL di skema (migrasi 2026_08_31_000003), jadi 0 di sini adalah
    // jawaban yang BENAR, bukan jalur yang belum teruji: penolakan skemanya
    // dipaku di tests/Unit/Services/SolidWasteDisposalReportServiceTest.php
    // (case 56), dan cabang agregasinya di case 32/33 lewat koleksi di memori.
    expect($summary->json())->toHaveKey('undated_load_count');
    expect($summary->json('undated_load_count'))->toBe(0);

    // Dan karena itu penjumlahan daily[] SAMA PERSIS dengan load_count.
    expect(collect($summary->json('daily'))->sum('load_count'))->toBe($summary->json('loads.load_count'));
    expect($summary->json('daily_total.load_count'))->toBe($summary->json('loads.load_count'));
});

it('rentang periode inklusif di kedua ujung, pada summary DAN pada ekspor', function () {
    swdApiLoad(swdApiRecord($this->stationA, $this->periodStart), $this->periodStart, ['net_weight_mt' => 1.0]);
    swdApiLoad(swdApiRecord($this->stationA, $this->periodEnd), $this->periodEnd, ['net_weight_mt' => 2.0]);
    swdApiLoad(
        swdApiRecord($this->stationA, $this->dayBeforePeriod),
        $this->dayBeforePeriod,
        ['net_weight_mt' => 999.0],
    );
    swdApiLoad(
        swdApiRecord($this->stationA, $this->dayAfterPeriod),
        $this->dayAfterPeriod,
        ['net_weight_mt' => 888.0],
    );

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    expect($summary->json('loads.load_count'))->toBe(2);
    expect($summary->json('loads.net_weight_total'))->toEqual(3.0);
    expect(collect($summary->json('daily'))->pluck('date')->all())->toBe([$this->periodStart, $this->periodEnd]);

    $export = $this->actingAs($this->supervisor, 'web')->get(swdApiUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));

    $export->assertOk();

    // Header + dua baris muatan, dan tidak lebih.
    expect(swdApiCsvLines(swdApiStreamed($export->baseResponse)))->toHaveCount(3);
});

// =====================================================================
// Skenario 27 + ekspor: bentuk berkas, header, format
// =====================================================================

it('skenario 27 — kolom shift ABSEN dari payload tetapi ADA di ekspor csv DAN xlsx', function () {
    $record = swdApiRecord($this->stationA, $this->day2);

    // `shift` TERISI di basis data, justru supaya ketiadaannya di payload tidak
    // lolos hanya karena kolomnya kosong.
    swdApiLoad($record, $this->day2, [
        'shift' => 'Shift 2',
        'operator_id' => 'OP-077',
        'gate_pass_no' => 'GP-5150',
        'security_seal_no' => 'SS-5150',
        'solid_waste_type' => 'Shell',
        'net_weight_mt' => 4.0,
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    // ABSEN DARI PAYLOAD — atas NAMA KUNCI, bukan atas prosa:
    // `_note_shift_not_published` membahas ketiadaan dimensi giliran kerja
    // dengan kalimat manusia yang memuat kata 'shift'.
    $keys = swdApiAllKeys($summary->json());

    foreach (['shift', 'shifts', 'by_shift', 'per_shift', 'shift_distribution'] as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
    }

    expect($summary->json('_note_shift_not_published'))
        ->toBe(SolidWasteDisposalReportService::NOTE_SHIFT_NOT_PUBLISHED);

    // ADA DI EKSPOR, karena ekspor adalah rincian MENTAH.
    $csv = $this->actingAs($this->supervisor, 'web')->get(swdApiUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));

    $csv->assertOk();

    $lines = swdApiCsvLines(swdApiStreamed($csv->baseResponse));
    $header = swdApiCsvRow($lines[0]);
    $row = swdApiCsvRow($lines[1]);

    expect($header)->toBe(SolidWasteDisposalReportService::EXPORT_HEADER);
    // 5 kolom konteks + KETUJUH BELAS kolom detail.
    expect($header)->toHaveCount(22);
    expect(SolidWasteDisposalReportService::DETAIL_EXPORT_FIELDS)->toHaveCount(17);
    expect(count($header))->toBe(SolidWasteDisposalReportService::EXPORT_CONTEXT_COLUMN_COUNT + 17);

    // BARIS HEADER MEMUAT 'Shift' — pada posisinya, bukan di mana saja.
    $this->assertContains('Shift', $header);
    expect($header[6])->toBe('Shift');
    expect($row[6])->toBe('Shift 2');

    // Dan ketiga kolom yang tidak punya satu pembaca pun di layar TETAP ada —
    // justru karena itu ketiganya yang paling mudah tertinggal.
    foreach (['Weighbridge Ticket No', 'Vehicle No', 'Driver Name', 'Gate Pass No', 'Security Seal No', 'Operator ID'] as $label) {
        $this->assertContains($label, $header);
    }

    // POSISINYA DITURUNKAN dari kedua konstanta, tidak diingat: 5 kolom
    // konteks + posisi kolom itu di dalam DETAIL_EXPORT_FIELDS.
    $kolom = fn (string $field) => SolidWasteDisposalReportService::EXPORT_CONTEXT_COLUMN_COUNT
        + (int) array_search($field, SolidWasteDisposalReportService::DETAIL_EXPORT_FIELDS, true);

    expect($row[$kolom('gate_pass_no')])->toBe('GP-5150');
    expect($row[$kolom('security_seal_no')])->toBe('SS-5150');
    expect($row[$kolom('operator_id')])->toBe('OP-077');
    expect($row[$kolom('net_weight_mt')])->toEqual(4.0);
    // Dan 'Shift' duduk di posisi yang sama pada HEADER-nya.
    expect($header[$kolom('shift')])->toBe('Shift');
    expect($row[$kolom('shift')])->toBe('Shift 2');

    // xlsx: berkas .xlsx SUNGGUHAN lewat SheetWriter, bukan CSV bernama .xlsx.
    $xlsx = $this->actingAs($this->supervisor, 'web')->get(swdApiUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'xlsx',
    ]));

    $xlsx->assertOk();
    expect(substr(swdApiStreamed($xlsx->baseResponse), 0, 2))->toBe('PK');
});

it('ekspor — Content-Type dan Content-Disposition untuk csv DAN xlsx', function () {
    $record = swdApiRecord($this->stationA, $this->day2);
    swdApiLoad($record, $this->day2, ['net_weight_mt' => 4.0, 'shift' => 'Shift 1']);

    $csv = $this->actingAs($this->supervisor, 'web')->get(swdApiUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));

    $csv->assertOk();
    $this->assertStringContainsString('text/csv', (string) $csv->headers->get('content-type'));

    $csvDisposition = (string) $csv->headers->get('content-disposition');

    $this->assertStringContainsString('attachment', $csvDisposition);
    $this->assertStringContainsString('laporan-solid-waste-disposal_periode-maret-alpha_', $csvDisposition);
    $this->assertStringContainsString('.csv', $csvDisposition);
    $this->assertStringNotContainsString('.xlsx', $csvDisposition);

    $xlsx = $this->actingAs($this->supervisor, 'web')->get(swdApiUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'xlsx',
    ]));

    $xlsx->assertOk();
    expect($xlsx->headers->get('content-type'))->toBe(SheetWriter::XLSX_CONTENT_TYPE);

    $xlsxDisposition = (string) $xlsx->headers->get('content-disposition');

    $this->assertStringContainsString('laporan-solid-waste-disposal_periode-maret-alpha_', $xlsxDisposition);
    $this->assertStringContainsString('.xlsx', $xlsxDisposition);

    // BARIS HEADER-NYA, dan ia memuat 'Shift'.
    $header = swdApiCsvRow(swdApiCsvLines(swdApiStreamed($csv->baseResponse))[0]);

    expect($header)->toBe(SolidWasteDisposalReportService::EXPORT_HEADER);
    $this->assertContains('Shift', $header);
});

it('format — TRIM + DEFAULT ditetapkan controller, dan hanya terlihat dari HTTP', function () {
    $record = swdApiRecord($this->stationA, $this->day2);
    swdApiLoad($record, $this->day2, ['net_weight_mt' => 4.0]);

    $base = [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ];

    // '' , '  ' dan ' csv ' SELURUHNYA menjadi csv: queryString() memulangkan
    // null untuk nilai yang kosong setelah trim, dan export() men-default 'csv'
    // ATAS NILAI YANG SUDAH DI-TRIM itu. Di tingkat SERVICE ketiganya justru
    // 422 terhadap daftar tertutup {csv,xlsx} — jadi separuh perilaku ini tidak
    // dapat dilihat dari uji unit sama sekali.
    foreach (['' => 'kosong', '  ' => 'hanya spasi', ' csv ' => 'csv berspasi ujung'] as $format => $label) {
        $response = $this->actingAs($this->supervisor, 'web')
            ->get(swdApiUrl('export', $base + ['format' => $format]));

        $response->assertOk();
        $this->assertStringContainsString(
            'text/csv',
            (string) $response->headers->get('content-type'),
            "format {$label} seharusnya jatuh ke csv",
        );
        $this->assertStringContainsString('.csv', (string) $response->headers->get('content-disposition'));

        // Dan ia benar-benar mengalirkan CSV, bukan hanya berjanji lewat header.
        $lines = swdApiCsvLines(swdApiStreamed($response->baseResponse));

        expect($lines)->toHaveCount(2);
        expect(swdApiCsvRow($lines[0]))->toBe(SolidWasteDisposalReportService::EXPORT_HEADER);
    }

    // Parameter format yang TIDAK DIKIRIM SAMA SEKALI: default yang sama.
    $tanpaFormat = $this->actingAs($this->supervisor, 'web')->get(swdApiUrl('export', $base));
    $tanpaFormat->assertOk();
    $this->assertStringContainsString('text/csv', (string) $tanpaFormat->headers->get('content-type'));

    // 'CSV' KAPITAL: daftarnya tertutup DAN peka besar-kecil huruf — trim bukan
    // normalisasi. 422, bukan 200.
    $kapital = $this->actingAs($this->supervisor, 'web')
        ->getJson(swdApiUrl('export', $base + ['format' => 'CSV']));

    $kapital->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonValidationErrors('format');
    expect($kapital->headers->get('content-disposition'))->toBeNull();

    // 'pdf': 422 SEBELUM satu byte pun dialirkan.
    $pdf = $this->actingAs($this->supervisor, 'web')
        ->getJson(swdApiUrl('export', $base + ['format' => 'pdf']));

    $pdf->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonValidationErrors('format');
    expect($pdf->headers->get('content-disposition'))->toBeNull();

    // 'excel' — yang dipakai keenam belas laporan saudaranya — BUKAN format
    // layar ini: daftarnya {csv, xlsx}, dan toolbar web yang menerjemahkannya
    // ada di komponen Livewire, bukan di API.
    $this->actingAs($this->supervisor, 'web')
        ->getJson(swdApiUrl('export', $base + ['format' => 'excel']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('format');

    // ─────────────────────────────────────────────────────────────────
    // BUKTI BAHWA KETIGA 200 DI ATAS TIDAK VAKUM
    // ─────────────────────────────────────────────────────────────────
    // Bila nilai mentahnya sampai UTUH ke service, daftar tertutup {csv,xlsx}
    // menolak ketiganya dengan 422. Jadi 200 di atas tidak mungkin datang dari
    // service yang longgar — ia HANYA dapat datang dari trim + default di
    // SolidWasteDisposalReportController::queryString()/export(). Tanpa blok
    // ini, ketiga asersi 200 itu akan tetap hijau terhadap implementasi yang
    // menerima ' csv ' di mana saja.
    $service = app(SolidWasteDisposalReportService::class);

    $this->actingAs($this->supervisor, 'web');

    foreach ([' csv ', '', '  ', 'CSV'] as $raw) {
        $thrown = null;

        try {
            $service->export($this->periodA, $raw, null, $this->lineA);
        } catch (Throwable $exception) {
            $thrown = $exception;
        }

        $this->assertInstanceOf(
            ValidationException::class,
            $thrown,
            "service seharusnya menolak format mentah '{$raw}' dengan 422",
        );
        expect($thrown->errors())->toHaveKey('format');
    }
});

it('ekspor — batas baris terlampaui adalah 422 SEBELUM aliran dimulai', function () {
    // Batasnya diturunkan ke 5 lewat subclass yang DIIKAT KE CONTAINER, jadi
    // controller yang sama persis me-resolve-nya. Mungkin hanya karena service
    // membaca batasnya lewat `static::`, bukan `self::`.
    $this->app->bind(
        SolidWasteDisposalReportService::class,
        SolidWasteDisposalReportApiTinyExportService::class,
    );

    // ENAM MUATAN DI DALAM SATU RECORD INDUK: implementasi yang menghitung
    // RECORD melihat 1 dan meloloskan berkasnya.
    $record = swdApiRecord($this->stationA, $this->day2);

    foreach (range(1, 6) as $index) {
        swdApiLoad($record, $this->day2, ['net_weight_mt' => 1.0]);
    }

    $response = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));

    $response->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonValidationErrors('period_id');

    // SEBELUM ALIRAN DIMULAI, diasersikan atas BENTUK RESPONS-nya: sebuah
    // JsonResponse tanpa Content-Disposition. Galat setelah header terkirim
    // mendarat sebagai berkas rusak, bukan sebagai pesan.
    expect($response->headers->get('content-disposition'))->toBeNull();
    $this->assertStringContainsString('application/json', (string) $response->headers->get('content-type'));
    $this->assertStringNotContainsString('Weighbridge Ticket No', (string) $response->getContent());

    // Pesannya MENYEBUT angka batasnya dan menyarankan mempersempit periode.
    $this->assertStringContainsString('Persempit', $response->json('errors.period_id.0'));

    // Dan TEPAT pada batasnya masih terekspor — batasnya inklusif, diuji pada
    // KEDUA sisinya.
    SolidWasteDisposalDetail::query()->whereKey(
        SolidWasteDisposalDetail::query()->orderBy('id')->value('id'),
    )->delete();

    $pasBatas = $this->actingAs($this->supervisor, 'web')->get(swdApiUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));

    $pasBatas->assertOk();
    expect(swdApiCsvLines(swdApiStreamed($pasBatas->baseResponse)))->toHaveCount(6);
});

// =====================================================================
// Penjaga lintas mill, dan bentuk ketiga jawaban galatnya
// =====================================================================

it('business_unit_id kiriman DIABAIKAN bagi peran terikat mill — 200 berisi mill akun, SENGAJA bukan 403', function () {
    swdApiLoad(swdApiRecord($this->stationA, $this->day2), $this->day2, [
        'net_weight_mt' => 3.0,
        'solid_waste_type' => 'Fibre',
    ]);
    swdApiLoad(swdApiRecord($this->stationB, $this->day2), $this->day2, [
        'net_weight_mt' => 999.0,
        'solid_waste_type' => 'Milik Mill Beta',
    ]);

    foreach ([$this->supervisor, $this->millManagement, $this->operator] as $user) {
        $response = $this->actingAs($user, 'web')->getJson(swdApiUrl('summary', [
            'business_unit_id' => (string) $this->businessUnitB->id,
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]));

        // 200 BERISI ANGKA MILL SENDIRI: 403 justru akan MENEGASKAN mill lain
        // itu ada, dan membandingkan lalu menolak adalah oracle lintas mill.
        $response->assertOk();
        expect($response->json('business_unit.id'))->toBe((string) $this->businessUnitA->id);
        expect($response->json('business_unit.name'))->toBe('Mill Alpha');
        expect($response->json('loads.net_weight_total'))->toEqual(3.0);
        expect(collect($response->json('by_waste_type'))->pluck('value')->all())->toBe(['Fibre']);
    }

    // Dan ekspornya pun tidak pernah memuat mill lain.
    $export = $this->actingAs($this->supervisor, 'web')->get(swdApiUrl('export', [
        'business_unit_id' => (string) $this->businessUnitB->id,
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));

    $export->assertOk();

    $csv = swdApiStreamed($export->baseResponse);

    $this->assertStringContainsString('Mill Alpha', $csv);
    $this->assertStringNotContainsString('Mill Beta', $csv);
    $this->assertStringNotContainsString('Milik Mill Beta', $csv);
});

it('production_line_id milik mill LAIN: 403 pada summary dan pada export, nol angka', function () {
    swdApiLoad(swdApiRecord($this->stationB, $this->day2), $this->day2, ['net_weight_mt' => 999.0]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineB,
    ]));

    // DITOLAK, BUKAN DIABAIKAN: line adalah parameter yang pengguna PILIH
    // secara sadar, jadi ia 403 — berbeda dengan business_unit_id yang dibuang.
    $summary->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
    expect(array_keys($summary->json()))->toBe(['message', 'code']);

    foreach (['loads', 'by_waste_type', 'daily', 'completeness', 'compliance'] as $block) {
        expect($summary->json())->not->toHaveKey($block);
    }

    // Dan ekspornya ditolak EAGER — bukan berkas separuh berisi header.
    $export = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineB,
        'format' => 'csv',
    ]));

    $export->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
    expect($export->headers->get('content-disposition'))->toBeNull();

    // Operator pun, meski ketiga rute data terbuka baginya.
    $this->actingAs($this->operator, 'web')
        ->getJson(swdApiUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineB,
        ]))
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');
});

it('period_id tidak ada → 404 NOT_FOUND; period_id mill lain → 403 FORBIDDEN, dan keduanya berbentuk sama', function () {
    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::SolidWasteDisposal->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Maret Beta')
        ->create();

    // 404 — id yang memang tidak ada.
    $notFound = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('summary', [
        'period_id' => '00000000-0000-0000-0000-000000000000',
        'production_line_id' => $this->lineA,
    ]));

    $notFound->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
    // BENTUKNYA: dua kunci, dan pesannya TIDAK membocorkan teks Eloquent
    // ('No query results for model …') beserta nama tabelnya.
    expect(array_keys($notFound->json()))->toBe(['message', 'code']);
    $this->assertStringNotContainsString('No query results', $notFound->json('message'));
    $this->assertStringNotContainsString('solid_waste_disposal', $notFound->json('message'));

    // 403 — periode mill LAIN, dengan line mill SENDIRI. Oracle lintas mill
    // tertutup: periode mill lain menjawab 403, tidak pernah 404.
    $forbidden = $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('summary', [
        'period_id' => (string) $periodB->id,
        'production_line_id' => $this->lineA,
    ]));

    $forbidden->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
    expect(array_keys($forbidden->json()))->toBe(['message', 'code']);

    // Dan ekspornya sama.
    $this->actingAs($this->supervisor, 'web')
        ->getJson(swdApiUrl('export', [
            'period_id' => (string) $periodB->id,
            'production_line_id' => $this->lineA,
            'format' => 'csv',
        ]))
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');
});

// =====================================================================
// Bentuk prefix-nya sendiri
// =====================================================================

it('layar hanya membaca: prefix API ini tidak punya satu rute tulis pun', function () {
    $record = swdApiRecord($this->stationA, $this->day2);
    swdApiLoad($record, $this->day2, ['net_weight_mt' => 2.0]);

    $recordCount = SolidWasteDisposalRecord::query()->count();
    $detailCount = SolidWasteDisposalDetail::query()->count();

    // Diasersikan atas DAFTAR RUTE TERDAFTAR, bukan dengan menembak URL: rute
    // yang tidak ada menjawab 404/405 yang tidak membuktikan apa pun tentang
    // niat prefix ini.
    $routes = collect(app('router')->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/solid-waste-disposal-reports'));

    expect($routes)->toHaveCount(4);

    foreach ($routes as $route) {
        expect(array_values(array_diff($route->methods(), ['HEAD'])))->toBe(['GET']);
    }

    // Dan ketiga rute data dibaca tanpa mengubah satu baris pun.
    $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('periods'))->assertOk();
    $this->actingAs($this->supervisor, 'web')->getJson(swdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]))->assertOk();
    $this->actingAs($this->supervisor, 'web')->get(swdApiUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]))->assertOk();

    expect(SolidWasteDisposalRecord::query()->count())->toBe($recordCount);
    expect(SolidWasteDisposalDetail::query()->count())->toBe($detailCount);
});

it('daftar middleware: prefix API menerima Operator sementara rute WEB laporan ini TIDAK', function () {
    // SKENARIO 2 LANGKAH 1 DARI SISI STRUKTURNYA. Yang 403-nya sendiri sudah
    // dibuktikan dengan menembak rutenya di
    // tests/Feature/Livewire/LaporanSolidWasteDisposalTest.php (skenario 2),
    // jadi yang ditambahkan di sini bukan pengulangannya: ini asersi bahwa
    // KEDUA daftar peran memang berbeda. Melebarkan admisi API — yang memang
    // dibutuhkan screen-163 (mobile) — tidak pernah boleh merembes menjadi
    // admisi layar web hanya karena seseorang menyalin satu daftar.
    $apiRoutes = collect(app('router')->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/solid-waste-disposal-reports'));

    expect($apiRoutes)->toHaveCount(4);

    foreach ($apiRoutes as $route) {
        $this->assertContains('role:supervisor,mill_management,admin,operator', $route->gatherMiddleware());
    }

    $webRoute = collect(app('router')->getRoutes())
        ->first(fn ($route) => $route->uri() === 'reports/solid-waste-disposal');

    expect($webRoute)->not->toBeNull();

    $webMiddleware = $webRoute->gatherMiddleware();

    $this->assertContains('role:supervisor,mill_management,admin', $webMiddleware);
    $this->assertNotContains('role:supervisor,mill_management,admin,operator', $webMiddleware);
});
