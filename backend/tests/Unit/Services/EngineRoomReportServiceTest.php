<?php

/**
 * EngineRoomReportServiceTest — screen-158--laporan-engine-room-web,
 * EngineRoomReportService.
 *
 * Satu uji per entri `unit_test_cases` pada tech spec screen-158 (74 kasus),
 * dikelompokkan sama seperti EffluentPlantReportServiceTest supaya keduanya
 * dapat dibaca berdampingan. Tiga kasus (71, 72, 73) menyangkut komponen
 * Livewire dan blade-nya, bukan service, dan dibuktikan di
 * tests/Feature/Livewire/LaporanEngineRoomTest.php dengan LABEL KASUS YANG
 * SAMA — berkas ini menutup kasus 1-70 dan 74, plus satu kasus tambahan (75)
 * untuk SATU BARIS StationReportService::REPORT_ROUTES yang menentukan
 * layarnya dapat dicapai.
 *
 * ────────────────────────────────────────────────────────────────────────
 * LAPORAN TERLEBAR SEJAUH INI, DAN UKURANNYA MENENTUKAN BENTUK UJINYA
 * ────────────────────────────────────────────────────────────────────────
 *   1. DUA PULUH TIGA KOLOM UKUR, TETAPI HANYA DUA PULUH DUA YANG
 *      MENERBITKAN RATA-RATA PERIODE. `daily_energy_export_kwh` TIDAK ada di
 *      `metrics` sama sekali; ia diterbitkan pada kuncinya sendiri
 *      (`daily_energy_export`) sebagai seri NILAI TERAKHIR per tanggal.
 *      Lihat kasus 35 dan 43 untuk pertentangan tech spec yang diselesaikan
 *      di sana.
 *   2. "TERAKHIR" DIAMBIL DARI POSISI SLOT PADA KISI KANONIS (07:00 .. 06:00
 *      keesokan hari), BUKAN dari perbandingan angka jam. 06:00 adalah slot
 *      PALING AKHIR, bukan paling awal — dan urutan leksikal akan
 *      menghasilkan angka yang terlihat sangat masuk akal. Kasus 44 menanam
 *      jebakan itu secara eksplisit.
 *   3. BARIS TERISI = minimal satu dari DUA PULUH TUJUH READING_FIELDS, bukan
 *      dua puluh dua dan bukan dua puluh tiga. Karena itu
 *      coverage.filled_slots DAPAT MELEBIHI penyebut setiap metrik (kasus
 *      31). Yang diasersi adalah KETIDAKSAMAAN-nya.
 *   4. KEDELAPAN KOLOM GENSET TETAP DELAPAN KUNCI TERPISAH. Tidak ada kunci
 *      gabungan / total / selisih / imbalance / rasio untuk keempat pasangan
 *      sejajarnya, dan asersinya atas NAMA KUNCI — bukan atas isi
 *      json_encode, karena `_note_absences` justru MENYEBUT ketiadaan itu
 *      dengan kata-kata yang sama (kasus 49, 50).
 *   5. DUA KOLOM STATUS KATEGORIS DENGAN TIGA NILAI. 'standby' tidak pernah
 *      dilipat ke 'off', dan slot NULL masuk `not_recorded` pada kunci
 *      TERPISAH. Porsinya memakai recorded_slots, bukan expected_slots.
 *   6. TIDAK ADA MASTER TARGET, jadi TIDAK ADA penandaan di luar batas sama
 *      sekali — dan itu berlaku MESKI frekuensi sistem, power factor dan
 *      tegangan busbar punya batas yang dikenal luas di luar aplikasi.
 *
 * Jangan "merapikan" angka-angka fixture di bawah: kolom sengaja diisi pada
 * HIMPUNAN SLOT YANG BERBEDA-BEDA, karena satu penyebut bersama akan
 * menjawab sama persis pada fixture yang rata dan asersinya menjadi kosong.
 *
 * ────────────────────────────────────────────────────────────────────────
 * OPERATOR DITERIMA PADA KETIGA RUTE DATA SEJAK COMMIT PERTAMA
 * ────────────────────────────────────────────────────────────────────────
 * Presedens Grading / Kernel Plant, bukan presedens Weighbridge / Effluent
 * Plant: screen-159--laporan-engine-room-mobile dibangun dalam seri yang
 * sama, jadi pemanggil Operator yang sah sudah ada. Pelebarannya menyentuh
 * TIGA tempat sekaligus — daftar peran middleware routes/api.php,
 * guardAccess(), DAN cabang TERIKAT-MILL resolveBusinessUnit(). KASUS 6 DAN 7
 * adalah penjaga regresi untuk yang ketiga, dan keduanya satu-satunya uji di
 * berkas ini yang gagal bila dua dari tiga dikerjakan: tanpa baris Operator di
 * cabang terikat-mill, Operator jatuh ke cabang ADMIN tempat business_unit_id
 * kiriman klien DIHORMATI — kebocoran lintas mill, bukan cacat tampilan.
 *
 * YANG TETAP TERTUTUP: kasus 1 dan 2 — /business-units/options bagi Operator,
 * Supervisor, dan Mill Management (403 diangkat DARI DALAM service, bukan oleh
 * middleware prefix yang memang menerima empat peran).
 *
 * TANGGAL FIXTURE SELALU RELATIF (now()->subDays(...)), tidak pernah tanggal
 * kalender yang dipaku: periodA di bawah harus SELALU sudah selesai supaya
 * days_counted === days_in_period, dan tanggal yang dipaku berhenti memenuhi
 * syarat itu begitu kalender melewatinya. Tech spec menyebut tanggal kalender
 * (2026-03-xx) pada beberapa kasus; yang dipertahankan di sini adalah
 * MAKSUDNYA, bukan digitnya.
 *
 * Suite ini berjalan di SQLite in-memory sementara produksi PostgreSQL —
 * alasan yang sama mengapa service-nya mengagregasi di PHP (kasus 42) dan
 * mengapa penyaring periode diasersi di UJUNG-ujungnya (kasus 33).
 */

use App\Enums\RecordStatus;
use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Models\BusinessUnit;
use App\Models\EngineRoomDetail;
use App\Models\EngineRoomRecord;
use App\Models\Period;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use App\Services\EngineRoomRecordService;
use App\Services\EngineRoomReportService;
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
 * EffluentPlantReportAllBusinessUnitsSpy.
 */
class EngineRoomReportAllBusinessUnitsSpy extends EngineRoomReportService
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
class EngineRoomReportTinyExportService extends EngineRoomReportService
{
    public const EXPORT_ROW_LIMIT = 5;
}

/** Satu record harian untuk satu unit Engine Room. */
function engineRoomReportRecord(
    Station $station,
    string $date,
    string $engineRoomId = 'ER-1',
    array $attributes = [],
): EngineRoomRecord {
    return EngineRoomRecord::factory()
        ->forStation($station)
        ->onDate($date)
        ->create(array_merge([
            'engine_room_id' => $engineRoomId,
            'note' => 'Catatan harian',
        ], $attributes));
}

/** Satu baris slot waktu. KEDUA PULUH TUJUH kolom bacaan default-nya null. */
function engineRoomReportSlot(
    EngineRoomRecord $record,
    string $timeSlot,
    array $values = [],
): EngineRoomDetail {
    return EngineRoomDetail::factory()
        ->forRecord($record)
        ->timeSlot($timeSlot)
        ->create($values);
}

/** 24 slot kanonis, dalam urutan layar input (07:00..06:00 keesokan hari). */
function engineRoomReportSlots(): array
{
    return EngineRoomRecordService::canonicalTimeSlots();
}

/** Setiap pernyataan SQL yang berjalan di dalam $callback. */
function engineRoomReportQueriesDuring(callable $callback): array
{
    $queries = [];

    DB::listen(function ($query) use (&$queries) {
        $queries[] = strtolower($query->sql);
    });

    $callback();

    return $queries;
}

