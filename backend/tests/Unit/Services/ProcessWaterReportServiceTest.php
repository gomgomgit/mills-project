<?php

/**
 * ProcessWaterReportServiceTest — screen-160--laporan-process-water-web,
 * ProcessWaterReportService.
 *
 * Satu uji per entri `unit_test_cases` pada tech spec screen-160 (52 kasus),
 * dikelompokkan sama seperti EngineRoomReportServiceTest supaya keduanya dapat
 * dibaca berdampingan. DUA kasus (50 dan 51) menyangkut komponen Livewire dan
 * blade-nya, bukan service, dan dibuktikan di
 * tests/Feature/Livewire/LaporanProcessWaterTest.php dengan LABEL KASUS YANG
 * SAMA — berkas ini menutup kasus 1-49 dan 52, plus satu kasus tambahan (53)
 * untuk SATU BARIS StationReportService::REPORT_ROUTES yang menentukan
 * layarnya dapat dicapai.
 *
 * ────────────────────────────────────────────────────────────────────────
 * ATURAN SEBELAS-BUKAN-TIGA-BELAS ADALAH POKOK BERKAS INI
 * ────────────────────────────────────────────────────────────────────────
 *   1. Baris detail stasiun ini membawa TIGA BELAS kolom
 *      (ProcessWaterRecordService::DETAIL_FIELDS), tetapi sebuah slot
 *      dianggap TERISI atas SEBELAS saja
 *      (ProcessWaterRecordService::READING_FIELDS). `shift` dan
 *      `inspector_id` adalah kolom IDENTITAS dan dikecualikan — docblock
 *      record service menyebut keduanya demikian dengan kata-katanya sendiri.
 *   2. DUA AKIBATNYA BERLAWANAN, dan keduanya punya kasusnya masing-masing:
 *      slot yang hanya mengisi kolom TEKS BEBAS (`findings` atau
 *      `action_taken_status`) TERHITUNG terisi, karena kedua kolom teks
 *      termasuk kesebelas; sementara slot yang hanya mengisi `shift`
 *      dan/atau `inspector_id` TIDAK terhitung.
 *   3. KASUS 17 MENANAM KEDUANYA SEKALIGUS DI DALAM SATU RECORD dan menuntut
 *      filled_slots === 1. Implementasi yang menghitung ke-13 kolom
 *      memulangkan 2 dan MEMERAH tepat di situ — itulah seluruh gunanya, dan
 *      kasus 17b membuktikan sifat itu dengan menjalankan pemeriksaan
 *      tiga-belas-kolom berdampingan atas fixture yang sama.
 *   4. Karena kesebelas kolom mencakup kedua kolom teks, coverage.filled_slots
 *      DAPAT MELEBIHI penyebut setiap metrik (kasus 20). Yang diasersi adalah
 *      KETIDAKSAMAAN-nya, bukan dua angka yang dipaku.
 *
 * ────────────────────────────────────────────────────────────────────────
 * NOL BLOK SEBARAN KATEGORIS, DAN KETIADAANNYA DIASERSI ATAS NAMA KUNCI
 * ────────────────────────────────────────────────────────────────────────
 * Stasiun ini tidak punya satu pun kolom berenum. `action_taken_status`
 * bertipe string polos MESKI NAMANYA MENYEBUT 'status' — formulir web
 * merendernya <input type="text">, label formulir mobile berbunyi
 * 'Action Taken/Status' DENGAN GARIS MIRING, dan nol entri di
 * Display::OPTION_LABELS. Ia karena itu diterbitkan sebagai DAFTAR TEKS.
 *
 * Ketiadaan itu diasersi atas NAMA KUNCI lewat processWaterReportAllKeys(),
 * BUKAN dengan memindai json_encode: `_note_absences` dan `_note_filled_rule`
 * adalah KALIMAT MANUSIA di dalam payload, dan pemindaian teks akan menjawab
 * pertanyaan yang salah — baik gagal pada kalimat yang justru membuktikan
 * klaimnya, maupun lulus hanya karena implementasinya memilih kata lain.
 * Absensi diasersi atas KUNCI, titik.
 *
 * ExportValue::option() TIDAK DIPAKAI SAMA SEKALI di laporan ini (kasus 46),
 * dan asersinya atas SUMBER yang KOMENTARNYA SUDAH DIBUANG lewat
 * token_get_all(): docblock ketiga berkas implementasi MEMANG menyebut
 * '::option()' ketika menjelaskan mengapa ia tidak dipakai, jadi grep mentah
 * akan memerah pada prosa yang membuktikan klaimnya.
 *
 * ────────────────────────────────────────────────────────────────────────
 * OPERATOR DITERIMA PADA KETIGA RUTE DATA SEJAK COMMIT PERTAMA
 * ────────────────────────────────────────────────────────────────────────
 * Presedens Grading / Kernel Plant / Engine Room: screen-161 (kembaran mobile
 * laporan ini) dibangun dalam seri yang SAMA, jadi pemanggil Operator yang
 * sah sudah ada. Pelebarannya menyentuh TIGA tempat — daftar peran middleware
 * routes/api.php, guardAccess(), DAN cabang TERIKAT-MILL
 * resolveBusinessUnit(). KASUS 5 DAN 6 adalah penjaga regresi untuk yang
 * ketiga: tanpa baris Operator di cabang terikat-mill, Operator jatuh ke
 * cabang ADMIN tempat business_unit_id kiriman klien DIHORMATI — kebocoran
 * lintas mill, bukan cacat tampilan.
 *
 * YANG TETAP TERTUTUP: kasus 1 dan 2 — /business-units/options bagi Operator,
 * Supervisor, dan Mill Management (403 diangkat DARI DALAM service, bukan oleh
 * middleware prefix yang memang menerima empat peran). Rute WEB juga tetap
 * tertutup, dan itu dibuktikan di berkas Livewire.
 *
 * TANGGAL FIXTURE SELALU RELATIF (now()->subDays(...)), tidak pernah tanggal
 * kalender yang dipaku: periodA di bawah harus SELALU sudah selesai supaya
 * days_counted === days_in_period, dan tanggal yang dipaku berhenti memenuhi
 * syarat itu begitu kalender melewatinya. Tech spec menyebut tanggal kalender
 * (2026-03-xx) pada beberapa kasus; yang dipertahankan di sini adalah
 * MAKSUDNYA, bukan digitnya.
 *
 * Suite ini berjalan di SQLite in-memory sementara produksi PostgreSQL —
 * alasan yang sama mengapa service-nya mengagregasi di PHP (kasus 38) dan
 * mengapa penyaring periode diasersi di UJUNG-ujungnya (kasus 39).
 */

use App\Enums\PeriodStatus;
use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\ProcessWaterDetail;
use App\Models\ProcessWaterRecord;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use App\Services\ProcessWaterRecordService;
use App\Services\ProcessWaterReportService;
use App\Services\StationReportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Spy yang membuktikan allBusinessUnits() tidak pernah tercapai pada jalur
 * gagal-tertutup. Perangkat yang sama dengan
 * EngineRoomReportAllBusinessUnitsSpy.
 */
class ProcessWaterReportAllBusinessUnitsSpy extends ProcessWaterReportService
{
    public int $allBusinessUnitsCalls = 0;

    public function allBusinessUnits(): Collection
    {
        $this->allBusinessUnitsCalls++;

        return parent::allBusinessUnits();
    }
}

/**
 * Menurunkan batas ekspor supaya perbedaan "baris slot versus record" dapat
 * dibuktikan tanpa menyemai 50.000 baris. Hanya mungkin karena service
 * membaca batasnya lewat `static::`, bukan `self::` — pilihan itulah yang
 * subclass ini ada untuk mengujinya.
 */
class ProcessWaterReportTinyExportService extends ProcessWaterReportService
{
    public const EXPORT_ROW_LIMIT = 5;
}

/** Satu record harian untuk satu unit Process Water. */
function processWaterReportRecord(
    Station $station,
    string $date,
    string $processWaterId = 'PW-1',
    array $attributes = [],
): ProcessWaterRecord {
    return ProcessWaterRecord::factory()
        ->forStation($station)
        ->onDate($date)
        ->create(array_merge([
            'process_water_id' => $processWaterId,
            'note' => 'Catatan harian',
        ], $attributes));
}

/** Satu baris slot waktu. KETIGA BELAS kolom detailnya default-nya null. */
function processWaterReportSlot(
    ProcessWaterRecord $record,
    string $timeSlot,
    array $values = [],
): ProcessWaterDetail {
    return ProcessWaterDetail::factory()
        ->forRecord($record)
        ->timeSlot($timeSlot)
        ->create($values);
}

/** 24 slot kanonis, dalam urutan layar input (07:00..06:00 keesokan hari). */
function processWaterReportSlots(): array
{
    return ProcessWaterRecordService::canonicalTimeSlots();
}

/** Setiap pernyataan SQL yang berjalan di dalam $callback. */
function processWaterReportQueriesDuring(callable $callback): array
{
    $queries = [];

    DB::listen(function ($query) use (&$queries) {
        $queries[] = strtolower($query->sql);
    });

    $callback();

    return $queries;
}

