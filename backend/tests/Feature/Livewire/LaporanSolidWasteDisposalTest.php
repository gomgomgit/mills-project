<?php

/**
 * LaporanSolidWasteDisposalTest (Feature/Livewire) —
 * screen-162--laporan-solid-waste-disposal-web, halamannya sendiri.
 *
 * Satu uji per entri `test_scenarios[].component_test` pada tech spec
 * screen-162 (29 skenario), ditambah beberapa invarian struktural yang hanya
 * lapisan ini dapat menjaganya.
 *
 * LAPISAN INI ADA UNTUK KEADAAN YANG TIDAK BISA DIATUR DI TEMPAT LAIN.
 * Angka-angkanya sudah dibuktikan terhadap data yang dikendalikan penuh di
 * backend/tests/Unit/Services/SolidWasteDisposalReportServiceTest.php (59
 * kasus), jadi berkas ini TIDAK mengulanginya. Yang dibuktikan di sini adalah
 * apa yang HALAMAN lakukan terhadap angka itu: pemilih mana yang ada untuk
 * peran mana, keadaan kosong mana yang digambar, dan hal-hal yang layar ini
 * tidak boleh biarkan terbaca salah.
 *
 * DIASERSI ATAS HTML TER-RENDER di mana pun klaimnya tentang apa yang pembaca
 * LIHAT: sebuah data-testid yang ada di markup tetapi tidak pernah dicapai
 * kondisional blade akan tetap meloloskan asersi atas state. Dan diasersi atas
 * PAYLOAD (`viewData('summary')`) di mana pun klaimnya tentang apa yang layar
 * ini TIDAK terbitkan — lihat jebakan di bawah.
 *
 * ────────────────────────────────────────────────────────────────────────
 * TIGA BENTUK ASERSI YANG SUDAH TERBUKTI HAMPA DI PROYEK INI
 * ────────────────────────────────────────────────────────────────────────
 *   1. `expect()->toContain($a, $b)` memperlakukan argumen kedua sebagai
 *      NEEDLE KEDUA, bukan pesan. Setiap asersi berpesan di bawah memakai
 *      `$this->assertStringContainsString(...)` / `$this->assertContains(...)`.
 *   2. assertSee/assertStringContainsString TIDAK membuktikan cacat TAMPILAN:
 *      markup dapat benar sementara elemennya terpotong induknya. Tidak satu
 *      klaim visual pun diangkat di berkas ini; geometri adalah pekerjaan spec
 *      Playwright.
 *   3. MENGASERSIKAN KETIADAAN LEWAT TEKS akan cocok dengan prosa yang justru
 *      MENYATAKAN ketiadaan itu. Di layar ini jebakannya nyata, bukan
 *      hipotetis: `_note_no_hourly` memuat kata 'jam' dan 'sebaran',
 *      `_note_shift_not_published` memuat kata 'shift', dan keduanya DIRENDER
 *      UTUH. Karena itu 'tidak ada blok per jam' dan 'kolom shift tidak
 *      diterbitkan' SELALU diasersikan atas NAMA KUNCI payload lewat penelusur
 *      rekursif, dan atas TESTID di DOM — tidak pernah atas kata.
 *
 * ────────────────────────────────────────────────────────────────────────
 * DUA HAL STRUKTURAL YANG HANYA LAPISAN INI MENJAGA
 * ────────────────────────────────────────────────────────────────────────
 *   1. canAccess() MEMEGANG DAFTAR PERANNYA SENDIRI dan TIDAK mendelegasikan
 *      ke SolidWasteDisposalReportService::guardAccess(). Service itu
 *      MENERIMA Operator SEJAK COMMIT PERTAMA (kembaran mobile screen-163
 *      dibangun dalam seri yang sama), jadi delegasi akan membuka layar web
 *      ini untuk Operator dalam tarikan napas yang sama — tanpa satu galat pun
 *      dan tanpa apa pun di layar yang menandainya. Bahayanya BUKAN di masa
 *      depan: ia sudah ada hari ini.
 *   2. KOTAK KETERANGAN MEMAKAI `.md-explain`, BUKAN `.md-threshold`. Keduanya
 *      tampil identik dan yang berbeda hanya namanya; layar ini sengaja tidak
 *      menandai satu pun nilai di luar batas, dan ketiadaan itu diasersi
 *      MENURUT NAMA KELAS pada HTML ter-render.
 *
 * TANGGALNYA SELALU RELATIF (now()->subDays(...)), dan tanggal record SELALU
 * DIPAKU lewat ->onDate(): default SolidWasteDisposalRecordFactory adalah
 * dateTimeBetween('-30 days','now'), dan asersi "tepat N muatan" yang
 * menumpang default itu gagal sesekali karena alasan yang tidak ada
 * hubungannya dengan layar ini.
 *
 * Helper swdLoad() juga me-NULL-kan setiap kolom yang berarti bagi laporan,
 * karena SolidWasteDisposalDetailFactory mengisi keempat kolom kategoris dan
 * kedua nomor dokumen dengan nilai faker ACAK — fixture yang menumpangnya akan
 * mengasersikan rekap yang isinya berubah tiap jalan.
 */

use App\Enums\RecordStatus;
use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Livewire\Dashboard\LaporanSolidWasteDisposal;
use App\Livewire\Dashboard\LaporanStasiun;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\SolidWasteDisposalDetail;
use App\Models\SolidWasteDisposalRecord;
use App\Models\Station;
use App\Models\User;
use App\Services\SolidWasteDisposalReportService;
use App\Support\ExportValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Setiap data-testid yang HANYA ada setelah sebuah angka benar-benar dirender
 * — yaitu setelah mill, production line DAN periode terpilih. Berlaku pada
 * periode berdata MAUPUN periode kosong: kartu tetap digambar dengan tanda
 * pisah, karena kartu yang hilang terbaca sebagai "angka itu tidak ada".
 */
const LAPORAN_SWD_FIGURE_TESTIDS = [
    'per-transaction-note',
    // SATU KOTAK: angka pokok + rekap tujuan penggunaan akhir.
    'loads-card',
    'kpi-load-count',
    'kartu-total-netto',
    'net-weight-total',
    'kartu-rata-netto',
    'net-weight-avg',
    'net-weight-load-count',
    'kartu-penimbangan-belum-selesai',
    'missing-net-weight-load-count',
    // `undated-note` SENGAJA TIDAK DI SINI: ia dirender HANYA ketika
    // undated_load_count > 0, dan `event_date` adalah kolom NOT NULL
    // (migrasi 2026_08_31_000003) sehingga angkanya selalu 0 dalam praktik.
    // Kedua cabangnya diuji tersendiri di skenario 'undated' di bawah.
    'by-purpose-end-use-sub',
    'by-purpose-end-use-table',
    'by-purpose-end-use-row-total',
    'by-purpose-end-use-note',
    // Tiga rekap kategoris lainnya.
    'by-waste-type-details',
    'by-waste-type-table',
    'by-waste-type-row-total',
    'by-waste-type-note',
    'by-source-station-details',
    'by-source-station-table',
    'by-source-station-row-total',
    'by-source-station-note',
    'by-disposal-site-details',
    'by-disposal-site-table',
    'by-disposal-site-row-total',
    'by-disposal-site-note',
    // Jejak kepatuhan — kartunya SENDIRI, bukan bagian blok muatan.
    'compliance-card',
    'kpi-missing-gate-pass',
    'kpi-missing-seal',
    'compliance-note',
    // KETIADAAN YANG DINYATAKAN.
    'no-hourly-note',
    'shift-not-published-note',
    'absences-note',
    // Rekap harian + tren + kedua rekap teks + kelengkapan + draft.
    'daily-recap-details',
    'daily-table',
    'daily-row-total',
    'daily-recap-note',
    'daily-trend',
    'findings-card',
    'findings-note',
    'remarks-card',
    'remarks-note',
    'completeness-card',
    'days-with-load',
    'days-counted',
    'days-with-load-percent',
    'completeness-note',
    'draft-card',
    'kpi-draft-load-count',
    'draft-note',
];

/** Satu record induk; tanggalnya SELALU dipaku. */
function swdLaporanRecord(Station $station, string $date, array $attributes = []): SolidWasteDisposalRecord
{
    return SolidWasteDisposalRecord::factory()
        ->forStation($station)
        ->onDate($date)
        ->create(array_merge([
            'status' => RecordStatus::Saved,
            'note' => null,
        ], $attributes));
}

/** Satu MUATAN KELUAR. Lihat docblock berkas untuk alasan semua default null. */
function swdLaporanLoad(
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
            'remarks' => null,
            'findings' => null,
            'gross_weight_mt' => null,
            'tare_weight_mt' => null,
            'net_weight_mt' => 1.0,
        ], $values));
}

/**
 * Potongan satu elemen ber-data-testid, dari atribut itu sampai $closing.
 *
 * DIPAKAI SETIAP KALI KLAIMNYA TENTANG SATU ELEMEN. '—' sepanjang satu
 * karakter dan label sependek 'Draft' / '6' cocok di mana saja pada halaman
 * ini; mengasersikannya atas seluruh HTML tidak membuktikan apa pun.
 */
function swdLaporanSlice(string $html, string $testid, string $closing = '</p>'): string
{
    $start = strpos($html, 'data-testid="'.$testid.'"');

    if ($start === false) {
        return '';
    }

    $end = strpos($html, $closing, $start);

    return $end === false ? substr($html, $start) : substr($html, $start, $end - $start);
}

/** Potongan satu kartu KPI (<article>). */
function swdLaporanCard(string $html, string $testid): string
{
    return swdLaporanSlice($html, $testid, '</article>');
}

/** Potongan satu tabel rekap. */
function swdLaporanTable(string $html, string $testid): string
{
    return swdLaporanSlice($html, $testid, '</table>');
}

/** Seluruh potongan <tr> di dalam satu potongan tabel. */
function swdLaporanRows(string $table): array
{
    preg_match_all('#<tr\b[^>]*>.*?</tr>#s', $table, $matches);

    return $matches[0];
}

/** Nilai sel ke-$index (1-basis) di dalam satu potongan <tr>. */
function swdLaporanCell(string $row, int $index): ?string
{
    if (preg_match_all('#<td[^>]*>(.*?)</td>#s', $row, $matches) < $index) {
        return null;
    }

    return trim(html_entity_decode(strip_tags($matches[1][$index - 1])));
}