function engineRoomReportStreamed(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

/** Entri metrics[] untuk satu kolom, dikunci NAMA KOLOM. */
function engineRoomReportMetric(array $summary, string $column): array
{
    expect($summary['metrics'])->toHaveKey($column);

    return $summary['metrics'][$column];
}

/**
 * Seluruh nama KUNCI yang muncul di mana pun di dalam struktur.
 *
 * DIPAKAI ALIH-ALIH MEMINDAI json_encode: `_note_absences` pada payload ini
 * MENYEBUTKAN ketiadaan downtime, ambang batas, dan selisih genset dengan
 * kata-kata yang persis sama dengan yang dicari, jadi pemindaian teks akan
 * gagal pada kalimat yang justru membuktikan klaimnya.
 *
 * @return list<string>
 */
function engineRoomReportAllKeys(mixed $value): array
{
    if (! is_array($value)) {
        return [];
    }

    $keys = [];

    foreach ($value as $key => $child) {
        if (is_string($key)) {
            $keys[] = $key;
        }

        $keys = array_merge($keys, engineRoomReportAllKeys($child));
    }

    return array_values(array_unique($keys));
}

/**
 * Nama kunci yang TIDAK BOLEH ADA di mana pun pada payload.
 *
 * SENGAJA TIDAK MEMUAT kunci yang memang ada dan sah — 'avg', 'min', 'max',
 * 'total', 'date', 'status', 'counts', 'series', 'group' — karena daftar ini
 * dicocokkan sebagai NAMA KUNCI yang utuh.
 *
 * @return list<string>
 */
function engineRoomReportForbiddenKeys(): array
{
    return [
        // Penandaan di luar batas — stasiun ini tidak punya master target.
        'out_of_range', 'is_out_of_range', 'threshold', 'thresholds', 'target',
        'targets', 'violation', 'violations', 'severity', 'flag', 'flags',
        'warning', 'warnings', 'breach', 'exceeds', 'critical_limit',
        'nominal', 'safe_range', 'anomaly', 'anomalies',
        // Blok downtime — engine_room_details tidak punya satu pun kolomnya.
        'downtime', 'downtime_minutes', 'downtime_events', 'total_minutes',
        'stop_events', 'stoppages',
        // Penggabungan kedua genset — kedelapan kolomnya tetap berdiri
        // sendiri.
        'delta', 'diff', 'difference', 'imbalance', 'deviation', 'ratio',
        'combined', 'combined_avg', 'genset_total', 'genset_combined',
        'load_share', 'load_ratio', 'shares_standard_with',
        // Rata-rata periode untuk kolom ekspor energi — tidak ada sama
        // sekali, di bucket mana pun.
        'daily_energy_export_kwh', 'energy_export_avg', 'average_energy_export',
        'total_energy_export', 'energy_export_total',
    ];
}

beforeEach(function () {
    $this->service = new EngineRoomReportService;

    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->engineRoom()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->engineRoom()->create();

    $this->lineA = (string) $this->stationA->production_line_id;
    $this->lineB = (string) $this->stationB->production_line_id;

    $this->supervisorA = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->supervisorB = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitB)->create();
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
        ->stationType(StationTypeEnum::EngineRoom->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Engine Room Alpha')
        ->create();

    $this->slots = engineRoomReportSlots();
});

// =====================================================================
// GROUP A — PEMILIH MILL: ADMIN SAJA (case 1-4)
//
// Penjagaannya ada DI DALAM service, bukan pada daftar peran middleware:
// prefix /api/engine-room-reports menerima EMPAT peran demi ketiga rute
// data, jadi penjaga di middleware tidak akan menutup endpoint ini sama
// sekali.
// =====================================================================

it('case 1 — 403 pada /business-units/options bagi Operator, dan master mill tidak pernah dibaca', function () {
    $this->actingAs($this->operatorA);

    $spy = new EngineRoomReportAllBusinessUnitsSpy;

    $queries = engineRoomReportQueriesDuring(function () use ($spy) {
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

        $spy = new EngineRoomReportAllBusinessUnitsSpy;

        // Lolos middleware rute (prefix ini memang menerima keduanya) TETAP
        // tertahan di sini. Uji ini gagal bila penjaganya dipindah ke rute.
        expect(fn () => $spy->businessUnitOptions())->toThrow(AuthorizationException::class);
        expect($spy->allBusinessUnitsCalls)->toBe(0);
    }
});

it('case 3 — 200 daftar mill bagi Admin, hanya id dan name', function () {
    BusinessUnit::factory()->create(['name' => 'Mill Gamma']);

    $this->actingAs($this->admin);

    $options = $this->service->businessUnitOptions();

    // Dibandingkan dengan jumlah baris master yang sebenarnya, bukan dengan
    // angka yang dipaku: StationFactory ikut melahirkan ProductionLine
    // beserta mill-nya sendiri, jadi master mill berisi lebih dari ketiga
    // yang dinamai di sini.
    expect($options)->toHaveCount(BusinessUnit::query()->count());

    $names = collect($options)->pluck('name');

    expect($names)->toContain('Mill Alpha', 'Mill Beta', 'Mill Gamma');
    // Terurut nama — urutan daftar itu yang dibaca pemilih Mill.
    expect($names->all())->toBe($names->sort()->values()->all());

    // Tidak ada kolom lain yang dibocorkan: bentuk entrinya tepat {id, name}.
    foreach ($options as $option) {
        expect(array_keys($option))->toBe(['id', 'name']);
    }
});

it('case 4 — master mill kosong mengembalikan daftar kosong, bukan 404', function () {
    // Admin tanpa business_unit_id, jadi menghapus seluruh master mill tidak
    // menyentuh akunnya sendiri.
    Period::query()->delete();
    EngineRoomRecord::query()->delete();
    Station::query()->delete();
    ProductionLine::query()->delete();
    User::query()->whereNotNull('business_unit_id')->delete();
    BusinessUnit::query()->delete();

    $this->actingAs($this->admin);

    expect($this->service->businessUnitOptions())->toBe([]);
});

// =====================================================================
// GROUP B — OPERATOR DITERIMA PADA KETIGA RUTE DATA, DAN DI CABANG
//           TERIKAT-MILL (case 5-10)
// =====================================================================

it('case 5 — Operator DITERIMA pada ketiga rute data sejak commit pertama', function () {
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-1');
    engineRoomReportSlot($record, '07:00', ['steam_turbine_rpm' => 3000.0]);

    $this->actingAs($this->operatorA);

    // guardAccess() TIDAK melempar bagi operator pada ketiga rute data.
    $periods = $this->service->listPeriods(null);
    expect(collect($periods)->pluck('id'))->toContain((string) $this->periodA->id);

    $summary = $this->service->buildSummary((string) $this->periodA->id, null, $this->lineA);
    expect($summary['business_unit']['name'])->toBe('Mill Alpha');
    expect(engineRoomReportMetric($summary, 'steam_turbine_rpm')['avg'])->toBe(3000.0);

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
it('case 6 — KRITIS: resolveBusinessUnit() menempatkan Operator pada cabang TERIKAT-MILL', function () {
    $this->actingAs($this->operatorA);

    // Nilai kiriman tidak divalidasi, tidak dibandingkan, DIBUANG.
    expect($this->service->resolveBusinessUnit((string) $this->businessUnitB->id))
        ->toBe((string) $this->businessUnitA->id);

    // Dan cabang ADMIN dibuktikan TIDAK dieksekusi: cabang itu melempar 422
    // ketika business_unit_id kiriman null, sementara cabang terikat-mill
    // justru menjawab mill akun.
    expect($this->service->resolveBusinessUnit(null))->toBe((string) $this->businessUnitA->id);
});

it('case 7 — KRITIS: Operator mill A yang mengirim business_unit_id mill B tetap menerima data mill A', function () {
    $recordA = engineRoomReportRecord($this->stationA, $this->day2, 'ER-A');
    engineRoomReportSlot($recordA, '07:00', ['steam_turbine_rpm' => 3000.0]);

    $recordB = engineRoomReportRecord($this->stationB, $this->day2, 'ER-B');
    engineRoomReportSlot($recordB, '07:00', ['steam_turbine_rpm' => 9999.0]);

    $this->actingAs($this->operatorA);

    // 200 berisi agregasi mill A — BUKAN 403 dan BUKAN data mill B. Sengaja
    // bukan 403: 403 justru akan menegaskan bahwa mill lain itu ada.
    $summary = $this->service->buildSummary(
        $this->periodA,
        (string) $this->businessUnitB->id,
        $this->lineA,
    );

    expect($summary['business_unit']['id'])->toBe((string) $this->businessUnitA->id);
    expect($summary['business_unit']['name'])->toBe('Mill Alpha');
    expect(engineRoomReportMetric($summary, 'steam_turbine_rpm')['max'])->toBe(3000.0);
    expect(collect($summary['by_unit'])->pluck('engine_room_id')->all())->toBe(['ER-A']);

    // Daftar periode mengikuti cakupan yang sama.
    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::EngineRoom->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Engine Room Beta')
        ->create();

    $ids = collect($this->service->listPeriods((string) $this->businessUnitB->id))->pluck('id');

    expect($ids)->toContain((string) $this->periodA->id);
    expect($ids)->not->toContain((string) $periodB->id);

    // Dan line mill lain TETAP 403 — penjagaan lintas mill yang sungguhan ada
    // di sana, bukan pada business_unit_id yang justru dibuang.
    expect(fn () => $this->service->buildSummary($this->periodA, null, $this->lineB))
        ->toThrow(AuthorizationException::class);
});

it('case 8 — ekspor Operator tidak pernah membawa baris mill lain', function () {
    $recordA = engineRoomReportRecord($this->stationA, $this->day2, 'ER-A');
    engineRoomReportSlot($recordA, '07:00', ['steam_turbine_rpm' => 3000.0]);

    $recordB = engineRoomReportRecord($this->stationB, $this->day2, 'ER-B');
    engineRoomReportSlot($recordB, '07:00', ['steam_turbine_rpm' => 9999.0]);

    $this->actingAs($this->operatorA);

    $csv = engineRoomReportStreamed(
        $this->service->export($this->periodA, 'csv', (string) $this->businessUnitB->id, $this->lineA),
    );

    expect($csv)->toContain('Mill Alpha');
    expect($csv)->toContain('ER-A');
    // Berkas yang mencampur mill adalah bentuk kesalahan yang paling sulit
    // dibantah setelah terkirim.
    expect($csv)->not->toContain('Mill Beta');
    expect($csv)->not->toContain('ER-B');

    $rows = iterator_to_array($this->service->buildExportRows($this->periodA, null, $this->lineA));

    expect($rows)->toHaveCount(1);
});

it('case 9 — Supervisor dan Mill Management yang mengirim mill lain tetap dikunci pada mill akunnya', function () {
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-A');
    engineRoomReportSlot($record, '07:00', ['steam_turbine_rpm' => 3000.0]);

    foreach ([$this->supervisorA, $this->millManagementA] as $user) {
        $this->actingAs($user);

        expect($this->service->resolveBusinessUnit((string) $this->businessUnitB->id))
            ->toBe((string) $this->businessUnitA->id);

        $summary = $this->service->buildSummary(
            $this->periodA,
            (string) $this->businessUnitB->id,
            $this->lineA,
        );

        expect($summary['business_unit']['id'])->toBe((string) $this->businessUnitA->id);
        expect($summary['has_data'])->toBeTrue();
    }
});

it('case 10 — Admin TETAP menghormati business_unit_id kiriman sesudah pelebaran akses Operator', function () {
    $recordB = engineRoomReportRecord($this->stationB, $this->day2, 'ER-B');
    engineRoomReportSlot($recordB, '07:00', ['steam_turbine_rpm' => 2500.0]);

    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::EngineRoom->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Engine Room Beta')
        ->create();

    $this->actingAs($this->admin);

    expect($this->service->resolveBusinessUnit((string) $this->businessUnitB->id))
        ->toBe((string) $this->businessUnitB->id);

    $summary = $this->service->buildSummary($periodB, (string) $this->businessUnitB->id, $this->lineB);

    expect($summary['business_unit']['id'])->toBe((string) $this->businessUnitB->id);
    expect($summary['business_unit']['name'])->toBe('Mill Beta');
    expect(engineRoomReportMetric($summary, 'steam_turbine_rpm')['avg'])->toBe(2500.0);
    expect(collect($summary['by_unit'])->pluck('engine_room_id')->all())->toBe(['ER-B']);
});

// =====================================================================
// GROUP C — RESOLUSI MILL (case 11-16)
// =====================================================================

it('case 11 — Admin tanpa business_unit_id pada /periods ditolak 422, tanpa kueri report_periods', function () {
    $this->actingAs($this->admin);

    $queries = engineRoomReportQueriesDuring(function () {
        expect(fn () => $this->service->listPeriods(null))->toThrow(ValidationException::class);
    });

    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "periods"');
        expect($sql)->not->toContain('from "period_stations"');
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

it('case 12 — /periods mengabaikan business_unit_id dan production_line_id bagi peran terikat mill', function () {
    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::EngineRoom->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Engine Room Beta')
        ->create();

    $this->actingAs($this->supervisorA);

    $queries = engineRoomReportQueriesDuring(function () use ($periodB) {
        $periods = $this->service->listPeriods((string) $this->businessUnitB->id);
        $ids = collect($periods)->pluck('id');

        expect($ids)->toContain((string) $this->periodA->id);
        expect($ids)->not->toContain((string) $periodB->id);
    });

    // production_line_id BUKAN parameter metode ini sama sekali: periode
    // milik MILL, bukan milik line. Dibuktikan atas kuerinya — tidak satu pun
    // menyebut kolom itu.
    foreach ($queries as $sql) {
        expect($sql)->not->toContain('production_line_id');
    }
});

it('case 13 — /periods hanya memuat periode yang mencakup stasiun Engine Room', function () {
    $keduaEngineRoom = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::EngineRoom->value)
        ->range(now()->subDays(40)->toDateString(), now()->subDays(31)->toDateString())
        ->open()
        ->named('Periode Engine Room Kedua')
        ->create();

    $lainnya = collect([
        StationTypeEnum::BoilerRoom->value,
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

    expect($periods)->toHaveCount(2);

    $ids = collect($periods)->pluck('id');

    expect($ids)->toContain((string) $this->periodA->id);
    expect($ids)->toContain((string) $keduaEngineRoom->id);

    foreach ($lainnya as $period) {
        expect($ids)->not->toContain((string) $period->id);
    }

    // BERTANDA HUBUNG: kunci snake_case akan ter-compile, tampak benar, dan
    // tidak pernah cocok dengan satu baris master pun — daftarnya lalu kosong
    // untuk setiap mill tanpa satu galat.
    expect(collect($periods)->pluck('station_type')->unique()->values()->all())->toBe(['engine-room']);
    expect(StationTypeEnum::EngineRoom->value)->toBe('engine-room');

    foreach ($periods as $period) {
        expect($period['station_type_label'])->not->toBe('');
        expect(array_keys($period))->toBe([
            'id', 'name', 'start_date', 'end_date', 'status', 'station_type', 'station_type_label',
        ]);
    }
});

it('case 14 — Admin tanpa business_unit_id pada /summary ditolak 422, nol kueri engine_room_records', function () {
    $this->actingAs($this->admin);

    $queries = engineRoomReportQueriesDuring(function () {
        expect(fn () => $this->service->buildSummary((string) $this->periodA->id, null, $this->lineA))
            ->toThrow(ValidationException::class);
    });

    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "engine_room_records"');
    }
});

it('case 15 — Supervisor dengan users.business_unit_id NULL gagal tertutup 422', function () {
    $supervisorWithoutMill = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $this->actingAs($supervisorWithoutMill);

    $spy = new EngineRoomReportAllBusinessUnitsSpy;

    $queries = engineRoomReportQueriesDuring(function () use ($spy) {
        expect(fn () => $spy->buildSummary((string) $this->periodA->id, null, $this->lineA))
            ->toThrow(ValidationException::class);
        expect(fn () => $spy->listPeriods(null))->toThrow(ValidationException::class);
    });

    // Sistem TIDAK jatuh ke cabang admin dan TIDAK menawarkan seluruh mill.
    expect($spy->allBusinessUnitsCalls)->toBe(0);

    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "business_units"');
        expect($sql)->not->toContain('from "engine_room_records"');
    }

    try {
        $spy->resolveBusinessUnit(null);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('business_unit_id');
        expect($e->errors()['business_unit_id'][0])->toContain('belum terhubung ke mill');
    }
});

it('case 16 — Operator dengan users.business_unit_id NULL gagal tertutup 422', function () {
    $operatorWithoutMill = User::factory()->role(UserRole::Operator)->create(['business_unit_id' => null]);

    $this->actingAs($operatorWithoutMill);

    $spy = new EngineRoomReportAllBusinessUnitsSpy;

    // Pelebaran akses Operator TIDAK membuka jalur mill-bebas: ia sampai ke
    // cabang terikat-mill dan di sanalah ia berhenti.
    expect(fn () => $spy->buildSummary((string) $this->periodA->id, null, $this->lineA))
        ->toThrow(ValidationException::class);
    expect($spy->allBusinessUnitsCalls)->toBe(0);

    // Dan pemilih mill tetap tertutup untuknya.
    expect(fn () => $spy->businessUnitOptions())->toThrow(AuthorizationException::class);
});

// =====================================================================
// GROUP D — PRODUCTION LINE DAN PERIODE, BESERTA URUTAN PENJAGANYA
//           (case 17-23)
// =====================================================================

it('case 17 — production_line_id absen ditolak 422 dan NOL kueri engine_room_records', function () {
    engineRoomReportSlot(
        engineRoomReportRecord($this->stationA, $this->day2, 'ER-1'),
        '07:00',
        ['steam_turbine_rpm' => 3000.0],
    );

    $this->actingAs($this->supervisorA);

    $queries = engineRoomReportQueriesDuring(function () {
        expect(fn () => $this->service->buildSummary((string) $this->periodA->id, null, null))
            ->toThrow(ValidationException::class);
    });

    // TIDAK PERNAH di-default ke seluruh line mill: melebarkan cakupan
    // laporan diam-diam karena satu parameter terlupa menjawab 200 dengan
    // angka yang tidak diminta siapa pun.
    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "engine_room_records"');
    }

    try {
        $this->service->buildSummary((string) $this->periodA->id, null, null);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('production_line_id');
    }
});

it('case 18 — production_line_id milik mill lain ditolak 403, nol kueri agregasi', function () {
    $this->actingAs($this->supervisorA);

    $queries = engineRoomReportQueriesDuring(function () {
        expect(fn () => $this->service->buildSummary((string) $this->periodA->id, null, $this->lineB))
            ->toThrow(AuthorizationException::class);
    });

    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "engine_room_records"');
        expect($sql)->not->toContain('from "engine_room_details"');
    }
});

it('case 19 — period_id absen ditolak 422', function () {
    $this->actingAs($this->supervisorA);

    expect(fn () => $this->service->buildSummary(null, null, $this->lineA))
        ->toThrow(ValidationException::class);

    try {
        $this->service->buildSummary(null, null, $this->lineA);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('period_id');
    }

    // String kosong pun 422, bukan 404 untuk id kosong.
    expect(fn () => $this->service->buildSummary('', null, $this->lineA))
        ->toThrow(ValidationException::class);
});

it('case 20 — period_id yang tidak ada ditolak 404', function () {
    $this->actingAs($this->supervisorA);

    expect(fn () => $this->service->buildSummary('00000000-0000-0000-0000-000000000000', null, $this->lineA))
        ->toThrow(ModelNotFoundException::class);
});

it('case 21 — periode milik mill lain ditolak 403 walau id-nya sah', function () {
    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::EngineRoom->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Engine Room Beta')
        ->create();

    $this->actingAs($this->supervisorA);

    expect(fn () => $this->service->buildSummary((string) $periodB->id, null, $this->lineA))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->authorizePeriod((string) $periodB->id))
        ->toThrow(AuthorizationException::class);
});

it('case 22 — urutan guard: peran → mill → line → period_id → periode', function () {
    $supervisorWithoutMill = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $this->actingAs($supervisorWithoutMill);

    // 1. Mill belum terselesaikan: VALIDATION_ERROR business_unit_id, walau
    //    production_line_id DAN period_id juga hilang.
    try {
        $this->service->buildSummary(null, null, null);
        $this->fail('seharusnya melempar ValidationException');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toBe(['business_unit_id']);
    }

    // 2. Mill terisi (peran terikat mill yang punya mill), line masih hilang:
    //    VALIDATION_ERROR production_line_id — BUKAN gabungan keduanya.
    $this->actingAs($this->supervisorA);

    try {
        $this->service->buildSummary(null, null, null);
        $this->fail('seharusnya melempar ValidationException');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toBe(['production_line_id']);
    }

    // 3. Line terisi, periode masih hilang: VALIDATION_ERROR period_id.
    try {
        $this->service->buildSummary(null, null, $this->lineA);
        $this->fail('seharusnya melempar ValidationException');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toBe(['period_id']);
    }

    // 4. Dan penjaga LINE berjalan SEBELUM penjaga periode: periode mill lain
    //    + line mill lain menjawab 403 dari penjaga line, sehingga pemanggil
    //    yang tidak berhak tidak pernah belajar id periode mana yang ada.
    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::EngineRoom->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->create();

    $queries = engineRoomReportQueriesDuring(function () use ($periodB) {
        expect(fn () => $this->service->buildSummary((string) $periodB->id, null, $this->lineB))
            ->toThrow(AuthorizationException::class);
    });

    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "engine_room_records"');
    }
});

it('case 23 — 401 bagi permintaan tanpa autentikasi pada keempat rute, sebelum satu kueri pun', function () {
    $queries = engineRoomReportQueriesDuring(function () {
        expect(fn () => $this->service->businessUnitOptions())->toThrow(AuthenticationException::class);
        expect(fn () => $this->service->listPeriods(null))->toThrow(AuthenticationException::class);
        expect(fn () => $this->service->buildSummary((string) $this->periodA->id, null, $this->lineA))
            ->toThrow(AuthenticationException::class);
        expect(fn () => $this->service->export($this->periodA, 'csv', null, $this->lineA))
            ->toThrow(AuthenticationException::class);
    });

    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "engine_room_records"');
        expect($sql)->not->toContain('from "business_units"');
        expect($sql)->not->toContain('from "periods"');
    }
});