function processWaterReportStreamed(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

/** Entri metrics[] untuk satu kolom, dikunci NAMA KOLOM. */
function processWaterReportMetric(array $summary, string $column): array
{
    expect($summary['metrics'])->toHaveKey($column);

    return $summary['metrics'][$column];
}

/**
 * Seluruh nama KUNCI yang muncul di mana pun di dalam struktur.
 *
 * DIPAKAI ALIH-ALIH MEMINDAI json_encode: `_note_absences` dan
 * `_note_filled_rule` adalah kalimat manusia di dalam payload ini, dan
 * pemindaian teks menjawab pertanyaan yang salah — ia bisa gagal pada kalimat
 * yang justru membuktikan klaimnya, atau lulus semata karena implementasinya
 * memilih kata lain. Yang ditanyakan adalah apakah sebuah KUNCI ada.
 *
 * @return list<string>
 */
function processWaterReportAllKeys(mixed $value): array
{
    if (! is_array($value)) {
        return [];
    }

    $keys = [];

    foreach ($value as $key => $child) {
        if (is_string($key)) {
            $keys[] = $key;
        }

        $keys = array_merge($keys, processWaterReportAllKeys($child));
    }

    return array_values(array_unique($keys));
}

/**
 * Nama kunci yang TIDAK BOLEH ADA di mana pun pada payload.
 *
 * SENGAJA TIDAK MEMUAT kunci yang memang ada dan sah — 'avg', 'min', 'max',
 * 'total', 'date', 'status', 'name', 'group', 'entries', 'text' — karena
 * daftar ini dicocokkan sebagai NAMA KUNCI yang utuh. 'action_taken_status'
 * pun TIDAK di sini: ia memang ada, sebagai daftar teks di dalam
 * `text_entries`. Yang dilarang adalah kunci yang mengubahnya menjadi
 * sebaran.
 *
 * @return list<string>
 */
function processWaterReportForbiddenKeys(): array
{
    return [
        // SEBARAN KATEGORIS — stasiun ini tidak punya satu pun kolom berenum,
        // dan `action_taken_status` yang namanya menyesatkan adalah teks
        // bebas. Engine Room dan Effluent Plant punya blok ini; layar ini
        // tidak, dan ketiadaannya disengaja.
        'status_distribution', 'known_values', 'counts', 'count',
        'distribution', 'distributions', 'value_counts', 'status_counts',
        'by_status', 'by_value', 'categories', 'category_counts',
        'action_taken_distribution', 'findings_distribution',
        'share', 'shares', 'percent_by_value', 'grouped',
        // PENANDAAN DI LUAR BATAS — stasiun ini tidak punya tabel master
        // target, dan itu berlaku MESKI pH dan TDS air umpan boiler punya
        // batas yang dikenal sangat luas di luar aplikasi.
        'out_of_range', 'is_out_of_range', 'threshold', 'thresholds', 'target',
        'targets', 'violation', 'violations', 'severity', 'flag', 'flags',
        'warning', 'warnings', 'breach', 'exceeds', 'critical_limit',
        'nominal', 'safe_range', 'anomaly', 'anomalies', 'operational_target',
        // BLOK DOWNTIME — process_water_details tidak punya satu pun
        // kolomnya.
        'downtime', 'downtime_minutes', 'downtime_events', 'total_minutes',
        'stop_events', 'stoppages',
        // DIMENSI GILIRAN KERJA — kolomnya kosong di seluruh baris dan
        // dijadwalkan diganti relasi ke master Shift. Ia TETAP ADA di ekspor,
        // dan itu diasersi terpisah (kasus 45).
        'shift', 'shifts', 'by_shift', 'shift_distribution', 'per_shift',
        // NAMA INSPECTOR — tidak ada relasi ke users, jadi tidak ada nama
        // yang dapat dipulangkan dan klien tidak boleh menebak pemetaannya.
        'inspector_name', 'inspector_names', 'inspector_user_id', 'inspectors',
    ];
}

/**
 * Sumber sebuah berkas PHP dengan SELURUH komentarnya dibuang.
 *
 * Diperlukan kasus 46: docblock ProcessWaterReportService,
 * ProcessWaterReportController, dan LaporanProcessWater MEMANG mengeja
 * '::option()' ketika menjelaskan mengapa pemeta itu tidak dipakai di
 * stasiun ini. Grep mentah akan memerah pada prosa yang justru membuktikan
 * klaimnya; token_get_all() menanyakan pertanyaan yang benar — apakah
 * KODE-nya memanggilnya.
 */
function processWaterReportStrippedSource(string $relativePath): string
{
    $code = '';

    foreach (token_get_all(file_get_contents(base_path($relativePath))) as $token) {
        if (is_array($token)) {
            if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $code .= $token[1];

            continue;
        }

        $code .= $token;
    }

    return $code;
}

beforeEach(function () {
    $this->service = new ProcessWaterReportService;

    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->processWater()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->processWater()->create();

    $this->lineA = (string) $this->stationA->production_line_id;
    $this->lineB = (string) $this->stationB->production_line_id;

    $this->supervisorA = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagementA = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operatorA = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    // Periode yang SUDAH SELESAI: 10 hari, sehingga days_counted ===
    // days_in_period dan penyebut cakupannya deterministik. Periode berjalan,
    // periode belum mulai, dan periode berdurasi 5 hari punya kasusnya
    // masing-masing.
    $this->periodStart = now()->subDays(20)->toDateString();
    $this->periodEnd = now()->subDays(11)->toDateString();

    $this->day1 = now()->subDays(19)->toDateString();
    $this->day2 = now()->subDays(18)->toDateString();
    $this->day3 = now()->subDays(17)->toDateString();
    $this->day4 = now()->subDays(16)->toDateString();
    $this->dayBeforePeriod = now()->subDays(21)->toDateString();
    $this->dayAfterPeriod = now()->subDays(10)->toDateString();

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::ProcessWater->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Process Water Alpha')
        ->create();

    $this->slots = processWaterReportSlots();
});

// =====================================================================
// GROUP A — PEMILIH MILL: ADMIN SAJA (case 1-3)
//
// Penjagaannya ada DI DALAM service, bukan pada daftar peran middleware:
// prefix /api/process-water-reports menerima EMPAT peran demi ketiga rute
// data, jadi penjaga di middleware tidak akan menutup endpoint ini sama
// sekali.
// =====================================================================

it('case 1 — 403 pada /business-units/options bagi Operator, dan master mill tidak pernah dibaca', function () {
    $this->actingAs($this->operatorA);

    $spy = new ProcessWaterReportAllBusinessUnitsSpy;

    $queries = processWaterReportQueriesDuring(function () use ($spy) {
        expect(fn () => $spy->businessUnitOptions())->toThrow(AuthorizationException::class);
    });

    expect($spy->allBusinessUnitsCalls)->toBe(0);

    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "business_units"');
    }
});

it('case 2 — 403 pada /business-units/options bagi Supervisor dan Mill Management, DARI DALAM service', function () {
    foreach ([$this->supervisorA, $this->millManagementA] as $user) {
        $this->actingAs($user);

        $spy = new ProcessWaterReportAllBusinessUnitsSpy;

        // Lolos middleware rute (prefix ini memang menerima keduanya) TETAP
        // tertahan di sini. Uji ini gagal bila penjaganya dipindah ke rute.
        expect(fn () => $spy->businessUnitOptions())->toThrow(AuthorizationException::class);
        expect($spy->allBusinessUnitsCalls)->toBe(0);
    }
});

it('case 3 — 200 daftar mill bagi Admin; master kosong mengembalikan [] bukan 404', function () {
    BusinessUnit::factory()->create(['name' => 'Mill Gamma']);

    $this->actingAs($this->admin);

    $options = $this->service->businessUnitOptions();

    // Dibandingkan dengan jumlah baris master yang sebenarnya, bukan dengan
    // angka yang dipaku: StationFactory ikut melahirkan ProductionLine
    // beserta mill-nya sendiri.
    expect($options)->toHaveCount(BusinessUnit::query()->count());

    $names = collect($options)->pluck('name');

    expect($names)->toContain('Mill Alpha', 'Mill Beta', 'Mill Gamma');
    expect($names->all())->toBe($names->sort()->values()->all());

    foreach ($options as $option) {
        expect(array_keys($option))->toBe(['id', 'name']);
    }

    // Master kosong adalah jawaban SAH: [] dengan 200, bukan 404.
    Period::query()->delete();
    ProcessWaterRecord::query()->delete();
    Station::query()->delete();
    ProductionLine::query()->delete();
    User::query()->whereNotNull('business_unit_id')->delete();
    BusinessUnit::query()->delete();

    expect($this->service->businessUnitOptions())->toBe([]);
});

// =====================================================================
// GROUP B — OPERATOR DITERIMA PADA KETIGA RUTE DATA, DAN DI CABANG
//           TERIKAT-MILL (case 4-7)
// =====================================================================

it('case 4 — Operator DITERIMA pada ketiga rute data sejak commit pertama', function () {
    $record = processWaterReportRecord($this->stationA, $this->day2, 'PW-1');
    processWaterReportSlot($record, '07:00', ['raw_water_flow_m3h' => 120.0]);

    $this->actingAs($this->operatorA);

    // guardAccess() TIDAK melempar bagi operator pada ketiga rute data.
    $periods = $this->service->listPeriods(null);
    expect(collect($periods)->pluck('id'))->toContain((string) $this->periodA->id);

    $summary = $this->service->buildSummary((string) $this->periodA->id, null, $this->lineA);
    expect($summary['business_unit']['name'])->toBe('Mill Alpha');
    expect(processWaterReportMetric($summary, 'raw_water_flow_m3h')['avg'])->toBe(120.0);

    $response = $this->service->export($this->periodA, 'csv', null, $this->lineA);
    expect($response)->toBeInstanceOf(StreamedResponse::class);

    // Dan bentuk payload-nya identik dengan yang dilihat Supervisor:
    // pelebaran ini menambah PEMANGGIL, bukan varian payload.
    $this->actingAs($this->supervisorA);
    $asSupervisor = $this->service->buildSummary((string) $this->periodA->id, null, $this->lineA);

    expect(array_keys($summary))->toBe(array_keys($asSupervisor));
    expect($summary)->toEqual($asSupervisor);
});

/**
 * PENJAGA REGRESI KEBOCORAN LINTAS MILL — tempat KETIGA dari tiga tempat
 * pelebaran. Satu-satunya uji yang gagal bila daftar peran middleware dan
 * guardAccess() dilebarkan sementara cabang terikat-mill resolveBusinessUnit()
 * TIDAK: tanpa baris Operator di cabang itu, Operator jatuh ke cabang ADMIN
 * tempat business_unit_id kiriman klien DIHORMATI.
 */
it('case 5 — KRITIS: resolveBusinessUnit() menempatkan Operator pada cabang TERIKAT-MILL', function () {
    $this->actingAs($this->operatorA);

    // Nilai kiriman tidak divalidasi, tidak dibandingkan, DIBUANG.
    expect($this->service->resolveBusinessUnit((string) $this->businessUnitB->id))
        ->toBe((string) $this->businessUnitA->id);

    // Dan cabang ADMIN dibuktikan TIDAK dieksekusi: cabang itu melempar 422
    // ketika business_unit_id kiriman null, sementara cabang terikat-mill
    // justru menjawab mill akun.
    expect($this->service->resolveBusinessUnit(null))->toBe((string) $this->businessUnitA->id);
});

it('case 6 — KRITIS: peran terikat mill yang mengirim mill lain tetap menerima data mill akunnya', function () {
    $recordA = processWaterReportRecord($this->stationA, $this->day2, 'PW-A');
    processWaterReportSlot($recordA, '07:00', ['raw_water_flow_m3h' => 120.0]);

    $recordB = processWaterReportRecord($this->stationB, $this->day2, 'PW-B');
    processWaterReportSlot($recordB, '07:00', ['raw_water_flow_m3h' => 9999.0]);

    foreach ([$this->operatorA, $this->supervisorA, $this->millManagementA] as $user) {
        $this->actingAs($user);

        expect($this->service->resolveBusinessUnit((string) $this->businessUnitB->id))
            ->toBe((string) $this->businessUnitA->id);

        // 200 berisi agregasi mill A — BUKAN 403 dan BUKAN data mill B.
        // Sengaja bukan 403: 403 justru akan menegaskan mill lain itu ada.
        $summary = $this->service->buildSummary(
            $this->periodA,
            (string) $this->businessUnitB->id,
            $this->lineA,
        );

        expect($summary['business_unit']['id'])->toBe((string) $this->businessUnitA->id);
        expect($summary['business_unit']['name'])->toBe('Mill Alpha');
        expect(processWaterReportMetric($summary, 'raw_water_flow_m3h')['max'])->toBe(120.0);
        expect(collect($summary['by_unit'])->pluck('process_water_id')->all())->toBe(['PW-A']);
    }

    // Ekspornya mengikuti cakupan yang sama: berkas yang mencampur mill
    // adalah bentuk kesalahan yang paling sulit dibantah setelah terkirim.
    $this->actingAs($this->operatorA);

    $csv = processWaterReportStreamed(
        $this->service->export($this->periodA, 'csv', (string) $this->businessUnitB->id, $this->lineA),
    );

    expect($csv)->toContain('Mill Alpha');
    expect($csv)->toContain('PW-A');
    expect($csv)->not->toContain('Mill Beta');
    expect($csv)->not->toContain('PW-B');

    // Dan line mill lain TETAP 403 — penjagaan lintas mill yang sungguhan ada
    // di sana, bukan pada business_unit_id yang justru dibuang.
    expect(fn () => $this->service->buildSummary($this->periodA, null, $this->lineB))
        ->toThrow(AuthorizationException::class);
});

it('case 7 — Admin TETAP menghormati business_unit_id kiriman', function () {
    $recordB = processWaterReportRecord($this->stationB, $this->day2, 'PW-B');
    processWaterReportSlot($recordB, '07:00', ['raw_water_flow_m3h' => 250.0]);

    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::ProcessWater->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Process Water Beta')
        ->create();

    $this->actingAs($this->admin);

    expect($this->service->resolveBusinessUnit((string) $this->businessUnitB->id))
        ->toBe((string) $this->businessUnitB->id);

    $summary = $this->service->buildSummary($periodB, (string) $this->businessUnitB->id, $this->lineB);

    expect($summary['business_unit']['id'])->toBe((string) $this->businessUnitB->id);
    expect($summary['business_unit']['name'])->toBe('Mill Beta');
    expect(processWaterReportMetric($summary, 'raw_water_flow_m3h')['avg'])->toBe(250.0);
    expect(collect($summary['by_unit'])->pluck('process_water_id')->all())->toBe(['PW-B']);
});