/**
 * Setiap nama KUNCI, serapa pun dalamnya, di dalam payload.
 *
 * Lihat jebakan (3) di docblock berkas: ketiadaan sebuah blok TIDAK PERNAH
 * diasersikan atas prosa, karena prosa yang menyatakan ketiadaannya memuat
 * kata yang sama.
 *
 * @return list<string>
 */
function swdLaporanAllKeys(mixed $value): array
{
    if (! is_array($value)) {
        return [];
    }

    $keys = [];

    foreach ($value as $key => $child) {
        if (is_string($key)) {
            $keys[] = $key;
        }

        $keys = array_merge($keys, swdLaporanAllKeys($child));
    }

    return array_values(array_unique($keys));
}

/**
 * Service yang membaca record dari MEMORI alih-alih dari basis data, dipakai
 * HANYA oleh skenario `undated-note` di bawah.
 *
 * ADA KARENA SKEMANYA, BUKAN KARENA KEMALASAN: `event_date` adalah kolom NOT
 * NULL (migrasi 2026_08_31_000003 menulis `$table->date('event_date')` tanpa
 * `->nullable()`, dan tidak ada migrasi sesudahnya yang mengubahnya), jadi
 * muatan tak bertanggal DITOLAK di tingkat basis data dan cabang `> 0` pada
 * blade tidak dapat dicapai lewat factory. Meng-override recordsFor() membuat
 * agregasinya tetap dijalankan sungguhan atas muatan itu, sehingga cabang
 * render-nya benar-benar diuji alih-alih ditandai 'tidak dapat diuji'.
 */
class LaporanSwdInMemoryReportService extends SolidWasteDisposalReportService
{
    /** @var Collection<int, SolidWasteDisposalRecord> */
    public static Collection $records;

    protected function recordsFor(Period $period, string $productionLineId): Collection
    {
        return static::$records;
    }
}

/** Isi sebuah StreamedResponse, ditangkap. ALIRKAN SEKALI per response. */
function swdLaporanStreamed($response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

/**
 * Komponen yang di-instansiasi LANGSUNG, dipakai hanya untuk mengambil
 * StreamedResponse ekspornya.
 *
 * Livewire::test()->call('exportCsv') membuktikan unduhannya TERJADI
 * (assertFileDownloaded) tetapi tidak menyerahkan byte-nya; dan yang perlu
 * dibuktikan untuk skenario 27/28 adalah ISI berkasnya — bahwa kolom `shift`
 * yang sengaja tidak ada di layar MEMANG ada di baris-barisnya. Jalur ini
 * tetap melewati exportCsv() milik komponen, termasuk penerjemahan
 * 'excel' -> 'xlsx' yang menjadi miliknya sendiri.
 */
function swdLaporanExport(string $periodId, string $productionLineId, string $format, string $businessUnitId = '')
{
    $component = new LaporanSolidWasteDisposal;
    $component->businessUnitId = $businessUnitId;
    $component->productionLineId = $productionLineId;
    $component->periodId = $periodId;

    return $component->exportCsv($format);
}

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->solidWasteDisposal()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->solidWasteDisposal()->create();

    $this->lineA = (string) $this->stationA->production_line_id;
    $this->lineB = (string) $this->stationB->production_line_id;

    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    // PERIODE 31 HARI YANG SUDAH SELESAI: days_counted === days_in_period ===
    // 31 apa pun tanggal kalender saat suite dijalankan.
    $this->periodStart = now()->subDays(40)->toDateString();
    $this->periodEnd = now()->subDays(10)->toDateString();

    $this->day1 = now()->subDays(39)->toDateString();
    $this->day2 = now()->subDays(38)->toDateString();
    $this->day3 = now()->subDays(37)->toDateString();

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::SolidWasteDisposal->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode SWD Alpha')
        ->create();
});

// =====================================================================
// JALUR SUKSES DAN PERAN
// =====================================================================

it('skenario 1 — Supervisor: tanpa pemilih mill, seluruh bagian laporan terender', function () {
    $record = swdLaporanRecord($this->stationA, $this->day1);

    foreach (range(1, 4) as $i) {
        swdLaporanLoad($record, $this->day1, [
            'net_weight_mt' => $i <= 3 ? 2.0 : null,
            'solid_waste_type' => 'Fiber',
            'source_station' => 'Pressing',
            'disposal_utilization_site' => 'TPA Utara',
            'purpose_end_use' => 'Composting',
            'findings' => $i === 1 ? 'Ceceran di gerbang' : null,
            'remarks' => $i === 1 ? 'Dibersihkan petugas' : null,
        ]);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSolidWasteDisposal::class)
        ->set('productionLineId', $this->lineA);

    $html = $component->html();

    // Peran terikat mill TIDAK melihat pemilih mill — menawarkan pemilih yang
    // tidak dapat ia pakai adalah kebohongan kecil yang mahal.
    expect($html)->not->toContain('data-testid="mill-select"');
    expect($html)->toContain('data-testid="mill-name"');
    expect($html)->toContain('Mill Alpha');

    foreach (LAPORAN_SWD_FIGURE_TESTIDS as $testid) {
        $this->assertStringContainsString('data-testid="'.$testid.'"', $html, "testid {$testid} tidak dirender");
    }

    // ASERSI ATAS DATA YANG DIPUBLIKASIKAN KOMPONEN, bukan atas prosa.
    $summary = $component->viewData('summary');

    expect($summary['loads']['load_count'])->toBe(4);
    expect($summary['loads']['net_weight_total'])->toBe(6.0);
    expect($summary['loads']['net_weight_load_count'])->toBe(3);
    expect($summary['loads']['missing_net_weight_load_count'])->toBe(1);

    // PENYEBUT RATA-RATA TERCETAK DI DALAM KARTU RATA-RATA, bukan di tempat
    // lain di halaman: '3' cocok di mana saja, jadi ia disasarkan ke kartunya.
    $kartuRata = swdLaporanCard($html, 'kartu-rata-netto');

    $this->assertStringContainsString('data-testid="net-weight-load-count"', $kartuRata);
    $this->assertStringContainsString('>3</b>', $kartuRata);

    // 'Penimbangan belum selesai' adalah KARTUNYA SENDIRI, bukan catatan kaki.
    $kartuBelum = swdLaporanCard($html, 'kartu-penimbangan-belum-selesai');

    $this->assertStringContainsString('Penimbangan Belum Selesai', $kartuBelum);
    $this->assertStringContainsString('penimbangan belum selesai', $kartuBelum);
    // Kalimatnya berbunyi itu, BUKAN 'data tidak lengkap'.
    $this->assertStringNotContainsString('data tidak lengkap', $kartuBelum);

    // KETIGA prosa dirender UTUH — dibandingkan string PENUH, bukan substring.
    foreach ([
        'no-hourly-note' => SolidWasteDisposalReportService::NOTE_NO_HOURLY,
        'shift-not-published-note' => SolidWasteDisposalReportService::NOTE_SHIFT_NOT_PUBLISHED,
        'absences-note' => SolidWasteDisposalReportService::NOTE_ABSENCES,
    ] as $testid => $prosa) {
        $potongan = swdLaporanSlice($html, $testid, '</div>');

        $this->assertStringContainsString(
            e($prosa),
            $potongan,
            "prosa {$testid} tidak dirender utuh",
        );
    }

    // Dua bagian teks TERPISAH, dengan judulnya masing-masing.
    expect($html)->toContain('Rekap Temuan');
    expect($html)->toContain('Rekap Catatan');
    $this->assertStringContainsString('Ceceran di gerbang', swdLaporanSlice($html, 'findings-card', '</section>'));
    $this->assertStringContainsString('Dibersihkan petugas', swdLaporanSlice($html, 'remarks-card', '</section>'));

    // Kedua tombol ekspor tampil.
    expect($html)->toContain('data-testid="export-button"');
    expect($html)->toContain('data-testid="export-excel-button"');
});