// =====================================================================
// GROUP E — KELENGKAPAN PENCATATAN: 24 SLOT KANONIS, PENYEBUTNYA, DAN
//           DEFINISI "TERISI" ATAS 27 KOLOM (case 24-32)
// =====================================================================

it('case 24 — expected_slots = jumlah unit × hari terhitung × 24', function () {
    // Periode 5 hari yang SUDAH SELESAI, sehingga days_counted === 5 secara
    // deterministik tanpa menyentuh jam sistem.
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::EngineRoom->value)
        ->range(now()->subDays(10)->toDateString(), now()->subDays(6)->toDateString())
        ->open()
        ->named('Periode Lima Hari')
        ->create();

    // DUA unit Engine Room pada line yang sama.
    foreach (['ER-1', 'ER-2'] as $unit) {
        engineRoomReportSlot(
            engineRoomReportRecord($this->stationA, now()->subDays(9)->toDateString(), $unit),
            '07:00',
            ['steam_turbine_rpm' => 3000.0],
        );
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($period, null, $this->lineA);

    expect($summary['coverage']['engine_room_unit_count'])->toBe(2);
    expect($summary['coverage']['days_counted'])->toBe(5);
    expect($summary['coverage']['days_in_period'])->toBe(5);
    expect($summary['coverage']['slots_per_unit_per_day'])->toBe(24);
    expect($summary['coverage']['expected_slots'])->toBe(2 * 5 * 24);
    expect($summary['coverage']['expected_slots'])->toBe(240);
});

it('case 25 — 24 slot kanonis diambil dari EngineRoomRecordService::canonicalTimeSlots()', function () {
    engineRoomReportSlot(
        engineRoomReportRecord($this->stationA, $this->day2, 'ER-1'),
        '07:00',
        ['steam_turbine_rpm' => 3000.0],
    );

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Angka 24 TIDAK ditulis keras di dua tempat: pengalinya adalah
    // count(canonicalTimeSlots()) itu sendiri.
    expect($summary['coverage']['slots_per_unit_per_day'])
        ->toBe(count(EngineRoomRecordService::canonicalTimeSlots()));
    expect(count(EngineRoomRecordService::canonicalTimeSlots()))->toBe(24);

    // Dan kisinya benar-benar 07:00 .. 06:00 keesokan hari — itulah yang
    // membuat 06:00 slot PALING AKHIR, bukan paling awal (lihat case 44).
    $slots = EngineRoomRecordService::canonicalTimeSlots();

    expect($slots[0])->toBe('07:00');
    expect($slots[23])->toBe('06:00');
    expect($slots)->toHaveCount(24);
});

it('case 26 — hari terhitung berhenti di HARI INI untuk periode yang masih berjalan', function () {
    // Mulai 9 hari lalu, berakhir 20 hari ke depan: hari yang sudah lewat
    // tepat 10 (termasuk hari ini), dan dua puluh hari sisanya tidak ikut
    // menjadi penyebut.
    $running = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::EngineRoom->value)
        ->range(now()->subDays(9)->toDateString(), now()->addDays(20)->toDateString())
        ->open()
        ->named('Periode Berjalan')
        ->create();

    engineRoomReportSlot(
        engineRoomReportRecord($this->stationA, now()->subDays(5)->toDateString(), 'ER-1'),
        '07:00',
        ['steam_turbine_rpm' => 3000.0],
    );

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($running, null, $this->lineA);

    expect($summary['coverage']['period_running'])->toBeTrue();
    expect($summary['coverage']['days_counted'])->toBe(10);
    expect($summary['coverage']['days_in_period'])->toBe(30);
    expect($summary['coverage']['engine_room_unit_count'])->toBe(1);
    // Hari ke-11 sampai ke-30 tidak masuk penyebut, sehingga tidak menurunkan
    // coverage_percent.
    expect($summary['coverage']['expected_slots'])->toBe(240);
});

it('case 27 — periode yang belum mulai menghasilkan nol slot yang diharapkan', function () {
    $future = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::EngineRoom->value)
        ->range(now()->addDays(5)->toDateString(), now()->addDays(14)->toDateString())
        ->open()
        ->named('Periode Belum Mulai')
        ->create();

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($future, null, $this->lineA);

    expect($summary['coverage']['days_counted'])->toBe(0);
    expect($summary['coverage']['expected_slots'])->toBe(0);
    // Tanpa DivisionByZeroError, dan 0.0 — bukan null: layar yang menampilkan
    // '—' membacanya dari expected_slots === 0, bukan dari persennya.
    expect($summary['coverage']['coverage_percent'])->toBe(0.0);
    expect($summary['has_data'])->toBeFalse();
});

it('case 28 — coverage_percent dan has_data pada periode yang terisi sebagian', function () {
    // SATU unit × 10 hari × 24 slot = 240 slot yang diharapkan, dan 60 slot
    // terisi: 25,0% tepat.
    foreach ([$this->day1, $this->day2, $this->day3] as $date) {
        $record = engineRoomReportRecord($this->stationA, $date, 'ER-1');

        foreach (array_slice($this->slots, 0, 20) as $slot) {
            engineRoomReportSlot($record, $slot, ['steam_turbine_rpm' => 3000.0]);
        }
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['coverage']['expected_slots'])->toBe(240);
    expect($summary['coverage']['filled_slots'])->toBe(60);
    expect($summary['coverage']['coverage_percent'])->toBe(25.0);
    expect($summary['has_data'])->toBeTrue();
});

it('case 28b — filled_slots 0 menghasilkan has_data false dan coverage_percent 0.0', function () {
    // Record ADA, tetapi seluruh baris slotnya kosong: unit tetap terhitung,
    // jadi penyebutnya bukan nol dan persennya benar-benar 0,0%.
    engineRoomReportSlot(engineRoomReportRecord($this->stationA, $this->day2, 'ER-1'), '07:00');

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['coverage']['expected_slots'])->toBe(240);
    expect($summary['coverage']['filled_slots'])->toBe(0);
    expect($summary['coverage']['coverage_percent'])->toBe(0.0);
    expect($summary['has_data'])->toBeFalse();
});

it('case 29 — slot terisi bila minimal satu dari 27 kolom pembacaan non-null', function () {
    // HANYA steam_turbine_rpm; kedua puluh enam kolom lain null.
    engineRoomReportSlot(
        engineRoomReportRecord($this->stationA, $this->day2, 'ER-1'),
        '07:00',
        ['steam_turbine_rpm' => 3000.0],
    );

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['coverage']['filled_slots'])->toBe(1);

    // Iterasinya atas READING_FIELDS yang berisi TEPAT 27 kolom: 23 ukur + 2
    // status + 2 teks bebas. Definisinya DIPINJAM dari layar input, tidak
    // diturunkan ulang di report service.
    expect(EngineRoomRecordService::READING_FIELDS)->toHaveCount(27);
    expect(count(EngineRoomReportService::NUMERIC_METRICS) + 1)->toBe(23);
    expect(EngineRoomReportService::STATUS_COLUMNS)->toHaveCount(2);
    expect(EngineRoomReportService::FREE_TEXT_COLUMNS)->toHaveCount(2);
    expect(23 + 2 + 2)->toBe(count(EngineRoomRecordService::READING_FIELDS));

    // Dan setiap kolom bacaan memang salah satu dari keempat golongan itu.
    $golongan = array_merge(
        EngineRoomReportService::NUMERIC_METRICS,
        [EngineRoomReportService::ENERGY_EXPORT_METRIC],
        array_keys(EngineRoomReportService::STATUS_COLUMNS),
        EngineRoomReportService::FREE_TEXT_COLUMNS,
    );

    expect(array_diff(EngineRoomRecordService::READING_FIELDS, $golongan))->toBe([]);
    expect(array_diff($golongan, EngineRoomRecordService::READING_FIELDS))->toBe([]);
});

it('case 30 — slot yang hanya mengisi kolom teks bebas tetap terhitung terisi', function () {
    engineRoomReportSlot(
        engineRoomReportRecord($this->stationA, $this->day2, 'ER-1'),
        '07:00',
        ['findings' => 'Suara bearing kasar'],
    );

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['coverage']['filled_slots'])->toBe(1);
    expect($summary['has_data'])->toBeTrue();

    // Tidak ada SATU PUN reading_count metrik yang bertambah.
    foreach ($summary['metrics'] as $column => $metric) {
        expect($metric['reading_count'])->toBe(0, $column);
        expect($metric['avg'])->toBeNull($column);
    }

    expect($summary['daily_energy_export']['has_data'])->toBeFalse();
    expect($summary['findings'])->toHaveCount(1);
    expect($summary['maintenance_remarks'])->toBe([]);
});

it('case 31 — filled_slots DAPAT MELEBIHI penyebut setiap kartu metrik, dan itu benar', function () {
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-1');

    // LIMA slot hanya mengisi catatan tindakan perawatan ...
    foreach (array_slice($this->slots, 0, 5) as $slot) {
        engineRoomReportSlot($record, $slot, ['action_taken_maintenance_remark' => 'Cek level oli']);
    }

    // ... dan DUA slot mengisi sebuah kolom ukur.
    foreach (array_slice($this->slots, 5, 2) as $slot) {
        engineRoomReportSlot($record, $slot, ['steam_turbine_rpm' => 3000.0]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['coverage']['filled_slots'])->toBe(7);

    $terbesar = collect($summary['metrics'])->max('reading_count');

    expect($terbesar)->toBe(2);
    // YANG DIASERSI ADALAH KETIDAKSAMAANNYA: kesamaan justru bug yang hendak
    // dicegah — satu penyebut bersama akan mencetak 7 pada kedua puluh dua
    // kartu.
    expect($summary['coverage']['filled_slots'])->toBeGreaterThan($terbesar);

    // Selisihnya TIDAK dikoreksi, dan payload memuat keterangan sebabnya.
    expect($summary['_note_absences'])->toBeString();
    expect($summary['maintenance_remarks'])->toHaveCount(5);
});

it('case 32 — record dengan seluruh 27 kolom null tidak terhitung terisi, tetapi tidak dibuang', function () {
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-1');
    engineRoomReportSlot($record, '07:00');

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['coverage']['filled_slots'])->toBe(0);
    expect($summary['has_data'])->toBeFalse();

    // Record TETAP tidak dibuang dari hasil kueri: hari ini tetap hari
    // ber-record, unitnya tetap muncul, dan unitnya tetap menambah penyebut.
    expect($summary['total']['days_with_records'])->toBe(1);
    expect($summary['coverage']['engine_room_unit_count'])->toBe(1);
    expect(collect($summary['by_unit'])->pluck('engine_room_id')->all())->toBe(['ER-1']);
    expect($summary['by_unit'][0]['reading_rows'])->toBe(0);
    // Tanggal tanpa satu slot terisi TIDAK mendapat baris rekap harian:
    // baris nol akan terbaca sebagai "terukur nol".
    expect($summary['daily'])->toBe([]);
});

// =====================================================================
// GROUP F — KEANGGOTAAN PERIODE DAN PENYARINGAN LINE (case 33-34)
// =====================================================================

it('case 33 — keanggotaan periode INKLUSIF di kedua ujung tanggal', function () {
    // Tepat di kedua ujung ...
    foreach ([$this->periodStart, $this->periodEnd] as $index => $date) {
        engineRoomReportSlot(
            engineRoomReportRecord($this->stationA, $date, 'ER-UJUNG-'.$index),
            '07:00',
            [
                'steam_turbine_rpm' => 3000.0,
                'diesel_gen_1_status' => 'run',
                'daily_energy_export_kwh' => 100.0 + $index,
                'findings' => 'Ujung '.$index,
            ],
        );
    }

    // ... dan sehari di luar masing-masing ujung.
    foreach ([$this->dayBeforePeriod, $this->dayAfterPeriod] as $index => $date) {
        engineRoomReportSlot(
            engineRoomReportRecord($this->stationA, $date, 'ER-LUAR-'.$index),
            '07:00',
            [
                'steam_turbine_rpm' => 9999.0,
                'diesel_gen_1_status' => 'off',
                'daily_energy_export_kwh' => 9999.0,
                'findings' => 'Di luar periode',
            ],
        );
    }

    $this->actingAs($this->supervisorA);

    $queries = engineRoomReportQueriesDuring(function () {
        $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

        // Kedua record ujung ikut terhitung di SELURUH angka.
        expect($summary['coverage']['filled_slots'])->toBe(2);
        expect(engineRoomReportMetric($summary, 'steam_turbine_rpm')['reading_count'])->toBe(2);
        expect(engineRoomReportMetric($summary, 'steam_turbine_rpm')['max'])->toBe(3000.0);
        expect($summary['status_distribution']['diesel_gen_1_status']['counts']['run'])->toBe(2);
        expect($summary['status_distribution']['diesel_gen_1_status']['counts']['off'])->toBe(0);
        expect($summary['daily_energy_export']['series'])->toHaveCount(2);
        expect($summary['daily'])->toHaveCount(2);
        expect($summary['findings'])->toHaveCount(2);

        expect(collect($summary['daily'])->pluck('date')->all())
            ->toBe([$this->periodStart, $this->periodEnd]);
        expect(collect($summary['by_unit'])->pluck('engine_room_id')->all())
            ->toBe(['ER-UJUNG-0', 'ER-UJUNG-1']);
    });

    // Penyaringnya whereDate pada engine_room_records.date — bukan where()
    // mentah, dan bukan created_at. Pada SQLite ia mengompilasi menjadi
    // strftime(), dan justru di ujung-ujung periode itulah bentuk mentah
    // berbeda — dan hanya di PostgreSQL.
    $recordQueries = array_values(array_filter(
        $queries,
        fn (string $sql) => str_contains($sql, 'from "engine_room_records"'),
    ));

    expect($recordQueries)->not->toBe([]);

    if (DB::connection()->getDriverName() === 'sqlite') {
        expect($recordQueries[0])->toContain('strftime');
    }

    expect($recordQueries[0])->not->toContain('created_at');

    // Dan kedua record di luar rentang pun tidak muncul di berkas ekspor.
    $csv = engineRoomReportStreamed($this->service->export($this->periodA, 'csv', null, $this->lineA));

    expect($csv)->toContain('ER-UJUNG-0');
    expect($csv)->toContain('ER-UJUNG-1');
    expect($csv)->not->toContain('ER-LUAR-0');
    expect($csv)->not->toContain('ER-LUAR-1');
});

