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
