<?php

use App\Enums\PeriodStatus;
use App\Enums\StationType;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
function openPeriodFor(string $businessUnitId, string $stationType): Period
{
    $period = Period::query()
        ->where('business_unit_id', $businessUnitId)
        ->where('name', 'Periode Prasyarat Test')
        ->first();

    if ($period === null) {
        $period = Period::factory()
            ->forBusinessUnit($businessUnitId)
            ->named('Periode Prasyarat Test')
            ->range('2000-01-01', '2999-12-31')
            ->noStations()
            ->create();
    }

    PeriodStation::query()->firstOrCreate(
        ['period_id' => $period->id, 'station_type' => $stationType],
        ['status' => PeriodStatus::Open->value],
    );

    return $period;
}

/** Varian yang menerima Station — bentuk yang dipegang hampir semua test. */
function openPeriodForStation(Station $station): Period
{
    $type = $station->type instanceof StationType
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
    Period::query()
        ->where('business_unit_id', $businessUnitId)
        ->where('name', 'Periode Prasyarat Test')
        ->get()
        ->each(function (Period $period) {
            PeriodStation::query()->where('period_id', $period->id)->delete();
            $period->delete();
        });

    foreach ([
        ['Periode Juli (Tertutup)', '2026-07-01', '2026-07-31', PeriodStatus::Closed],
        ['Periode Agustus (Terbuka)', PERIOD_LOCK_OPEN_START, PERIOD_LOCK_OPEN_END, PeriodStatus::Open],
    ] as [$name, $start, $end, $status]) {
        $period = Period::factory()
            ->forBusinessUnit($businessUnitId)
            ->named($name)
            ->range($start, $end)
            ->noStations()
            ->create();

        PeriodStation::factory()
            ->forPeriod($period)
            ->stationType($stationType)
            ->create(['status' => $status->value]);
    }
}

/**
 * fakeRealImage() — file unggahan berisi PNG SUNGGUHAN (1x1, ditambah byte
 * pengisi sampai $kilobytes). Pengganti UploadedFile::fake()->create(
 * 'logo.jpg', N, 'image/jpeg'), yang isinya hanya byte nol: sejak App\Rules\
 * RealImage memeriksa ISI file (temuan audit 2026-10-04 #6), file seperti itu
 * memang harus ditolak. GD tidak terpasang, jadi ->image() tidak tersedia.
 */
function fakeRealImage(string $name = 'logo.png', int $kilobytes = 1): UploadedFile
{
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    $padding = max(0, $kilobytes * 1024 - strlen($png));

    return UploadedFile::fake()->createWithContent($name, $png.str_repeat("\0", $padding));
}

/**
 * exportBodyAsCsv() — isi file ekspor sebagai teks CSV. Body CSV
 * dikembalikan apa adanya; body xlsx (diawali "PK", sejak temuan audit
 * 2026-10-04 #1 "Ekspor Excel" adalah file .xlsx sungguhan, bukan CSV
 * berlabel .xlsx) dibuka dengan ZipArchive, xl/worksheets/sheet1.xml
 * dibaca, dan setiap baris ditulis ulang sebagai satu baris CSV — supaya
 * tes yang memeriksa judul kolom/isi tetap berlaku untuk kedua format.
 */
function exportBodyAsCsv(string $body): string
{
    if (! str_starts_with($body, 'PK')) {
        return $body;
    }

    return implode('', array_map(function (array $row) {
        $out = fopen('php://memory', 'w+');
        fputcsv($out, $row, ',', '"', '\\');
        rewind($out);
        $line = stream_get_contents($out);
        fclose($out);

        return $line;
    }, xlsxRows($body)));
}

/**
 * xlsxRows() — baca lembar pertama file xlsx (bytes) menjadi array baris.
 * Gagal (melempar) bila bytes bukan paket xlsx yang sah: tidak ada
 * [Content_Types].xml, workbook, atau sheet1.xml, atau XML-nya rusak.
 *
 * @return list<list<string|int|float|null>>
 */
function xlsxRows(string $bytes): array
{
    $path = tempnam(sys_get_temp_dir(), 'xlsx-test-');
    file_put_contents($path, $bytes);

    $zip = new ZipArchive;
    if ($zip->open($path) !== true) {
        @unlink($path);
        throw new RuntimeException('Bukan file zip/xlsx.');
    }

    foreach (['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/worksheets/sheet1.xml'] as $part) {
        if ($zip->locateName($part) === false) {
            $zip->close();
            @unlink($path);
            throw new RuntimeException("Bagian xlsx hilang: {$part}");
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    @unlink($path);

    $sheet = simplexml_load_string($sheetXml);
    if ($sheet === false) {
        throw new RuntimeException('sheet1.xml bukan XML yang sah.');
    }

    $rows = [];
    foreach ($sheet->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $cell) {
            $ref = (string) $cell['r'];
            $letters = preg_replace('/\d+/', '', $ref);
            $index = 0;
            foreach (str_split($letters) as $char) {
                $index = $index * 26 + (ord($char) - 64);
            }
            $index--;

            $value = (string) $cell['t'] === 'inlineStr'
                ? (string) $cell->is->t
                : ((string) $cell->v === '' ? null : (str_contains((string) $cell->v, '.') ? (float) (string) $cell->v : (int) (string) $cell->v));

            for ($i = count($cells); $i < $index; $i++) {
                $cells[$i] = null;
            }
            $cells[$index] = $value;
        }
        $rows[] = $cells;
    }

    return $rows;
}
