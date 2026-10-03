<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case — test infrastructure (shared-modules)
|--------------------------------------------------------------------------
| Feature tests boot the full Laravel application (Tests\TestCase) and reset
| the database between tests (RefreshDatabase, against the `sqlite`
| in-memory testing connection configured in phpunit.xml). Covers API
| endpoints (Sanctum) and Livewire web routes generated per-screen in
| impl-2-screen.
*/
uses(TestCase::class, RefreshDatabase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Unit Tests
|--------------------------------------------------------------------------
| Unit tests do not boot the application — plain Pest test cases, used for
| isolated logic (e.g. App\Support\Pagination, service classes).
*/
uses()->in('Unit');

/*
|--------------------------------------------------------------------------
| Kunci Periode Pelaporan (usecase-141) — prasyarat bersama
|--------------------------------------------------------------------------
|
| Sejak kunci periode ditegakkan di ke-18 *RecordService, SETIAP penulisan data
| stasiun lewat service menuntut adanya Periode Pelaporan yang TERBUKA untuk
| jenis stasiun itu di mill-nya, dengan tanggal kejadian di dalam rentangnya.
| Ratusan test yang sudah ada menulis record tanpa periode apa pun — bukan
| karena mereka menguji kunci itu, melainkan karena dulu kuncinya tidak ada.
|
| Helper di bawah memenuhi prasyarat itu supaya test tersebut kembali menguji
| hal yang memang jadi subjeknya. Kunci periodenya sendiri diuji tersendiri di
| tests/Unit/Support/EnforcesPeriodLockTest.php dan
| tests/Feature/Api/KelolaPeriodePelaporanTest.php — JANGAN memakai helper ini
| di sana, karena di sana ketiadaan periode justru keadaan yang diuji.
|
| Rentangnya sengaja sangat lebar (tahun 2000-2999): test memakai tanggal yang
| tersebar dari 2026 sampai era tahun 9000-an, dan satu periode lebar membuat
| helper ini tidak perlu tahu tanggal apa yang dipakai test pemanggilnya. Ia
| MEM-BYPASS PeriodService, jadi aturan tumpang tindih tidak ikut berjalan —
| itu disengaja: ini menyiapkan prasyarat, bukan menguji pembuatan periode.
*/

/**
 * Membuka periode untuk satu (mill, jenis stasiun). Idempoten: satu periode
 * lebar per mill, baris stasiunnya ditambahkan sesuai kebutuhan, sehingga
 * memanggilnya untuk beberapa jenis stasiun di mill yang sama tidak melahirkan
 * periode bertumpuk.
 */
function openPeriodFor(string $businessUnitId, string $stationType): \App\Models\Period
{
    $period = \App\Models\Period::query()
        ->where('business_unit_id', $businessUnitId)
        ->where('name', 'Periode Prasyarat Test')
        ->first();

    if ($period === null) {
        $period = \App\Models\Period::factory()
            ->forBusinessUnit($businessUnitId)
            ->named('Periode Prasyarat Test')
            ->range('2000-01-01', '2999-12-31')
            ->noStations()
            ->create();
    }

    \App\Models\PeriodStation::query()->firstOrCreate(
        ['period_id' => $period->id, 'station_type' => $stationType],
        ['status' => \App\Enums\PeriodStatus::Open->value],
    );

    return $period;
}

/** Varian yang menerima Station — bentuk yang dipegang hampir semua test. */
function openPeriodForStation(\App\Models\Station $station): \App\Models\Period
{
    $type = $station->type instanceof \App\Enums\StationType
        ? $station->type->value
        : (string) $station->type;

    return openPeriodFor($station->business_unit_id, $type);
}

/*
|--------------------------------------------------------------------------
| Kunci Periode Pelaporan (usecase-141) — fixture kontrak HTTP per stasiun
|--------------------------------------------------------------------------
|
| Dipakai oleh blok "kunci periode" di ke-18 tests/Feature/Api/Form*Test.php.
| Berkas-berkas itu membuka periode prasyarat yang sangat lebar di beforeEach
| (openPeriodFor()); selama periode itu ada, setiap tanggal lolos dan kunci
| periodenya tidak pernah teruji. Helper ini MENGGANTI periode prasyarat itu
| dengan dua periode sempit yang tanggalnya diketahui test:
|
|   - "Periode Juli (Tertutup)"  2026-07-01 s/d 2026-07-31, baris stasiun CLOSED
|   - "Periode Agustus (Terbuka)" 2026-08-01 s/d 2026-08-31, baris stasiun OPEN
|
| Tanggal ditulis 'Y-m-d' polos: kunci periodenya membandingkan lewat
| whereDate(), jadi bentuk ini sama-sama benar di SQLite dan PostgreSQL.
*/

/** Konstanta tanggal fixture — dipakai test agar angka ajaibnya tidak tersebar. */
const PERIOD_LOCK_CLOSED_DATE = '2026-07-15';
const PERIOD_LOCK_OPEN_START = '2026-08-01';
const PERIOD_LOCK_OPEN_MID = '2026-08-15';
const PERIOD_LOCK_OPEN_END = '2026-08-31';

function replacePrerequisiteWithClosedAndOpenPeriods(string $businessUnitId, string $stationType): void
{
    // Periode prasyarat dihapus seluruhnya (baris period_stations ikut terhapus
    // lewat cascadeOnDelete), supaya tidak ada rentang lebar yang diam-diam
    // menerima tanggal yang seharusnya ditolak.
    \App\Models\Period::query()
        ->where('business_unit_id', $businessUnitId)
        ->where('name', 'Periode Prasyarat Test')
        ->get()
        ->each(function (\App\Models\Period $period) {
            \App\Models\PeriodStation::query()->where('period_id', $period->id)->delete();
            $period->delete();
        });

    foreach ([
        ['Periode Juli (Tertutup)', '2026-07-01', '2026-07-31', \App\Enums\PeriodStatus::Closed],
        ['Periode Agustus (Terbuka)', PERIOD_LOCK_OPEN_START, PERIOD_LOCK_OPEN_END, \App\Enums\PeriodStatus::Open],
    ] as [$name, $start, $end, $status]) {
        $period = \App\Models\Period::factory()
            ->forBusinessUnit($businessUnitId)
            ->named($name)
            ->range($start, $end)
            ->noStations()
            ->create();

        \App\Models\PeriodStation::factory()
            ->forPeriod($period)
            ->stationType($stationType)
            ->create(['status' => $status->value]);
    }
}