// =====================================================================
// GROUP C — RESOLUSI MILL, LINE, DAN PERIODE (case 8-16)
// =====================================================================

it('case 8 — Admin tanpa business_unit_id ditolak 422 pada /periods dan /summary', function () {
    $this->actingAs($this->admin);

    $queries = processWaterReportQueriesDuring(function () {
        expect(fn () => $this->service->listPeriods(null))->toThrow(ValidationException::class);
        expect(fn () => $this->service->buildSummary((string) $this->periodA->id, null, $this->lineA))
            ->toThrow(ValidationException::class);
    });

    // NOL kueri process_water_records pada /summary.
    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "process_water_records"');
        expect($sql)->not->toContain('from "periods"');
    }

    try {
        $this->service->listPeriods(null);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('business_unit_id');
        // 422, bukan 403: masukan yang belum lengkap, bukan akses yang
        // ditolak.
        expect($e->status)->toBe(422);
    }
});

it('case 9 — akun terikat mill dengan business_unit_id NULL gagal tertutup 422', function () {
    $supervisorNoMill = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);
    $operatorNoMill = User::factory()->role(UserRole::Operator)->create(['business_unit_id' => null]);

    foreach ([$supervisorNoMill, $operatorNoMill] as $user) {
        $this->actingAs($user);

        $spy = new ProcessWaterReportAllBusinessUnitsSpy;

        expect(fn () => $spy->listPeriods(null))->toThrow(ValidationException::class);
        expect(fn () => $spy->buildSummary((string) $this->periodA->id, null, $this->lineA))
            ->toThrow(ValidationException::class);

        // Sistem TIDAK jatuh ke cabang admin dan TIDAK menawarkan seluruh
        // mill: daftar seluruh mill tidak pernah dibaca pada jalur ini.
        expect($spy->allBusinessUnitsCalls)->toBe(0);
    }
});

it('case 10 — production_line_id absen ditolak 422 dan NOL kueri record', function () {
    $this->actingAs($this->supervisorA);

    $queries = processWaterReportQueriesDuring(function () {
        expect(fn () => $this->service->buildSummary((string) $this->periodA->id, null, null))
            ->toThrow(ValidationException::class);
    });

    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "process_water_records"');
        expect($sql)->not->toContain('from "process_water_details"');
    }

    try {
        $this->service->buildSummary((string) $this->periodA->id, null, null);
    } catch (ValidationException $e) {
        // TIDAK PERNAH di-default ke "seluruh line mill".
        expect(array_keys($e->errors()))->toBe(['production_line_id']);
        expect($e->status)->toBe(422);
    }
});

it('case 11 — production_line_id milik mill lain ditolak 403 sebelum kueri agregasi', function () {
    processWaterReportSlot(
        processWaterReportRecord($this->stationB, $this->day2, 'PW-B'),
        '07:00',
        ['raw_water_flow_m3h' => 9999.0],
    );

    $this->actingAs($this->supervisorA);

    $queries = processWaterReportQueriesDuring(function () {
        expect(fn () => $this->service->buildSummary((string) $this->periodA->id, null, $this->lineB))
            ->toThrow(AuthorizationException::class);
    });

    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "process_water_records"');
        expect($sql)->not->toContain('from "process_water_details"');
    }
});

it('case 12 — period_id absen 422; tidak ada 404; milik mill lain 403', function () {
    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::ProcessWater->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Process Water Beta')
        ->create();

    $this->actingAs($this->supervisorA);

    // 1. Tanpa period_id → 422 (bukan 404 untuk string kosong).
    expect(fn () => $this->service->buildSummary(null, null, $this->lineA))
        ->toThrow(ValidationException::class);

    // 2. period_id yang tidak ada → 404.
    expect(fn () => $this->service->buildSummary('00000000-0000-0000-0000-000000000000', null, $this->lineA))
        ->toThrow(ModelNotFoundException::class);

    // 3. period_id milik mill lain, line mill SENDIRI → 403.
    expect(fn () => $this->service->buildSummary((string) $periodB->id, null, $this->lineA))
        ->toThrow(AuthorizationException::class);
});

it('case 13 — urutan guard: peran → mill → line → period_id → periode', function () {
    $supervisorNoMill = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $this->actingAs($supervisorNoMill);

    // 1. Mill belum terselesaikan: VALIDATION_ERROR business_unit_id, walau
    //    production_line_id DAN period_id juga hilang.
    try {
        $this->service->buildSummary(null, null, null);
        $this->fail('seharusnya melempar ValidationException');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toBe(['business_unit_id']);
    }

    // 2. Mill terisi, line masih hilang: production_line_id — BUKAN gabungan.
    $this->actingAs($this->supervisorA);

    try {
        $this->service->buildSummary(null, null, null);
        $this->fail('seharusnya melempar ValidationException');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toBe(['production_line_id']);
    }

    // 3. Line terisi, periode masih hilang: period_id.
    try {
        $this->service->buildSummary(null, null, $this->lineA);
        $this->fail('seharusnya melempar ValidationException');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toBe(['period_id']);
    }

    // 4. Penjaga LINE berjalan SEBELUM penjaga periode: periode mill lain +
    //    line mill lain menjawab 403 dari penjaga LINE, sehingga pemanggil
    //    yang tidak berhak tidak pernah belajar id periode mana yang ada.
    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::ProcessWater->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->create();

    // Dan id periode yang TIDAK ADA pun tetap dijawab 403 oleh penjaga line,
    // bukan 404: kalau 404 lebih dulu, pemanggil belajar id mana yang ada.
    expect(fn () => $this->service->buildSummary(
        '00000000-0000-0000-0000-000000000000',
        null,
        $this->lineB,
    ))->toThrow(AuthorizationException::class);

    $queries = processWaterReportQueriesDuring(function () use ($periodB) {
        expect(fn () => $this->service->buildSummary((string) $periodB->id, null, $this->lineB))
            ->toThrow(AuthorizationException::class);
    });

    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "process_water_records"');
    }
});

it('case 14 — 401 bagi permintaan tanpa autentikasi pada keempat rute', function () {
    $queries = processWaterReportQueriesDuring(function () {
        expect(fn () => $this->service->businessUnitOptions())->toThrow(AuthenticationException::class);
        expect(fn () => $this->service->listPeriods(null))->toThrow(AuthenticationException::class);
        expect(fn () => $this->service->buildSummary((string) $this->periodA->id, null, $this->lineA))
            ->toThrow(AuthenticationException::class);
        expect(fn () => $this->service->export($this->periodA, 'csv', null, $this->lineA))
            ->toThrow(AuthenticationException::class);
    });

    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "process_water_records"');
        expect($sql)->not->toContain('from "business_units"');
        expect($sql)->not->toContain('from "periods"');
    }
});

it('case 15 — /periods hanya memuat periode yang mencakup Process Water', function () {
    $keduaProcessWater = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::ProcessWater->value)
        ->range(now()->subDays(40)->toDateString(), now()->subDays(31)->toDateString())
        ->open()
        ->named('Periode Process Water Kedua')
        ->create();

    $lainnya = collect([
        StationTypeEnum::EngineRoom->value,
        StationTypeEnum::EffluentPlant->value,
        StationTypeEnum::StorageTank->value,
    ])->map(fn (string $type, int $index) => Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType($type)
        ->range(
            now()->subDays(70 - $index * 11)->toDateString(),
            now()->subDays(63 - $index * 11)->toDateString(),
        )
        ->open()
        ->named('Periode '.$type)
        ->create());

    $this->actingAs($this->supervisorA);

    $periods = $this->service->listPeriods(null);
    $ids = collect($periods)->pluck('id');

    expect($periods)->toHaveCount(2);
    expect($ids)->toContain((string) $this->periodA->id);
    expect($ids)->toContain((string) $keduaProcessWater->id);

    foreach ($lainnya as $period) {
        expect($ids)->not->toContain((string) $period->id);
    }

    // KUNCINYA BERTANDA HUBUNG. Kunci snake_case akan ter-compile, tampak
    // benar, dan tidak pernah cocok dengan satu baris master pun — daftar
    // periodenya lalu kosong untuk setiap mill tanpa satu galat.
    foreach ($periods as $option) {
        expect($option['station_type'])->toBe('process-water');
    }

    expect(StationTypeEnum::ProcessWater->value)->toBe('process-water');
});

it('case 16 — /periods mengabaikan business_unit_id dan production_line_id bagi peran terikat mill', function () {
    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::ProcessWater->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Process Water Beta')
        ->create();

    $this->actingAs($this->supervisorA);

    $queries = processWaterReportQueriesDuring(function () use ($periodB) {
        $ids = collect($this->service->listPeriods((string) $this->businessUnitB->id))->pluck('id');

        expect($ids)->toContain((string) $this->periodA->id);
        expect($ids)->not->toContain((string) $periodB->id);
    });

    // production_line_id BUKAN parameter metode ini sama sekali: periode
    // milik MILL, bukan milik line. Dibuktikan atas kuerinya.
    foreach ($queries as $sql) {
        expect($sql)->not->toContain('production_line_id');
    }
});

// =====================================================================
// GROUP D — SEBELAS KOLOM, BUKAN TIGA BELAS (case 17-22)
//
// POKOK BERKAS INI. Dua akibatnya BERLAWANAN, dan masing-masing punya
// kasusnya sendiri justru karena keduanya mudah tertukar.
// =====================================================================

it('case 17 — BARIS TERISI DIHITUNG ATAS 11 KOLOM, BUKAN 13 — kasus jebakan', function () {
    // SATU record, DUA slot, DAN KEDUA KASUS BERLAWANAN DITANAM SEKALIGUS:
    //   - slot 07:00 hanya mengisi inspector_id  → kolom IDENTITAS, TIDAK
    //     terhitung terisi;
    //   - slot 08:00 hanya mengisi findings      → termasuk kesebelas kolom
    //     pembacaan, TERHITUNG terisi.
    //
    // Jawaban yang benar karena itu 1. Implementasi yang menghitung ke-13
    // kolom memulangkan 2 dan MEMERAH di sini — itulah seluruh guna kasus
    // ini, dan case 17b di bawah membuktikan sifat itu alih-alih
    // mengklaimnya.
    $record = processWaterReportRecord($this->stationA, $this->day2, 'PW-1');

    processWaterReportSlot($record, '07:00', ['inspector_id' => 'INS-01']);
    processWaterReportSlot($record, '08:00', ['findings' => 'Keruh di bak pengendap']);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['coverage']['filled_slots'])->toBe(1);
    expect($summary['has_data'])->toBeTrue();

    // Dan yang terhitung adalah slot findings — bukan slot inspector_id.
    expect($summary['text_entries']['findings'])->toHaveCount(1);
    expect($summary['text_entries']['findings'][0]['time_slot'])->toBe('08:00');
    // Slot ber-inspector_id saja TIDAK ikut rekap inspector, karena rekap itu
    // mengelompokkan SLOT TERISI.
    expect($summary['inspector_recap']['recorded_slots'])->toBe(1);
    expect($summary['inspector_recap']['entries'])->toBe([]);
    expect($summary['inspector_recap']['not_recorded'])->toBe(1);
});