it('skenario 1b — Mill Management melihat layar yang SAMA PERSIS dengan Supervisor', function () {
    swdLaporanLoad(swdLaporanRecord($this->stationA, $this->day1), $this->day1, ['net_weight_mt' => 3.0]);

    $html = Livewire::actingAs($this->millManagement)
        ->test(LaporanSolidWasteDisposal::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->not->toContain('data-testid="mill-select"');
    expect($html)->toContain('data-testid="mill-name"');

    foreach (LAPORAN_SWD_FIGURE_TESTIDS as $testid) {
        $this->assertStringContainsString('data-testid="'.$testid.'"', $html, "testid {$testid} tidak dirender");
    }
});

it('skenario 2 — Operator ditolak pada RUTE WEB dan pada mount() komponennya', function () {
    // Component test TIDAK melewati middleware, jadi hanya baris ini yang
    // membuktikan penjagaan rutenya ('role:supervisor,mill_management,admin').
    $this->actingAs($this->operator, 'web')
        ->get('/reports/solid-waste-disposal')
        ->assertStatus(403);

    // Dan peran yang memang dibuka benar-benar sampai ke halamannya — tanpa
    // ini, 403 di atas tetap hijau pada rute yang menolak semua orang.
    $this->actingAs($this->supervisor, 'web')
        ->get('/reports/solid-waste-disposal')
        ->assertOk()
        ->assertSee('data-testid="laporan-solid-waste-disposal"', false);

    // Lapisan komponen: mount() melakukan abort_unless atas daftar perannya
    // SENDIRI.
    $html = Livewire::actingAs($this->operator)->test(LaporanSolidWasteDisposal::class)->html();

    expect($html)->toContain('Forbidden');
    // NOL DATA TERPUBLIKASI — diasersikan atas ketiadaan akar komponennya,
    // bukan atas HTML yang kebetulan tidak memuat sebuah angka.
    expect($html)->not->toContain('data-testid="laporan-solid-waste-disposal"');
    expect($html)->not->toContain('data-testid="loads-card"');
    expect($html)->not->toContain('data-testid="period-select"');
    expect($html)->not->toContain('data-testid="production-line-select"');
});

it('skenario 2b — canAccess() memegang daftar perannya SENDIRI dan tidak mendelegasikan ke guardAccess()', function () {
    // BUKAN BAHAYA MASA DEPAN: SolidWasteDisposalReportService::guardAccess()
    // MENERIMA Operator HARI INI, karena kembaran mobile screen-163 dibangun
    // dalam seri yang sama. Kalau canAccess() mendelegasikan ke sana, layar
    // web ini terbuka untuk Operator SEKARANG — tanpa satu galat pun.
    //
    // Diasersi SECARA STRUKTURAL atas badan metodenya, karena asersi perilaku
    // (skenario 2) baru gagal SETELAH delegasi terjadi, yaitu sesudah
    // kebocorannya mendarat.
    $method = new ReflectionMethod(LaporanSolidWasteDisposal::class, 'canAccess');
    $source = implode('', array_slice(
        file($method->getFileName()),
        $method->getStartLine() - 1,
        $method->getEndLine() - $method->getStartLine() + 1,
    ));

    expect($source)->not->toContain('guardAccess');
    expect($source)->not->toContain('SolidWasteDisposalReportService');
    expect($source)->toContain('UserRole::Supervisor->value');
    expect($source)->toContain('UserRole::MillManagement->value');
    expect($source)->toContain('UserRole::Admin->value');
    expect($source)->not->toContain('UserRole::Operator');

    // Dan service-nya MEMANG menerima Operator — tanpa baris ini, asersi di
    // atas bisa hijau pada service yang juga menutup Operator, yang berarti ia
    // tidak lagi menjaga apa pun.
    $this->actingAs($this->operator, 'web')
        ->getJson('/api/solid-waste-disposal-reports/periods')
        ->assertOk();
});

it('skenario 3 — Admin memilih mill lebih dulu', function () {
    swdLaporanLoad(swdLaporanRecord($this->stationA, $this->day1), $this->day1, ['net_weight_mt' => 4.0]);

    $component = Livewire::actingAs($this->admin)->test(LaporanSolidWasteDisposal::class);

    $html = $component->html();

    // SEBELUM mill dipilih: pemilih Mill dirender, daftar line DAN daftar
    // periode keduanya kosong, nol angka.
    expect($html)->toContain('data-testid="mill-select"');
    expect($html)->toContain('data-testid="mill-select-hint"');
    expect($component->viewData('productionLineOptions'))->toBe([]);
    expect($component->viewData('periods'))->toBe([]);
    expect($component->viewData('summary'))->toBeNull();

    foreach (['loads-card', 'compliance-card', 'daily-table', 'completeness-card'] as $testid) {
        $this->assertStringNotContainsString('data-testid="'.$testid.'"', $html);
    }

    // SESUDAH ketiganya dipilih: payload sama bentuknya dengan peran terikat
    // mill, dan business_unit.id-nya mill yang dipilih Admin.
    $terpilih = $component
        ->set('businessUnitId', (string) $this->businessUnitA->id)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $this->periodA->id);

    $summary = $terpilih->viewData('summary');

    expect($summary)->not->toBeNull();
    expect($summary['business_unit']['id'])->toBe((string) $this->businessUnitA->id);
    expect($summary['loads']['load_count'])->toBe(1);

    foreach (LAPORAN_SWD_FIGURE_TESTIDS as $testid) {
        $this->assertStringContainsString('data-testid="'.$testid.'"', $terpilih->html(), "testid {$testid} tidak dirender");
    }
});

it('skenario 4 — Admin mengganti mill: periode DAN production line keduanya kosong kembali', function () {
    swdLaporanLoad(swdLaporanRecord($this->stationA, $this->day1), $this->day1, ['net_weight_mt' => 777.0]);

    Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::SolidWasteDisposal->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode SWD Beta')
        ->create();

    $component = Livewire::actingAs($this->admin)
        ->test(LaporanSolidWasteDisposal::class)
        ->set('businessUnitId', (string) $this->businessUnitA->id)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $this->periodA->id);

    expect($component->viewData('summary'))->not->toBeNull();
    $this->assertStringContainsString('777', $component->html());

    $sesudah = $component->set('businessUnitId', (string) $this->businessUnitB->id);

    // assertSet null (string kosong), BUKAN assertDontSee: sebuah periode DAN
    // sebuah line masing-masing milik tepat satu mill, jadi membawa salah
    // satunya menyeberang akan merender penolakan akses atas sesuatu yang
    // tidak dilakukan pengguna (temuan QA LP-W-09).
    $sesudah->assertSet('periodId', '');
    $sesudah->assertSet('productionLineId', '');

    // Daftar line dan daftar periode dimuat ulang berisi milik mill-B saja.
    expect(array_column($sesudah->viewData('productionLineOptions'), 'id'))->toBe([$this->lineB]);
    expect(array_column($sesudah->viewData('periods'), 'name'))->toBe(['Periode SWD Beta']);

    // Payload angka menjadi kosong — tidak satu nilai angka mill-A pun
    // tertinggal di state, dan arahan memilih line kembali dirender.
    $html = $sesudah->html();

    expect($sesudah->viewData('summary'))->toBeNull();
    $this->assertStringNotContainsString('777', $html);
    $this->assertStringContainsString('data-testid="select-production-line-hint"', $html);
    $this->assertStringNotContainsString('data-testid="forbidden-notice"', $html);
});

it('skenario 5 — akun belum terhubung ke mill: pesan hubungi Admin, tanpa pemilih mill', function () {
    $yatim = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $component = Livewire::actingAs($yatim)->test(LaporanSolidWasteDisposal::class);
    $html = $component->html();

    $this->assertStringContainsString('data-testid="no-mill-hint"', $html);
    $this->assertStringContainsString('Hubungi Admin', $html);

    // Pemilih Mill TIDAK dirender sebagai gantinya: menawarkan daftar seluruh
    // mill kepada peran yang semestinya terikat satu mill mengubah master yang
    // rusak menjadi kebocoran lintas mill. 422, bukan 403.
    $this->assertStringNotContainsString('data-testid="mill-select"', $html);
    expect($component->viewData('businessUnitOptions'))->toBe([]);
    expect($component->viewData('summary'))->toBeNull();
    expect($component->viewData('hasNoMillForAccount'))->toBeTrue();

    foreach (['loads-card', 'compliance-card', 'completeness-card'] as $testid) {
        $this->assertStringNotContainsString('data-testid="'.$testid.'"', $html);
    }
});

it('skenario 6 — Production Line belum dipilih: nol angka, nol tombol ekspor, nol angka gabungan', function () {
    $stationLine2 = Station::factory()->forBusinessUnit($this->businessUnitA)->solidWasteDisposal()->create();

    $r1 = swdLaporanRecord($this->stationA, $this->day1);
    foreach (range(1, 11) as $i) {
        swdLaporanLoad($r1, $this->day1);
    }

    $r2 = swdLaporanRecord($stationLine2, $this->day1);
    foreach (range(1, 7) as $i) {
        swdLaporanLoad($r2, $this->day1);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSolidWasteDisposal::class)
        ->set('periodId', (string) $this->periodA->id);

    $html = $component->html();

    expect($component->viewData('summary'))->toBeNull();
    $this->assertStringContainsString('data-testid="select-production-line-hint"', $html);

    foreach (['loads-card', 'compliance-card', 'daily-table', 'completeness-card', 'draft-card'] as $testid) {
        $this->assertStringNotContainsString('data-testid="'.$testid.'"', $html);
    }

    // TOMBOL EKSPOR BELUM ADA DI DOM — diasersikan atas atribut wire:click
    // ekspornya, BUKAN atas kata 'Ekspor' yang juga muncul di prosa pemilih.
    $this->assertStringNotContainsString('wire:click="exportCsv', $html);
    $this->assertStringNotContainsString('data-testid="export-button"', $html);
    $this->assertStringNotContainsString('data-testid="export-excel-button"', $html);
    $this->assertStringContainsString('Ekspor', $html);

    // DAN TIDAK ADA ANGKA GABUNGAN DUA LINE: 18 tidak muncul sebagai angka di
    // mana pun. Disasarkan ke penanda nilai, bukan ke halaman — '18' sebagai
    // substring akan cocok dengan potongan tanggal dan id.
    $this->assertStringNotContainsString('>18 <span>muatan</span>', $html);
    $this->assertStringNotContainsString('data-testid="kpi-load-count"', $html);
});

it('skenario 7 — mill belum punya periode yang mencakup Solid Waste Disposal', function () {
    Period::query()->delete();

    Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::Weighbridge->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Weighbridge')
        ->create();

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSolidWasteDisposal::class)
        ->set('productionLineId', $this->lineA);

    // Yang kosong di sini adalah PEMILIHNYA — dibedakan dari 'periode tanpa
    // data' di skenario 8.
    expect($component->viewData('periods'))->toBe([]);

    $html = $component->html();

    $this->assertStringContainsString('data-testid="no-period-hint"', $html);
    $this->assertStringContainsString('Kelola Periode Pelaporan', $html);
    $this->assertStringNotContainsString('data-testid="loads-card"', $html);
});

it('skenario 8 — periode tanpa data: kerangka penuh, kartu berat bertanda pisah, bukan 0,00', function () {
    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSolidWasteDisposal::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $this->periodA->id);

    $summary = $component->viewData('summary');
    $html = $component->html();

    expect($summary['loads']['load_count'])->toBe(0);
    $this->assertNull($summary['loads']['net_weight_total']);
    $this->assertNull($summary['loads']['net_weight_avg']);

    // '—' DISASARKAN KE KARTUNYA: satu karakter yang terlalu pendek untuk
    // diasersikan atas halaman.
    $this->assertStringContainsString('—', swdLaporanCard($html, 'kartu-total-netto'));
    $this->assertStringContainsString('—', swdLaporanCard($html, 'kartu-rata-netto'));
    $this->assertStringNotContainsString('0,00 <span>MT</span>', swdLaporanCard($html, 'kartu-rata-netto'));

    // Keempat rekap [] dengan keadaan kosong BERNARASI yang menyebut nama
    // periode dan nama line.
    foreach (['by-purpose-end-use', 'by-waste-type', 'by-source-station', 'by-disposal-site'] as $rekap) {
        $this->assertStringContainsString('data-testid="'.$rekap.'-empty"', $html, "rekap {$rekap} tidak punya keadaan kosong");

        $kosong = swdLaporanSlice($html, $rekap.'-empty', '</tr>');

        $this->assertStringContainsString('Periode SWD Alpha', $kosong);
        $this->assertStringContainsString($summary['production_line']['name'], $kosong);
    }

    expect($summary['daily'])->toBe([]);
    $this->assertStringContainsString('data-testid="daily-empty"', $html);

    // Kelengkapan TETAP dirender dengan days_counted 31 supaya pembaca tahu
    // berapa hari seharusnya terisi.
    expect($summary['completeness']['days_counted'])->toBe(31);
    $this->assertStringContainsString('31', swdLaporanSlice($html, 'days-counted', '</span>'));

    // NOL GRAFIK KOSONG DI DOM: tanpa satu titik pun, grafiknya tidak digambar
    // — sebuah svg kosong akan terbaca sebagai 'nol MT', bukan 'belum diukur'.
    $this->assertStringNotContainsString('data-testid="daily-trend-chart"', $html);
    $this->assertStringContainsString('data-testid="daily-trend-unavailable"', $html);
});