it('case 34 — penyaringan line memakai kolom production_line_id milik record SENDIRI', function () {
    // Stasiun yang SEKARANG tertaut line C, sementara recordnya menyimpan
    // snapshot line A — bentuk persis dari sebuah stasiun yang DIPINDAH
    // sesudah recordnya ditulis.
    $lineC = ProductionLine::factory()->create([
        'business_unit_id' => $this->businessUnitA->id,
        'name' => 'Line Ketiga',
    ]);

    $stationC = Station::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->forProductionLine($lineC)
        ->engineRoom()
        ->create();

    engineRoomReportSlot(
        engineRoomReportRecord($stationC, $this->day2, 'ER-PINDAH', [
            'production_line_id' => $this->lineA,
        ]),
        '07:00',
        ['steam_turbine_rpm' => 3000.0],
    );

    $this->actingAs($this->supervisorA);

    $queries = engineRoomReportQueriesDuring(function () use ($lineC) {
        // Muncul pada laporan line A — line tempat data itu BENAR-BENAR
        // dihasilkan.
        $onLineA = $this->service->buildSummary($this->periodA, null, $this->lineA);

        expect($onLineA['coverage']['filled_slots'])->toBe(1);
        expect(collect($onLineA['by_unit'])->pluck('engine_room_id')->all())->toBe(['ER-PINDAH']);

        // Dan TIDAK pada laporan line C — menyaring lewat join ke `stations`
        // akan menulis ulang sejarah setiap kali sebuah stasiun dipindahkan.
        $onLineC = $this->service->buildSummary($this->periodA, null, (string) $lineC->id);

        expect($onLineC['coverage']['filled_slots'])->toBe(0);
        expect($onLineC['by_unit'])->toBe([]);
    });

    $recordQueries = array_values(array_filter(
        $queries,
        fn (string $sql) => str_contains($sql, 'from "engine_room_records"'),
    ));

    expect($recordQueries)->not->toBe([]);

    foreach ($recordQueries as $sql) {
        // Kolomnya milik tabel record sendiri; join ke `stations` ada hanya
        // untuk menyaring MILL dan JENIS STASIUN, bukan line.
        expect($sql)->toContain('"engine_room_records"."production_line_id"');
        expect($sql)->not->toContain('"stations"."production_line_id"');
    }
});

// =====================================================================
// GROUP G — METRIK: DUA PULUH DUA RATA-RATA PERIODE, DUA PULUH DUA
//           PENYEBUT (case 35-42)
// =====================================================================

/**
 * PERTENTANGAN TECH SPEC YANG DISELESAIKAN DI SINI (2026-10-15).
 *
 * Judul kasus 35 pada tech spec berbunyi "payload memuat tepat 23 kunci
 * metrik ukur". TIGA pernyataan lain pada spec yang sama menyebut DUA PULUH
 * DUA: `success_schema.metrics._note`, business_logic langkah 12, dan langkah
 * 14 — ketiganya menyatakan `daily_energy_export_kwh` DIKECUALIKAN. Implementasinya
 * 22, dan 22 adalah jawaban yang benar: kolom ke-23 memang diterbitkan, tetapi
 * pada kuncinya SENDIRI (`daily_energy_export`) sebagai seri nilai terakhir per
 * tanggal, karena ia tidak punya rata-rata periode sama sekali.
 *
 * MAKSUD kasus ini — pertanggungjawaban SELURUH kolom ukur — dipertahankan
 * utuh: 22 di `metrics` PLUS 1 yang diterbitkan terpisah = 23 kolom ukur, dan
 * tidak ada yang hilang. Menyalin angka "23" ke dalam asersi akan memerahkan
 * kode yang benar.
 */
it('case 35 — metrics memuat TEPAT 22 kunci, dan kolom ke-23 diterbitkan terpisah', function () {
    engineRoomReportSlot(
        engineRoomReportRecord($this->stationA, $this->day2, 'ER-1'),
        '07:00',
        array_fill_keys(
            array_merge(EngineRoomReportService::NUMERIC_METRICS, [EngineRoomReportService::ENERGY_EXPORT_METRIC]),
            7.0,
        ),
    );

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // 22 di `metrics` ...
    expect($summary['metrics'])->toHaveCount(22);
    expect(EngineRoomReportService::NUMERIC_METRICS)->toHaveCount(22);
    expect(array_keys($summary['metrics']))->toBe(EngineRoomReportService::NUMERIC_METRICS);

    // ... DAN kolom ke-23 diterbitkan pada kuncinya sendiri, bukan hilang.
    expect($summary['metrics'])->not->toHaveKey(EngineRoomReportService::ENERGY_EXPORT_METRIC);
    expect($summary['metrics'])->not->toHaveKey('daily_energy_export_kwh');
    expect($summary)->toHaveKey('daily_energy_export');
    expect($summary['daily_energy_export']['has_data'])->toBeTrue();
    expect($summary['daily_energy_export']['series'][0]['last_value'])->toBe(7.0);

    // Pertanggungjawaban kolomnya: 22 + 1 = 23 kolom ukur, tidak satu pun
    // hilang dan tidak satu pun ganda.
    expect(count($summary['metrics']) + 1)->toBe(23);
    expect(EngineRoomReportService::ENERGY_EXPORT_METRIC)->toBe('daily_energy_export_kwh');
    expect(in_array(
        EngineRoomReportService::ENERGY_EXPORT_METRIC,
        EngineRoomReportService::NUMERIC_METRICS,
        true,
    ))->toBeFalse();

    // Nama kuncinya SAMA PERSIS dengan nama kolom di basis data.
    foreach (array_keys($summary['metrics']) as $column) {
        expect(in_array($column, EngineRoomRecordService::READING_FIELDS, true))->toBeTrue($column);
    }

    // Dan setiap entri membawa `group`-nya — blade menyusun keenam bloknya
    // DARI RESPONS, bukan dari daftar kolom yang dipaku di template.
    expect(EngineRoomReportService::METRIC_GROUPS)->toHaveCount(22);
    expect(collect($summary['metrics'])->pluck('group')->unique()->sort()->values()->all())->toBe([
        'auxiliary', 'compressor', 'diesel_gen_1', 'diesel_gen_2', 'electrical_sync', 'steam_turbine',
    ]);
    expect(collect($summary['metrics'])->countBy('group')->all())->toBe([
        'steam_turbine' => 6,
        'diesel_gen_1' => 4,
        'diesel_gen_2' => 4,
        'electrical_sync' => 4,
        'compressor' => 2,
        'auxiliary' => 2,
    ]);
});