it('case 17b — fixture case 17 MEMANG memerahkan pemeriksaan tiga-belas-kolom', function () {
    // Kasus 17 tidak ada gunanya kalau fixture-nya menjawab sama pada kedua
    // aturan. Dibuktikan di tempat, atas fixture yang sama persis: aturan
    // SEBELAS kolom menjawab 1, aturan TIGA BELAS kolom menjawab 2.
    $record = processWaterReportRecord($this->stationA, $this->day2, 'PW-1');

    processWaterReportSlot($record, '07:00', ['inspector_id' => 'INS-01']);
    processWaterReportSlot($record, '08:00', ['findings' => 'Keruh di bak pengendap']);

    $details = ProcessWaterDetail::query()->where('process_water_record_id', $record->id)->get();

    expect($details)->toHaveCount(2);
    expect(ProcessWaterRecordService::READING_FIELDS)->toHaveCount(11);
    expect(ProcessWaterRecordService::DETAIL_FIELDS)->toHaveCount(13);

    $filledOverEleven = $details
        ->filter(fn (ProcessWaterDetail $detail) => collect(ProcessWaterRecordService::READING_FIELDS)
            ->contains(fn (string $field) => $detail->{$field} !== null && $detail->{$field} !== ''))
        ->count();

    $filledOverThirteen = $details
        ->filter(fn (ProcessWaterDetail $detail) => collect(ProcessWaterRecordService::DETAIL_FIELDS)
            ->contains(fn (string $field) => $detail->{$field} !== null && $detail->{$field} !== ''))
        ->count();

    expect($filledOverEleven)->toBe(1);
    expect($filledOverThirteen)->toBe(2);

    // Dan service-nya menjawab yang pertama.
    $this->actingAs($this->supervisorA);

    expect($this->service->buildSummary($this->periodA, null, $this->lineA)['coverage']['filled_slots'])
        ->toBe($filledOverEleven);
});

it('case 18 — slot yang hanya mengisi shift TIDAK terhitung terisi', function () {
    $record = processWaterReportRecord($this->stationA, $this->day2, 'PW-1');

    processWaterReportSlot($record, '07:00', ['shift' => 'Shift 1']);
    processWaterReportSlot($record, '08:00', ['shift' => 'Shift 2', 'inspector_id' => 'INS-07']);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // KEDUA kolom identitas dikecualikan isRowFilled().
    expect($summary['coverage']['filled_slots'])->toBe(0);
    expect($summary['has_data'])->toBeFalse();
    expect($summary['inspector_recap']['recorded_slots'])->toBe(0);
    expect($summary['inspector_recap']['entries'])->toBe([]);
    // Record-nya TIDAK dibuang dari hasil kueri — hanya tidak dihitung
    // terisi. Unitnya tetap muncul, dan karena itu expected_slots tetap
    // benar.
    expect($summary['coverage']['process_water_unit_count'])->toBe(1);
    expect($summary['total']['days_with_records'])->toBe(1);
});

it('case 19 — slot yang hanya mengisi kolom teks bebas TERHITUNG terisi', function () {
    $record = processWaterReportRecord($this->stationA, $this->day2, 'PW-1');

    processWaterReportSlot($record, '07:00', ['action_taken_status' => 'Sudah ditangani']);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['coverage']['filled_slots'])->toBe(1);
    expect($summary['has_data'])->toBeTrue();

    // TANPA satu pun reading_count metrik bertambah: kedua kolom teks
    // termasuk kesebelas kolom pembacaan, tetapi bukan kolom UKUR.
    foreach (ProcessWaterReportService::NUMERIC_METRICS as $column) {
        expect(processWaterReportMetric($summary, $column)['reading_count'])->toBe(0);
        expect(processWaterReportMetric($summary, $column)['avg'])->toBeNull();
    }

    expect($summary['text_entries']['action_taken_status'])->toHaveCount(1);
});

it('case 20 — filled_slots dapat MELEBIHI penyebut setiap kartu metrik', function () {
    $record = processWaterReportRecord($this->stationA, $this->day2, 'PW-1');

    // 5 slot hanya mengisi findings …
    foreach (array_slice($this->slots, 0, 5) as $index => $slot) {
        processWaterReportSlot($record, $slot, ['findings' => 'Temuan '.$index]);
    }

    // … dan 2 slot mengisi raw_water_flow_m3h.
    foreach (array_slice($this->slots, 5, 2) as $index => $slot) {
        processWaterReportSlot($record, $slot, ['raw_water_flow_m3h' => 100.0 + $index]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['coverage']['filled_slots'])->toBe(7);

    $denominators = collect($summary['metrics'])->pluck('reading_count');

    expect($denominators->max())->toBe(2);
    // KETIDAKSAMAAN-nya yang diasersi, bukan dua angka yang dipaku: selisih
    // itu BENAR dan tidak dikoreksi.
    expect($summary['coverage']['filled_slots'])->toBeGreaterThan($denominators->max());

    foreach ($denominators as $denominator) {
        expect($denominator)->toBeLessThanOrEqual($summary['coverage']['filled_slots']);
    }

    // Dan payload MENYATAKAN sebabnya bersama angkanya, supaya pembaca tidak
    // perlu menebak mengapa filled_slots bisa melebihi setiap reading_count.
    expect($summary['coverage'])->toHaveKey('_note_filled_rule');
    $this->assertStringContainsString(
        'MELEBIHI',
        $summary['coverage']['_note_filled_rule'],
        'keterangan aturan sebelas kolom tidak menyatakan bahwa filled_slots dapat melebihi reading_count',
    );
    $this->assertStringContainsString(
        'SEBELAS',
        $summary['coverage']['_note_filled_rule'],
        'keterangan aturan tidak menyebut jumlah kolom pembacaan',
    );
});

it('case 21 — record dengan seluruh 13 kolom null tidak terhitung terisi', function () {
    $record = processWaterReportRecord($this->stationA, $this->day2, 'PW-1');

    foreach (array_slice($this->slots, 0, 3) as $slot) {
        processWaterReportSlot($record, $slot);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['coverage']['filled_slots'])->toBe(0);
    expect($summary['has_data'])->toBeFalse();
    // Record tidak dibuang dari hasil kueri, hanya tidak dihitung terisi:
    // sebuah hari yang punya record tetapi seluruh barisnya kosong tetap
    // sebuah hari yang dicatat orang.
    expect($summary['total']['days_with_records'])->toBe(1);
    expect($summary['coverage']['process_water_unit_count'])->toBe(1);
    // Blok cakupan TETAP diterbitkan.
    expect($summary['coverage']['expected_slots'])->toBe(1 * 10 * 24);
    // Rekap harian dan tren TIDAK menggambar baris/titik nol.
    expect($summary['daily'])->toBe([]);
    expect($summary['daily_trend']['raw_water_flow_m3h'])->toBe([]);
});

it('case 22 — isRowFilled dipinjam dari record service, bukan ditulis ulang', function () {
    // Diasersi SECARA STRUKTURAL atas sumber service-nya (komentarnya
    // dibuang): asersi perilaku saja baru gagal setelah kedua definisi
    // berbeda pendapat, yaitu sesudah perbedaannya mendarat.
    $source = processWaterReportStrippedSource('app/Services/ProcessWaterReportService.php');

    $this->assertStringContainsString(
        'ProcessWaterRecordService::READING_FIELDS',
        $source,
        'definisi kolom pembacaan tidak dipinjam dari record service',
    );
    $this->assertStringContainsString(
        'isRowFilled(',
        $source,
        'pemeriksaan slot terisi tidak memanggil isRowFilled()',
    );

    // Dan ia TIDAK menulis ulang daftar kolomnya: tidak satu pun nama kolom
    // teks bebas muncul di dalam daftar konstanta metrik numerik, dan
    // NUMERIC_METRICS berisi SEMBILAN — bukan sebelas, bukan tiga belas.
    expect(ProcessWaterReportService::NUMERIC_METRICS)->toHaveCount(9);
    expect(ProcessWaterReportService::NUMERIC_METRICS)->not->toContain('action_taken_status');
    expect(ProcessWaterReportService::NUMERIC_METRICS)->not->toContain('findings');
    expect(ProcessWaterReportService::NUMERIC_METRICS)->not->toContain('shift');
    expect(ProcessWaterReportService::NUMERIC_METRICS)->not->toContain('inspector_id');

    // Keduanya memang `public` — itu yang membuat peminjaman mungkin.
    expect((new ReflectionMethod(ProcessWaterRecordService::class, 'isRowFilled'))->isPublic())->toBeTrue();
    expect((new ReflectionClassConstant(ProcessWaterRecordService::class, 'READING_FIELDS'))->isPublic())->toBeTrue();
    expect((new ReflectionClassConstant(ProcessWaterRecordService::class, 'DETAIL_FIELDS'))->isPublic())->toBeTrue();

    // Dan kesebelas kolom itu adalah kesembilan kolom ukur + kedua kolom
    // teks: diasersi atas HIMPUNANNYA, bukan atas jumlahnya saja.
    expect(ProcessWaterRecordService::READING_FIELDS)->toBe(array_merge(
        ProcessWaterReportService::NUMERIC_METRICS,
        ProcessWaterReportService::FREE_TEXT_COLUMNS,
    ));
    expect(ProcessWaterRecordService::DETAIL_FIELDS)->toBe(array_merge(
        ['shift', 'inspector_id'],
        ProcessWaterRecordService::READING_FIELDS,
    ));
});

// =====================================================================
// GROUP E — SEMBILAN METRIK, SEMBILAN PENYEBUT (case 23-26)
// =====================================================================

it('case 23 — payload memuat tepat 9 kunci metrik ukur', function () {
    $record = processWaterReportRecord($this->stationA, $this->day2, 'PW-1');

    processWaterReportSlot($record, '07:00', array_fill_keys(
        ProcessWaterReportService::NUMERIC_METRICS,
        7.5,
    ) + ['action_taken_status' => 'Sudah ditangani', 'findings' => 'Keruh']);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['metrics'])->toHaveCount(9);
    expect(array_keys($summary['metrics']))->toBe(ProcessWaterReportService::NUMERIC_METRICS);

    // Nama kuncinya SAMA PERSIS dengan nama kolom di basis data.
    foreach (array_keys($summary['metrics']) as $column) {
        expect(ProcessWaterRecordService::READING_FIELDS)->toContain($column);
    }

    // Kedua kolom TEKS tidak ada di dalamnya — keduanya diterbitkan sebagai
    // daftar, bukan sebagai metrik.
    expect($summary['metrics'])->not->toHaveKey('action_taken_status');
    expect($summary['metrics'])->not->toHaveKey('findings');
    expect($summary['metrics'])->not->toHaveKey('shift');
    expect($summary['metrics'])->not->toHaveKey('inspector_id');
});