// =====================================================================
// BERAT BERSIH, PENYEBUT, DAN NULL YANG BUKAN NOL
// =====================================================================

it('skenario 9 — seluruh muatan periode belum selesai ditimbang', function () {
    $record = swdLaporanRecord($this->stationA, $this->day1);

    foreach (range(1, 6) as $i) {
        swdLaporanLoad($record, $this->day1, ['net_weight_mt' => null]);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSolidWasteDisposal::class)
        ->set('productionLineId', $this->lineA);

    $summary = $component->viewData('summary');
    $html = $component->html();

    $this->assertNull($summary['loads']['net_weight_total']);
    $this->assertNull($summary['loads']['net_weight_avg']);
    expect($summary['loads']['net_weight_load_count'])->toBe(0);
    expect($summary['loads']['load_count'])->toBe(6);
    expect($summary['loads']['missing_net_weight_load_count'])->toBe(6);

    // KEDUA kartu berat mencetak '—' DI DALAM KARTUNYA MASING-MASING, NEVER
    // '0,00 MT'.
    foreach (['kartu-total-netto', 'kartu-rata-netto'] as $kartu) {
        $potongan = swdLaporanCard($html, $kartu);

        $this->assertStringContainsString('—', $potongan, "kartu {$kartu} tidak mencetak tanda pisah");
        $this->assertStringNotContainsString('0,00 <span>MT</span>', $potongan, "kartu {$kartu} mencetak nol palsu");
        $this->assertStringNotContainsString('0,0 <span>MT</span>', $potongan, "kartu {$kartu} mencetak nol palsu");
    }

    // 'Penimbangan belum selesai' DITONJOLKAN SEBAGAI KARTU TERSENDIRI.
    $kartuBelum = swdLaporanCard($html, 'kartu-penimbangan-belum-selesai');

    $this->assertStringContainsString('6 <span>muatan</span>', $kartuBelum);
    $this->assertStringContainsString('penimbangan belum selesai', $kartuBelum);
    $this->assertStringNotContainsString('data tidak lengkap', $kartuBelum);
});

it('skenario 10 / 21 — sebagian muatan belum ditimbang: penyebut 6 tercetak di dalam kartu rata-rata', function () {
    $record = swdLaporanRecord($this->stationA, $this->day1);

    foreach (range(1, 6) as $i) {
        swdLaporanLoad($record, $this->day1, ['net_weight_mt' => 5.0]);
    }

    // 2 hanya bruto, 2 hanya tara — keduanya netto null, dan itu bentuk
    // 'penimbangan menggantung' yang sungguhan.
    foreach (range(1, 2) as $i) {
        swdLaporanLoad($record, $this->day1, ['gross_weight_mt' => 12.0, 'net_weight_mt' => null]);
        swdLaporanLoad($record, $this->day1, ['tare_weight_mt' => 2.0, 'net_weight_mt' => null]);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSolidWasteDisposal::class)
        ->set('productionLineId', $this->lineA);

    $summary = $component->viewData('summary');
    $html = $component->html();

    expect($summary['loads']['net_weight_total'])->toBe(30.0);
    // 5.0, BUKAN 3.0: bruto/tara kosong TIDAK diperlakukan sebagai 0 — muatan
    // itu HILANG dari rata-rata, tidak menurunkannya.
    expect($summary['loads']['net_weight_avg'])->toBe(5.0);
    expect($summary['loads']['net_weight_avg'])->not->toBe(3.0);
    expect($summary['loads']['net_weight_load_count'])->toBe(6);
    expect($summary['loads']['load_count'])->toBe(10);
    expect($summary['loads']['missing_net_weight_load_count'])->toBe(4);

    // IDENTITAS yang dapat direkonsiliasi pembaca: 10 = 6 + 4.
    $this->assertSame(
        $summary['loads']['load_count'],
        $summary['loads']['net_weight_load_count'] + $summary['loads']['missing_net_weight_load_count'],
    );

    // ANGKA 6 BERADA DI DALAM KARTU RATA-RATA, bukan di mana pun di halaman:
    // '6' juga muncul sebagai angka lain.
    $kartuRata = swdLaporanCard($html, 'kartu-rata-netto');

    $this->assertStringContainsString('data-testid="net-weight-load-count"', $kartuRata);
    $this->assertStringContainsString('>6</b>', $kartuRata);
    $this->assertStringContainsString('5,00 <span>MT</span>', $kartuRata);

    $this->assertStringContainsString('4 <span>muatan</span>', swdLaporanCard($html, 'kartu-penimbangan-belum-selesai'));
});

it('skenario 20 — laporan MEMBACA net_weight_mt apa adanya, tidak menghitungnya ulang dari bruto dikurangi tara', function () {
    $record = swdLaporanRecord($this->stationA, $this->day1);

    // Baris yang nettonya SENGAJA tidak sama dengan bruto − tara: 10 − 3 = 7,
    // tetapi kolomnya berisi 4. Dan satu baris bergaya fixture e2e: netto 5
    // sementara bruto DAN tara keduanya null.
    swdLaporanLoad($record, $this->day1, ['gross_weight_mt' => 10.0, 'tare_weight_mt' => 3.0, 'net_weight_mt' => 4.0]);
    swdLaporanLoad($record, $this->day1, ['gross_weight_mt' => null, 'tare_weight_mt' => null, 'net_weight_mt' => 5.0]);

    foreach (range(1, 3) as $i) {
        swdLaporanLoad($record, $this->day1, ['net_weight_mt' => null]);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSolidWasteDisposal::class)
        ->set('productionLineId', $this->lineA);

    $summary = $component->viewData('summary');

    expect($summary['loads']['net_weight_total'])->toBe(9.0);
    // 12.0 adalah jawaban implementasi yang menghitung ulang (7 + 5).
    expect($summary['loads']['net_weight_total'])->not->toBe(12.0);
    expect($summary['loads']['net_weight_load_count'])->toBe(2);
    expect($summary['loads']['missing_net_weight_load_count'])->toBe(3);

    // RATA-RATA TIDAK TERTARIK KE BAWAH — dibandingkan terhadap nilai yang
    // akan muncul bila null dianggap 0, dan dipastikan BERBEDA.
    $kalauNullDianggapNol = round(9.0 / 5, 2);

    expect($summary['loads']['net_weight_avg'])->toBe(4.5);
    $this->assertNotSame($kalauNullDianggapNol, $summary['loads']['net_weight_avg']);

    $this->assertStringContainsString(
        '3 <span>muatan</span>',
        swdLaporanCard($component->html(), 'kartu-penimbangan-belum-selesai'),
    );
});

// =====================================================================
// KEEMPAT REKAP KATEGORIS
// =====================================================================

it("skenario 11 — kolom pengelompokan kosong pada seluruh muatan: satu baris 'tidak tercatat', bukan rekap hilang", function () {
    $record = swdLaporanRecord($this->stationA, $this->day1);

    foreach (range(1, 9) as $i) {
        swdLaporanLoad($record, $this->day1, ['purpose_end_use' => null]);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSolidWasteDisposal::class)
        ->set('productionLineId', $this->lineA);

    $summary = $component->viewData('summary');
    $html = $component->html();

    expect($summary['by_purpose_end_use'])->toHaveCount(1);
    $this->assertNull($summary['by_purpose_end_use'][0]['value']);
    expect($summary['by_purpose_end_use'][0]['load_count'])->toBe(9);

    // Rekapnya TETAP dirender dan tetap berdampingan dengan angka total —
    // bukan []. Blade mencetaknya 'tidak tercatat'.
    $tabel = swdLaporanTable($html, 'by-purpose-end-use-table');

    $this->assertStringContainsString('data-testid="by-purpose-end-use-row-null"', $tabel);
    $this->assertStringContainsString('tidak tercatat', $tabel);
    $this->assertStringNotContainsString('data-testid="by-purpose-end-use-empty"', $tabel);

    // Penjumlahan load_count rekap = 9 = load_count.
    $this->assertSame(9, array_sum(array_column($summary['by_purpose_end_use'], 'load_count')));
    $this->assertSame($summary['loads']['load_count'], array_sum(array_column($summary['by_purpose_end_use'], 'load_count')));
});

it('skenario 12 — nilai kategoris langka atau hanya berbeda besar-kecil huruf tetap barisnya sendiri', function () {
    $record = swdLaporanRecord($this->stationA, $this->day1);

    foreach (range(1, 2) as $i) {
        swdLaporanLoad($record, $this->day1, ['disposal_utilization_site' => 'TPA Utara']);
    }

    foreach (range(1, 3) as $i) {
        swdLaporanLoad($record, $this->day1, ['disposal_utilization_site' => 'tpa utara']);
    }

    swdLaporanLoad($record, $this->day1, ['disposal_utilization_site' => ' TPA Utara ']);
    swdLaporanLoad($record, $this->day1, ['disposal_utilization_site' => 'Zinc Scrap']);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSolidWasteDisposal::class)
        ->set('productionLineId', $this->lineA);

    $rows = $component->viewData('summary')['by_disposal_site'];
    $byValue = collect($rows)->keyBy(fn (array $row) => (string) $row['value']);

    // DUA entri terpisah; spasi tepi bergabung, besar-kecil huruf TIDAK.
    expect($byValue['TPA Utara']['load_count'])->toBe(3);
    expect($byValue['tpa utara']['load_count'])->toBe(3);
    // Nilai berfrekuensi satu tetap punya barisnya sendiri.
    expect($byValue['Zinc Scrap']['load_count'])->toBe(1);
    expect($rows)->toHaveCount(3);

    // Nol entri bernama 'lainnya'.
    $this->assertNotContains('lainnya', array_column($rows, 'value'));
    $this->assertNotContains('Lainnya', array_column($rows, 'value'));

    // Dan kedua ejaan BENAR-BENAR dirender sebagai dua baris berbeda.
    $tabel = swdLaporanTable($component->html(), 'by-disposal-site-table');

    $this->assertStringContainsString('TPA Utara', $tabel);
    $this->assertStringContainsString('tpa utara', $tabel);
});