it('case 36 — min/avg/max dan reading_count setiap metrik dihitung dari baris non-null MILIKNYA SENDIRI', function () {
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-1');

    // inlet_pressure: 20, 22, null, 24 — tiga nilai.
    // rpm: 3000, null, null, null — satu nilai.
    $pressures = [20.0, 22.0, null, 24.0];
    $rpms = [3000.0, null, null, null];

    foreach ($pressures as $index => $pressure) {
        engineRoomReportSlot($record, $this->slots[$index], [
            'steam_turbine_inlet_pressure_bar' => $pressure,
            'steam_turbine_rpm' => $rpms[$index],
        ]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    $pressure = engineRoomReportMetric($summary, 'steam_turbine_inlet_pressure_bar');

    expect($pressure['min'])->toBe(20.0);
    expect($pressure['avg'])->toBe(22.0);
    expect($pressure['max'])->toBe(24.0);
    expect($pressure['reading_count'])->toBe(3);

    $rpm = engineRoomReportMetric($summary, 'steam_turbine_rpm');

    expect($rpm['min'])->toBe(3000.0);
    expect($rpm['avg'])->toBe(3000.0);
    expect($rpm['max'])->toBe(3000.0);
    // PENYEBUTNYA TIDAK DIBAGI BERSAMA: satu penyebut bersama 4 akan
    // menerbitkan rata-rata rpm 750 — angka yang tetap terlihat sah.
    expect($rpm['reading_count'])->toBe(1);

    // Slot ketiga mengisi NOL kolom ukur dari keduanya dan tetap terhitung
    // terisi — row ke-3 (index 2) tidak mengisi apa pun, jadi 3 dari 4 slot
    // terisi.
    expect($summary['coverage']['filled_slots'])->toBe(3);
});

it('case 37 — metrik tanpa satu pun nilai menghasilkan null, BUKAN 0', function () {
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-1');

    foreach (array_slice($this->slots, 0, 3) as $slot) {
        engineRoomReportSlot($record, $slot, [
            'steam_turbine_rpm' => 3000.0,
            'electrical_sync_power_factor' => null,
        ]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    $powerFactor = engineRoomReportMetric($summary, 'electrical_sync_power_factor');

    // null/null/null dengan reading_count 0 — BUKAN 0/0/0, yang akan terbaca
    // sebagai "kami mengukurnya dan hasilnya nol".
    expect($powerFactor['min'])->toBeNull();
    expect($powerFactor['avg'])->toBeNull();
    expect($powerFactor['max'])->toBeNull();
    expect($powerFactor['reading_count'])->toBe(0);
    expect($powerFactor['avg'])->not->toBe(0.0);

    // Metrik lain tetap normal.
    expect(engineRoomReportMetric($summary, 'steam_turbine_rpm')['reading_count'])->toBe(3);
});

it('case 38 — dua suhu bearing alternator tetap DUA metrik terpisah', function () {
    engineRoomReportSlot(
        engineRoomReportRecord($this->stationA, $this->day2, 'ER-1'),
        '07:00',
        [
            'steam_turbine_alternator_bearing_temp_1_c' => 60.0,
            'steam_turbine_alternator_bearing_temp_2_c' => 85.0,
        ],
    );

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect(engineRoomReportMetric($summary, 'steam_turbine_alternator_bearing_temp_1_c')['avg'])->toBe(60.0);
    expect(engineRoomReportMetric($summary, 'steam_turbine_alternator_bearing_temp_2_c')['avg'])->toBe(85.0);

    // TIDAK ADA kunci rata-rata bearing gabungan — diasersi atas NAMA KUNCI.
    $keys = engineRoomReportAllKeys($summary);

    foreach ([
        'bearing_avg', 'alternator_bearing_avg', 'steam_turbine_alternator_bearing_temp_avg',
        'bearing_temp_combined', 'bearing_delta',
    ] as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
    }
});

it('case 39 — dua kolom kompresor tetap dua metrik terpisah walau dinamai TIDAK KONSISTEN', function () {
    engineRoomReportSlot(
        engineRoomReportRecord($this->stationA, $this->day2, 'ER-1'),
        '07:00',
        [
            'air_compressor_1_pressure_bar' => 7.0,
            'compressor_2_pressure_bar' => 6.2,
        ],
    );

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Nama kolom ASLINYA, tidak dinormalkan dan tidak digabungkan.
    expect($summary['metrics'])->toHaveKey('air_compressor_1_pressure_bar');
    expect($summary['metrics'])->toHaveKey('compressor_2_pressure_bar');
    expect($summary['metrics'])->not->toHaveKey('compressor_1_pressure_bar');
    expect($summary['metrics'])->not->toHaveKey('air_compressor_2_pressure_bar');

    expect(engineRoomReportMetric($summary, 'air_compressor_1_pressure_bar')['avg'])->toBe(7.0);
    expect(engineRoomReportMetric($summary, 'compressor_2_pressure_bar')['avg'])->toBe(6.2);

    // Keduanya di blok yang sama, dan itu MURNI TATA LETAK — tidak ada satu
    // angka pun dihitung per blok.
    expect(engineRoomReportMetric($summary, 'air_compressor_1_pressure_bar')['group'])->toBe('compressor');
    expect(engineRoomReportMetric($summary, 'compressor_2_pressure_bar')['group'])->toBe('compressor');
});

it('case 40 — payload TIDAK memuat satu pun kunci penandaan di luar batas', function () {
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-1');

    // Nilai SENGAJA jauh dari nominal yang dikenal luas DI LUAR aplikasi:
    // frekuensi 43 Hz, power factor 0,35, tegangan busbar 310 V. Justru di
    // sinilah godaan menandai penyimpangan paling besar, dan ambang yang
    // diturunkan dari data periode sendiri akan terbaca sebagai BATAS
    // KESELAMATAN KELISTRIKAN.
    engineRoomReportSlot($record, '07:00', [
        'electrical_sync_system_frequency_hz' => 43.0,
        'electrical_sync_power_factor' => 0.35,
        'electrical_sync_busbar_voltage_v' => 310.0,
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Bentuk entri metrik: TEPAT lima kunci, tidak satu pun tambahan.
    foreach ($summary['metrics'] as $column => $metric) {
        expect(array_keys($metric))->toBe(['min', 'avg', 'max', 'reading_count', 'group'], $column);
    }

    // DIASERSI ATAS KETIADAAN KUNCI, bukan atas ketiadaan frasa:
    // `_note_absences` justru MEMUAT kata "target", "di luar batas", dan
    // "downtime" karena ia menyatakan ketiadaannya.
    $keys = engineRoomReportAllKeys($summary);

    foreach (engineRoomReportForbiddenKeys() as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
    }

    // Dan nilainya diterbitkan APA ADANYA.
    expect(engineRoomReportMetric($summary, 'electrical_sync_system_frequency_hz')['avg'])->toBe(43.0);
    expect(engineRoomReportMetric($summary, 'electrical_sync_power_factor')['avg'])->toBe(0.35);
    expect(engineRoomReportMetric($summary, 'electrical_sync_busbar_voltage_v')['avg'])->toBe(310.0);
});

it('case 41 — payload TIDAK memuat blok downtime', function () {
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-1');

    engineRoomReportSlot($record, '07:00', array_fill_keys(
        array_merge(EngineRoomReportService::NUMERIC_METRICS, [EngineRoomReportService::ENERGY_EXPORT_METRIC]),
        7.0,
    ));

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    $keys = engineRoomReportAllKeys($summary);

    foreach (['downtime', 'downtime_minutes', 'downtime_events', 'total_minutes', 'stop_events', 'stoppages'] as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
    }

    // Kunci yang MEMANG ada, dan hanya itu — daftar ini yang menangkap sebuah
    // blok baru yang diselipkan tanpa sengaja.
    expect(array_keys($summary))->toBe([
        'period', 'business_unit', 'production_line', 'has_data', 'coverage',
        'metrics', 'daily_energy_export', 'status_distribution',
        'maintenance_remarks', 'findings', 'daily_trend', 'by_unit', 'daily',
        'daily_total', 'total', '_note_absences',
    ]);

    // Dan ketiadaannya DINYATAKAN di payload, supaya tidak terbaca sebagai
    // bagian yang gagal dimuat.
    expect($summary['_note_absences'])->toContain('TIDAK ADA kunci downtime');

    // engine_room_details memang tidak punya satu pun kolom downtime.
    foreach (EngineRoomRecordService::READING_FIELDS as $field) {
        expect($field)->not->toContain('downtime');
    }
});

it('case 42 — seluruh agregasi dijalankan DI PHP: nol agregat SQL', function () {
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-1');
    engineRoomReportSlot($record, '07:00', ['steam_turbine_rpm' => 3000.0]);
    engineRoomReportSlot($record, '08:00', ['electrical_sync_power_factor' => 0.95]);

    $this->actingAs($this->supervisorA);

    $queries = engineRoomReportQueriesDuring(function () {
        $this->service->buildSummary($this->periodA, null, $this->lineA);
    });

    // SQLite (suite) dan PostgreSQL (produksi) berbeda perilaku pada
    // AVG/SUM atas kolom bernull, dan dengan DUA PULUH TIGA kolom nullable
    // ini paparan terbesar di seluruh codebase.
    foreach ($queries as $sql) {
        foreach (['avg(', 'min(', 'max(', 'sum(', 'group by'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $sql, "kueri memakai {$forbidden}: {$sql}");
        }
    }

    $recordQueries = array_values(array_filter(
        $queries,
        fn (string $sql) => str_contains($sql, 'from "engine_room_records"'),
    ));

    // SATU kueri record + satu eager-load detail: tidak ada N+1.
    expect($recordQueries)->toHaveCount(1);

    $detailQueries = array_values(array_filter(
        $queries,
        fn (string $sql) => str_contains($sql, 'from "engine_room_details"'),
    ));

    expect($detailQueries)->toHaveCount(1);
    // time_slot diurutkan sebagai nilai TIME apa adanya, tidak di-cast ke
    // jam integer.
    expect($detailQueries[0])->toContain('order by "time_slot" asc');
});

// =====================================================================
// GROUP H — EKSPOR ENERGI HARIAN: KOLOM KE-23, DAN SATU-SATUNYA YANG
//           TIDAK DIRATA-RATAKAN (case 43-47)
//
// ────────────────────────────────────────────────────────────────────────
// PERTENTANGAN TECH SPEC KEDUA, DISELESAIKAN DI GROUP INI (2026-10-15)
// ────────────────────────────────────────────────────────────────────────
// Tiga kasus pada tech spec (44, 45, 47) menulis fixture-nya dengan slot
// 06:00 / 12:00 / 18:00 dan mengharapkan nilai pada "slot waktu TERBESAR"
// — yaitu 18:00 atau 12:00. Harapan itu adalah PERBANDINGAN ANGKA JAM, dan
// perbandingan angka jam adalah JEBAKAN yang seluruh desain ini ada untuk
// menghindarinya: kisi kanonis berjalan 07:00 .. 06:00 KEESOKAN HARI, jadi
// 06:00 adalah slot PALING AKHIR hari itu, bukan paling awal. Pernyataan
// otoritatifnya ada di tiga tempat yang sepakat — docblock
// lastEnergyValueOf(), keterangan `rowOf()` tentang `slot_order`, dan
// keterangan seri pada blade — dan implementasinya mengurutkan menurut
// POSISI pada kisi.
//
// Yang dipertahankan di bawah adalah MAKSUD ketiga kasus itu (nilai
// terakhir hari itu; null di slot terakhir dilewati; nilai yang turun
// diterbitkan apa adanya), dengan fixture yang dipilih agar maksud itu
// dapat dibuktikan DI BAWAH aturan kisi kanonis. Menyalin harapan spec apa
// adanya akan memerahkan kode yang benar dan — jauh lebih buruk — akan
// mendorong "perbaikan" ke arah urutan leksikal.
// =====================================================================

it('case 43 — daily_energy_export_kwh TIDAK diterbitkan sebagai rata-rata periode', function () {
    // UJI INI MENEMUKAN cacat `sortBy([closure, closure])` di
    // lastEnergyValueOf() dan SUDAH HIJAU sejak perbaikannya (2026-10-15).
    // Diagnosis lengkap dan perbaikan satu barisnya ada di kepala case 44.
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-1');

    foreach ([100.0, 150.0, 200.0] as $index => $value) {
        engineRoomReportSlot($record, $this->slots[$index], [
            EngineRoomReportService::ENERGY_EXPORT_METRIC => $value,
        ]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // TIDAK ADA kunci rata-rata/jumlah periode untuk kolom ini, di bucket
    // mana pun: tidak di `metrics`, tidak di `metrics_avg` rekap harian,
    // tidak di `metrics` rekap per unit.
    expect($summary['metrics'])->not->toHaveKey(EngineRoomReportService::ENERGY_EXPORT_METRIC);
    expect($summary['daily'][0]['metrics_avg'])->not->toHaveKey(EngineRoomReportService::ENERGY_EXPORT_METRIC);
    expect($summary['daily'][0]['metrics_avg'])->toHaveCount(22);
    expect($summary['by_unit'][0]['metrics'])->not->toHaveKey(EngineRoomReportService::ENERGY_EXPORT_METRIC);
    expect($summary['by_unit'][0]['metrics'])->toHaveCount(22);
    expect($summary['daily_trend'])->not->toHaveKey(EngineRoomReportService::ENERGY_EXPORT_METRIC);

    // Yang diterbitkan adalah seri per tanggal, bukan satu angka periode.
    expect($summary['daily_energy_export']['series'])->toHaveCount(1);
    expect(array_keys($summary['daily_energy_export']))->toBe(['series', 'has_data']);
    expect(array_keys($summary['daily_energy_export']['series'][0]))
        ->toBe(['date', 'last_value', 'time_slot', 'reading_count']);

    // Rata-rata ketiga nilai adalah 150; angka itu TIDAK BOLEH muncul
    // sebagai nilai seri — nilai serinya nilai TERAKHIR (200 pada 09:00).
    expect($summary['daily_energy_export']['series'][0]['last_value'])->toBe(200.0);
    expect($summary['daily_energy_export']['series'][0]['reading_count'])->toBe(3);
});

it('case 44 — seri memakai nilai slot TERAKHIR hari itu MENURUT POSISI KISI KANONIS', function () {
    // ──────────────────────────────────────────────────────────────────
    // UJI INI MERAH SAAT DITULIS, DAN ITU BENAR — ia menemukan cacat
    // nyata, yang SUDAH DIPERBAIKI pada 2026-10-15. Riwayat di bawah
    // dipertahankan karena ia menerangkan mengapa bentuk pengurutannya
    // harus tetap seperti sekarang; ia BUKAN cacat yang masih terbuka.
    // EngineRoomReportService::lastEnergyValueOf() memakai
    // `sortBy([closure, closure])`, dan ITU BUKAN API pengurut dua kunci —
    // Laravel\Collection::sortByMany() memperlakukan setiap closure sebagai
    // KOMPARATOR ($a, $b), bukan sebagai pengambil nilai. Closure-nya
    // memulangkan string tanggal, yang dibaca uasort() sebagai "selalu lebih
    // besar", sehingga koleksinya TERBALIK dan ->last() memulangkan elemen
    // PERTAMA urutan eager-load (orderBy('time_slot') — yaitu urutan
    // LEKSIKAL jam).
    //
    // Akibatnya angka yang diterbitkan sebagai "nilai TERAKHIR" justru nilai
    // pada slot yang PALING AWAL secara leksikal — tepat jebakan yang
    // docblock metode itu sendiri menyatakan ada untuk dihindari.
    // KernelPlantReportService::findingsOf() dan
    // DepricarpingReportService memperingatkan jebakan ini dengan kata-kata
    // eksplisit ("Laravel's sortBy([closure, closure]) is NOT that API");
    // laporan ini melakukannya.
    //
    // PERBAIKANNYA SATU BARIS, di implementasi — bukan di uji ini:
    //   ->sortBy(fn ($row) => $row->date.'|'.str_pad((string) $row->slot_order, 2, '0', STR_PAD_LEFT))
    // atau ->sort() dengan satu komparator dua kunci.
    // ──────────────────────────────────────────────────────────────────

    // 07:00 = 400, 12:00 = 900, 18:00 = 700. Di kisi kanonis urutannya
    // 07:00 (ke-1), 12:00 (ke-6), 18:00 (ke-12), jadi nilai TERAKHIR hari itu
    // adalah 700 — bukan 400 (slot pertama) dan bukan 900 (nilai tertinggi).
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-1');

    engineRoomReportSlot($record, '07:00', [EngineRoomReportService::ENERGY_EXPORT_METRIC => 400.0]);
    engineRoomReportSlot($record, '12:00', [EngineRoomReportService::ENERGY_EXPORT_METRIC => 900.0]);
    engineRoomReportSlot($record, '18:00', [EngineRoomReportService::ENERGY_EXPORT_METRIC => 700.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);
    $point = $summary['daily_energy_export']['series'][0];

    expect($point['date'])->toBe($this->day2);
    expect($point['last_value'])->toBe(700.0);
    expect($point['last_value'])->not->toBe(900.0);
    expect($point['last_value'])->not->toBe(400.0);
    // Tiap titik membawa SLOT ASALNYA supaya pembaca dapat memeriksa nilai
    // mana yang dipakai.
    expect($point['time_slot'])->toBe('18:00');
    expect($point['reading_count'])->toBe(3);

    // JEBAKAN KISI KANONIS, DITANAM EKSPLISIT: 07:00, 12:00, dan 06:00 —
    // yang TERAKHIR adalah 06:00, karena kisinya berakhir di 06:00 KEESOKAN
    // hari. Perbandingan angka jam akan memilih 12:00 (jam terbesar) dan
    // menerbitkan 20 — angka yang terlihat sangat masuk akal.
    $trap = engineRoomReportRecord($this->stationA, $this->day3, 'ER-1');

    engineRoomReportSlot($trap, '07:00', [EngineRoomReportService::ENERGY_EXPORT_METRIC => 10.0]);
    engineRoomReportSlot($trap, '12:00', [EngineRoomReportService::ENERGY_EXPORT_METRIC => 20.0]);
    engineRoomReportSlot($trap, '06:00', [EngineRoomReportService::ENERGY_EXPORT_METRIC => 30.0]);

    $withTrap = $this->service->buildSummary($this->periodA, null, $this->lineA);
    $trapPoint = collect($withTrap['daily_energy_export']['series'])->firstWhere('date', $this->day3);

    expect($trapPoint['last_value'])->toBe(30.0);
    expect($trapPoint['time_slot'])->toBe('06:00');
    // 20.0 adalah jawaban yang akan diberikan perbandingan angka jam.
    expect($trapPoint['last_value'])->not->toBe(20.0);
});

it('case 45 — slot terakhir yang NULL dilewati, bukan dianggap kosong', function () {
    // UJI INI MENEMUKAN cacat `sortBy([closure, closure])` di
    // lastEnergyValueOf() dan SUDAH HIJAU sejak perbaikannya (2026-10-15).
    // Diagnosis lengkap dan perbaikan satu barisnya ada di kepala case 44.
    // 12:00 = 500, 18:00 = 800, dan 06:00 — slot kanonis PALING AKHIR —
    // null. Jawabannya 800: slot terakhir yang benar-benar PUNYA nilai.
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-1');

    engineRoomReportSlot($record, '12:00', [EngineRoomReportService::ENERGY_EXPORT_METRIC => 500.0]);
    engineRoomReportSlot($record, '18:00', [EngineRoomReportService::ENERGY_EXPORT_METRIC => 800.0]);
    engineRoomReportSlot($record, '06:00', [
        EngineRoomReportService::ENERGY_EXPORT_METRIC => null,
        'steam_turbine_rpm' => 3000.0,
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);
    $point = $summary['daily_energy_export']['series'][0];

    expect($point['last_value'])->toBe(800.0);
    expect($point['time_slot'])->toBe('18:00');
    // Tanggal itu TIDAK dilaporkan tanpa data, dan penyebutnya hanya slot
    // yang mengisi kolom ini sendiri — dua, bukan tiga.
    expect($point['reading_count'])->toBe(2);
    expect($summary['daily_energy_export']['has_data'])->toBeTrue();
    expect($summary['coverage']['filled_slots'])->toBe(3);
});

it('case 46 — kolom ekspor energi kosong sepanjang periode menghasilkan seri TANPA DATA', function () {
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-1');

    foreach (array_slice($this->slots, 0, 3) as $slot) {
        engineRoomReportSlot($record, $slot, [
            'steam_turbine_rpm' => 3000.0,
            EngineRoomReportService::ENERGY_EXPORT_METRIC => null,
        ]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Seri KOSONG dengan penanda tidak ada data — dan TIDAK ADA satu pun
    // titik bernilai 0, yang akan mengklaim angka meter yang tidak pernah
    // dibaca siapa pun.
    expect($summary['daily_energy_export']['series'])->toBe([]);
    expect($summary['daily_energy_export']['has_data'])->toBeFalse();

    // Tanggalnya tetap punya baris rekap harian (kolom lain terisi), dan sel
    // energinya null — bukan 0.
    expect($summary['daily'])->toHaveCount(1);
    expect($summary['daily'][0]['energy_export']['last_value'])->toBeNull();
    expect($summary['daily'][0]['energy_export']['reading_count'])->toBe(0);
    expect($summary['by_unit'][0]['energy_export']['last_value'])->toBeNull();
});

it('case 47 — nilai ekspor energi yang TURUN di dalam satu hari diterbitkan apa adanya', function () {
    // UJI INI MENEMUKAN cacat `sortBy([closure, closure])` di
    // lastEnergyValueOf() dan SUDAH HIJAU sejak perbaikannya (2026-10-15).
    // Diagnosis lengkap dan perbaikan satu barisnya ada di kepala case 44.
    // 07:00 = 900 lalu 12:00 = 300 sebagai slot TERAKHIR pada kisi kanonis
    // (07:00 ke-1, 12:00 ke-6). Keadaan ini MUSTAHIL bila kolomnya angka
    // meter kumulatif, jadi ia justru bukti yang berguna untuk menjawab
    // pertanyaan terbuka — bukan galat.
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-1');

    engineRoomReportSlot($record, '07:00', [EngineRoomReportService::ENERGY_EXPORT_METRIC => 900.0]);
    engineRoomReportSlot($record, '12:00', [EngineRoomReportService::ENERGY_EXPORT_METRIC => 300.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);
    $point = $summary['daily_energy_export']['series'][0];

    expect($point['last_value'])->toBe(300.0);
    expect($point['time_slot'])->toBe('12:00');

    // Tanpa koreksi, dan tanpa satu pun kunci anomaly/flag/warning.
    $keys = engineRoomReportAllKeys($summary);

    foreach (['anomaly', 'anomalies', 'flag', 'flags', 'warning', 'warnings', 'corrected', 'correction'] as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
    }
});

// =====================================================================
// GROUP I — KEDELAPAN KOLOM GENSET TETAP DELAPAN KUNCI TERPISAH
//           (case 48-51)
// =====================================================================

it('case 48 — kedelapan metrik genset tetap DELAPAN kunci terpisah', function () {
    engineRoomReportSlot(
        engineRoomReportRecord($this->stationA, $this->day2, 'ER-1'),
        '07:00',
        [
            'diesel_gen_1_load_kw' => 800.0,
            'diesel_gen_1_amperage_a' => 1200.0,
            'diesel_gen_1_jacket_water_temp_c' => 85.0,
            'diesel_gen_1_lube_oil_pressure_bar' => 4.5,
            'diesel_gen_2_load_kw' => 40.0,
            'diesel_gen_2_amperage_a' => 60.0,
            'diesel_gen_2_jacket_water_temp_c' => 45.0,
            'diesel_gen_2_lube_oil_pressure_bar' => 3.1,
        ],
    );

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    $genset = [
        'diesel_gen_1_load_kw' => 800.0,
        'diesel_gen_1_amperage_a' => 1200.0,
        'diesel_gen_1_jacket_water_temp_c' => 85.0,
        'diesel_gen_1_lube_oil_pressure_bar' => 4.5,
        'diesel_gen_2_load_kw' => 40.0,
        'diesel_gen_2_amperage_a' => 60.0,
        'diesel_gen_2_jacket_water_temp_c' => 45.0,
        'diesel_gen_2_lube_oil_pressure_bar' => 3.1,
    ];

    foreach ($genset as $column => $value) {
        $metric = engineRoomReportMetric($summary, $column);

        expect($metric['min'])->toBe($value, $column);
        expect($metric['avg'])->toBe($value, $column);
        expect($metric['max'])->toBe($value, $column);
        expect($metric['reading_count'])->toBe(1, $column);
    }

    // Delapan kunci, dan `group` menandainya sebagai DUA blok — satu-satunya
    // hubungan yang diterbitkan.
    expect(collect($summary['metrics'])->where('group', 'diesel_gen_1'))->toHaveCount(4);
    expect(collect($summary['metrics'])->where('group', 'diesel_gen_2'))->toHaveCount(4);
});

it('case 49 — beban kedua genset yang sangat timpang TIDAK PERNAH digabung', function () {
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-1');

    foreach (array_slice($this->slots, 0, 4) as $slot) {
        engineRoomReportSlot($record, $slot, [
            'diesel_gen_1_load_kw' => 800.0,
            'diesel_gen_2_load_kw' => 40.0,
        ]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect(engineRoomReportMetric($summary, 'diesel_gen_1_load_kw')['avg'])->toBe(800.0);
    expect(engineRoomReportMetric($summary, 'diesel_gen_2_load_kw')['avg'])->toBe(40.0);

    // DIASERSI ATAS NAMA KUNCI, BUKAN ATAS ISI json_encode: `_note_absences`
    // justru MENYEBUT "gabungan", "selisih", dan "ketidakseimbangan" karena
    // ia menyatakan ketiadaannya, jadi pemindaian teks akan gagal pada
    // kalimat yang membuktikan klaimnya.
    $keys = engineRoomReportAllKeys($summary);

    foreach ([
        'combined', 'combined_avg', 'genset_total', 'genset_combined',
        'delta', 'difference', 'imbalance', 'deviation', 'ratio', 'load_share',
        'load_ratio', 'shares_standard_with',
    ] as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
    }

    // Rata-rata gabungan 420 TIDAK ADA di mana pun sebagai nilai metrik.
    expect(collect($summary['metrics'])->pluck('avg')->all())->not->toContain(420.0);
});

it('case 50 — tidak ada penanda selisih antar KEEMPAT pasangan kolom sejajar genset', function () {
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-1');

    // Keempat pasangan terisi dengan selisih besar.
    engineRoomReportSlot($record, '07:00', [
        'diesel_gen_1_load_kw' => 900.0,
        'diesel_gen_2_load_kw' => 20.0,
        'diesel_gen_1_amperage_a' => 1400.0,
        'diesel_gen_2_amperage_a' => 30.0,
        'diesel_gen_1_jacket_water_temp_c' => 95.0,
        'diesel_gen_2_jacket_water_temp_c' => 30.0,
        'diesel_gen_1_lube_oil_pressure_bar' => 5.0,
        'diesel_gen_2_lube_oil_pressure_bar' => 1.0,
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Keempat pasangan: tidak satu pun entri metriknya membawa kunci
    // tambahan. Bentuk entri yang TETAP lima kunci itulah yang menangkap
    // sebuah penanda yang diselipkan ke salah satu pasangan saja.
    foreach ([
        'load_kw', 'amperage_a', 'jacket_water_temp_c', 'lube_oil_pressure_bar',
    ] as $suffix) {
        foreach (['diesel_gen_1_'.$suffix, 'diesel_gen_2_'.$suffix] as $column) {
            expect(array_keys(engineRoomReportMetric($summary, $column)))
                ->toBe(['min', 'avg', 'max', 'reading_count', 'group'], $column);
        }
    }

    $keys = engineRoomReportAllKeys($summary);

    foreach (['delta', 'diff', 'difference', 'imbalance', 'flag', 'flags', 'deviation'] as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
    }

    // KESEJAJARAN YANG DINYATAKAN HANYA KESEJAJARAN FISIK, bukan standar
    // bersama: stasiun ini tidak punya master target sama sekali, jadi tidak
    // ada kunci `shares_standard_with` seperti pada pasangan Kernel Plant.
    $this->assertNotContains('shares_standard_with', $keys);
});

it('case 51 — genset yang tidak pernah menyala menghasilkan empat metrik null dengan penyebut nol', function () {
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-1');

    foreach (array_slice($this->slots, 0, 3) as $slot) {
        engineRoomReportSlot($record, $slot, [
            'diesel_gen_1_load_kw' => 700.0,
            'diesel_gen_1_amperage_a' => 1100.0,
            'diesel_gen_1_jacket_water_temp_c' => 80.0,
            'diesel_gen_1_lube_oil_pressure_bar' => 4.2,
        ]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    foreach ([
        'diesel_gen_2_load_kw', 'diesel_gen_2_amperage_a',
        'diesel_gen_2_jacket_water_temp_c', 'diesel_gen_2_lube_oil_pressure_bar',
    ] as $column) {
        $metric = engineRoomReportMetric($summary, $column);

        expect($metric['min'])->toBeNull($column);
        expect($metric['avg'])->toBeNull($column);
        expect($metric['max'])->toBeNull($column);
        expect($metric['reading_count'])->toBe(0, $column);
    }

    foreach ([
        'diesel_gen_1_load_kw', 'diesel_gen_1_amperage_a',
        'diesel_gen_1_jacket_water_temp_c', 'diesel_gen_1_lube_oil_pressure_bar',
    ] as $column) {
        expect(engineRoomReportMetric($summary, $column)['reading_count'])->toBe(3, $column);
    }

    // Tidak ada galat yang dilempar, dan keadaan ini operasional yang SAH.
    expect($summary['has_data'])->toBeTrue();
});

// =====================================================================
// GROUP J — DUA SEBARAN STATUS KATEGORIS, TIGA NILAI MASING-MASING
//           (case 52-58)
// =====================================================================

it('case 52 — sebaran mencetak KETIGA nilai dikenal termasuk yang berjumlah NOL', function () {
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-1');

    // 10 slot 'run' dan 2 slot 'off'; 'standby' TIDAK PERNAH muncul.
    foreach (array_slice($this->slots, 0, 12) as $index => $slot) {
        engineRoomReportSlot($record, $slot, [
            'diesel_gen_1_status' => $index < 10 ? 'run' : 'off',
        ]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);
    $dist = $summary['status_distribution']['diesel_gen_1_status'];

    // Iterasinya atas known_values ['run','standby','off'], sehingga
    // 'standby' tetap tercetak dengan 0 — baris yang hilang tidak dapat
    // dibedakan dari nilai yang tidak pernah ada di basis data.
    expect($dist['counts'])->toBe(['run' => 10, 'standby' => 0, 'off' => 2]);
    expect($dist['known_values'])->toBe(['run', 'standby', 'off']);
    expect(EngineRoomReportService::STATUS_COLUMNS['diesel_gen_1_status'])->toBe(['run', 'standby', 'off']);
    expect($dist['not_recorded'])->toBe(0);
    expect($dist['recorded_slots'])->toBe(12);
});

it('case 53 — slot dengan status NULL masuk not_recorded yang TERPISAH', function () {
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-1');

    // 12 slot terisi: 5 bernilai 'run', 7 dengan status null tetapi kolom
    // ukurnya terisi sehingga slotnya tetap terhitung terisi.
    foreach (array_slice($this->slots, 0, 12) as $index => $slot) {
        engineRoomReportSlot($record, $slot, $index < 5
            ? ['diesel_gen_1_status' => 'run']
            : ['steam_turbine_rpm' => 3000.0]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);
    $dist = $summary['status_distribution']['diesel_gen_1_status'];

    expect($dist['counts'])->toBe(['run' => 5, 'standby' => 0, 'off' => 0]);
    // KUNCI TERPISAH: ketujuh slot itu TIDAK PERNAH dilipat ke run, standby,
    // maupun off — "tidak kami tulis" dan "memang tidak jalan" adalah dua
    // kenyataan yang berbeda akibatnya.
    expect($dist['not_recorded'])->toBe(7);
    expect($dist['recorded_slots'])->toBe(12);

    // Invarian yang membuktikan sebaran ini utuh:
    // sum(counts) + not_recorded === recorded_slots === slot terisi.
    expect(array_sum($dist['counts']) + $dist['not_recorded'])->toBe($dist['recorded_slots']);
    expect($dist['recorded_slots'])->toBe($summary['coverage']['filled_slots']);
});

it('case 54 — standby TIDAK PERNAH digabung dengan off', function () {
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-1');

    foreach (array_slice($this->slots, 0, 10) as $index => $slot) {
        engineRoomReportSlot($record, $slot, [
            'diesel_gen_2_status' => $index < 6 ? 'standby' : 'off',
        ]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);
    $dist = $summary['status_distribution']['diesel_gen_2_status'];

    // Dua entri TERPISAH; tidak ada entri gabungan bernilai 10 dan tidak ada
    // pemetaan standby→off. Genset siaga siap menanggung beban, genset mati
    // tidak — melipat keduanya melaporkan pembangkit mati yang tidak pernah
    // terjadi.
    expect($dist['counts']['standby'])->toBe(6);
    expect($dist['counts']['off'])->toBe(4);
    expect($dist['counts'])->not->toHaveKey('standby_or_off');
    expect($dist['counts'])->not->toHaveKey('inactive');
    expect(array_values($dist['counts']))->not->toContain(10);
    expect(array_keys($dist['counts']))->toBe(['run', 'standby', 'off']);
});

it('case 55 — porsi sebaran memakai jumlah slot TERCATAT sebagai penyebut', function () {
    // Periode 10 hari × 1 unit × 24 = 240 slot yang diharapkan, 20 slot
    // tercatat, 15 di antaranya 'run'.
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-1');

    foreach (array_slice($this->slots, 0, 20) as $index => $slot) {
        engineRoomReportSlot($record, $slot, $index < 15
            ? ['diesel_gen_1_status' => 'run']
            : ['diesel_gen_1_status' => 'off']);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);
    $dist = $summary['status_distribution']['diesel_gen_1_status'];

    expect($summary['coverage']['expected_slots'])->toBe(240);
    expect($dist['recorded_slots'])->toBe(20);
    expect($dist['counts']['run'])->toBe(15);

    // 15/20 = 75,0% — BUKAN 15/240 = 6,3%. Penyebut yang dipublikasikan
    // adalah recorded_slots; expected_slots akan mencampur slot tak
    // tersentuh ke dalam porsi sebuah nilai yang justru tercatat.
    expect(round(100 * $dist['counts']['run'] / $dist['recorded_slots'], 1))->toBe(75.0);
    expect($dist['recorded_slots'])->not->toBe($summary['coverage']['expected_slots']);

    // not_recorded tidak ikut mengempiskan porsi nilai: di sini ia nol dan
    // ketiga porsinya menjumlah tepat 100%.
    expect($dist['not_recorded'])->toBe(0);
    expect(array_sum($dist['counts']))->toBe($dist['recorded_slots']);
});

it('case 56 — kolom status tidak punya min/avg/max dan tidak muncul pada seri harian', function () {
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-1');

    engineRoomReportSlot($record, '07:00', [
        'diesel_gen_1_status' => 'run',
        'diesel_gen_2_status' => 'standby',
        'electrical_sync_total_factory_load_kw' => 800.0,
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    foreach (array_keys(EngineRoomReportService::STATUS_COLUMNS) as $column) {
        $dist = $summary['status_distribution'][$column];

        // Entri sebaran TIDAK memuat min, avg, maupun max: terendah,
        // rata-rata dan tertinggi tidak punya arti untuk kolom kategoris.
        expect(array_keys($dist))->toBe(['known_values', 'counts', 'not_recorded', 'recorded_slots'], $column);
        expect($dist)->not->toHaveKey('min');
        expect($dist)->not->toHaveKey('avg');
        expect($dist)->not->toHaveKey('max');

        // Dan tidak muncul pada kartu metrik, rekap harian, rekap per unit,
        // maupun tren mana pun.
        expect($summary['metrics'])->not->toHaveKey($column);
        expect($summary['daily_trend'])->not->toHaveKey($column);
        expect($summary['daily'][0]['metrics_avg'])->not->toHaveKey($column);
        expect($summary['by_unit'][0]['metrics'])->not->toHaveKey($column);
    }
});

it('case 57 — dua sebaran status diterbitkan sebagai DUA struktur terpisah', function () {
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-1');

    // Pola yang BERBEDA pada kedua kolom — tanpa itu, sebuah sebaran
    // gabungan akan menjawab sama dan asersinya kosong.
    foreach (array_slice($this->slots, 0, 6) as $index => $slot) {
        engineRoomReportSlot($record, $slot, [
            'diesel_gen_1_status' => 'run',
            'diesel_gen_2_status' => $index < 2 ? 'standby' : 'off',
        ]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['status_distribution'])->toHaveCount(2);
    expect(array_keys($summary['status_distribution']))->toBe(['diesel_gen_1_status', 'diesel_gen_2_status']);

    expect($summary['status_distribution']['diesel_gen_1_status']['counts'])
        ->toBe(['run' => 6, 'standby' => 0, 'off' => 0]);
    expect($summary['status_distribution']['diesel_gen_2_status']['counts'])
        ->toBe(['run' => 0, 'standby' => 2, 'off' => 4]);

    // Tidak ada sebaran gabungan dua genset.
    expect($summary['status_distribution'])->not->toHaveKey('diesel_gen_status');
    expect($summary['status_distribution'])->not->toHaveKey('genset_status');
});

it('case 58 — seluruh slot tanpa status menghasilkan ketiga nilai nol dan semuanya not_recorded', function () {
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-1');

    foreach (array_slice($this->slots, 0, 18) as $slot) {
        engineRoomReportSlot($record, $slot, ['steam_turbine_rpm' => 3000.0]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);
    $dist = $summary['status_distribution']['diesel_gen_1_status'];

    expect($dist['counts'])->toBe(['run' => 0, 'standby' => 0, 'off' => 0]);
    expect($dist['not_recorded'])->toBe(18);
    expect($dist['recorded_slots'])->toBe(18);
    // Sebarannya TETAP diterbitkan, bukan dihilangkan.
    expect($summary['status_distribution'])->toHaveCount(2);
    expect($dist['known_values'])->toBe(['run', 'standby', 'off']);
});

// =====================================================================
// GROUP K — DUA KOLOM TEKS BEBAS, DUA DAFTAR TERPISAH (case 59-61)
// =====================================================================

it('case 59 — dua kolom teks bebas diterbitkan sebagai dua daftar terpisah dengan bentuk entri lengkap', function () {
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-1');

    engineRoomReportSlot($record, '07:00', ['action_taken_maintenance_remark' => 'Ganti filter oli']);
    engineRoomReportSlot($record, '08:00', ['findings' => 'Getaran turbin naik']);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['maintenance_remarks'])->toHaveCount(1);
    expect($summary['findings'])->toHaveCount(1);

    // Bentuk entrinya {date, time_slot, text}, dan slot waktunya HH:MM.
    expect($summary['maintenance_remarks'][0])->toBe([
        'date' => $this->day2,
        'time_slot' => '07:00',
        'text' => 'Ganti filter oli',
    ]);
    expect($summary['findings'][0])->toBe([
        'date' => $this->day2,
        'time_slot' => '08:00',
        'text' => 'Getaran turbin naik',
    ]);

    // Keduanya tidak pernah dirata-rata maupun masuk seri harian.
    expect($summary['metrics'])->not->toHaveKey('action_taken_maintenance_remark');
    expect($summary['metrics'])->not->toHaveKey('findings');
    expect($summary['daily_trend'])->not->toHaveKey('findings');
    expect($summary['daily'][0]['metrics_avg'])->not->toHaveKey('findings');

    // Dan tidak ada daftar gabungan.
    expect($summary)->not->toHaveKey('notes');
    expect($summary)->not->toHaveKey('remarks');
    expect($summary)->not->toHaveKey('text_entries');
});

it('case 60 — satu slot yang mengisi KEDUA kolom teks muncul pada KEDUA daftar', function () {
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-1');

    engineRoomReportSlot($record, '07:00', [
        'action_taken_maintenance_remark' => 'Kencangkan baut pondasi',
        'findings' => 'Baut pondasi longgar',
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // SEKALI di masing-masing daftar — menyatukan keduanya menjadi satu
    // daftarlah yang akan menghitung slot itu dua kali, di dalam satu
    // pengertian yang sama.
    expect($summary['maintenance_remarks'])->toHaveCount(1);
    expect($summary['findings'])->toHaveCount(1);
    expect($summary['maintenance_remarks'][0]['time_slot'])->toBe('07:00');
    expect($summary['findings'][0]['time_slot'])->toBe('07:00');
    expect($summary['maintenance_remarks'][0]['text'])->not->toBe($summary['findings'][0]['text']);

    // Dan filled_slots tetap bertambah SATU, bukan dua.
    expect($summary['coverage']['filled_slots'])->toBe(1);
});

it('case 61 — kedua kolom teks kosong sepanjang periode menghasilkan DUA daftar kosong', function () {
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-1');

    // Termasuk teks yang hanya berisi spasi: dipangkas menjadi null, bukan
    // entri kosong di dalam daftar.
    engineRoomReportSlot($record, '07:00', [
        'steam_turbine_rpm' => 3000.0,
        'action_taken_maintenance_remark' => '   ',
        'findings' => null,
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // DUA kunci terpisah yang tetap diterbitkan — bukan satu kunci null dan
    // bukan daftar yang dihilangkan.
    expect($summary)->toHaveKey('maintenance_remarks');
    expect($summary)->toHaveKey('findings');
    expect($summary['maintenance_remarks'])->toBe([]);
    expect($summary['findings'])->toBe([]);
    expect($summary['maintenance_remarks'])->not->toBeNull();
    expect($summary['findings'])->not->toBeNull();
});

// =====================================================================
// GROUP L — TREN HARIAN, REKAP PER UNIT, REKAP HARIAN (case 62-65)
// =====================================================================

it('case 62 — tren harian beban total pabrik adalah RATA-RATA per tanggal', function () {
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-1');

    foreach ([600.0, 800.0, 1000.0] as $index => $load) {
        engineRoomReportSlot($record, $this->slots[$index], [
            'electrical_sync_total_factory_load_kw' => $load,
        ]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // SATU tren saja, dan ia rata-rata — beban total pabrik besaran SESAAT
    // per slot, bukan angka kumulatif, jadi rata-rata sah di sini.
    expect(EngineRoomReportService::TREND_METRICS)->toBe(['electrical_sync_total_factory_load_kw']);
    expect($summary['daily_trend'])->toHaveCount(1);

    $trend = $summary['daily_trend']['electrical_sync_total_factory_load_kw'];

    expect($trend)->toHaveCount(1);
    expect($trend[0])->toBe([
        'date' => $this->day2,
        'avg' => 800.0,
        'reading_count' => 3,
    ]);
});

it('case 63 — agregasi tren beban BERBEDA dari agregasi seri ekspor energi pada hari yang sama', function () {
    // UJI INI MENEMUKAN cacat `sortBy([closure, closure])` di
    // lastEnergyValueOf() dan SUDAH HIJAU sejak perbaikannya (2026-10-15).
    // Diagnosis lengkap ada di kepala case 44. Asersi tren (rata-rata)
    // selalu benar; yang dahulu meleset adalah asersi seri energi.
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-1');

    // Slot 07:00, 12:00, 18:00 — berurutan juga pada kisi kanonis, sehingga
    // "nilai terakhir" = nilai 18:00.
    $rows = [
        '07:00' => [600.0, 400.0],
        '12:00' => [800.0, 900.0],
        '18:00' => [1000.0, 700.0],
    ];

    foreach ($rows as $slot => [$load, $energy]) {
        engineRoomReportSlot($record, $slot, [
            'electrical_sync_total_factory_load_kw' => $load,
            EngineRoomReportService::ENERGY_EXPORT_METRIC => $energy,
        ]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Tren beban = RATA-RATA (800) ...
    expect($summary['daily_trend']['electrical_sync_total_factory_load_kw'][0]['avg'])->toBe(800.0);
    // ... sementara seri energi = NILAI TERAKHIR (700). Kedua agregasi tidak
    // pernah dipertukarkan, dan layar menyatakan perbedaannya justru supaya
    // keduanya tidak terbaca sejenis.
    expect($summary['daily_energy_export']['series'][0]['last_value'])->toBe(700.0);
    // Rata-rata ketiga nilai energi adalah 666,667 — angka itu tidak boleh
    // muncul sebagai nilai seri.
    expect($summary['daily_energy_export']['series'][0]['last_value'])->not->toBe(666.667);
});

it('case 64 — rekap per unit dikelompokkan pada engine_room_records.engine_room_id', function () {
    // DUA unit pada line DAN stasiun yang SAMA: pengelompokan menurut
    // station_id atau production_line_id akan menghasilkan SATU baris, dan
    // itulah cacat yang asersi ini cari.
    $unitA = engineRoomReportRecord($this->stationA, $this->day1, 'ER-ALFA');
    engineRoomReportSlot($unitA, '07:00', ['steam_turbine_rpm' => 3000.0]);
    engineRoomReportSlot($unitA, '08:00', ['steam_turbine_rpm' => 3100.0]);

    // Unit yang sama pada tanggal LAIN tetap SATU baris dengan dua hari
    // tercatat — engine_room_id adalah NAMA UNIT yang diketik di layar
    // input, bukan kunci baris.
    $unitASecondDay = engineRoomReportRecord($this->stationA, $this->day2, 'ER-ALFA');
    engineRoomReportSlot($unitASecondDay, '07:00', ['steam_turbine_rpm' => 3200.0]);

    $unitB = engineRoomReportRecord($this->stationA, $this->day1, 'ER-BETA');
    engineRoomReportSlot($unitB, '07:00', ['steam_turbine_rpm' => 2000.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['by_unit'])->toHaveCount(2);
    expect(collect($summary['by_unit'])->pluck('engine_room_id')->all())->toBe(['ER-ALFA', 'ER-BETA']);

    $alfa = collect($summary['by_unit'])->firstWhere('engine_room_id', 'ER-ALFA');
    $beta = collect($summary['by_unit'])->firstWhere('engine_room_id', 'ER-BETA');

    expect($alfa['reading_rows'])->toBe(3);
    expect($alfa['reading_count'])->toBe(3);
    expect($alfa['days_with_rows'])->toBe(2);
    expect($alfa['metrics']['steam_turbine_rpm']['avg'])->toBe(3100.0);
    expect($alfa['metrics']['steam_turbine_rpm']['reading_count'])->toBe(3);

    expect($beta['reading_rows'])->toBe(1);
    expect($beta['days_with_rows'])->toBe(1);
    expect($beta['metrics']['steam_turbine_rpm']['avg'])->toBe(2000.0);

    // Bentuk entrinya, dan `energy_export` DI SAMPING `metrics` — bukan di
    // dalamnya: kolom itu nilai TERAKHIR unit ini, bukan rata-ratanya.
    expect(array_keys($alfa))->toBe([
        'engine_room_id', 'reading_rows', 'reading_count', 'days_with_rows',
        'metrics', 'energy_export',
    ]);
    expect($alfa['metrics'])->toHaveCount(22);
    expect(array_keys($alfa['energy_export']))->toBe(['last_value', 'date', 'time_slot', 'reading_count']);

    // Jumlah slot per unit menjumlah ke slot terisi periode: pengelompokan
    // tidak kehilangan maupun menghitung ganda satu slot pun.
    expect(collect($summary['by_unit'])->sum('reading_rows'))->toBe($summary['coverage']['filled_slots']);
    expect($summary['coverage']['engine_room_unit_count'])->toBe(2);
});

it('case 65 — unit tanpa pembacaan TETAP muncul pada rekap per unit dengan jumlah nol', function () {
    $terisi = engineRoomReportRecord($this->stationA, $this->day1, 'ER-TERISI');
    engineRoomReportSlot($terisi, '07:00', ['steam_turbine_rpm' => 3000.0]);

    // Unit kedua: punya record, tetapi tidak satu pun baris slotnya terisi.
    $kosong = engineRoomReportRecord($this->stationA, $this->day1, 'ER-KOSONG');
    engineRoomReportSlot($kosong, '07:00');

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['by_unit'])->toHaveCount(2);

    $kosongRow = collect($summary['by_unit'])->firstWhere('engine_room_id', 'ER-KOSONG');

    // Membuangnya akan menyembunyikan JUSTRU unit yang tidak pernah ditulis
    // — yang paling perlu dilihat — dan unit itu tetap ikut menambah
    // penyebut kelengkapan.
    expect($kosongRow)->not->toBeNull();
    expect($kosongRow['reading_rows'])->toBe(0);
    expect($kosongRow['days_with_rows'])->toBe(0);
    expect($kosongRow['energy_export']['last_value'])->toBeNull();

    foreach ($kosongRow['metrics'] as $column => $metric) {
        expect($metric['avg'])->toBeNull($column);
        expect($metric['reading_count'])->toBe(0, $column);
    }

    expect($summary['coverage']['engine_room_unit_count'])->toBe(2);
    expect($summary['coverage']['expected_slots'])->toBe(2 * 10 * 24);
});

// =====================================================================
// GROUP M — EKSPOR (case 66-70)
// =====================================================================

it('case 66 — ekspor menghasilkan SATU BARIS PER SLOT WAKTU dengan 27 kolom pembacaan', function () {
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-9');

    foreach (array_slice($this->slots, 0, 3) as $slot) {
        engineRoomReportSlot($record, $slot, [
            'steam_turbine_rpm' => 3000.0,
            'diesel_gen_1_status' => 'run',
            'action_taken_maintenance_remark' => 'Ganti seal',
            'findings' => 'Suara kasar',
        ]);
    }

    $this->actingAs($this->supervisorA);

    // TIGA PULUH LIMA kolom: 8 konteks (Periode, Mill, Production Line,
    // Tanggal, Unit Engine Room, Status, Catatan, Slot Waktu) + 27
    // READING_FIELDS.
    expect(EngineRoomReportService::EXPORT_HEADER)->toHaveCount(35);
    expect(array_slice(EngineRoomReportService::EXPORT_HEADER, 0, 8))->toBe([
        'Periode', 'Mill', 'Production Line', 'Tanggal',
        'Unit Engine Room', 'Status', 'Catatan', 'Slot Waktu',
    ]);
    expect(8 + count(EngineRoomRecordService::READING_FIELDS))
        ->toBe(count(EngineRoomReportService::EXPORT_HEADER));

    // Tidak ada kolom downtime dan tidak ada kolom ambang batas.
    foreach (EngineRoomReportService::EXPORT_HEADER as $header) {
        expect(strtolower($header))->not->toContain('downtime');
        expect(strtolower($header))->not->toContain('target');
        expect(strtolower($header))->not->toContain('ambang');
    }

    $rows = iterator_to_array($this->service->buildExportRows($this->periodA, null, $this->lineA));

    expect($rows)->toHaveCount(3);

    // Kolom konteks DIULANG VERBATIM pada ketiga baris — diulang, bukan
    // dikosongkan, supaya berkasnya langsung dapat dipivot di spreadsheet.
    foreach ($rows as $row) {
        expect($row)->toHaveCount(35);
        expect($row[0])->toBe('Periode Engine Room Alpha');
        expect($row[1])->toBe('Mill Alpha');
        expect($row[2])->not->toBe('');
        expect($row[3])->toBe($this->day2);
        expect($row[4])->toBe('ER-9');
        expect($row[6])->toBe('Catatan harian');
    }

    // Slot selalu HH:MM dan satu baris per slot.
    expect($rows[0][7])->toBe('07:00');
    expect($rows[1][7])->toBe('08:00');
    expect($rows[2][7])->toBe('09:00');

    // Urutan kolom bacaannya URUTAN READING_FIELDS, bukan urutan
    // NUMERIC_METRICS: kedua kolom status genset duduk DI DALAM blok
    // gensetnya di sana.
    $rpmIndex = 8 + array_search('steam_turbine_rpm', EngineRoomRecordService::READING_FIELDS, true);
    $remarkIndex = 8 + array_search('action_taken_maintenance_remark', EngineRoomRecordService::READING_FIELDS, true);
    $findingsIndex = 8 + array_search('findings', EngineRoomRecordService::READING_FIELDS, true);

    expect($rows[0][$rpmIndex])->toBe(3000.0);
    expect($rows[0][$remarkIndex])->toBe('Ganti seal');
    expect($rows[0][$findingsIndex])->toBe('Suara kasar');
    // Kedua kolom teks bebas ADA, dan di ujung blok 27 kolom.
    expect($findingsIndex)->toBe(34);

    // Slot yang kolom ukurnya kosong TETAP menjadi baris dengan sel KOSONG —
    // bukan dibuang, dan BUKAN ditulis 0.
    $empty = engineRoomReportRecord($this->stationA, $this->day3, 'ER-KOSONG');
    engineRoomReportSlot($empty, '07:00');

    $withEmpty = iterator_to_array($this->service->buildExportRows($this->periodA, null, $this->lineA));

    expect($withEmpty)->toHaveCount(4);
    expect($withEmpty[3][4])->toBe('ER-KOSONG');
    expect($withEmpty[3][8])->toBeNull();
    expect($withEmpty[3][34])->toBeNull();
    expect($withEmpty[3][8])->not->toBe(0);

    // Dan berkas yang di-stream memuat seluruh judul kolomnya.
    $body = engineRoomReportStreamed($this->service->export($this->periodA, 'csv', null, $this->lineA));

    foreach (EngineRoomReportService::EXPORT_HEADER as $header) {
        expect($body)->toContain($header);
    }
});

it('case 67 — ekspor lebih dari 50.000 baris ditolak 422 EXPORT_FAILED, dihitung atas BARIS SLOT', function () {
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-1');

    // SATU record, ENAM baris slot. Batas yang dihitung per RECORD akan
    // meloloskannya — satu record harian membawa sampai 24 baris, jadi batas
    // per record akan meloloskan berkas 24x lebih besar daripada yang
    // dimaksud.
    foreach (array_slice($this->slots, 0, 6) as $slot) {
        engineRoomReportSlot($record, $slot, ['steam_turbine_rpm' => 3000.0]);
    }

    $this->actingAs($this->supervisorA);

    $tiny = new EngineRoomReportTinyExportService;

    // Penolakannya datang dari PEMANGGILAN ITU SENDIRI, sebelum satu byte
    // pun dialirkan: buildExportRows() bukan generator, ia MEMULANGKAN
    // generator.
    expect(fn () => $tiny->buildExportRows($this->periodA, null, $this->lineA))
        ->toThrow(ExportFailedException::class);
    expect(fn () => $tiny->export($this->periodA, 'csv', null, $this->lineA))
        ->toThrow(ExportFailedException::class);

    $thrown = null;

    try {
        $tiny->buildExportRows($this->periodA, null, $this->lineA);
    } catch (ExportFailedException $e) {
        $thrown = $e;
    }

    expect($thrown)->not->toBeNull();
    expect($thrown->getStatusCode())->toBe(422);
    expect($thrown->errorCode())->toBe('EXPORT_FAILED');

    // Dan batas yang PERSIS SAMA masih lolos: "strictly greater than".
    EngineRoomDetail::query()->where('time_slot', 'like', $this->slots[5].'%')->delete();

    expect($tiny->buildExportRows($this->periodA, null, $this->lineA))->toBeInstanceOf(Generator::class);

    // Batas sungguhannya 50.000, dibaca lewat static:: sehingga subclass di
    // atas dapat menurunkannya alih-alih menyemai 50.000 baris.
    expect(EngineRoomReportService::EXPORT_ROW_LIMIT)->toBe(50000);
});

it('case 68 — format di luar csv|excel ditolak 422, dan format kosong jatuh ke csv', function () {
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-1');
    engineRoomReportSlot($record, '07:00', ['steam_turbine_rpm' => 3000.0]);

    $this->actingAs($this->supervisorA);

    expect(fn () => $this->service->export($this->periodA, 'pdf', null, $this->lineA))
        ->toThrow(ValidationException::class);

    try {
        $this->service->export($this->periodA, 'pdf', null, $this->lineA);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('format');
        expect($e->status)->toBe(422);
    }

    // Tanpa parameter format: bawaan metode ini csv.
    $default = $this->service->export($this->periodA, requestedBusinessUnitId: null, productionLineId: $this->lineA);

    expect($default->headers->get('content-type'))->toContain('text/csv');
    expect($default->headers->get('content-disposition'))->toContain('.csv');
    expect(EngineRoomReportService::SUPPORTED_FORMATS)->toBe(['csv', 'excel']);
});

it('case 69 — guard, resolusi mill/line, dan batas baris diperiksa EAGER sebelum streaming dimulai', function () {
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-1');
    engineRoomReportSlot($record, '07:00', ['steam_turbine_rpm' => 3000.0]);

    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::EngineRoom->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->create();

    // Operator mill A membawa production_line_id milik mill B: 403 dilempar
    // SEBELUM response streaming dibuka, sehingga klien tidak pernah
    // menerima berkas separuh yang sudah berisi header.
    $this->actingAs($this->operatorA);

    expect(fn () => $this->service->buildExportRows($this->periodA, null, $this->lineB))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->export($this->periodA, 'csv', null, $this->lineB))
        ->toThrow(AuthorizationException::class);

    // Line absen: 422, juga sebelum pengaliran.
    expect(fn () => $this->service->export($this->periodA, 'csv', null, null))
        ->toThrow(ValidationException::class);

    // Periode mill lain: 403.
    $this->actingAs($this->supervisorA);

    expect(fn () => $this->service->export($periodB, 'csv', null, $this->lineA))
        ->toThrow(AuthorizationException::class);

    // Dan format yang tidak didukung 422 — BUKAN sebuah stream yang dimulai
    // lalu gagal di tengah.
    expect(fn () => $this->service->export($this->periodA, 'pdf', null, $this->lineA))
        ->toThrow(ValidationException::class);
});

it('case 70 — ekspor memakai helper penulis nilai yang SAMA dengan layar Detail', function () {
    $record = engineRoomReportRecord($this->stationA, $this->day2, 'ER-9', [
        'status' => RecordStatus::Saved,
    ]);

    engineRoomReportSlot($record, '06:00', [
        'diesel_gen_1_status' => 'standby',
        'diesel_gen_2_status' => null,
        'steam_turbine_rpm' => 3000.0,
        'electrical_sync_power_factor' => null,
    ]);

    $this->actingAs($this->supervisorA);

    $rows = iterator_to_array($this->service->buildExportRows($this->periodA, null, $this->lineA));

    expect($rows)->toHaveCount(1);

    $readingFields = EngineRoomRecordService::READING_FIELDS;
    $gen1 = 8 + array_search('diesel_gen_1_status', $readingFields, true);
    $gen2 = 8 + array_search('diesel_gen_2_status', $readingFields, true);
    $powerFactor = 8 + array_search('electrical_sync_power_factor', $readingFields, true);

    // KEDUA PEMETA YANG TIDAK BOLEH DIPERTUKARKAN. Kolom status GENSET
    // memakai ExportValue::option() (Display::OPTION_LABELS → Run/Standby/
    // Off) supaya terbaca identik dengan layar Detail; kolom Status KONTEKS
    // memakai ExportValue::status() (Tersimpan/Tersinkron). Keduanya dinamai
    // mirip, dan menukarnya menghasilkan label yang tampak benar sampai
    // dibandingkan dengan layar itu — karena itu asersinya pada STRING
    // labelnya.
    expect($rows[0][$gen1])->toBe('Standby');
    expect($rows[0][$gen1])->not->toBe('Tersimpan');
    expect($rows[0][$gen2])->toBeNull();
    expect($rows[0][5])->toBe('Tersimpan');
    expect($rows[0][5])->not->toBe('Standby');

    // Sel kosong tetap KOSONG: bukan 0, bukan '-'.
    expect($rows[0][$powerFactor])->toBeNull();

    // Slot waktu '06:00' lewat ExportValue::time() — HH:MM, tanpa detik.
    expect($rows[0][7])->toBe('06:00');

    // Dan berkas excel adalah .xlsx SUNGGUHAN yang ditulis
    // App\Support\SheetWriter — bukan CSV bernama .xlsx (temuan audit
    // 2026-10-04 #1). Berkas xlsx adalah arsip ZIP, jadi dua byte
    // pertamanya 'PK' (0x504b).
    $excel = $this->service->export($this->periodA, 'excel', null, $this->lineA);

    expect($excel->headers->get('content-type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect($excel->headers->get('content-disposition'))->toContain('.xlsx');
    expect(bin2hex(substr(engineRoomReportStreamed($excel), 0, 2)))->toBe('504b');

    // Nama berkasnya menyebut laporan ini.
    expect($excel->headers->get('content-disposition'))->toContain('laporan-engine-room');
});

// =====================================================================
// GROUP N — JALUR SUKSES PENUH (case 74)
//
// case 71, 72 dan 73 menyangkut komponen Livewire dan blade-nya; ketiganya
// dibuktikan di tests/Feature/Livewire/LaporanEngineRoomTest.php dengan
// LABEL KASUS YANG SAMA.
// =====================================================================

it('case 74 — jalur sukses: seluruh prasyarat terpenuhi mengembalikan payload lengkap', function () {
    // DUA unit, kedua status, kedua kolom teks, kolom ekspor energi, dan
    // kolom ukur yang diisi pada HIMPUNAN SLOT YANG BERBEDA-BEDA.
    $alfa = engineRoomReportRecord($this->stationA, $this->day1, 'ER-ALFA');

    engineRoomReportSlot($alfa, '07:00', [
        'steam_turbine_inlet_pressure_bar' => 20.0,
        'steam_turbine_rpm' => 3000.0,
        'electrical_sync_total_factory_load_kw' => 900.0,
        'diesel_gen_1_status' => 'run',
        'diesel_gen_2_status' => 'standby',
        EngineRoomReportService::ENERGY_EXPORT_METRIC => 1200.0,
        'action_taken_maintenance_remark' => 'Ganti filter',
    ]);
    engineRoomReportSlot($alfa, '08:00', [
        'steam_turbine_rpm' => 3100.0,
        'electrical_sync_total_factory_load_kw' => 950.0,
        'findings' => 'Getaran naik',
    ]);

    $beta = engineRoomReportRecord($this->stationA, $this->day2, 'ER-BETA');

    engineRoomReportSlot($beta, '07:00', [
        'steam_turbine_rpm' => 2000.0,
        'diesel_gen_1_status' => 'off',
        EngineRoomReportService::ENERGY_EXPORT_METRIC => 800.0,
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Bentuk payload-nya, dan hanya itu.
    expect(array_keys($summary))->toBe([
        'period', 'business_unit', 'production_line', 'has_data', 'coverage',
        'metrics', 'daily_energy_export', 'status_distribution',
        'maintenance_remarks', 'findings', 'daily_trend', 'by_unit', 'daily',
        'daily_total', 'total', '_note_absences',
    ]);

    expect($summary['period']['name'])->toBe('Periode Engine Room Alpha');
    expect($summary['business_unit']['name'])->toBe('Mill Alpha');
    expect($summary['production_line']['id'])->toBe($this->lineA);
    expect($summary['has_data'])->toBeTrue();

    // Kelengkapan pencatatan: 2 unit × 10 hari × 24 slot.
    expect($summary['coverage']['filled_slots'])->toBe(3);
    expect($summary['coverage']['engine_room_unit_count'])->toBe(2);
    expect($summary['coverage']['expected_slots'])->toBe(480);

    // 22 kunci metrik, masing-masing berpenyebut SENDIRI.
    expect($summary['metrics'])->toHaveCount(22);
    expect(engineRoomReportMetric($summary, 'steam_turbine_rpm')['reading_count'])->toBe(3);
    expect(engineRoomReportMetric($summary, 'steam_turbine_inlet_pressure_bar')['reading_count'])->toBe(1);
    expect(engineRoomReportMetric($summary, 'electrical_sync_total_factory_load_kw')['reading_count'])->toBe(2);
    expect(engineRoomReportMetric($summary, 'electrical_sync_power_factor')['reading_count'])->toBe(0);
    // Penyebutnya BERBEDA-BEDA — satu penyebut bersama akan mencetak 3 pada
    // kedua puluh dua kartu.
    expect(collect($summary['metrics'])->pluck('reading_count')->unique())->toHaveCount(4);

    // Dua sebaran status lengkap dengan not_recorded.
    expect($summary['status_distribution'])->toHaveCount(2);
    expect($summary['status_distribution']['diesel_gen_1_status']['counts'])
        ->toBe(['run' => 1, 'standby' => 0, 'off' => 1]);
    expect($summary['status_distribution']['diesel_gen_1_status']['not_recorded'])->toBe(1);
    expect($summary['status_distribution']['diesel_gen_2_status']['counts'])
        ->toBe(['run' => 0, 'standby' => 1, 'off' => 0]);
    expect($summary['status_distribution']['diesel_gen_2_status']['not_recorded'])->toBe(2);

    // Seri harian ekspor energi: dua tanggal berdata, nilai TERAKHIR
    // masing-masing, dan TANPA rata-rata periode.
    expect($summary['daily_energy_export']['has_data'])->toBeTrue();
    expect($summary['daily_energy_export']['series'])->toHaveCount(2);
    expect($summary['metrics'])->not->toHaveKey(EngineRoomReportService::ENERGY_EXPORT_METRIC);

    // Tren beban total: RATA-RATA per tanggal.
    expect($summary['daily_trend']['electrical_sync_total_factory_load_kw'])->toHaveCount(2);
    expect($summary['daily_trend']['electrical_sync_total_factory_load_kw'][0]['avg'])->toBe(925.0);

    // Dua daftar teks TERPISAH.
    expect($summary['maintenance_remarks'])->toHaveCount(1);
    expect($summary['findings'])->toHaveCount(1);

    // Rekap per unit dan rekap harian.
    expect($summary['by_unit'])->toHaveCount(2);
    expect($summary['daily'])->toHaveCount(2);
    expect($summary['daily_total']['reading_rows'])->toBe(3);
    expect($summary['total']['days_with_records'])->toBe(2);
    expect($summary['total']['reading_rows'])->toBe(3);
    // Satu definisi, satu jawaban: slot per hari dan slot per unit
    // keduanya menjumlah ke angka yang sama.
    expect(collect($summary['daily'])->sum('reading_rows'))->toBe($summary['coverage']['filled_slots']);
    expect(collect($summary['by_unit'])->sum('reading_rows'))->toBe($summary['coverage']['filled_slots']);

    // TANPA kunci downtime, TANPA kunci ambang batas, TANPA rata-rata
    // periode untuk kolom ekspor energi — diasersi atas NAMA KUNCI.
    $keys = engineRoomReportAllKeys($summary);

    foreach (engineRoomReportForbiddenKeys() as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
    }
});

// =====================================================================
// SATU BARIS YANG MENENTUKAN LAYARNYA DAPAT DICAPAI (case 75)
// =====================================================================

it('case 75 — REPORT_ROUTES memetakan engine-room, dan posisinya mengikuti sort_order', function () {
    // Tanpa entri 'engine-room' pada StationReportService::REPORT_ROUTES,
    // layar laporan ada, seluruh uji lainnya lolos, dan tile-nya tetap
    // kelabu — pola kegagalan yang sudah pernah terjadi pada laporan Cages &
    // Tracks versi web.
    expect(StationReportService::REPORT_ROUTES)
        ->toHaveKey(StationTypeEnum::EngineRoom->value, 'reports.engine-room');
    // Kuncinya BERTANDA HUBUNG; kunci snake_case akan ter-compile, tampak
    // benar, dan tidak pernah cocok dengan satu baris master pun.
    expect(StationTypeEnum::EngineRoom->value)->toBe('engine-room');
    expect(route('reports.engine-room', [], false))->toBe('/reports/engine-room');

    // URUTANNYA LOAD-BEARING: peta ini harus tetap urut menurut
    // station_types.sort_order, karena layar pemilih stasiun membandingkan
    // urutannya dengan urutan master. engine-room di 120 — di belakang
    // depricarping (110), di depan process-water (130).
    //
    // PENERUSNYA DIPERBARUI 2026-10-16 (screen-160--laporan-process-water-web):
    // dulu storage-tank (140), karena dulu itulah entri berikutnya yang ada.
    // Entri 'process-water' DISISIPKAN di antara keduanya — sort_order 130 —
    // bukan di-append, jadi asersi inilah yang menjadi salah, dan entri
    // barunya yang benar. Asersi ini memang dirancang gugur ketika sebuah
    // laporan stasiun baru mendarat di antara dua entri yang sudah ada;
    // memperbaruinya adalah perawatan, bukan pelemahan.
    $codes = array_keys(StationReportService::REPORT_ROUTES);
    $at = array_search(StationTypeEnum::EngineRoom->value, $codes, true);

    expect($codes[$at - 1])->toBe(StationTypeEnum::Depricarping->value);
    expect($codes[$at + 1])->toBe(StationTypeEnum::ProcessWater->value);
});