it('case 24 — min/avg/max dan reading_count setiap metrik dari baris non-null miliknya sendiri', function () {
    $record = processWaterReportRecord($this->stationA, $this->day2, 'PW-1');

    // raw_water_flow_m3h = 20, 22, null, 24 ; boiler_feed_tds_ppm = 300, null, null, null
    processWaterReportSlot($record, $this->slots[0], ['raw_water_flow_m3h' => 20.0, 'boiler_feed_tds_ppm' => 300.0]);
    processWaterReportSlot($record, $this->slots[1], ['raw_water_flow_m3h' => 22.0]);
    processWaterReportSlot($record, $this->slots[2], ['softener_inlet_ph' => 7.1]);
    processWaterReportSlot($record, $this->slots[3], ['raw_water_flow_m3h' => 24.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    $raw = processWaterReportMetric($summary, 'raw_water_flow_m3h');

    expect($raw['min'])->toBe(20.0);
    expect($raw['avg'])->toBe(22.0);
    expect($raw['max'])->toBe(24.0);
    expect($raw['reading_count'])->toBe(3);

    $tds = processWaterReportMetric($summary, 'boiler_feed_tds_ppm');

    expect($tds['min'])->toBe(300.0);
    expect($tds['avg'])->toBe(300.0);
    expect($tds['max'])->toBe(300.0);
    // PENYEBUTNYA TIDAK DIBAGI BERSAMA: satu penyebut bersama akan mencetak
    // 4 di sini dan tetap terlihat masuk akal.
    expect($tds['reading_count'])->toBe(1);

    expect(processWaterReportMetric($summary, 'softener_inlet_ph')['reading_count'])->toBe(1);
    expect(collect($summary['metrics'])->pluck('reading_count')->unique()->sort()->values()->all())
        ->toBe([0, 1, 3]);
});

it('case 25 — metrik tanpa satu pun nilai menghasilkan null, bukan 0', function () {
    $record = processWaterReportRecord($this->stationA, $this->day2, 'PW-1');

    processWaterReportSlot($record, '07:00', ['raw_water_flow_m3h' => 120.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    $hardness = processWaterReportMetric($summary, 'softener_outlet_hardness_ppm');

    // null, BUKAN 0: nol akan terbaca sebagai hasil pengukuran.
    expect($hardness['min'])->toBeNull();
    expect($hardness['avg'])->toBeNull();
    expect($hardness['max'])->toBeNull();
    expect($hardness['reading_count'])->toBe(0);

    // Dan kartunya tetap ADA di payload, di dalam bloknya.
    expect($hardness['group'])->toBe('softener');
});

it('case 26 — tiap metrik membawa group yang benar', function () {
    $record = processWaterReportRecord($this->stationA, $this->day2, 'PW-1');
    processWaterReportSlot($record, '07:00', array_fill_keys(ProcessWaterReportService::NUMERIC_METRICS, 7.5));

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    $expected = [
        'raw_water_flow_m3h' => 'water_flow',
        'clarified_water_flow_m3h' => 'water_flow',
        'softener_inlet_ph' => 'softener',
        'softener_outlet_hardness_ppm' => 'softener',
        'alum_dosing_kgh' => 'chemical_dosing',
        'polymer_dosing_gh' => 'chemical_dosing',
        'boiler_feed_tank_temp_c' => 'boiler_feed',
        'boiler_feed_water_ph' => 'boiler_feed',
        'boiler_feed_tds_ppm' => 'boiler_feed',
    ];

    foreach ($expected as $column => $group) {
        expect(processWaterReportMetric($summary, $column)['group'])->toBe($group);
    }

    // Blade menyusun keempat blok berjudul DARI RESPONS, bukan dari daftar
    // nama kolom yang dipaku di template — jadi keempat nama grup harus
    // benar-benar terbit, dan hanya keempat itu.
    expect(collect($summary['metrics'])->pluck('group')->unique()->values()->all())
        ->toBe(['water_flow', 'softener', 'chemical_dosing', 'boiler_feed']);
    expect(ProcessWaterReportService::METRIC_GROUPS)->toBe($expected);
});

// =====================================================================
// GROUP F — NOL SEBARAN KATEGORIS, DAN DUA DAFTAR TEKS (case 27-31)
// =====================================================================

it('case 27 — PAYLOAD TIDAK MEMUAT SATU PUN KUNCI SEBARAN KATEGORIS', function () {
    $record = processWaterReportRecord($this->stationA, $this->day2, 'PW-1');

    // action_taken_status TERISI pada banyak slot — justru keadaan yang
    // paling mengundang sebuah blok sebaran dibangun.
    foreach (array_slice($this->slots, 0, 6) as $slot) {
        processWaterReportSlot($record, $slot, [
            'action_taken_status' => 'Sudah ditangani',
            'raw_water_flow_m3h' => 120.0,
        ]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // DIASERSI ATAS NAMA KUNCI. Pemindaian prosa akan menjawab pertanyaan
    // yang salah: `_note_absences` adalah kalimat manusia yang membahas
    // ketiadaan itu, jadi ia bisa memerahkan asersi yang benar — atau,
    // kalau implementasinya memilih kata lain, meloloskan asersi yang kosong.
    $keys = processWaterReportAllKeys($summary);

    foreach (processWaterReportForbiddenKeys() as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
    }

    // Dan action_taken_status muncul HANYA sebagai daftar di text_entries.
    expect($summary['text_entries'])->toHaveKey('action_taken_status');
    expect($summary['text_entries']['action_taken_status'])->toHaveCount(6);
    expect($summary['metrics'])->not->toHaveKey('action_taken_status');
    expect($summary['daily_trend'])->not->toHaveKey('action_taken_status');

    // Bentuk payload-nya, dan hanya itu — kunci baru yang menyelinap masuk
    // akan terlihat di sini.
    expect(array_keys($summary))->toBe([
        'period', 'business_unit', 'production_line', 'has_data', 'coverage',
        'metrics', 'text_entries', 'inspector_recap', 'daily_trend',
        'by_unit', 'daily', 'daily_total', 'total', '_note_absences',
    ]);
});

it('case 28 — isi action_taken_status yang berulang sama persis tidak dikelompokkan', function () {
    $record = processWaterReportRecord($this->stationA, $this->day2, 'PW-1');

    // 'Sudah ditangani' pada DUA PULUH slot.
    foreach (array_slice($this->slots, 0, 20) as $slot) {
        processWaterReportSlot($record, $slot, ['action_taken_status' => 'Sudah ditangani']);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    $entries = $summary['text_entries']['action_taken_status'];

    // DUA PULUH entri, bukan satu entri berjumlah dua puluh: mengelompokkan
    // teks bebas membuat setiap kalimat berbeda menjadi satu 'nilai'
    // tersendiri, dan daftar kalimat berjumlah satu-satu bukan sebaran.
    expect($entries)->toHaveCount(20);
    expect(collect($entries)->pluck('text')->unique()->all())->toBe(['Sudah ditangani']);
    // Masing-masing membawa SLOT WAKTUNYA SENDIRI — itu yang membedakannya
    // dari satu baris berangka dua puluh.
    expect(collect($entries)->pluck('time_slot')->unique())->toHaveCount(20);

    foreach ($entries as $entry) {
        expect(array_keys($entry))->toBe(['date', 'time_slot', 'text']);
    }
});

it('case 29 — dua kolom teks diterbitkan sebagai dua daftar terpisah', function () {
    $record = processWaterReportRecord($this->stationA, $this->day2, 'PW-1');

    processWaterReportSlot($record, '07:00', ['action_taken_status' => 'Dosis alum dinaikkan']);
    processWaterReportSlot($record, '08:00', ['findings' => 'Air baku keruh']);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect(array_keys($summary['text_entries']))->toBe(['action_taken_status', 'findings']);
    expect($summary['text_entries']['action_taken_status'])->toHaveCount(1);
    expect($summary['text_entries']['findings'])->toHaveCount(1);
    expect($summary['text_entries']['action_taken_status'][0]['text'])->toBe('Dosis alum dinaikkan');
    expect($summary['text_entries']['findings'][0]['text'])->toBe('Air baku keruh');

    // Keduanya tidak pernah masuk metrics, dan tidak pernah disatukan menjadi
    // daftar ketiga.
    expect($summary['metrics'])->not->toHaveKey('action_taken_status');
    expect($summary['metrics'])->not->toHaveKey('findings');

    foreach (['text_entries_combined', 'notes', 'remarks', 'all_texts'] as $forbidden) {
        $this->assertNotContains($forbidden, processWaterReportAllKeys($summary));
    }
});

it('case 30 — satu slot yang mengisi kedua kolom teks muncul pada kedua daftar', function () {
    $record = processWaterReportRecord($this->stationA, $this->day2, 'PW-1');

    processWaterReportSlot($record, '07:00', [
        'action_taken_status' => 'Backwash softener',
        'findings' => 'Kesadahan keluaran naik',
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['text_entries']['action_taken_status'])->toHaveCount(1);
    expect($summary['text_entries']['findings'])->toHaveCount(1);
    expect($summary['text_entries']['action_taken_status'][0]['time_slot'])->toBe('07:00');
    expect($summary['text_entries']['findings'][0]['time_slot'])->toBe('07:00');

    // Dan filled_slots tetap bertambah SATU: muncul di kedua daftar BUKAN
    // penghitungan ganda, karena keduanya menjawab pertanyaan berbeda.
    expect($summary['coverage']['filled_slots'])->toBe(1);
});

it('case 31 — kedua kolom teks kosong menghasilkan dua daftar kosong', function () {
    $record = processWaterReportRecord($this->stationA, $this->day2, 'PW-1');

    processWaterReportSlot($record, '07:00', ['raw_water_flow_m3h' => 120.0]);
    // Spasi tepi saja BUKAN catatan: '   ' menjadi null, bukan entri kosong.
    processWaterReportSlot($record, '08:00', [
        'raw_water_flow_m3h' => 130.0,
        'action_taken_status' => '   ',
        'findings' => '   ',
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // DUA kunci TERPISAH yang tetap diterbitkan — bukan satu kunci gabungan
    // dan bukan kunci yang dihilangkan.
    expect(array_keys($summary['text_entries']))->toBe(['action_taken_status', 'findings']);
    expect($summary['text_entries']['action_taken_status'])->toBe([]);
    expect($summary['text_entries']['findings'])->toBe([]);
    expect($summary['coverage']['filled_slots'])->toBe(2);
});

// =====================================================================
// GROUP G — REKAP PER INSPECTOR (case 32-35)
//
// DI LAYAR, dengan kode MENTAH. Penyimpangan SADAR dari preseden Storage
// Tank (yang docblock-nya menyatakan kolom sejenis 'appear only in the
// export'), diminta pemilik produk 2026-10-16.
// =====================================================================

it('case 32 — rekap per inspector mengelompokkan slot terisi menurut inspector_id', function () {
    $record = processWaterReportRecord($this->stationA, $this->day2, 'PW-1');

    foreach (array_slice($this->slots, 0, 7) as $index => $slot) {
        processWaterReportSlot($record, $slot, ['inspector_id' => 'INS-01', 'raw_water_flow_m3h' => 100.0 + $index]);
    }

    foreach (array_slice($this->slots, 7, 5) as $index => $slot) {
        processWaterReportSlot($record, $slot, ['inspector_id' => 'INS-02', 'raw_water_flow_m3h' => 200.0 + $index]);
    }

    $this->actingAs($this->supervisorA);

    $recap = $this->service->buildSummary($this->periodA, null, $this->lineA)['inspector_recap'];

    expect(array_keys($recap))->toBe(['entries', 'not_recorded', 'recorded_slots']);
    expect($recap['entries'])->toBe([
        ['inspector_id' => 'INS-01', 'filled_slots' => 7],
        ['inspector_id' => 'INS-02', 'filled_slots' => 5],
    ]);
    expect($recap['recorded_slots'])->toBe(12);
    expect($recap['not_recorded'])->toBe(0);

    // Invarian yang membuktikan rekapnya: sum(entries) + not_recorded ===
    // recorded_slots.
    expect(collect($recap['entries'])->sum('filled_slots') + $recap['not_recorded'])
        ->toBe($recap['recorded_slots']);
});

it('case 33 — slot terisi tanpa inspector_id masuk not_recorded yang TERPISAH', function () {
    $record = processWaterReportRecord($this->stationA, $this->day2, 'PW-1');

    foreach (array_slice($this->slots, 0, 7) as $index => $slot) {
        processWaterReportSlot($record, $slot, ['inspector_id' => 'INS-01', 'raw_water_flow_m3h' => 100.0 + $index]);
    }

    foreach (array_slice($this->slots, 7, 5) as $index => $slot) {
        processWaterReportSlot($record, $slot, ['raw_water_flow_m3h' => 200.0 + $index]);
    }

    $this->actingAs($this->supervisorA);

    $recap = $this->service->buildSummary($this->periodA, null, $this->lineA)['inspector_recap'];

    expect($recap['entries'])->toBe([['inspector_id' => 'INS-01', 'filled_slots' => 7]]);
    // KUNCI TERPISAH, tidak pernah dilipat ke salah satu entri.
    expect($recap['not_recorded'])->toBe(5);
    // PENYEBUT PORSINYA recorded_slots — bukan 7, dan bukan expected_slots.
    expect($recap['recorded_slots'])->toBe(12);
    expect(collect($recap['entries'])->sum('filled_slots') + $recap['not_recorded'])
        ->toBe($recap['recorded_slots']);
});

it('case 34 — seluruh slot tanpa inspector menghasilkan entries kosong, bukan rekap yang hilang', function () {
    $record = processWaterReportRecord($this->stationA, $this->day2, 'PW-1');

    foreach (array_slice($this->slots, 0, 18) as $index => $slot) {
        processWaterReportSlot($record, $slot, ['raw_water_flow_m3h' => 100.0 + $index]);
    }

    $this->actingAs($this->supervisorA);

    $recap = $this->service->buildSummary($this->periodA, null, $this->lineA)['inspector_recap'];

    // Rekapnya TETAP diterbitkan, sehingga layar dapat MENYATAKAN keadaannya
    // alih-alih merender rekap kosong tanpa penjelasan.
    expect($recap['entries'])->toBe([]);
    expect($recap['not_recorded'])->toBe(18);
    expect($recap['recorded_slots'])->toBe(18);
});

it('case 35 — inspector_id diterbitkan apa adanya tanpa pemetaan; NOL join ke users', function () {
    $record = processWaterReportRecord($this->stationA, $this->day2, 'PW-1');

    // Kode yang tidak dikenal master mana pun, dan format yang TIDAK seragam
    // — 'orang-baru-123' bersebelahan dengan '1180734' dan ' INS-01 '.
    processWaterReportSlot($record, $this->slots[0], ['inspector_id' => 'orang-baru-123', 'raw_water_flow_m3h' => 100.0]);
    processWaterReportSlot($record, $this->slots[1], ['inspector_id' => '1180734', 'raw_water_flow_m3h' => 110.0]);
    processWaterReportSlot($record, $this->slots[2], ['inspector_id' => ' INS-01 ', 'raw_water_flow_m3h' => 120.0]);
    processWaterReportSlot($record, $this->slots[3], ['inspector_id' => 'INS-01', 'raw_water_flow_m3h' => 130.0]);

    $this->actingAs($this->supervisorA);

    $queries = processWaterReportQueriesDuring(function () {
        $recap = $this->service->buildSummary($this->periodA, null, $this->lineA)['inspector_recap'];

        // Terurut menurut KODE, bukan menurut jumlah slot: mengurutkan
        // menurut jumlah membuat tabel ini terbaca sebagai papan peringkat.
        expect($recap['entries'])->toBe([
            ['inspector_id' => '1180734', 'filled_slots' => 1],
            ['inspector_id' => 'INS-01', 'filled_slots' => 2],
            ['inspector_id' => 'orang-baru-123', 'filled_slots' => 1],
        ]);
    });

    // NOL join ke users dan nol percobaan memulangkan nama — kolomnya teks
    // bebas tanpa relasi, jadi tidak ada pemetaan untuk divalidasi maupun
    // ditebak (keputusan pemilik produk 2026-10-16).
    foreach ($queries as $sql) {
        expect($sql)->not->toContain('join "users"');
        expect($sql)->not->toContain('from "users"');
    }

    // Dan PENYIMPANGAN dari preseden Storage Tank itu disengaja: rekap ini
    // TIDAK boleh menjadi ekspor-saja. Diasersi atas kuncinya di payload
    // /summary, karena itulah yang dibaca layar.
    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);
    expect($summary)->toHaveKey('inspector_recap');
});

// =====================================================================
// GROUP H — KETIADAAN YANG DISENGAJA (case 36-37)
// =====================================================================

it('case 36 — payload tidak memuat satu pun kunci penandaan di luar batas', function () {
    $record = processWaterReportRecord($this->stationA, $this->day2, 'PW-1');

    // Nilai SENGAJA jauh dari nominal yang dikenal SANGAT luas DI LUAR
    // aplikasi: pH air umpan boiler 4,2 dan TDS 6000. Di sinilah godaan
    // menandai paling besar di antara seluruh laporan stasiun.
    processWaterReportSlot($record, $this->slots[0], [
        'boiler_feed_water_ph' => 4.2,
        'boiler_feed_tds_ppm' => 6000.0,
    ]);
    processWaterReportSlot($record, $this->slots[1], [
        'boiler_feed_water_ph' => 11.8,
        'boiler_feed_tds_ppm' => 12.0,
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Nilainya diterbitkan APA ADANYA.
    expect(processWaterReportMetric($summary, 'boiler_feed_water_ph')['min'])->toBe(4.2);
    expect(processWaterReportMetric($summary, 'boiler_feed_tds_ppm')['max'])->toBe(6000.0);

    // Dan NOL kunci penandaan di mana pun — diasersi atas NAMA KUNCI, karena
    // `_note_absences` membahas ketiadaan itu dengan kalimat manusia.
    $keys = processWaterReportAllKeys($summary);

    foreach ([
        'out_of_range', 'is_out_of_range', 'threshold', 'thresholds', 'target',
        'targets', 'violation', 'violations', 'severity', 'flag', 'flags',
        'warning', 'warnings', 'breach', 'exceeds', 'critical_limit',
        'nominal', 'safe_range', 'anomaly', 'anomalies',
    ] as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
    }

    // Bentuk entri metrik pun TETAP lima kunci: tidak ada kunci penanda yang
    // menyelinap per-metrik.
    foreach ($summary['metrics'] as $column => $metric) {
        expect(array_keys($metric))->toBe(['min', 'avg', 'max', 'reading_count', 'group']);
    }
});

it('case 37 — payload tidak memuat blok downtime maupun kunci shift', function () {
    $record = processWaterReportRecord($this->stationA, $this->day2, 'PW-1');

    // `shift` TERISI di basis data, justru supaya ketiadaannya di payload
    // tidak lolos hanya karena kolomnya kosong.
    processWaterReportSlot($record, $this->slots[0], array_fill_keys(
        ProcessWaterReportService::NUMERIC_METRICS,
        7.5,
    ) + ['shift' => 'Shift 1', 'inspector_id' => 'INS-01']);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);
    $keys = processWaterReportAllKeys($summary);

    foreach ([
        'downtime', 'downtime_minutes', 'downtime_events', 'total_minutes',
        'stop_events', 'stoppages',
        'shift', 'shifts', 'by_shift', 'shift_distribution', 'per_shift',
    ] as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
    }

    // process_water_details memang tidak punya satu pun kolom downtime —
    // diasersi atas skemanya, bukan atas payload saja, supaya kasus ini tidak
    // menjadi tautologi.
    foreach (ProcessWaterRecordService::DETAIL_FIELDS as $field) {
        expect($field)->not->toContain('downtime');
    }

    // Dan `shift` TETAP ADA di ekspor: pengecualiannya dari pemeriksaan slot
    // terisi bukan alasan membuangnya dari berkas (lihat case 45).
    expect(ProcessWaterRecordService::DETAIL_FIELDS[0])->toBe('shift');
});

// =====================================================================
// GROUP I — AGREGASI DI PHP, KEANGGOTAAN PERIODE, PENYARINGAN LINE
//           (case 38-40)
// =====================================================================

it('case 38 — seluruh agregasi dijalankan di PHP tanpa agregat SQL', function () {
    $record = processWaterReportRecord($this->stationA, $this->day2, 'PW-1');

    foreach (array_slice($this->slots, 0, 4) as $index => $slot) {
        processWaterReportSlot($record, $slot, ['raw_water_flow_m3h' => 100.0 + $index]);
    }

    $this->actingAs($this->supervisorA);

    $queries = processWaterReportQueriesDuring(function () {
        $this->service->buildSummary($this->periodA, null, $this->lineA);
    });

    $recordQueries = array_values(array_filter(
        $queries,
        fn (string $sql) => str_contains($sql, 'from "process_water_records"'),
    ));

    // SATU kueri record, plus eager load detail — bukan satu kueri per
    // tanggal, per unit, atau per metrik.
    expect($recordQueries)->toHaveCount(1);
    expect(array_values(array_filter(
        $queries,
        fn (string $sql) => str_contains($sql, 'from "process_water_details"'),
    )))->toHaveCount(1);

    // NOL agregat SQL atas kolom nullable: perilaku agregat SQL atas kolom
    // nullable TIDAK identik antara SQLite (suite ini) dan PostgreSQL
    // (produksi), dan aturan penyebut-sendiri serta null-bukan-nol justru
    // yang hilang dalam perbedaan itu.
    foreach ($queries as $sql) {
        if (! str_contains($sql, 'process_water')) {
            continue;
        }

        foreach (['avg(', 'sum(', 'min(', 'max(', 'count(', 'group by'] as $aggregate) {
            $this->assertStringNotContainsString($aggregate, $sql, "agregat SQL {$aggregate} dipakai: {$sql}");
        }
    }

    // Dan sumbernya tidak memakai SATU PUN ekspresi SQL mentah (komentar
    // dibuang supaya prosa docblock tidak ikut terbaca).
    //
    // `groupBy(` SENGAJA TIDAK DI DAFTAR INI: service memang memakainya —
    // atas Illuminate\Support\Collection, DI PHP, yang justru inti aturan
    // ini. Melarang namanya akan melarang implementasi yang benar dan
    // mendorong yang salah.
    $source = processWaterReportStrippedSource('app/Services/ProcessWaterReportService.php');

    foreach (['selectRaw(', 'DB::raw(', 'whereRaw(', 'havingRaw(', 'orderByRaw('] as $raw) {
        $this->assertStringNotContainsString($raw, $source, "service memakai {$raw}");
    }

    // Dan pengelompokan yang ADA memang pengelompokan Collection: ia
    // dijalankan atas hasil yang sudah terbaca, bukan dikirim ke SQL. Kedua
    // pemanggilannya (per tanggal, per unit) dibuktikan atas kuerinya di
    // atas — nol 'group by' pada SQL mana pun yang menyentuh process_water.
    $this->assertStringContainsString("groupBy('date')", $source);
    $this->assertStringContainsString("groupBy('process_water_id')", $source);
});

it('case 39 — keanggotaan periode inklusif di kedua ujung tanggal', function () {
    // Record TEPAT pada tanggal mulai dan tanggal akhir, plus satu hari
    // sebelum dan satu hari sesudah. TANGGALNYA DIPAKU DI SINI, bukan
    // dibiarkan default factory: ProcessWaterRecordFactory memakai
    // dateTimeBetween('-30 days','now') kalau dibiarkan, dan "tepat N record"
    // lalu gagal sesekali karena alasan yang tidak ada hubungannya.
    $awal = processWaterReportRecord($this->stationA, $this->periodStart, 'PW-AWAL');
    processWaterReportSlot($awal, '07:00', ['raw_water_flow_m3h' => 100.0]);

    $akhir = processWaterReportRecord($this->stationA, $this->periodEnd, 'PW-AKHIR');
    processWaterReportSlot($akhir, '07:00', ['raw_water_flow_m3h' => 200.0]);

    $sebelum = processWaterReportRecord($this->stationA, $this->dayBeforePeriod, 'PW-SEBELUM');
    processWaterReportSlot($sebelum, '07:00', ['raw_water_flow_m3h' => 9999.0]);

    $sesudah = processWaterReportRecord($this->stationA, $this->dayAfterPeriod, 'PW-SESUDAH');
    processWaterReportSlot($sesudah, '07:00', ['raw_water_flow_m3h' => 8888.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // KEDUA UJUNG ikut, keduanya di luar TIDAK.
    expect($summary['coverage']['filled_slots'])->toBe(2);
    expect(processWaterReportMetric($summary, 'raw_water_flow_m3h')['reading_count'])->toBe(2);
    expect(processWaterReportMetric($summary, 'raw_water_flow_m3h')['min'])->toBe(100.0);
    expect(processWaterReportMetric($summary, 'raw_water_flow_m3h')['max'])->toBe(200.0);

    expect(collect($summary['daily'])->pluck('date')->all())->toBe([$this->periodStart, $this->periodEnd]);
    expect(collect($summary['by_unit'])->pluck('process_water_id')->all())->toBe(['PW-AKHIR', 'PW-AWAL']);

    // Dan ekspornya mengikuti keanggotaan yang sama persis.
    $csv = processWaterReportStreamed($this->service->export($this->periodA, 'csv', null, $this->lineA));

    expect($csv)->toContain('PW-AWAL');
    expect($csv)->toContain('PW-AKHIR');
    expect($csv)->not->toContain('PW-SEBELUM');
    expect($csv)->not->toContain('PW-SESUDAH');

    // Memakai whereDate pada kolom `date`, konvensi yang sama dengan seluruh
    // laporan lain — satu ejaan keanggotaan periode, bukan satu per laporan.
    $queries = processWaterReportQueriesDuring(function () {
        $this->service->buildSummary($this->periodA, null, $this->lineA);
    });

    $recordSql = collect($queries)->first(fn (string $sql) => str_contains($sql, 'from "process_water_records"'));

    $this->assertStringContainsString('process_water_records"."date"', $recordSql);
});

it('case 40 — penyaringan line memakai kolom production_line_id milik record sendiri', function () {
    // DUA line di dalam MILL YANG SAMA. Stasiun kedua tertaut line B-dalam
    // mill A, tetapi recordnya menyimpan production_line_id line A — itulah
    // keadaan sesudah sebuah stasiun DIPINDAH ke line lain.
    $lineKedua = ProductionLine::factory()->forBusinessUnit($this->businessUnitA)->create(['name' => 'Line Kedua']);
    $stationDipindah = Station::factory()->forProductionLine($lineKedua)->processWater()->create();

    $record = ProcessWaterRecord::factory()
        ->forStation($stationDipindah)
        ->onDate($this->day2)
        ->create([
            'process_water_id' => 'PW-DIPINDAH',
            // Snapshot line saat record dibuat — line A, bukan line stasiun
            // itu SEKARANG.
            'production_line_id' => $this->lineA,
        ]);

    processWaterReportSlot($record, '07:00', ['raw_water_flow_m3h' => 150.0]);

    $this->actingAs($this->supervisorA);

    // Record muncul pada laporan line A …
    $onLineA = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($onLineA['coverage']['filled_slots'])->toBe(1);
    expect(collect($onLineA['by_unit'])->pluck('process_water_id')->all())->toBe(['PW-DIPINDAH']);

    // … dan TIDAK pada laporan line stasiunnya sekarang: menyaring lewat join
    // ke `stations` akan menulis ulang sejarah setiap kali stasiun dipindah.
    $onLineKedua = $this->service->buildSummary($this->periodA, null, (string) $lineKedua->id);

    expect($onLineKedua['coverage']['filled_slots'])->toBe(0);
    expect($onLineKedua['by_unit'])->toBe([]);

    // Diasersi juga atas SQL-nya: penyaringnya kolom record, bukan kolom
    // stations.
    $queries = processWaterReportQueriesDuring(function () {
        $this->service->buildSummary($this->periodA, null, $this->lineA);
    });

    $recordSql = collect($queries)->first(fn (string $sql) => str_contains($sql, 'from "process_water_records"'));

    $this->assertStringContainsString('"process_water_records"."production_line_id" = ?', $recordSql);
    $this->assertStringNotContainsString('"stations"."production_line_id"', $recordSql);
});

// =====================================================================
// GROUP J — KELENGKAPAN, REKAP PER UNIT, TREN HARIAN (case 41-44)
// =====================================================================

it('case 41 — expected_slots = unit × hari terhitung × 24 dari canonicalTimeSlots()', function () {
    // Periode 5 hari yang SUDAH SELESAI, sehingga days_counted === 5 secara
    // deterministik tanpa menyentuh jam sistem.
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::ProcessWater->value)
        ->range(now()->subDays(10)->toDateString(), now()->subDays(6)->toDateString())
        ->open()
        ->named('Periode Lima Hari')
        ->create();

    foreach (['PW-1', 'PW-2'] as $unit) {
        processWaterReportSlot(
            processWaterReportRecord($this->stationA, now()->subDays(9)->toDateString(), $unit),
            '07:00',
            ['raw_water_flow_m3h' => 120.0],
        );
    }

    $this->actingAs($this->supervisorA);

    $coverage = $this->service->buildSummary($period, null, $this->lineA)['coverage'];

    expect($coverage['process_water_unit_count'])->toBe(2);
    expect($coverage['days_counted'])->toBe(5);
    expect($coverage['days_in_period'])->toBe(5);
    // 24 datang dari count(canonicalTimeSlots()) — satu definisi, satu
    // jawaban, dan tidak ditulis keras di dua tempat.
    expect($coverage['slots_per_unit_per_day'])->toBe(count(ProcessWaterRecordService::canonicalTimeSlots()));
    expect($coverage['slots_per_unit_per_day'])->toBe(24);
    expect($coverage['expected_slots'])->toBe(2 * 5 * 24);
    expect($coverage['expected_slots'])->toBe(240);
    expect($coverage['coverage_percent'])->toBe(round(100 * 2 / 240, 1));
});

it('case 42 — hari terhitung berhenti di hari ini; periode belum mulai menghasilkan nol', function () {
    // Periode yang MASIH BERJALAN: mulai 9 hari lalu, berakhir 20 hari lagi.
    // Hari yang sudah lewat = 10 (termasuk hari ini).
    $running = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::ProcessWater->value)
        ->range(now()->subDays(9)->toDateString(), now()->addDays(20)->toDateString())
        ->open()
        ->named('Periode Berjalan')
        ->create();

    processWaterReportSlot(
        processWaterReportRecord($this->stationA, now()->subDays(8)->toDateString(), 'PW-1'),
        '07:00',
        ['raw_water_flow_m3h' => 120.0],
    );

    $this->actingAs($this->supervisorA);

    $coverage = $this->service->buildSummary($running, null, $this->lineA)['coverage'];

    expect($coverage['period_running'])->toBeTrue();
    expect($coverage['days_in_period'])->toBe(30);
    // BERHENTI DI HARI INI: hari yang belum terjadi tidak mungkin tercatat.
    expect($coverage['days_counted'])->toBe(10);
    expect($coverage['expected_slots'])->toBe(1 * 10 * 24);
    expect($coverage['expected_slots'])->toBe(240);

    // Periode yang BELUM MULAI: nol hari terhitung, nol slot diharapkan,
    // TANPA pembagian nol, dan coverage_percent 0.0 (layar menampilkan '—').
    $future = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::ProcessWater->value)
        ->range(now()->addDays(40)->toDateString(), now()->addDays(50)->toDateString())
        ->open()
        ->named('Periode Belum Mulai')
        ->create();

    $futureCoverage = $this->service->buildSummary($future, null, $this->lineA)['coverage'];

    expect($futureCoverage['days_counted'])->toBe(0);
    expect($futureCoverage['expected_slots'])->toBe(0);
    expect($futureCoverage['coverage_percent'])->toBe(0.0);
    expect($futureCoverage['filled_slots'])->toBe(0);
});

it('case 43 — rekap per unit dikelompokkan pada process_water_id', function () {
    // Unit pertama punya pembacaan; unit kedua punya record TANPA satu pun
    // baris terisi.
    $satu = processWaterReportRecord($this->stationA, $this->day2, 'PW-1');
    processWaterReportSlot($satu, '07:00', ['raw_water_flow_m3h' => 100.0]);
    processWaterReportSlot($satu, '08:00', ['raw_water_flow_m3h' => 140.0]);

    $dua = processWaterReportRecord($this->stationA, $this->day2, 'PW-2');
    processWaterReportSlot($dua, '07:00', ['shift' => 'Shift 1']);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['by_unit'])->toHaveCount(2);

    $byUnit = collect($summary['by_unit'])->keyBy('process_water_id');

    expect($byUnit['PW-1']['reading_rows'])->toBe(2);
    // Unit tanpa pembacaan TETAP MUNCUL dengan reading_rows 0 — membuangnya
    // akan menyembunyikan tepat unit yang tidak pernah ditulis.
    expect($byUnit['PW-2']['reading_rows'])->toBe(0);
    expect($byUnit['PW-2']['metrics'])->toHaveCount(9);
    expect($byUnit['PW-2']['metrics']['raw_water_flow_m3h']['avg'])->toBeNull();

    // … dan ikut menambah process_water_unit_count sehingga expected_slots
    // tetap benar.
    expect($summary['coverage']['process_water_unit_count'])->toBe(2);
    expect($summary['coverage']['expected_slots'])->toBe(2 * 10 * 24);

    // Satu definisi, satu jawaban: slot per unit dan slot per hari keduanya
    // menjumlah ke coverage.filled_slots.
    expect(collect($summary['by_unit'])->sum('reading_rows'))->toBe($summary['coverage']['filled_slots']);
    expect(collect($summary['daily'])->sum('reading_rows'))->toBe($summary['coverage']['filled_slots']);
    expect($summary['daily_total']['reading_rows'])->toBe($summary['coverage']['filled_slots']);
});

it('case 44 — tren harian memakai rata-rata raw_water_flow_m3h per tanggal', function () {
    $record = processWaterReportRecord($this->stationA, $this->day3, 'PW-1');

    processWaterReportSlot($record, $this->slots[0], ['raw_water_flow_m3h' => 600.0]);
    processWaterReportSlot($record, $this->slots[1], ['raw_water_flow_m3h' => 800.0]);
    processWaterReportSlot($record, $this->slots[2], ['raw_water_flow_m3h' => 1000.0]);

    // Tanggal lain tanpa kolom itu: titiknya avg null, BUKAN nol.
    $lain = processWaterReportRecord($this->stationA, $this->day4, 'PW-1');
    processWaterReportSlot($lain, $this->slots[0], ['softener_inlet_ph' => 7.2]);

    $this->actingAs($this->supervisorA);

    $trend = $this->service->buildSummary($this->periodA, null, $this->lineA)['daily_trend'];

    expect(array_keys($trend))->toBe(['raw_water_flow_m3h']);
    expect($trend['raw_water_flow_m3h'])->toHaveCount(2);

    $points = collect($trend['raw_water_flow_m3h'])->keyBy('date');

    expect($points[$this->day3]['avg'])->toBe(800.0);
    expect($points[$this->day3]['reading_count'])->toBe(3);
    // Tanggal yang tidak pernah mengisi kolom tergrafik: avg NULL, bukan 0.
    expect($points[$this->day4]['avg'])->toBeNull();
    expect($points[$this->day4]['reading_count'])->toBe(0);

    expect(ProcessWaterReportService::TREND_METRICS)->toBe(['raw_water_flow_m3h']);
});

// =====================================================================
// GROUP K — EKSPOR (case 45-49)
// =====================================================================

it('case 45 — ekspor memuat KE-13 kolom detail termasuk shift dan inspector_id', function () {
    foreach ([['PW-1', $this->day1], ['PW-2', $this->day1], ['PW-1', $this->day2]] as [$unit, $date]) {
        $record = processWaterReportRecord($this->stationA, $date, $unit);

        processWaterReportSlot($record, '07:00', [
            'shift' => 'Shift 1',
            'inspector_id' => 'INS-01',
            'raw_water_flow_m3h' => 120.0,
            'findings' => 'Normal',
        ]);
    }

    $this->actingAs($this->supervisorA);

    $rows = iterator_to_array($this->service->buildExportRows($this->periodA, null, $this->lineA));

    // Header + 3 baris slot.
    expect($rows)->toHaveCount(3);

    // 7 kolom konteks + Slot Waktu + 13 kolom detail = 21.
    expect(ProcessWaterReportService::EXPORT_HEADER)->toHaveCount(21);
    expect(ProcessWaterRecordService::DETAIL_FIELDS)->toHaveCount(13);

    foreach ($rows as $row) {
        expect($row)->toHaveCount(count(ProcessWaterReportService::EXPORT_HEADER));
        // Kolom konteks diulang VERBATIM di setiap baris.
        expect($row[0])->toBe('Periode Process Water Alpha');
        expect($row[1])->toBe('Mill Alpha');
    }

    // KETIGA BELAS kolom detail dalam urutan DETAIL_FIELDS, mulai indeks 8
    // (sesudah 7 konteks + Slot Waktu). shift dan inspector_id LEBIH DULU.
    $detailOffset = 8;

    expect(ProcessWaterReportService::EXPORT_HEADER[$detailOffset])->toBe('Shift');
    expect(ProcessWaterReportService::EXPORT_HEADER[$detailOffset + 1])->toBe('Inspector ID');
    expect($rows[0][7])->toBe('07:00');
    expect($rows[0][$detailOffset])->toBe('Shift 1');
    expect($rows[0][$detailOffset + 1])->toBe('INS-01');

    foreach (ProcessWaterRecordService::DETAIL_FIELDS as $index => $field) {
        expect(ProcessWaterReportService::EXPORT_HEADER)
            ->toHaveKey($detailOffset + $index);
    }

    // Dan keduanya BENAR-BENAR ada di berkas: pengecualian kedua kolom
    // identitas dari pemeriksaan slot terisi BUKAN alasan membuangnya dari
    // ekspor, karena ekspor adalah rincian mentah.
    $csv = processWaterReportStreamed($this->service->export($this->periodA, 'csv', null, $this->lineA));

    expect($csv)->toContain('Shift');
    expect($csv)->toContain('Inspector ID');
    expect($csv)->toContain('Shift 1');
    expect($csv)->toContain('INS-01');
    // Sel kosong tetap KOSONG — bukan 0, bukan '-'.
    expect($csv)->not->toContain(',-,');
});

it('case 46 — ExportValue::option() TIDAK DIPAKAI SAMA SEKALI di laporan ini', function () {
    // DIASERSI ATAS SUMBER YANG KOMENTARNYA DIBUANG. Docblock ketiga berkas
    // implementasi MEMANG mengeja '::option()' ketika menjelaskan mengapa
    // pemeta itu tidak dipakai di stasiun ini — jadi grep mentah akan memerah
    // pada prosa yang justru membuktikan klaimnya, dan menanyakan pertanyaan
    // yang salah. Yang ditanyakan: apakah KODE-nya memanggilnya.
    foreach ([
        'app/Services/ProcessWaterReportService.php',
        'app/Http/Controllers/Api/ProcessWaterReportController.php',
        'app/Livewire/Dashboard/LaporanProcessWater.php',
    ] as $file) {
        $code = processWaterReportStrippedSource($file);

        $this->assertStringNotContainsString('::option(', $code, "{$file} memanggil ExportValue::option()");
        $this->assertStringNotContainsString('OPTION_LABELS', $code, "{$file} memakai Display::OPTION_LABELS");
    }

    // Blade tidak memanggilnya pula.
    foreach ([
        'resources/views/livewire/dashboard/laporan-process-water.blade.php',
        'resources/views/dashboard/laporan-process-water.blade.php',
    ] as $view) {
        $this->assertStringNotContainsString('::option(', file_get_contents(base_path($view)), "{$view} memanggil ExportValue::option()");
    }

    // KEDUA pemeta yang MEMANG dipakai: status() untuk status RECORD, dan
    // time() untuk label slot. Stasiun ini tidak punya kolom enum pengukuran,
    // jadi jebakan tukar status()/option() tidak berlaku di sini.
    $service = processWaterReportStrippedSource('app/Services/ProcessWaterReportService.php');

    $this->assertStringContainsString('ExportValue::status(', $service);
    $this->assertStringContainsString('ExportValue::time(', $service);

    // Dibuktikan juga atas hasilnya: kolom Status konteks memuat label status
    // RECORD, bukan label pilihan apa pun.
    $record = processWaterReportRecord($this->stationA, $this->day2, 'PW-1', ['status' => 'synced']);
    processWaterReportSlot($record, '07:00', ['raw_water_flow_m3h' => 120.0]);

    $this->actingAs($this->supervisorA);

    $rows = iterator_to_array($this->service->buildExportRows($this->periodA, null, $this->lineA));

    expect($rows)->toHaveCount(1);
    expect($rows[0][5])->toBe('Tersinkron');
    expect($rows[0][7])->toBe('07:00');
});

it('case 47 — ekspor lebih dari batas baris ditolak 422 EXPORT_FAILED, dihitung atas BARIS SLOT', function () {
    // Batasnya diturunkan ke 5 lewat subclass — mungkin HANYA karena service
    // membacanya lewat `static::`, bukan `self::`. Itulah yang subclass ini
    // ada untuk mengujinya.
    $tiny = new ProcessWaterReportTinyExportService;

    $record = processWaterReportRecord($this->stationA, $this->day2, 'PW-1');

    foreach (array_slice($this->slots, 0, 5) as $slot) {
        processWaterReportSlot($record, $slot, ['raw_water_flow_m3h' => 120.0]);
    }

    $this->actingAs($this->supervisorA);

    // TEPAT di batas: ekspor tetap berjalan (lebih besar SECARA KETAT).
    expect(iterator_to_array($tiny->buildExportRows($this->periodA, null, $this->lineA)))->toHaveCount(5);

    // SATU baris slot lagi — dan ia baris SLOT, bukan record: satu record
    // dapat menghasilkan sampai 24 baris, jadi menghitung record akan
    // melampaui batas 24 kali.
    processWaterReportSlot($record, $this->slots[5], ['raw_water_flow_m3h' => 130.0]);

    expect(ProcessWaterRecord::query()->count())->toBe(1);
    expect(ProcessWaterDetail::query()->count())->toBe(6);

    expect(fn () => $tiny->buildExportRows($this->periodA, null, $this->lineA))
        ->toThrow(ExportFailedException::class);
    expect(fn () => $tiny->export($this->periodA, 'csv', null, $this->lineA))
        ->toThrow(ExportFailedException::class);

    // Batas sungguhannya tetap 50.000.
    expect(ProcessWaterReportService::EXPORT_ROW_LIMIT)->toBe(50000);
});

it('case 48 — format di luar csv|excel ditolak; format kosong jatuh ke csv', function () {
    $record = processWaterReportRecord($this->stationA, $this->day2, 'PW-1');
    processWaterReportSlot($record, '07:00', ['raw_water_flow_m3h' => 120.0]);

    $this->actingAs($this->supervisorA);

    try {
        $this->service->export($this->periodA, 'pdf', null, $this->lineA);
        $this->fail('seharusnya melempar ValidationException');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toBe(['format']);
        expect($e->status)->toBe(422);
    }

    // Tanpa parameter format → CSV.
    $csv = $this->service->export($this->periodA, 'csv', null, $this->lineA);

    expect($csv->headers->get('Content-Type'))->toContain('text/csv');

    $excel = $this->service->export($this->periodA, 'excel', null, $this->lineA);

    expect($excel->headers->get('Content-Type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    expect(ProcessWaterReportService::SUPPORTED_FORMATS)->toBe(['csv', 'excel']);
});

it('case 49 — guard dan batas baris diperiksa EAGER sebelum streaming dimulai', function () {
    processWaterReportSlot(
        processWaterReportRecord($this->stationB, $this->day2, 'PW-B'),
        '07:00',
        ['raw_water_flow_m3h' => 9999.0],
    );

    $this->actingAs($this->operatorA);

    // ForbiddenException dilempar SEBELUM response streaming dibuka: klien
    // tidak menerima berkas separuh berisi header. Kalau export() sendiri
    // menjadi generator, 403 ini tertunda sampai iterasi pertama, dan ekspor
    // yang ditolak akan terlihat seperti panggilan sukses yang tidak
    // menghasilkan apa pun.
    expect(fn () => $this->service->export($this->periodA, 'csv', null, $this->lineB))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->buildExportRows($this->periodA, null, $this->lineB))
        ->toThrow(AuthorizationException::class);

    // Dan buildExportRows() memang BUKAN fungsi generator: ia mengeksekusi
    // penjaganya saat dipanggil, lalu MENGEMBALIKAN generator dari metode
    // lain.
    expect((new ReflectionMethod(ProcessWaterReportService::class, 'buildExportRows'))->isGenerator())
        ->toBeFalse();

    // Line absen pun 422 EAGER, bukan berkas kosong.
    expect(fn () => $this->service->export($this->periodA, 'csv', null, null))
        ->toThrow(ValidationException::class);
});

// =====================================================================
// GROUP L — STATUS PERIODE TIDAK MEMBATASI PEMBACAAN (case 52)
//
// Case 50 (mount() komponen) dan case 51 (mengganti mill mengosongkan
// periode DAN line) menyangkut komponen Livewire, bukan service, dan
// dibuktikan di tests/Feature/Livewire/LaporanProcessWaterTest.php dengan
// LABEL KASUS YANG SAMA.
// =====================================================================

it('case 52 — status periode tidak membatasi pembacaan maupun ekspor', function () {
    $closed = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::ProcessWater->value)
        ->range(now()->subDays(40)->toDateString(), now()->subDays(31)->toDateString())
        ->closed($this->admin)
        ->named('Periode Tertutup')
        ->create();

    $record = processWaterReportRecord($this->stationA, now()->subDays(35)->toDateString(), 'PW-TUTUP');
    processWaterReportSlot($record, '07:00', ['raw_water_flow_m3h' => 120.0, 'findings' => 'Normal']);

    $this->actingAs($this->supervisorA);

    // Tetap TERDAFTAR: status tidak pernah menyaring daftar periode.
    expect(collect($this->service->listPeriods(null))->pluck('id'))->toContain((string) $closed->id);

    $summary = $this->service->buildSummary($closed, null, $this->lineA);

    expect($summary['period']['status'])->toBe(PeriodStatus::Closed->value);
    // Payload LENGKAP.
    expect($summary['has_data'])->toBeTrue();
    expect($summary['metrics'])->toHaveCount(9);
    expect($summary['coverage']['filled_slots'])->toBe(1);
    expect($summary['text_entries']['findings'])->toHaveCount(1);

    // Dan jalur ekspornya tetap dapat dijalankan.
    $csv = processWaterReportStreamed($this->service->export($closed, 'csv', null, $this->lineA));

    expect($csv)->toContain('PW-TUTUP');
});

// =====================================================================
// SATU BARIS YANG MENENTUKAN LAYARNYA DAPAT DICAPAI (case 53)
// =====================================================================

it('case 53 — REPORT_ROUTES memetakan process-water, dan posisinya mengikuti sort_order', function () {
    // Tanpa entri 'process-water' pada StationReportService::REPORT_ROUTES,
    // layar laporan ada, seluruh uji lainnya lolos, dan tile-nya tetap
    // kelabu — pola kegagalan yang sudah pernah terjadi pada laporan Cages &
    // Tracks versi web.
    expect(StationReportService::REPORT_ROUTES)
        ->toHaveKey(StationTypeEnum::ProcessWater->value, 'reports.process-water');
    // Kuncinya BERTANDA HUBUNG; kunci snake_case akan ter-compile, tampak
    // benar, dan tidak pernah cocok dengan satu baris master pun.
    expect(StationTypeEnum::ProcessWater->value)->toBe('process-water');
    expect(route('reports.process-water', [], false))->toBe('/reports/process-water');

    // URUTANNYA LOAD-BEARING: peta ini harus tetap urut menurut
    // station_types.sort_order, karena layar pemilih stasiun membandingkan
    // urutannya dengan urutan master. process-water di 130 — DI BELAKANG
    // engine-room (120) dan DI DEPAN storage-tank (140), jadi entrinya
    // DISISIPKAN di antara keduanya dan tidak di-append.
    $codes = array_keys(StationReportService::REPORT_ROUTES);
    $at = array_search(StationTypeEnum::ProcessWater->value, $codes, true);

    expect($at)->not->toBeFalse();
    expect($codes[$at - 1])->toBe(StationTypeEnum::EngineRoom->value);
    expect($codes[$at + 1])->toBe(StationTypeEnum::StorageTank->value);
});