it('skenario 26 — keempat rekap kategoris: penjumlahan menjumlah, urutan menurun, baris null di atas', function () {
    $record = swdLaporanRecord($this->stationA, $this->day1);

    // Kelompok NULL disemai BERTONASE TERBESAR pada keempat kolom sekaligus.
    foreach (range(1, 5) as $i) {
        swdLaporanLoad($record, $this->day1, ['net_weight_mt' => 20.0]);
    }

    foreach (range(1, 4) as $i) {
        swdLaporanLoad($record, $this->day1, [
            'net_weight_mt' => 5.0,
            'solid_waste_type' => 'Zinc Scrap',
            'source_station' => 'Zinc Scrap',
            'disposal_utilization_site' => 'Zinc Scrap',
            'purpose_end_use' => 'Zinc Scrap',
        ]);
    }

    foreach (range(1, 3) as $i) {
        swdLaporanLoad($record, $this->day1, [
            'net_weight_mt' => 1.0,
            'solid_waste_type' => 'Alpha Ash',
            'source_station' => 'Alpha Ash',
            'disposal_utilization_site' => 'Alpha Ash',
            'purpose_end_use' => 'Alpha Ash',
        ]);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSolidWasteDisposal::class)
        ->set('productionLineId', $this->lineA);

    $summary = $component->viewData('summary');

    expect($summary['loads']['load_count'])->toBe(12);

    foreach ([
        'by_waste_type' => 'by-waste-type',
        'by_source_station' => 'by-source-station',
        'by_disposal_site' => 'by-disposal-site',
        'by_purpose_end_use' => 'by-purpose-end-use',
    ] as $key => $testid) {
        $rows = $summary[$key];

        // (a) PENJUMLAHAN — asersi inilah yang menangkap baris null yang
        //     dibuang, dan pesannya menamai rekapnya.
        $this->assertSame(
            12,
            array_sum(array_column($rows, 'load_count')),
            "penjumlahan load_count rekap {$key} tidak sama dengan loads.load_count",
        );

        // (b) URUTAN: net_weight_total menurun monoton.
        $totals = array_map(fn ($value) => (float) ($value ?? 0.0), array_column($rows, 'net_weight_total'));

        for ($i = 1; $i < count($totals); $i++) {
            $this->assertLessThanOrEqual($totals[$i - 1], $totals[$i], "urutan rekap {$key} tidak menurun pada indeks {$i}");
        }

        // (c) BARIS NULL DI INDEKS 0 karena tonasenya terbesar, dan BUKAN
        //     baris terakhir.
        $nullIndex = null;

        foreach ($rows as $index => $row) {
            if ($row['value'] === null) {
                $nullIndex = $index;
            }
        }

        $this->assertSame(0, $nullIndex, "baris 'tidak tercatat' rekap {$key} tidak berada di atas");
        $this->assertNotSame(count($rows) - 1, $nullIndex, "baris 'tidak tercatat' rekap {$key} terkubur di baris terakhir");

        // (d) Kaki tabel mencetak total periode, dan baris null memang
        //     dirender.
        $tabel = swdLaporanTable($component->html(), $testid.'-table');

        $this->assertStringContainsString('data-testid="'.$testid.'-row-null"', $tabel);
        $this->assertStringContainsString('tidak tercatat', $tabel);
    }
});

it('skenario 22 — rekap tujuan penggunaan akhir berada DI DALAM kotak yang sama dengan kartu angka total', function () {
    $record = swdLaporanRecord($this->stationA, $this->day1);

    foreach (['Composting', 'Landfill', 'Fuel'] as $nilai) {
        swdLaporanLoad($record, $this->day1, ['purpose_end_use' => $nilai, 'net_weight_mt' => 2.0]);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSolidWasteDisposal::class)
        ->set('productionLineId', $this->lineA);

    $html = $component->html();

    // DIASERSIKAN SECARA STRUKTURAL: potongan <section data-testid="loads-card">
    // sampai </section>-nya HARUS memuat keduanya. 'Keduanya ada di halaman'
    // akan hijau walaupun rekapnya dipindah ke kartu lain — dan hubungan DOM
    // itulah kontraknya, bukan kebetulan tata letak.
    $kotak = swdLaporanSlice($html, 'loads-card', '</section>');

    $this->assertStringContainsString('data-testid="kartu-total-netto"', $kotak);
    $this->assertStringContainsString('data-testid="kpi-load-count"', $kotak);
    $this->assertStringContainsString('data-testid="by-purpose-end-use-table"', $kotak);
    $this->assertStringContainsString('data-testid="by-purpose-end-use-row-total"', $kotak);

    // Dan rekap-rekap LAIN justru berada di LUAR kotak itu — tanpa baris ini,
    // asersi di atas juga hijau pada layar yang menaruh segalanya di satu
    // kotak, yang berarti ia tidak membuktikan hubungan yang dimaksud.
    $this->assertStringNotContainsString('data-testid="by-waste-type-table"', $kotak);
    $this->assertStringNotContainsString('data-testid="compliance-card"', $kotak);

    // Penjumlahan rekap itu = load_count.
    $summary = $component->viewData('summary');

    $this->assertSame(
        $summary['loads']['load_count'],
        array_sum(array_column($summary['by_purpose_end_use'], 'load_count')),
    );
});

it('skenario 23 — rekap tujuan penggunaan akhir per NILAI apa adanya, bukan dua ember dibuang/dimanfaatkan', function () {
    $record = swdLaporanRecord($this->stationA, $this->day1);

    foreach (['Composting', 'Landfill', 'Fuel', 'Road Base'] as $nilai) {
        swdLaporanLoad($record, $this->day1, ['purpose_end_use' => $nilai, 'net_weight_mt' => 3.0]);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSolidWasteDisposal::class)
        ->set('productionLineId', $this->lineA);

    $summary = $component->viewData('summary');

    expect($summary['by_purpose_end_use'])->toHaveCount(4);

    foreach ($summary['by_purpose_end_use'] as $row) {
        $this->assertArrayHasKey('load_count', $row);
        $this->assertArrayHasKey('net_weight_total', $row);
    }

    // ASERSI NEGATIF YANG MENJADI INTI SKENARIO INI — rekursif, ATAS NAMA
    // KUNCI, bukan atas prosa layar (yang justru menyebut kedua kata itu untuk
    // menjelaskan mengapa pengelompokannya tidak dibuat).
    $keys = swdLaporanAllKeys($summary);

    foreach ([
        'disposed', 'utilized', 'dibuang', 'dimanfaatkan',
        'disposal_group', 'utilization_group', 'by_disposal_mode',
    ] as $terlarang) {
        $this->assertNotContains($terlarang, $keys, "payload tidak boleh punya kunci {$terlarang}");
    }

    $values = array_column($summary['by_purpose_end_use'], 'value');

    foreach (['Dibuang', 'Dimanfaatkan', 'Disposed', 'Utilized'] as $ember) {
        $this->assertNotContains($ember, $values, "rekap purpose_end_use melipat nilainya ke ember '{$ember}'");
    }
});

// =====================================================================
// KEPATUHAN, TEKS BEBAS, DRAFT
// =====================================================================

it('skenario 13 / 25 — jejak kepatuhan: dua penghitung, nol ukuran, kartu terpisah dari kartu muatan', function () {
    $record = swdLaporanRecord($this->stationA, $this->day1);

    foreach ([
        [null, null], [null, null], [null, null],
        ['', null], ['', null], ['  ', 'SS-1'],
        ['GP-90021', 'SS-2'], ['GP-2', 'SS-90018'],
    ] as [$gate, $seal]) {
        swdLaporanLoad($record, $this->day1, ['gate_pass_no' => $gate, 'security_seal_no' => $seal]);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSolidWasteDisposal::class)
        ->set('productionLineId', $this->lineA);

    $summary = $component->viewData('summary');
    $html = $component->html();

    expect($summary['compliance']['missing_gate_pass_count'])->toBe(6);
    expect($summary['compliance']['missing_seal_count'])->toBe(5);

    // DISAJIKAN SEBAGAI TEMUAN, bukan sebagai galat/alert error: kartunya
    // memakai kosakata KPI biasa, bukan kelas galat.
    $kartu = swdLaporanSlice($html, 'compliance-card', '</section>');

    $this->assertStringContainsString('data-testid="kpi-missing-gate-pass"', $kartu);
    $this->assertStringContainsString('data-testid="kpi-missing-seal"', $kartu);
    $this->assertStringNotContainsString('md-alert--error', $kartu);
    // NOL GRAFIK di dalam kartu compliance.
    $this->assertStringNotContainsString('<svg class="md-lc__svg"', $kartu);
    $this->assertStringNotContainsString('<table', $kartu);

    // compliance TIDAK digabung ke dalam blok loads — asersi STRUKTURAL atas
    // lokasi kuncinya.
    $this->assertArrayNotHasKey('missing_gate_pass_count', $summary['loads']);
    $this->assertArrayNotHasKey('missing_seal_count', $summary['loads']);
    $this->assertArrayHasKey('missing_gate_pass_count', $summary['compliance']);

    // NOL KUNCI BERNUANSA UKURAN di seluruh payload, dan nomornya sendiri
    // tidak pernah diterbitkan di layar.
    $keys = swdLaporanAllKeys($summary);

    foreach ([
        'avg_gate_pass', 'gate_pass_chart', 'min_gate_pass', 'max_gate_pass', 'sorted_gate_pass',
        'avg_seal', 'seal_chart', 'min_seal', 'max_seal', 'sorted_seal',
        'gate_pass_no', 'security_seal_no',
    ] as $terlarang) {
        $this->assertNotContains($terlarang, $keys, "payload tidak boleh punya kunci {$terlarang}");
    }

    $this->assertStringNotContainsString('GP-90021', json_encode($summary));
    $this->assertStringNotContainsString('SS-90018', json_encode($summary));

    // PERIODE KEDUA — kelengkapan penuh: KEDUA kunci TETAP dirender bernilai
    // nol, karena nol itulah yang memberi tahu pemeriksaannya berjalan.
    $lengkap = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::SolidWasteDisposal->value)
        ->range(now()->subDays(80)->toDateString(), now()->subDays(50)->toDateString())
        ->open()
        ->named('Periode Lengkap')
        ->create();

    $recordLengkap = swdLaporanRecord($this->stationA, now()->subDays(70)->toDateString());

    foreach (range(1, 8) as $i) {
        swdLaporanLoad($recordLengkap, now()->subDays(70)->toDateString(), [
            'gate_pass_no' => 'GP-L'.$i,
            'security_seal_no' => 'SS-L'.$i,
        ]);
    }

    $kedua = Livewire::actingAs($this->supervisor)
        ->test(LaporanSolidWasteDisposal::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $lengkap->id);

    $compliance = $kedua->viewData('summary')['compliance'];

    $this->assertArrayHasKey('missing_gate_pass_count', $compliance);
    $this->assertArrayHasKey('missing_seal_count', $compliance);
    expect($compliance['missing_gate_pass_count'])->toBe(0);
    expect($compliance['missing_seal_count'])->toBe(0);

    $kartuKedua = swdLaporanSlice($kedua->html(), 'compliance-card', '</section>');

    $this->assertStringContainsString('data-testid="kpi-missing-gate-pass"', $kartuKedua);
    $this->assertStringContainsString('data-testid="kpi-missing-seal"', $kartuKedua);
});

it('skenario 14 — rekap temuan dan catatan: dua container terpisah, kosong maupun terisi keduanya', function () {
    // PERIODE 1 — keduanya kosong sepanjang periode.
    swdLaporanLoad(swdLaporanRecord($this->stationA, $this->day1), $this->day1);

    $kosong = Livewire::actingAs($this->supervisor)
        ->test(LaporanSolidWasteDisposal::class)
        ->set('productionLineId', $this->lineA);

    $htmlKosong = $kosong->html();

    expect($kosong->viewData('summary')['text_entries']['findings'])->toBe([]);
    expect($kosong->viewData('summary')['text_entries']['remarks'])->toBe([]);

    // DIRENDER SEBAGAI KETERANGAN 'tidak ada catatan' — bukan tabel kosong dan
    // bukan bagian yang hilang.
    $this->assertStringContainsString('data-testid="findings-card"', $htmlKosong);
    $this->assertStringContainsString('data-testid="findings-empty"', $htmlKosong);
    $this->assertStringContainsString('data-testid="remarks-card"', $htmlKosong);
    $this->assertStringContainsString('data-testid="remarks-empty"', $htmlKosong);

    // PERIODE 2 — satu muatan mengisi KEDUANYA.
    $kedua = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::SolidWasteDisposal->value)
        ->range(now()->subDays(80)->toDateString(), now()->subDays(50)->toDateString())
        ->open()
        ->named('Periode Bercatatan')
        ->create();

    swdLaporanLoad(
        swdLaporanRecord($this->stationA, now()->subDays(70)->toDateString()),
        now()->subDays(70)->toDateString(),
        ['findings' => 'Terpal robek di bak', 'remarks' => 'Diganti sebelum berangkat'],
    );

    $terisi = Livewire::actingAs($this->supervisor)
        ->test(LaporanSolidWasteDisposal::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $kedua->id);

    $summary = $terisi->viewData('summary');

    expect($summary['text_entries']['findings'])->toHaveCount(1);
    expect($summary['text_entries']['remarks'])->toHaveCount(1);

    // DIASERSIKAN ATAS DUA CONTAINER TESTID BERBEDA, bukan atas satu blok:
    // satu muatan yang mengisi keduanya memang muncul di KEDUA daftar, dan itu
    // benar — bukan penghitungan ganda.
    $htmlTerisi = $terisi->html();
    $bagianTemuan = swdLaporanSlice($htmlTerisi, 'findings-card', '</section>');
    $bagianCatatan = swdLaporanSlice($htmlTerisi, 'remarks-card', '</section>');

    $this->assertStringContainsString('Terpal robek di bak', $bagianTemuan);
    $this->assertStringContainsString('Diganti sebelum berangkat', $bagianCatatan);
    // DAN TIDAK TERTUKAR.
    $this->assertStringNotContainsString('Diganti sebelum berangkat', $bagianTemuan);
    $this->assertStringNotContainsString('Terpal robek di bak', $bagianCatatan);
});

it('skenario 18 / 28 — muatan draft tetap terhitung penuh dan jumlahnya dinyatakan', function () {
    $draft = swdLaporanRecord($this->stationA, $this->day1, ['status' => RecordStatus::DraftOngoing]);
    $final = swdLaporanRecord($this->stationA, $this->day2, ['status' => RecordStatus::Saved]);

    foreach (range(1, 3) as $i) {
        swdLaporanLoad($draft, $this->day1, [
            'net_weight_mt' => 2.0,
            'solid_waste_type' => 'Fiber',
            'source_station' => 'Pressing',
            'disposal_utilization_site' => 'TPA Utara',
            'purpose_end_use' => 'Composting',
        ]);
    }

    foreach (range(1, 5) as $i) {
        swdLaporanLoad($final, $this->day2, [
            'net_weight_mt' => 3.0,
            'solid_waste_type' => 'Shell',
            'source_station' => 'Threshing',
            'disposal_utilization_site' => 'TPA Selatan',
            'purpose_end_use' => 'Fuel',
        ]);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSolidWasteDisposal::class)
        ->set('productionLineId', $this->lineA);

    $summary = $component->viewData('summary');
    $html = $component->html();

    expect($summary['loads']['load_count'])->toBe(8);
    expect($summary['loads']['net_weight_load_count'])->toBe(8);
    expect($summary['draft_load_count'])->toBe(3);
    expect($summary['compliance']['missing_gate_pass_count'])->toBe(8);
    expect(array_sum(array_column($summary['daily'], 'load_count')))->toBe(8);

    foreach (['by_waste_type', 'by_source_station', 'by_disposal_site', 'by_purpose_end_use'] as $rekap) {
        $this->assertSame(8, array_sum(array_column($summary[$rekap], 'load_count')), "rekap {$rekap} menyaring draft keluar");
    }

    // ASERSI NEGATIF: tidak satu bagian pun menyaring draft — 5 adalah
    // jawaban implementasi yang menyaringnya.
    expect($summary['loads']['load_count'])->not->toBe(5);

    // Kartu draft dirender BERDAMPINGAN dengan totalnya. Label 'Draft'
    // DISASARKAN KE KARTUNYA: kata itu juga muncul di pemilih periode.
    $kartuDraft = swdLaporanCard($html, 'kpi-draft-load-count');

    $this->assertStringContainsString('Muatan Draft', $kartuDraft);
    $this->assertStringContainsString('3 <span>dari 8 muatan</span>', $kartuDraft);

    // EKSPOR memuat 8 baris, dengan kolom status dicetak lewat
    // ExportValue::status() — status RECORD, bukan option() yang dipakai untuk
    // kolom enum PENGUKURAN (tabel detail stasiun ini tidak punya satu pun).
    $this->actingAs($this->supervisor);

    $csv = swdLaporanStreamed(swdLaporanExport((string) $this->periodA->id, $this->lineA, 'csv'));
    $lines = array_values(array_filter(explode("\n", trim($csv)), fn ($line) => trim($line) !== ''));

    expect($lines)->toHaveCount(9);

    $header = str_getcsv(trim($lines[0], "\r"), ',', '"', '\\');
    $kolomStatus = array_search('Status', $header, true);

    $statuses = array_map(
        fn (string $line) => str_getcsv(trim($line, "\r"), ',', '"', '\\')[$kolomStatus],
        array_slice($lines, 1),
    );

    expect(array_count_values($statuses))->toBe([
        ExportValue::status(RecordStatus::DraftOngoing->value) => 3,
        ExportValue::status(RecordStatus::Saved->value) => 5,
    ]);
    expect(array_unique($statuses))->toContain('Draft');
});

// =====================================================================
// LINGKUP, KELENGKAPAN, STATUS PERIODE
// =====================================================================

it('skenario 15 — line dengan lebih dari satu unit SWD digabung tepat sekali', function () {
    $unit2 = Station::factory()
        ->forProductionLine($this->stationA->production_line_id)
        ->solidWasteDisposal()
        ->create();

    $r1 = swdLaporanRecord($this->stationA, $this->day1);
    foreach (range(1, 3) as $i) {
        swdLaporanLoad($r1, $this->day1);
    }

    $r2 = swdLaporanRecord($unit2, $this->day2);
    foreach (range(1, 2) as $i) {
        swdLaporanLoad($r2, $this->day2);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSolidWasteDisposal::class)
        ->set('productionLineId', $this->lineA);

    $summary = $component->viewData('summary');

    // 5 — bukan 3 (unit terlewat) dan bukan 10 (join ganda).
    expect($summary['loads']['load_count'])->toBe(5);
    expect($summary['loads']['load_count'])->not->toBe(3);
    expect($summary['loads']['load_count'])->not->toBe(10);

    // NOL pengelompokan per unit di payload maupun di DOM: pembaca membaca
    // satu angka per line.
    $keys = swdLaporanAllKeys($summary);

    foreach (['by_unit', 'units', 'by_station', 'stations'] as $terlarang) {
        $this->assertNotContains($terlarang, $keys, "payload tidak boleh punya kunci {$terlarang}");
    }

    $html = $component->html();

    $this->assertStringNotContainsString('data-testid="by-unit-table"', $html);
    $this->assertStringNotContainsString('data-testid="unit-select"', $html);
});

it('skenario 16 — periode masih berjalan atau belum mulai', function () {
    // BERJALAN: mulai 8 hari lalu, berakhir 22 hari lagi. Hari yang sudah
    // lewat = 9, dan muatan ada pada kesembilannya.
    $berjalan = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::SolidWasteDisposal->value)
        ->range(now()->subDays(8)->toDateString(), now()->addDays(22)->toDateString())
        ->open()
        ->named('Periode Berjalan')
        ->create();

    foreach (range(0, 8) as $offset) {
        $hari = now()->subDays($offset)->toDateString();
        swdLaporanLoad(swdLaporanRecord($this->stationA, $hari), $hari, ['net_weight_mt' => 2.0]);
    }

    $komponenBerjalan = Livewire::actingAs($this->supervisor)
        ->test(LaporanSolidWasteDisposal::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $berjalan->id);

    $completeness = $komponenBerjalan->viewData('summary')['completeness'];

    expect($completeness['days_counted'])->toBe(9);
    expect($completeness['days_with_load'])->toBe(9);
    // 100.0, BUKAN 29.0.
    expect($completeness['percent'])->toBe(100.0);

    // LAYAR MENYATAKAN penyebutnya berhenti di hari ini.
    $htmlBerjalan = $komponenBerjalan->html();

    $this->assertStringContainsString('data-testid="period-running-note"', $htmlBerjalan);
    $this->assertStringContainsString('periode masih berjalan', swdLaporanSlice($htmlBerjalan, 'period-running-note'));
    $this->assertStringContainsString('100,0%', swdLaporanSlice($htmlBerjalan, 'days-with-load-percent', '</span>'));

    // BELUM MULAI: days_counted 0, percent null, blade mencetak '—'.
    $belumMulai = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::SolidWasteDisposal->value)
        ->range(now()->addDays(40)->toDateString(), now()->addDays(69)->toDateString())
        ->open()
        ->named('Periode Belum Mulai')
        ->create();

    $komponenBelum = Livewire::actingAs($this->supervisor)
        ->test(LaporanSolidWasteDisposal::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $belumMulai->id);

    $belum = $komponenBelum->viewData('summary')['completeness'];

    expect($belum['days_counted'])->toBe(0);
    $this->assertNull($belum['percent']);

    $htmlBelum = $komponenBelum->html();
    $sel = swdLaporanSlice($htmlBelum, 'days-with-load-percent', '</span>');

    // '—' DI DALAM SEL KELENGKAPAN, BUKAN '0,0%' yang terbaca sebagai
    // kelalaian pencatatan.
    $this->assertStringContainsString('—', $sel);
    $this->assertStringNotContainsString('0,0%', $sel);
    $this->assertStringContainsString('data-testid="period-not-started-note"', $htmlBelum);
    $this->assertStringContainsString('belum dimulai', swdLaporanSlice($htmlBelum, 'period-not-started-note'));
});

it('skenario 17 — periode berstatus tertutup tampil PENUH dan tetap dapat diekspor', function () {
    $tertutup = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::SolidWasteDisposal->value)
        ->range(now()->subDays(80)->toDateString(), now()->subDays(50)->toDateString())
        ->closed()
        ->named('Periode Tertutup')
        ->create();

    $record = swdLaporanRecord($this->stationA, now()->subDays(70)->toDateString());

    foreach (range(1, 3) as $i) {
        swdLaporanLoad($record, now()->subDays(70)->toDateString(), [
            'net_weight_mt' => 2.0,
            'purpose_end_use' => 'Composting',
        ]);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSolidWasteDisposal::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $tertutup->id);

    $html = $component->html();

    foreach (LAPORAN_SWD_FIGURE_TESTIDS as $testid) {
        $this->assertStringContainsString('data-testid="'.$testid.'"', $html, "testid {$testid} tidak dirender");
    }

    expect($component->viewData('summary')['period']['status'])->toBe('closed');

    // Badge status pada ELEMENNYA SENDIRI: 'Tertutup' sebagai substring
    // halaman tidak membuktikan apa pun — kata itu juga ada di pemilih
    // periode.
    $this->assertStringContainsString('Tertutup', swdLaporanSlice($html, 'period-status-badge', '</span>'));

    // Kedua tombol ekspor tetap ada, dan keduanya benar-benar mengalirkan
    // berkas.
    $this->assertStringContainsString('data-testid="export-button"', $html);
    $this->assertStringContainsString('data-testid="export-excel-button"', $html);

    $component->call('exportCsv', 'csv')->assertFileDownloaded();
    $component->call('exportCsv', 'excel')->assertFileDownloaded();
});

it('skenario 19 — rekap harian 9 baris, dan menutup/membukanya tidak memicu permintaan data', function () {
    $offsets = [31, 39, 20, 35, 12, 28, 17, 24, 14];

    foreach ($offsets as $index => $offset) {
        $hari = now()->subDays($offset)->toDateString();
        $record = swdLaporanRecord($this->stationA, $hari);

        foreach (range(1, $index % 3 + 1) as $i) {
            swdLaporanLoad($record, $hari, ['net_weight_mt' => 2.0]);
        }
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSolidWasteDisposal::class)
        ->set('productionLineId', $this->lineA);

    $summary = $component->viewData('summary');
    $html = $component->html();

    // TEPAT 9, bukan 31 — asersi jumlah entri ini WAJIB.
    expect($summary['daily'])->toHaveCount(9);
    expect($summary['completeness']['days_in_period'])->toBe(31);

    foreach ($summary['daily'] as $row) {
        $this->assertGreaterThan(0, $row['load_count'], 'daily[] memuat entri ber-load_count 0');
    }

    $dates = array_column($summary['daily'], 'date');
    $sorted = $dates;
    sort($sorted);

    expect($dates)->toBe($sorted);

    // Tabelnya memang punya 9 baris data, dihitung dari DOM.
    $tabel = swdLaporanTable($html, 'daily-table');

    // DIHITUNG ATAS POLA TANGGALNYA, bukan atas jumlah <tr>: kaki tabel
    // membawa barisnya sendiri (daily-row-total), dan menghitung <tr> akan
    // menjawab 11 tanpa ada yang salah.
    expect(preg_match_all('#data-testid="daily-row-\d{4}-\d{2}-\d{2}"#', $tabel))->toBe(9);
    expect(substr_count($tabel, 'data-testid="daily-row-total"'))->toBe(1);
    $this->assertStringNotContainsString('data-testid="daily-empty"', $tabel);

    // MENUTUP/MEMBUKA REKAP TIDAK MEMICU PERMINTAAN DATA — dibuktikan secara
    // STRUKTURAL, bukan dengan mensimulasikan klik: togglenya adalah <details>
    // BAWAAN PERAMBAN tanpa satu atribut wire pun, jadi tidak ada putaran
    // Livewire yang dapat terjadi sama sekali.
    $toggle = swdLaporanSlice($html, 'daily-recap-toggle', '</summary>');

    $this->assertStringContainsString('<summary data-testid="daily-recap-toggle"', $html);
    $this->assertStringNotContainsString('wire:click', $toggle);
    $this->assertStringNotContainsString('wire:model', $toggle);

    $details = swdLaporanSlice($html, 'daily-recap-details', '>');

    $this->assertStringContainsString('<details', substr($html, max(0, strpos($html, 'data-testid="daily-recap-details"') - 40), 60));
    $this->assertStringNotContainsString('wire:', $details);
});

// =====================================================================
// KETIADAAN YANG DINYATAKAN — DIASERSIKAN ATAS KUNCI DAN TESTID
// =====================================================================

it('skenario 24 — tidak ada blok per jam, dan ketiadaannya dinyatakan UTUH', function () {
    $record = swdLaporanRecord($this->stationA, $this->day1);

    foreach (range(1, 6) as $i) {
        swdLaporanLoad($record, $this->day1, ['net_weight_mt' => 2.0, 'solid_waste_type' => 'Fiber']);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSolidWasteDisposal::class)
        ->set('productionLineId', $this->lineA);

    $summary = $component->viewData('summary');
    $html = $component->html();

    // DI AKAR PAYLOAD.
    foreach (['hourly', 'busiest_hour', 'empty_hour_count'] as $terlarang) {
        $this->assertArrayNotHasKey($terlarang, $summary);
    }

    // DAN REKURSIF atas seluruh sub-array.
    $keys = swdLaporanAllKeys($summary);

    foreach (['hourly', 'busiest_hour', 'empty_hour_count', 'by_hour', 'hour', 'hours'] as $terlarang) {
        $this->assertNotContains($terlarang, $keys, "payload tidak boleh punya kunci {$terlarang}");
    }

    // JEBAKAN YANG WAJIB DIHINDARI: ketiadaan ini TIDAK PERNAH diasersikan
    // dengan assertDontSee('Sebaran'/'jam'/'tersibuk') — prosa di bawah JUSTRU
    // memuat kata-kata itu, dan asersi berbasis teks akan cocok dengan kalimat
    // yang menyatakan ketiadaannya lalu lolos tanpa membuktikan apa pun. Dua
    // baris ini membuktikan jebakannya NYATA pada layar ini.
    $this->assertStringContainsString('jam', SolidWasteDisposalReportService::NOTE_NO_HOURLY);
    $this->assertStringContainsString('Sebaran', SolidWasteDisposalReportService::NOTE_NO_HOURLY);

    // DOM: nol blok per jam, dicari atas TESTID.
    foreach (['hourly-block', 'hourly-table', 'hourly-chart', 'busiest-hour', 'empty-hour-count'] as $terlarang) {
        $this->assertStringNotContainsString('data-testid="'.$terlarang.'"', $html);
    }

    // Dan prosanya hadir UTUH — dibandingkan string PENUH, bukan substring.
    $this->assertStringContainsString(
        e(SolidWasteDisposalReportService::NOTE_NO_HOURLY),
        swdLaporanSlice($html, 'no-hourly-note', '</div>'),
    );

    // KOTAKNYA memakai .md-explain, BUKAN .md-threshold: ketiadaan penandaan
    // diasersi MENURUT NAMA KELAS pada HTML ter-render.
    $this->assertStringContainsString('class="md-explain" data-testid="no-hourly-note"', $html);
    $this->assertStringContainsString('class="md-explain" data-testid="absences-note"', $html);
    $this->assertStringNotContainsString('md-threshold', $html);
});

it('skenario 27 — kolom shift TIDAK diterbitkan di layar tetapi TETAP ada di ekspor', function () {
    $record = swdLaporanRecord($this->stationA, $this->day1);

    // `shift` TERISI di basis data, justru supaya ketiadaannya di layar tidak
    // lolos hanya karena kolomnya kosong.
    foreach (['Shift Malam', 'Shift Pagi', 'shift 1'] as $index => $shift) {
        swdLaporanLoad($record, $this->day1, ['shift' => $shift, 'net_weight_mt' => 2.0]);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSolidWasteDisposal::class)
        ->set('productionLineId', $this->lineA);

    $summary = $component->viewData('summary');
    $html = $component->html();

    // PAYLOAD: tidak ada kunci 'shift' di akar MAUPUN di mana pun.
    $this->assertArrayNotHasKey('shift', $summary);

    foreach (['shift', 'shifts', 'by_shift', 'shift_distribution'] as $terlarang) {
        $this->assertNotContains($terlarang, swdLaporanAllKeys($summary), "payload tidak boleh punya kunci {$terlarang}");
    }

    // JEBAKAN: ini TIDAK dibuktikan dengan assertDontSee('shift') — prosa
    // _note_shift_not_published MEMUAT kata itu dan dirender utuh.
    $this->assertStringContainsString('shift', SolidWasteDisposalReportService::NOTE_SHIFT_NOT_PUBLISHED);
    $this->assertStringContainsString(
        e(SolidWasteDisposalReportService::NOTE_SHIFT_NOT_PUBLISHED),
        swdLaporanSlice($html, 'shift-not-published-note', '</div>'),
    );

    // DOM: nol kolom/kartu/penyaring shift, dicari atas TESTID dan atas NILAI
    // yang disemai — bukan atas kata 'shift'.
    foreach (['shift-select', 'shift-column', 'by-shift-table', 'kpi-shift'] as $terlarang) {
        $this->assertStringNotContainsString('data-testid="'.$terlarang.'"', $html);
    }

    $this->assertStringNotContainsString('Shift Malam', $html);
    $this->assertStringNotContainsString('Shift Pagi', $html);

    // EKSPOR: header memuat kolom Shift dan nilai tiap muatan ada di BARISNYA
    // — dibandingkan per baris, bukan atas seluruh berkas.
    $this->actingAs($this->supervisor);

    $csv = swdLaporanStreamed(swdLaporanExport((string) $this->periodA->id, $this->lineA, 'csv'));
    $lines = array_values(array_filter(explode("\n", trim($csv)), fn ($line) => trim($line) !== ''));
    $header = str_getcsv(trim($lines[0], "\r"), ',', '"', '\\');
    $kolomShift = array_search('Shift', $header, true);

    $this->assertNotFalse($kolomShift, 'header ekspor kehilangan kolom Shift');

    $nilaiShift = array_map(
        fn (string $line) => str_getcsv(trim($line, "\r"), ',', '"', '\\')[$kolomShift],
        array_slice($lines, 1),
    );

    sort($nilaiShift);

    expect($nilaiShift)->toBe(['Shift Malam', 'Shift Pagi', 'shift 1']);

    // Dan 'excel' DITERJEMAHKAN menjadi 'xlsx' OLEH KOMPONEN ini — service
    // hanya mengenal {csv, xlsx}, sementara toolbar bersama memanggil 'excel'.
    $xlsx = swdLaporanExport((string) $this->periodA->id, $this->lineA, 'excel');

    $this->assertStringStartsWith("PK\x03\x04", swdLaporanStreamed($xlsx));
    $this->assertStringContainsString('spreadsheetml.sheet', (string) $xlsx->headers->get('Content-Type'));
});

// =====================================================================
// BACA-SAJA DAN PINTU MASUK
// =====================================================================

it('layar hanya membaca: nol kontrol tulis dan nol metode publik yang menulis', function () {
    swdLaporanLoad(swdLaporanRecord($this->stationA, $this->day1), $this->day1, ['net_weight_mt' => 2.0]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSolidWasteDisposal::class)
        ->set('productionLineId', $this->lineA);

    $html = $component->html();

    expect($html)->not->toContain('>Simpan<');
    expect($html)->not->toContain('>Hapus<');
    expect($html)->not->toContain('>Ubah<');
    expect($html)->not->toContain('>Verifikasi<');

    $public = collect((new ReflectionClass(LaporanSolidWasteDisposal::class))->getMethods(ReflectionMethod::IS_PUBLIC))
        ->filter(fn (ReflectionMethod $method) => $method->class === LaporanSolidWasteDisposal::class)
        ->map(fn (ReflectionMethod $method) => $method->name)
        ->values()
        ->all();

    expect($public)->toBe([
        'mount', 'updatingBusinessUnitId', 'updatedBusinessUnitId',
        'updatedPeriodId', 'exportCsv', 'render',
    ]);

    // Dan membacanya tidak mengubah satu baris pun.
    $sebelum = [SolidWasteDisposalRecord::query()->count(), SolidWasteDisposalDetail::query()->count()];

    $component->call('exportCsv', 'csv')->assertFileDownloaded();

    expect([SolidWasteDisposalRecord::query()->count(), SolidWasteDisposalDetail::query()->count()])->toBe($sebelum);
});

it('skenario 29 — pintu masuk: tile Solid Waste Disposal pada Laporan Stasiun AKTIF dan menunjuk ke rute laporan ini', function () {
    // Tanpa entri 'solid-waste-disposal' pada
    // StationReportService::REPORT_ROUTES, layar ini ada, seluruh test lainnya
    // lulus, dan tile-nya tetap kelabu — pola kegagalan yang sudah pernah
    // terjadi pada laporan Cages & Tracks versi web.
    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanStasiun::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // DIPOTONG DARI TAG PEMBUKA YANG MEMBAWA TESTID-NYA SAMPAI TAG
    // PENUTUPNYA, bukan dari jendela sekitarnya: grid screen-140 merender
    // delapan belas tile berdampingan, dan sebuah jendela akan ikut memuat
    // tile tetangga yang memang masih kelabu.
    $pattern = '/<(a|span)\b[^>]*data-testid="station-tile-solid-waste-disposal"[^>]*>.*?<\/\1>/s';

    expect(preg_match($pattern, $html, $matches))->toBe(1);

    $tile = $matches[0];

    expect($tile)->toContain('station-tile active');
    expect($tile)->not->toContain('station-tile disabled');
    expect($tile)->not->toContain('aria-disabled');
    expect($tile)->toContain('/reports/solid-waste-disposal');
    expect($tile)->not->toContain('Belum tersedia');
});

// =====================================================================
// undated_load_count — KEDUA CABANGNYA
// =====================================================================

it('undated — kuncinya SELALU ada di payload, tetapi kotak keterangannya hanya dirender ketika angkanya > 0', function () {
    swdLaporanLoad(swdLaporanRecord($this->stationA, $this->day1), $this->day1, ['net_weight_mt' => 2.0]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSolidWasteDisposal::class)
        ->set('productionLineId', $this->lineA);

    $summary = $component->viewData('summary');

    // KUNCINYA TETAP DITERBITKAN, selalu: ia yang membuat invarian
    // 'penjumlahan rekap harian = jumlah muatan dikurangi muatan tak
    // bertanggal' dapat diperiksa pembaca mana pun, dan ia tetap benar bila
    // kolom event_date suatu saat dilonggarkan.
    $this->assertArrayHasKey('undated_load_count', $summary);
    expect($summary['undated_load_count'])->toBe(0);

    // Dan pada angka 0, penjumlahan harian memang SAMA DENGAN jumlah muatan —
    // tidak ada selisih yang perlu dijelaskan, jadi kotaknya tidak dirender.
    expect($summary['daily_total']['load_count'])->toBe($summary['loads']['load_count']);

    $html = $component->html();

    $this->assertStringNotContainsString('data-testid="undated-note"', $html);
    $this->assertStringNotContainsString('data-testid="undated-load-count"', $html);
});

it('undated — ketika ADA muatan tak bertanggal, kotaknya dirender dan menamai selisihnya', function () {
    // Dibangun DI MEMORI: `event_date` NOT NULL di skema, jadi muatan tak
    // bertanggal tidak dapat disemai lewat factory sama sekali. Service
    // penggantinya dipasang di container, tempat komponen mengambilnya lewat
    // app() — jadi yang diuji tetap render() dan blade yang sungguhan.
    $record = new SolidWasteDisposalRecord;
    $record->status = RecordStatus::Saved;

    $details = collect();

    foreach ([$this->day1, $this->day1, $this->day2] as $tanggal) {
        $details->push(new SolidWasteDisposalDetail(['event_date' => $tanggal, 'net_weight_mt' => 2.0]));
    }

    foreach (range(1, 3) as $i) {
        $details->push(new SolidWasteDisposalDetail(['event_date' => null, 'net_weight_mt' => 2.0]));
    }

    $record->setRelation('solidWasteDisposalDetails', $details);

    LaporanSwdInMemoryReportService::$records = collect([$record]);

    app()->bind(SolidWasteDisposalReportService::class, fn () => new LaporanSwdInMemoryReportService);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSolidWasteDisposal::class)
        ->set('productionLineId', $this->lineA);

    $summary = $component->viewData('summary');

    expect($summary['loads']['load_count'])->toBe(6);
    expect($summary['undated_load_count'])->toBe(3);
    expect($summary['daily_total']['load_count'])->toBe(3);

    $html = $component->html();
    $kotak = swdLaporanSlice($html, 'undated-note', '</div>');

    $this->assertStringContainsString('data-testid="undated-note"', $html);
    // Angkanya DICETAK, dan kalimatnya MENAMAI hubungan kedua angka itu:
    // tanpa itu, penjumlahan rekap harian yang lebih kecil daripada jumlah
    // muatan akan selalu dicurigai sebagai bug.
    $this->assertStringContainsString('>3</b>', $kotak);
    $this->assertStringContainsString('tidak punya tanggal kejadian', $kotak);
    $this->assertStringContainsString('tidak ikut', $kotak);
});
