<?php

/**
 * LaporanKernelDispatchTest (Livewire/komponen) —
 * screen-164--laporan-kernel-dispatch-web /
 * usecase-170--laporan-kernel-dispatch-web, terhadap
 * App\Livewire\Dashboard\LaporanKernelDispatch dan markup ter-render-nya.
 *
 * Satu uji per entri `test_scenarios[].component_test` pada tech spec
 * screen-164 (35 skenario; skenario 32 MURNI HTTP dan hidup di
 * tests/Feature/Api/LaporanKernelDispatchTest.php). Aturan agregasinya sendiri
 * dibuktikan di tests/Unit/Services/KernelDispatchReportServiceTest.php
 * terhadap service-nya, dan kontrak HTTP-nya di berkas Api di atas; yang
 * dibuktikan berkas ini adalah APA YANG DIPUBLIKASIKAN KOMPONEN dan APA YANG
 * BENAR-BENAR ADA DI MARKUP.
 *
 * ────────────────────────────────────────────────────────────────────────
 * EMPAT JEBAKAN YANG MENENTUKAN BENTUK SETIAP ASERSI DI BAWAH
 * ────────────────────────────────────────────────────────────────────────
 * (1) KLAIM TENTANG SATU ELEMEN DISASARKAN KE ELEMEN ITU. '—' sepanjang satu
 *     karakter, dan label sependek '3' atau 'Draft', cocok di mana saja pada
 *     halaman ini — mengasersikannya atas SELURUH HTML tidak membuktikan apa
 *     pun. Setiap asersi semacam itu memakai kdLaporanCard() /
 *     kdLaporanSlice() / kdLaporanQualityCard().
 *
 * (2) KETIADAAN SEBUAH BLOK DIASERSIKAN ATAS NAMA KUNCI, TIDAK PERNAH ATAS
 *     PROSA. Payload ini memuat TIGA kalimat manusia — `_note_no_hourly`
 *     (memuat kata 'jam' DAN menyebut 'CPO Dispatch'),
 *     `_note_no_quality_thresholds` (memuat 'batas') dan
 *     `_note_shift_not_published` (memuat 'shift') — dan ketiganya ada justru
 *     untuk MENYATAKAN ketiadaan hal-hal itu. Pemindaian teks karena itu
 *     menjawab pertanyaan yang salah. Lihat kdLaporanAllKeys().
 *
 * (3) assertSee TIDAK MEMBUKTIKAN CACAT TAMPILAN. Tidak satu asersi di bawah
 *     yang memakai assertSee untuk klaim tata letak/penampilan; yang
 *     diasersikan adalah kehadiran testid dan isi elemen yang disasarkan.
 *
 * (4) EMPAT KARTU MUTU, EMPAT PENYEBUT. Jebakan utama layar ini: satu
 *     penyebut tunggal membuat keempat measured_count sama (dan biasanya sama
 *     dengan dispatch_count). Fixture skenario 17 karena itu disusun supaya
 *     KELIMA angka itu BERBEDA, dan skenario 18 mengasersikan satu kartu
 *     kosong sementara tiga lainnya berangka DI MARKUP YANG SAMA — sebuah
 *     penjaga global akan mengosongkan keempatnya dan memerahkannya.
 *
 * ────────────────────────────────────────────────────────────────────────
 * FIXTURE: TANGGAL SELALU DIPAKU, DAN SELALU RELATIF
 * ────────────────────────────────────────────────────────────────────────
 * KernelDispatchRecordFactory men-default `date` ke
 * dateTimeBetween('-30 days','now') dan KernelDispatchDetailFactory
 * men-default `event_date` dengan cara yang sama; StationFactory bersarang pun
 * membuat ProductionLine sendiri. Asersi 'tepat N pengiriman' yang menumpang
 * default itu gagal sesekali tanpa ada hubungannya dengan layar ini. SETIAP
 * record dan SETIAP pengiriman di bawah memaku tanggalnya lewat helper, dan
 * tanggalnya RELATIF supaya `$this->periodA` SELALU sudah selesai.
 *
 * `remarks_gate_status` adalah enum(['released','not_released'])->nullable()
 * di migrasi 2026_08_31_000007 — sebuah CHECK CONSTRAINT di SQLite DAN
 * PostgreSQL — sehingga nilai di luar keduanya TIDAK DAPAT disemai lewat
 * factory. Skenario 24 karena itu memasang service-ganda in-memory, bukan
 * berpura-pura menyemainya dan diam-diam menguji nol baris. Hal yang sama
 * berlaku untuk `event_date` NULL (kolomnya NOT NULL) pada kedua uji
 * `undated` di bawah.
 */

use App\Enums\PeriodStatus;
use App\Enums\RecordStatus;
use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Livewire\Dashboard\LaporanKernelDispatch;
use App\Livewire\Dashboard\LaporanStasiun;
use App\Models\BusinessUnit;
use App\Models\KernelDispatchDetail;
use App\Models\KernelDispatchRecord;
use App\Models\Period;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\StationType;
use App\Models\User;
use App\Services\KernelDispatchReportService;
use App\Services\StationReportService;
use App\Support\Display;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * Service yang membaca PENGIRIMAN dari MEMORI alih-alih dari basis data.
 *
 * ADA KARENA SKEMANYA, BUKAN KARENA KEMALASAN — lihat docblock berkas. Dua
 * cabang render tidak dapat dicapai lewat factory: nilai `remarks_gate_status`
 * di luar enum-nya, dan `event_date` NULL. Meng-override dispatchesFor()
 * membuat SELURUH agregasi tetap dijalankan sungguhan atas pengiriman itu,
 * sehingga cabang render-nya benar-benar diuji alih-alih ditandai 'tidak dapat
 * diuji'.
 */
class LaporanKdInMemoryReportService extends KernelDispatchReportService
{
    /** @var Collection<int, object> */
    public static Collection $dispatches;

    protected function dispatchesFor(Period $period, string $productionLineId): Collection
    {
        return static::$dispatches;
    }
}

/** Satu record induk; TANGGALNYA argumen posisional. */
function kdLaporanRecord(Station $station, string $date, array $attributes = []): KernelDispatchRecord
{
    return KernelDispatchRecord::factory()
        ->forStation($station)
        ->onDate($date)
        ->create(array_merge([
            'kernel_dispatch_id' => 'KD-'.Str::upper(Str::random(8)),
            'status' => RecordStatus::Saved,
            'note' => null,
        ], $attributes));
}

/**
 * Satu baris detail = SATU PENGIRIMAN, dengan SELURUH kolom yang berarti bagi
 * laporan di-default NULL: KernelDispatchDetailFactory mengisi ketiga kolom
 * kategoris, keempat kolom mutu, kedua segel, nomor surat jalan, shift dan
 * gate status dengan nilai faker ACAK, sehingga fixture yang menumpangnya akan
 * mengasersikan rekap yang isinya berubah tiap jalan.
 */
function kdLaporanDispatch(
    KernelDispatchRecord $record,
    string $eventDate,
    array $values = [],
): KernelDispatchDetail {
    return KernelDispatchDetail::factory()
        ->forRecord($record)
        ->create(array_merge([
            'event_date' => $eventDate,
            'shift' => null,
            'weighbridge_ticket_no' => null,
            'waybill_number' => null,
            'transporter_contractor' => null,
            'vehicle_plate_no' => null,
            'driver_name' => null,
            'silo_source_id' => null,
            'destination_buyer' => null,
            'gross_weight_mt' => null,
            'tare_weight_mt' => null,
            'net_weight_mt' => 1.0,
            'kernel_moisture_percent' => null,
            'dirt_impurities_percent' => null,
            'ffa_percent' => null,
            'broken_kernel_percent' => null,
            'security_seal_no_top' => null,
            'security_seal_no_bottom' => null,
            'weighbridge_operator_id' => null,
            'remarks_gate_status' => null,
            'findings' => null,
        ], $values));
}

/** Satu PENGIRIMAN DI MEMORI, berbentuk persis seperti keluaran dispatchesOf(). */
function kdLaporanMemoryDispatch(?string $date, array $values = []): object
{
    $quality = [];

    foreach (KernelDispatchReportService::QUALITY_MEASURES as $measure) {
        $quality[$measure] = $values['quality'][$measure] ?? null;
    }

    unset($values['quality']);

    return (object) array_merge([
        'date' => $date,
        'net_weight' => 1.0,
        'quality' => $quality,
        'transporter' => null,
        'silo_source' => null,
        'destination_buyer' => null,
        'gate_status' => null,
        'waybill_number' => null,
        'security_seal_no_top' => null,
        'security_seal_no_bottom' => null,
        'findings' => null,
        'status' => RecordStatus::Saved->value,
    ], $values);
}

/**
 * Potongan satu elemen ber-data-testid, dari atribut itu sampai $closing.
 *
 * DIPAKAI SETIAP KALI KLAIMNYA TENTANG SATU ELEMEN — lihat jebakan (1) di
 * docblock berkas.
 */
function kdLaporanSlice(string $html, string $testid, string $closing = '</p>'): string
{
    $start = strpos($html, 'data-testid="'.$testid.'"');

    if ($start === false) {
        return '';
    }

    $end = strpos($html, $closing, $start);

    return $end === false ? substr($html, $start) : substr($html, $start, $end - $start);
}

/** Potongan satu kartu KPI (<article>). */
function kdLaporanCard(string $html, string $testid): string
{
    return kdLaporanSlice($html, $testid, '</article>');
}

/** Potongan satu tabel rekap. */
function kdLaporanTable(string $html, string $testid): string
{
    return kdLaporanSlice($html, $testid, '</table>');
}

/**
 * Potongan kartu mutu untuk SATU ukuran, dicari lewat atribut
 * data-measure-nya — keempat kartu memakai testid yang sama
 * (`quality-card`), jadi penyebut '4' pada kartu mana pun akan lolos bila
 * dicari atas testid saja.
 */
function kdLaporanQualityCard(string $html, string $measure): string
{
    $start = strpos($html, 'data-measure="'.$measure.'"');

    if ($start === false) {
        return '';
    }

    // Kembali sedikit ke belakang supaya potongan memuat pembuka <article>-nya.
    $open = strrpos(substr($html, 0, $start), '<article');
    $from = $open === false ? $start : $open;
    $end = strpos($html, '</article>', $from);

    return $end === false ? substr($html, $from) : substr($html, $from, $end - $from);
}

/** Seluruh potongan <tr> di dalam satu potongan tabel. */
function kdLaporanRows(string $table): array
{
    preg_match_all('#<tr\b[^>]*>.*?</tr>#s', $table, $matches);

    return $matches[0];
}

/** Nilai sel ke-$index (1-basis) di dalam satu potongan <tr>. */
function kdLaporanCell(string $row, int $index): ?string
{
    if (preg_match_all('#<td[^>]*>(.*?)</td>#s', $row, $matches) < $index) {
        return null;
    }

    return trim(html_entity_decode(strip_tags($matches[1][$index - 1])));
}

/**
 * Setiap nama KUNCI, serapa pun dalamnya, di dalam payload.
 *
 * Lihat jebakan (2) di docblock berkas: ketiadaan sebuah blok TIDAK PERNAH
 * diasersikan atas prosa, karena prosa yang menyatakan ketiadaannya memuat
 * kata yang sama.
 *
 * @return list<string>
 */
function kdLaporanAllKeys(mixed $value): array
{
    if (! is_array($value)) {
        return [];
    }

    $keys = [];

    foreach ($value as $key => $child) {
        if (is_string($key)) {
            $keys[] = $key;
        }

        $keys = array_merge($keys, kdLaporanAllKeys($child));
    }

    return array_values(array_unique($keys));
}

/** Satu baris rekap untuk satu nilai (null = kelompok 'tidak tercatat'). */
function kdLaporanEntry(array $rows, ?string $value): ?array
{
    foreach ($rows as $row) {
        if ($row['value'] === $value) {
            return $row;
        }
    }

    return null;
}

/** Posisi baris 'tidak tercatat' di dalam satu rekap, atau -1. */
function kdLaporanNullIndex(array $rows): int
{
    foreach ($rows as $index => $row) {
        if ($row['value'] === null) {
            return $index;
        }
    }

    return -1;
}

/** Isi sebuah StreamedResponse, ditangkap. ALIRKAN SEKALI per response. */
function kdLaporanStreamed($response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

/**
 * Komponen yang di-instansiasi LANGSUNG, dipakai hanya untuk mengambil
 * StreamedResponse ekspornya.
 *
 * Livewire::test()->call('exportCsv') membuktikan unduhannya TERJADI tetapi
 * tidak menyerahkan byte-nya; dan yang perlu dibuktikan untuk skenario 31
 * adalah ISI berkasnya — bahwa kolom `shift` dan kelima kolom yang sengaja
 * tidak ada di layar MEMANG ada di baris-barisnya. Jalur ini tetap melewati
 * exportCsv() milik komponen, termasuk penerjemahan 'excel' -> 'xlsx' yang
 * menjadi miliknya sendiri.
 */
function kdLaporanExport(string $periodId, string $productionLineId, string $format, string $businessUnitId = '')
{
    $component = new LaporanKernelDispatch;
    $component->businessUnitId = $businessUnitId;
    $component->productionLineId = $productionLineId;
    $component->periodId = $periodId;

    return $component->exportCsv($format);
}

/**
 * TESTID yang WAJIB ADA pada laporan yang berdata — daftarnya dibaca dari
 * blade dan diasersikan sebagai kelompok supaya satu bagian yang hilang tidak
 * lolos hanya karena tak satu uji pun menyebutnya.
 */
const LAPORAN_KD_FIGURE_TESTIDS = [
    'laporan-kernel-dispatch', 'report-hero', 'hybrid-note',
    'dispatch-quality-card', 'kpi-dispatch-count', 'dispatch-count',
    'kartu-total-netto', 'net-weight-total', 'kartu-rata-netto',
    'net-weight-avg', 'net-weight-dispatch-count',
    'kartu-penimbangan-belum-selesai', 'missing-net-weight-dispatch-count',
    'quality-sub', 'quality-card', 'quality-avg', 'quality-min', 'quality-max',
    'quality-measured-count', 'quality-note',
    'by-transporter-table', 'by-silo-source-table', 'by-destination-buyer-table',
    'gate-status-card', 'kpi-not-released', 'not-released-count',
    'by-gate-status-table', 'gate-status-note',
    'compliance-card', 'kpi-missing-waybill', 'missing-waybill-count',
    'kpi-missing-seal-top', 'missing-seal-top-count',
    'kpi-missing-seal-bottom', 'missing-seal-bottom-count', 'compliance-note',
    'no-hourly-note', 'no-quality-thresholds-note', 'shift-not-published-note',
    'daily-recap-details', 'daily-table', 'daily-row-total', 'daily-total-note',
    'findings-card', 'findings-note',
    'completeness-card', 'days-with-dispatch', 'days-counted',
    'days-with-dispatch-percent', 'completeness-note',
    'draft-card', 'draft-dispatch-count', 'draft-note',
];

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->kernelDispatch()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->kernelDispatch()->create();

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
        ->stationType(StationTypeEnum::KernelDispatch->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode KD Alpha')
        ->create();
});

// =====================================================================
// JALUR SUKSES DAN PERAN (skenario 1-10)
// =====================================================================

it('skenario 1 — Supervisor: tanpa pemilih mill, seluruh bagian laporan terender, keempat penyebut mutu BERBEDA', function () {
    $record = kdLaporanRecord($this->stationA, $this->day1);

    // Pola mutu BERBEDA per kolom sehingga keempat measured_count berbeda:
    // kernel_moisture 4, dirt_impurities 3, ffa 2, broken_kernel 1.
    $pola = [
        ['kernel_moisture_percent' => 6.0, 'dirt_impurities_percent' => 0.01, 'ffa_percent' => 3.0, 'broken_kernel_percent' => 5.0],
        ['kernel_moisture_percent' => 7.0, 'dirt_impurities_percent' => 0.02, 'ffa_percent' => 4.0, 'broken_kernel_percent' => null],
        ['kernel_moisture_percent' => 8.0, 'dirt_impurities_percent' => 0.03, 'ffa_percent' => null, 'broken_kernel_percent' => null],
        ['kernel_moisture_percent' => 9.0, 'dirt_impurities_percent' => null, 'ffa_percent' => null, 'broken_kernel_percent' => null],
    ];

    foreach ($pola as $i => $mutu) {
        kdLaporanDispatch($record, $this->day1, array_merge([
            // Sebagian ber-netto null.
            'net_weight_mt' => $i < 3 ? 2.0 : null,
            'transporter_contractor' => 'PT Angkut Jaya',
            'silo_source_id' => 'SILO-A',
            'destination_buyer' => 'PT Pembeli',
            'remarks_gate_status' => $i === 0 ? 'not_released' : 'released',
            'waybill_number' => $i === 0 ? null : 'SJ-'.$i,
            'security_seal_no_top' => $i === 0 ? null : 'SST-'.$i,
            'security_seal_no_bottom' => 'SSB-'.$i,
            'findings' => $i === 0 ? 'Segel bawah terlepas' : null,
        ], $mutu));
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA);

    $html = $component->html();

    // Peran terikat mill TIDAK melihat pemilih mill — menawarkan pemilih yang
    // tidak dapat ia pakai adalah kebohongan kecil yang mahal.
    expect($html)->not->toContain('data-testid="mill-select"');
    expect($html)->toContain('data-testid="mill-name"');
    $this->assertStringContainsString('Mill Alpha', kdLaporanSlice($html, 'mill-name', '</div>'));

    foreach (LAPORAN_KD_FIGURE_TESTIDS as $testid) {
        $this->assertStringContainsString('data-testid="'.$testid.'"', $html, "testid {$testid} tidak dirender");
    }

    // ASERSI ATAS DATA YANG DIPUBLIKASIKAN KOMPONEN, bukan assertSee atas
    // prosa.
    $summary = $component->viewData('summary');

    expect($summary['dispatches']['dispatch_count'])->toBe(4);
    expect($summary['dispatches']['net_weight_total'])->toBe(6.0);
    expect($summary['dispatches']['net_weight_dispatch_count'])->toBe(3);
    expect($summary['dispatches']['missing_net_weight_dispatch_count'])->toBe(1);
    expect($summary['dispatches']['net_weight_avg'])->toBe(2.0);

    // KEEMPAT PENYEBUT BERBEDA.
    expect($summary['quality']['kernel_moisture_percent']['measured_count'])->toBe(4);
    expect($summary['quality']['dirt_impurities_percent']['measured_count'])->toBe(3);
    expect($summary['quality']['ffa_percent']['measured_count'])->toBe(2);
    expect($summary['quality']['broken_kernel_percent']['measured_count'])->toBe(1);

    expect($summary['not_released_count'])->toBe(1);
    expect($summary['compliance']['missing_waybill_count'])->toBe(1);
    expect($summary['compliance']['missing_seal_top_count'])->toBe(1);
    expect($summary['compliance']['missing_seal_bottom_count'])->toBe(0);
    expect($summary['draft_dispatch_count'])->toBe(0);
    expect($summary['text_entries']['findings'])->toHaveCount(1);
    expect($summary['daily'])->toHaveCount(1);
    expect($summary['completeness']['days_in_period'])->toBe(31);

    // PENYEBUT RATA-RATA TERCETAK DI DALAM KARTU RATA-RATA: '3' cocok di mana
    // saja, jadi ia disasarkan ke kartunya.
    $kartuRata = kdLaporanCard($html, 'kartu-rata-netto');

    $this->assertStringContainsString('data-testid="net-weight-dispatch-count"', $kartuRata);
    $this->assertStringContainsString('>3</b>', $kartuRata);

    // Dan penyebut TOTAL ada di dalam kartu totalnya sendiri.
    $kartuTotal = kdLaporanCard($html, 'kartu-total-netto');
    $this->assertStringContainsString('data-testid="net-weight-total"', $kartuTotal);

    // TONASE DAN MUTU DIRENDER DI DALAM SATU KOTAK — keduanya hampir selalu
    // dibaca bersama. Dibuktikan atas STRUKTUR: keempat kartu mutu berada di
    // dalam potongan section dispatch-quality-card yang sama dengan kartu
    // tonase.
    $kotak = kdLaporanSlice($html, 'dispatch-quality-card', '</section>');

    foreach (['kpi-dispatch-count', 'kartu-total-netto', 'kartu-rata-netto', 'quality-sub'] as $testid) {
        $this->assertStringContainsString('data-testid="'.$testid.'"', $kotak);
    }

    foreach (KernelDispatchReportService::QUALITY_MEASURES as $measure) {
        $this->assertStringContainsString('data-measure="'.$measure.'"', $kotak);
    }

    // KEEMPAT PROSA dirender UTUH — dibandingkan string PENUH, bukan
    // substring.
    foreach ([
        'quality-note' => KernelDispatchReportService::NOTE_QUALITY_DENOMINATORS,
        'no-hourly-note' => KernelDispatchReportService::NOTE_NO_HOURLY,
        'no-quality-thresholds-note' => KernelDispatchReportService::NOTE_NO_QUALITY_THRESHOLDS,
        'shift-not-published-note' => KernelDispatchReportService::NOTE_SHIFT_NOT_PUBLISHED,
    ] as $testid => $prosa) {
        $this->assertStringContainsString(
            e($prosa),
            kdLaporanSlice($html, $testid, '</div>'),
            "prosa {$testid} tidak dirender utuh",
        );
    }

    // Kedua tombol ekspor tampil.
    expect($html)->toContain('data-testid="export-button"');
    expect($html)->toContain('data-testid="export-excel-button"');
});

it('skenario 1b — Mill Management melihat layar yang SAMA PERSIS dengan Supervisor', function () {
    kdLaporanDispatch(kdLaporanRecord($this->stationA, $this->day1), $this->day1, ['net_weight_mt' => 3.0]);

    $html = Livewire::actingAs($this->millManagement)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->not->toContain('data-testid="mill-select"');
    expect($html)->toContain('data-testid="mill-name"');

    foreach (LAPORAN_KD_FIGURE_TESTIDS as $testid) {
        $this->assertStringContainsString('data-testid="'.$testid.'"', $html, "testid {$testid} tidak dirender");
    }
});

it('skenario 2 — Operator ditolak pada RUTE WEB dan pada mount() komponennya', function () {
    // Component test TIDAK melewati middleware, jadi hanya baris ini yang
    // membuktikan penjagaan rutenya ('role:supervisor,mill_management,admin').
    $this->actingAs($this->operator, 'web')
        ->get('/reports/kernel-dispatch')
        ->assertStatus(403);

    // Dan peran yang memang dibuka benar-benar sampai ke halamannya — tanpa
    // ini, 403 di atas tetap hijau pada rute yang menolak semua orang.
    $this->actingAs($this->supervisor, 'web')
        ->get('/reports/kernel-dispatch')
        ->assertOk()
        ->assertSee('data-testid="laporan-kernel-dispatch"', false);

    // Lapisan komponen: mount() melakukan abort_unless atas daftar perannya
    // SENDIRI.
    $html = Livewire::actingAs($this->operator)->test(LaporanKernelDispatch::class)->html();

    expect($html)->toContain('Forbidden');
    // NOL DATA TERPUBLIKASI — diasersikan atas ketiadaan akar komponennya,
    // bukan atas HTML yang kebetulan tidak memuat sebuah angka.
    expect($html)->not->toContain('data-testid="laporan-kernel-dispatch"');
    expect($html)->not->toContain('data-testid="dispatch-quality-card"');
    expect($html)->not->toContain('data-testid="period-select"');
    expect($html)->not->toContain('data-testid="production-line-select"');

    // KETIGA RUTE DATA TETAP MENERIMA OPERATOR: itu jalur screen-165, dan
    // tidak ada gate yang perlu dilebarkan nanti.
    $this->actingAs($this->operator, 'web')
        ->getJson('/api/kernel-dispatch-reports/periods')
        ->assertOk();
});

it('skenario 2b — canAccess() memegang daftar perannya SENDIRI dan tidak mendelegasikan ke guardAccess()', function () {
    // BUKAN BAHAYA MASA DEPAN: KernelDispatchReportService::guardAccess()
    // MENERIMA Operator HARI INI, karena kembaran mobile screen-165 dibangun
    // dalam seri yang sama. Kalau canAccess() mendelegasikan ke sana, layar
    // web ini terbuka untuk Operator SEKARANG — tanpa satu galat pun.
    //
    // Diasersi SECARA STRUKTURAL atas badan metodenya, karena asersi perilaku
    // (skenario 2) baru gagal SETELAH delegasi terjadi, yaitu sesudah
    // kebocorannya mendarat.
    $method = new ReflectionMethod(LaporanKernelDispatch::class, 'canAccess');
    $source = implode('', array_slice(
        file($method->getFileName()),
        $method->getStartLine() - 1,
        $method->getEndLine() - $method->getStartLine() + 1,
    ));

    expect($source)->not->toContain('guardAccess');
    expect($source)->not->toContain('KernelDispatchReportService');
    expect($source)->toContain('UserRole::Supervisor->value');
    expect($source)->toContain('UserRole::MillManagement->value');
    expect($source)->toContain('UserRole::Admin->value');
    expect($source)->not->toContain('UserRole::Operator');

    // Dan service-nya MEMANG menerima Operator — tanpa baris ini, asersi di
    // atas bisa hijau pada service yang juga menutup Operator, yang berarti ia
    // tidak lagi menjaga apa pun.
    $this->actingAs($this->operator, 'web')
        ->getJson('/api/kernel-dispatch-reports/periods')
        ->assertOk();
});

it('skenario 3 — Admin memilih mill lebih dulu', function () {
    kdLaporanDispatch(kdLaporanRecord($this->stationA, $this->day1), $this->day1, ['net_weight_mt' => 4.0]);

    $component = Livewire::actingAs($this->admin)->test(LaporanKernelDispatch::class);

    // SEBELUM mill dipilih: pemilih Mill DIRENDER, daftar line DAN daftar
    // periode KEDUANYA kosong, nol angka terpublikasi.
    $html = $component->html();

    expect($html)->toContain('data-testid="mill-select"');
    expect($html)->toContain('data-testid="mill-select-hint"');
    expect($component->viewData('productionLineOptions'))->toBe([]);
    expect($component->viewData('periods'))->toBe([]);
    $this->assertNull($component->viewData('summary'));
    expect($component->viewData('needsMillSelection'))->toBeTrue();
    expect($html)->not->toContain('data-testid="dispatch-quality-card"');

    // SESUDAH ketiganya dipilih: payload sebentuk dengan peran terikat mill.
    $component->set('businessUnitId', (string) $this->businessUnitA->id)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $this->periodA->id);

    $summary = $component->viewData('summary');

    $this->assertNotNull($summary);
    expect($summary['business_unit']['id'])->toBe((string) $this->businessUnitA->id);
    expect($summary['dispatches']['dispatch_count'])->toBe(1);
    $this->assertStringContainsString('data-testid="dispatch-quality-card"', $component->html());

    // Dan bentuknya SAMA dengan yang dilihat Supervisor.
    $asSupervisor = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $this->periodA->id)
        ->viewData('summary');

    expect(array_keys($summary))->toBe(array_keys($asSupervisor));
    expect($summary['dispatches'])->toEqual($asSupervisor['dispatches']);
});

it('skenario 4 — Admin mengganti mill: periode DAN production line keduanya kosong kembali', function () {
    kdLaporanDispatch(kdLaporanRecord($this->stationA, $this->day1), $this->day1, ['net_weight_mt' => 4.0]);

    $component = Livewire::actingAs($this->admin)
        ->test(LaporanKernelDispatch::class)
        ->set('businessUnitId', (string) $this->businessUnitA->id)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $this->periodA->id);

    $this->assertNotNull($component->viewData('summary'));

    $component->set('businessUnitId', (string) $this->businessUnitB->id);

    // KEDUANYA kembali ke keadaan belum terpilih — diasersikan atas STATE,
    // bukan atas HTML: sebuah periode MAUPUN sebuah line milik tepat satu
    // mill, jadi mempertahankan salah satunya akan membocorkan atau (lebih
    // buruk) merender penolakan akses atas sesuatu yang tidak dilakukan
    // pengguna.
    $component->assertSet('productionLineId', '');
    $component->assertSet('periodId', '');

    // NOL ANGKA mill-A yang tertinggal.
    $this->assertNull($component->viewData('summary'));

    // Daftar line dan periode DIMUAT ULANG untuk mill-B.
    expect($component->viewData('periods'))->toBe([]);
    expect(array_column($component->viewData('productionLineOptions'), 'id'))
        ->toBe([$this->lineB]);

    // ASERSI NEGATIF: tidak ada permintaan summary dengan kombinasi mill-B +
    // periode mill-A — layar tidak merender penolakan atas sesuatu yang tidak
    // dilakukan pengguna.
    expect($component->viewData('forbidden'))->toBeFalse();
    expect($component->html())->not->toContain('data-testid="forbidden-notice"');
});

it('skenario 5 — mill kiriman dari peran terikat mill DIABAIKAN, bukan ditolak; sementara line mill lain TETAP ditolak', function () {
    $periodeB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::KernelDispatch->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('P-B')
        ->create();

    $this->periodA->update(['name' => 'P-A']);

    kdLaporanDispatch(kdLaporanRecord($this->stationA, $this->day1), $this->day1, ['net_weight_mt' => 5.0]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        // Di-set PAKSA seolah dari klien (query string business_unit_id).
        ->set('businessUnitId', (string) $this->businessUnitB->id)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $this->periodA->id);

    $periods = $component->viewData('periods');

    expect($periods)->toHaveCount(1);
    expect($periods[0]['id'])->toBe((string) $this->periodA->id);
    expect($component->viewData('summary')['business_unit']['id'])->toBe((string) $this->businessUnitA->id);

    // ASERSI NEGATIF EKSPLISIT atas id DAN atas nama — bukan atas jumlah
    // entri. 403 di sini akan membocorkan keberadaan mill-B; diabaikan adalah
    // perilaku yang benar.
    $this->assertNotContains((string) $periodeB->id, array_column($periods, 'id'));
    $this->assertNotContains('P-B', array_column($periods, 'name'));

    // KONTRASNYA DIASERSIKAN DI TEST YANG SAMA: production_line_id mill lain
    // TETAP ditolak — ia parameter yang dipilih sadar, bukan konteks yang
    // diturunkan dari akun.
    $kontras = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineB)
        ->set('periodId', (string) $this->periodA->id);

    // Pada jalur RENDER layar, line mill lain jatuh ke keadaan 'belum memilih
    // line' — TIDAK memamerkan bahwa line itu ada. Jalur API dan EKSPOR tetap
    // 403; keduanya dibuktikan di berkas Api.
    $this->assertNull($kontras->viewData('summary'));
    expect($kontras->viewData('needsProductionLineSelection'))->toBeTrue();
    $this->assertStringContainsString('data-testid="select-production-line-hint"', $kontras->html());
    // Dan nama line mill lain tidak pernah terbit.
    $this->assertStringNotContainsString(
        (string) ProductionLine::find($this->lineB)->name,
        $kontras->html(),
    );
});

it('skenario 6 — akun belum terhubung ke mill: pesan hubungi Admin, tanpa pemilih mill', function () {
    $orphan = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $component = Livewire::actingAs($orphan)->test(LaporanKernelDispatch::class);
    $html = $component->html();

    expect($component->viewData('hasNoMillForAccount'))->toBeTrue();
    $this->assertStringContainsString('data-testid="no-mill-hint"', $html);
    $this->assertStringContainsString(
        'Akun Anda belum terhubung ke mill',
        kdLaporanSlice($html, 'no-mill-hint', '</div>'),
    );

    // NOL periode dan NOL angka.
    expect($component->viewData('periods'))->toBe([]);
    $this->assertNull($component->viewData('summary'));

    // GAGAL TERTUTUP: pemilih Mill TIDAK ditawarkan sebagai gantinya —
    // menawarkan daftar seluruh mill kepada peran yang seharusnya terikat satu
    // mill mengubah data master yang rusak menjadi kebocoran lintas mill.
    expect($html)->not->toContain('data-testid="mill-select"');
    expect($component->viewData('businessUnitOptions'))->toBe([]);

    // Dan jalur API-nya memang 422 VALIDATION_ERROR, BUKAN 403 — kelas
    // exception-nya, bukan potongan pesan.
    $this->actingAs($orphan, 'web')
        ->getJson('/api/kernel-dispatch-reports/periods')
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonPath('errors.business_unit_id.0', 'Akun Anda belum terhubung ke mill. Hubungi Admin.');
});

it("skenario 7 — Production Line wajib dipilih, tanpa opsi 'semua line'", function () {
    // DUA LINE yang MASING-MASING berpengiriman: implementasi yang memilih
    // 'line pertama' atau menjumlahkan semua line akan menerbitkan angka dan
    // test ini memerah.
    $lineKedua = Station::factory()->forBusinessUnit($this->businessUnitA)->kernelDispatch()->create();

    kdLaporanDispatch(kdLaporanRecord($this->stationA, $this->day1), $this->day1, ['net_weight_mt' => 10.0]);
    kdLaporanDispatch(kdLaporanRecord($lineKedua, $this->day1), $this->day1, ['net_weight_mt' => 100.0]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('periodId', (string) $this->periodA->id);

    $html = $component->html();

    // NOL ANGKA terpublikasi dan layar meminta memilih line.
    $this->assertNull($component->viewData('summary'));
    expect($component->viewData('needsProductionLineSelection'))->toBeTrue();
    $this->assertStringContainsString('data-testid="select-production-line-hint"', $html);
    // Frasa dipilih yang TIDAK terpotong baris di blade — kalimat 'termasuk
    // tidak menampilkan angka / seluruh mill' terpisah newline di sumbernya,
    // jadi mencarinya sebagai satu string akan merah karena spasi, bukan
    // karena isinya.
    $arahan = kdLaporanSlice($html, 'select-production-line-hint', '</div>');

    $this->assertStringContainsString('Pilih production line terlebih dahulu', $arahan);
    $this->assertStringContainsString('tidak menampilkan satu angka pun', $arahan);

    // Dan BENAR-BENAR tidak ada angka di DOM.
    foreach (['dispatch-quality-card', 'compliance-card', 'daily-table', 'completeness-card', 'draft-card', 'gate-status-card'] as $testid) {
        $this->assertStringNotContainsString('data-testid="'.$testid.'"', $html, "{$testid} dirender sebelum line dipilih");
    }

    // KEDUA TOMBOL EKSPOR belum dirender sama sekali.
    expect($html)->not->toContain('data-testid="export-button"');
    expect($html)->not->toContain('data-testid="export-excel-button"');

    // PEMILIH LINE TIDAK PUNYA OPSI 'SEMUA LINE' — diasersikan atas daftar
    // opsi yang DIPUBLIKASIKAN komponen, bukan atas HTML.
    $options = $component->viewData('productionLineOptions');

    expect($options)->toHaveCount(2);

    foreach ($options as $option) {
        expect(array_keys($option))->toBe(['id', 'name']);
        $this->assertNotSame('', (string) $option['id'], 'ada opsi line ber-id kosong (mode semua-line)');
    }

    foreach (array_column($options, 'name') as $name) {
        $this->assertStringNotContainsStringIgnoringCase('semua', $name);
    }
});

it('skenario 8 — mill hanya punya satu Production Line dan line itu TETAP tidak dipilihkan', function () {
    // Mill B hanya punya satu line (stationB), dan supervisornya terikat ke
    // sana.
    $supervisorB = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitB)->create();

    $periodeB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::KernelDispatch->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode KD Beta')
        ->create();

    kdLaporanDispatch(kdLaporanRecord($this->stationB, $this->day1), $this->day1, ['net_weight_mt' => 9.0]);

    $component = Livewire::actingAs($supervisorB)
        ->test(LaporanKernelDispatch::class)
        ->set('periodId', (string) $periodeB->id);

    expect($component->viewData('productionLineOptions'))->toHaveCount(1);

    // productionLineId TETAP kosong dan NOL angka terpublikasi — line tunggal
    // TIDAK dipilihkan otomatis. Line adalah konteks yang DIPILIH, bukan
    // ikatan akun, dan 'kebetulan hanya ada satu' bukan alasan memilihkan.
    $component->assertSet('productionLineId', '');
    $this->assertNull($component->viewData('summary'));
    expect($component->viewData('needsProductionLineSelection'))->toBeTrue();
});

it('skenario 9 — mill belum punya periode yang mencakup Kernel Dispatch', function () {
    $this->periodA->stations()->delete();
    $this->periodA->delete();

    $lain = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::SolidWasteDisposal->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode SWD saja')
        ->create();

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA);

    $periods = $component->viewData('periods');

    // Array KOSONG, bukan null dan bukan galat.
    expect($periods)->toBe([]);
    $this->assertNull($component->viewData('summary'));

    $html = $component->html();

    $this->assertStringContainsString('data-testid="no-period-hint"', $html);
    $this->assertStringContainsString(
        'Belum ada Periode Pelaporan',
        kdLaporanSlice($html, 'no-period-hint', '</div>'),
    );

    // ASERSI NEGATIF atas ID: periode berstasiun lain tidak bocor ke daftar.
    $this->assertNotContains((string) $lain->id, array_column($periods, 'id'));
});

it('skenario 10 — periode milik mill lain ditolak SECARA TERLIHAT, tanpa satu angka pun dirender', function () {
    $periodeB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::KernelDispatch->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode KD Beta')
        ->create();

    kdLaporanDispatch(kdLaporanRecord($this->stationB, $this->day1), $this->day1, ['net_weight_mt' => 77.0]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA)
        // Di-set PAKSA seolah dari klien.
        ->set('periodId', (string) $periodeB->id);

    expect($component->viewData('forbidden'))->toBeTrue();
    $this->assertNull($component->viewData('summary'));

    $html = $component->html();

    $this->assertStringContainsString('data-testid="forbidden-notice"', $html);
    $this->assertStringNotContainsString('data-testid="dispatch-quality-card"', $html);
    // Tidak satu angka mill-B pun dirender.
    $this->assertStringNotContainsString('77,0', $html);

    // KONTRAS DINYATAKAN DALAM SATU DESKRIPSI: production_line_id mill lain
    // DITOLAK karena ia parameter yang dipilih sadar, sementara
    // business_unit_id mill lain DIABAIKAN karena ia konteks yang diturunkan
    // dari akun — dua perilaku berbeda atas dua jenis parameter. Jalur API
    // membuktikan 403-nya; di sini yang dibuktikan adalah layar tidak
    // merender satu angka pun.
    $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/kernel-dispatch-reports/summary?period_id='.$periodeB->id.'&production_line_id='.$this->lineB)
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');
});

// =====================================================================
// TONASE, MUTU, DAN KEMANDIRIAN KEDUANYA (skenario 11-19)
// =====================================================================

it('skenario 11 — periode tanpa satu pun pengiriman: KERANGKA LENGKAP, bukan galat dan bukan nol di mana-mana', function () {
    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $this->periodA->id);

    $summary = $component->viewData('summary');

    $this->assertNotNull($summary, 'periode tanpa data seharusnya tetap menerbitkan kerangka');

    expect($summary['dispatches']['dispatch_count'])->toBe(0);
    // assertNull, BUKAN 0.
    $this->assertNull($summary['dispatches']['net_weight_total']);
    $this->assertNull($summary['dispatches']['net_weight_avg']);

    foreach (KernelDispatchReportService::QUALITY_MEASURES as $measure) {
        expect($summary['quality'][$measure]['measured_count'])->toBe(0);
        $this->assertNull($summary['quality'][$measure]['min'], "{$measure}.min bukan null");
        $this->assertNull($summary['quality'][$measure]['avg'], "{$measure}.avg bukan null");
        $this->assertNull($summary['quality'][$measure]['max'], "{$measure}.max bukan null");
    }

    foreach (['by_transporter', 'by_silo_source', 'by_destination_buyer', 'by_gate_status'] as $rekap) {
        expect($summary[$rekap])->toBe([]);
    }

    expect($summary['daily'])->toBe([]);

    // KETIGA PENGHITUNG KEPATUHAN 0 dengan KETIGA KUNCINYA TETAP ADA.
    foreach (['missing_waybill_count', 'missing_seal_top_count', 'missing_seal_bottom_count'] as $kunci) {
        $this->assertArrayHasKey($kunci, $summary['compliance']);
        expect($summary['compliance'][$kunci])->toBe(0);
    }

    expect($summary['not_released_count'])->toBe(0);
    expect($summary['draft_dispatch_count'])->toBe(0);
    expect($summary['undated_dispatch_count'])->toBe(0);
    expect($summary['text_entries']['findings'])->toBe([]);

    $html = $component->html();

    // LAYAR MENCETAK KEADAAN KOSONG YANG MENYEBUT NAMA PERIODE DAN NAMA LINE —
    // disasarkan ke sel kosong rekapnya, bukan dicari di seluruh halaman.
    $kosong = kdLaporanSlice($html, 'by-transporter-empty', '</tr>');

    $this->assertStringContainsString('Periode KD Alpha', $kosong);
    $this->assertStringContainsString((string) ProductionLine::find($this->lineA)->name, $kosong);

    // Dan kartu beratnya mencetak tanda pisah, BUKAN 0,00.
    $this->assertStringContainsString('—', kdLaporanSlice($html, 'net-weight-total', '</p>'));
    $this->assertStringNotContainsString('0,00', kdLaporanSlice($html, 'net-weight-total', '</p>'));
});

it('skenario 12 — seluruh pengiriman belum selesai ditimbang: berat bertanda pisah SEMENTARA keempat ukuran mutu TETAP berangka', function () {
    $record = kdLaporanRecord($this->stationA, $this->day1);

    foreach (range(1, 4) as $i) {
        kdLaporanDispatch($record, $this->day1, [
            'net_weight_mt' => null,
            'kernel_moisture_percent' => 6.0 + $i,
            'dirt_impurities_percent' => 0.01 * $i,
            'ffa_percent' => 1.0 * $i,
            'broken_kernel_percent' => 2.0 * $i,
        ]);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA);

    $summary = $component->viewData('summary');

    $this->assertNull($summary['dispatches']['net_weight_total']);
    $this->assertNull($summary['dispatches']['net_weight_avg']);
    expect($summary['dispatches']['net_weight_dispatch_count'])->toBe(0);
    expect($summary['dispatches']['missing_net_weight_dispatch_count'])->toBe(4);
    expect($summary['dispatches']['dispatch_count'])->toBe(4);

    // KEEMPAT UKURAN MUTU TETAP BERANGKA — mutu dan berat adalah kolom
    // berbeda dan blok berat yang kosong tidak boleh mengosongkan blok mutu.
    foreach (KernelDispatchReportService::QUALITY_MEASURES as $measure) {
        expect($summary['quality'][$measure]['measured_count'])->toBe(4);
        $this->assertNotNull($summary['quality'][$measure]['avg'], "{$measure}.avg ikut dikosongkan");
    }

    $html = $component->html();

    // BLADE MENCETAK '—', TIDAK PERNAH 0,00 MT — disasarkan ke elemen
    // angkanya, karena '—' sepanjang satu karakter cocok di mana saja.
    foreach (['net-weight-total', 'net-weight-avg'] as $testid) {
        $potongan = kdLaporanSlice($html, $testid, '</p>');

        $this->assertStringContainsString('—', $potongan, "{$testid} tidak mencetak tanda pisah");
        $this->assertStringNotContainsString('0,00', $potongan, "{$testid} mencetak 0,00");
    }

    // Sementara keempat kartu mutu TIDAK mencetak tanda pisah pada rata-ratanya.
    foreach (KernelDispatchReportService::QUALITY_MEASURES as $measure) {
        $kartu = kdLaporanQualityCard($html, $measure);

        $this->assertNotSame('', $kartu, "kartu mutu {$measure} tidak dirender");
        $this->assertStringNotContainsString('data-testid="quality-unmeasured"', $kartu);
    }
});

it('skenario 13 — sebagian pengiriman belum ditimbang: penyebut 4 tercetak DI DALAM kartu rata-rata', function () {
    $record = kdLaporanRecord($this->stationA, $this->day1);

    foreach ([10.0, 20.0, 30.0, 40.0, null, null, null] as $netto) {
        kdLaporanDispatch($record, $this->day1, ['net_weight_mt' => $netto]);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA);

    $dispatches = $component->viewData('summary')['dispatches'];

    expect($dispatches['dispatch_count'])->toBe(7);
    expect($dispatches['net_weight_total'])->toBe(100.0);
    expect($dispatches['net_weight_dispatch_count'])->toBe(4);
    // 25, BUKAN 100/7.
    expect($dispatches['net_weight_avg'])->toBe(25.0);
    $this->assertNotEqualsWithDelta(100 / 7, $dispatches['net_weight_avg'], 0.01);
    expect($dispatches['missing_net_weight_dispatch_count'])->toBe(3);

    $html = $component->html();

    // PENYEBUT DISASARKAN KE KARTUNYA, bukan ke halaman: '4' cocok di mana
    // saja pada halaman ini.
    $kartuRata = kdLaporanCard($html, 'kartu-rata-netto');

    $this->assertStringContainsString('data-testid="net-weight-dispatch-count"', $kartuRata);
    $this->assertStringContainsString('>4</b>', $kartuRata);
    $this->assertStringContainsString('25,00', kdLaporanSlice($html, 'net-weight-avg', '</p>'));

    // missing sebagai ANGKA TERSENDIRI di kartunya sendiri, bukan catatan kaki.
    $kartuBelum = kdLaporanCard($html, 'kartu-penimbangan-belum-selesai');

    $this->assertStringContainsString('Penimbangan Belum Selesai', $kartuBelum);
    $this->assertStringContainsString('penimbangan belum selesai', $kartuBelum);
    $this->assertStringNotContainsString('data tidak lengkap', $kartuBelum);
});

it('skenario 14 — berat bersih dibaca APA ADANYA dan tidak pernah dihitung ulang dari bruto dikurangi tara', function () {
    $record = kdLaporanRecord($this->stationA, $this->day1);

    // Baris bernetto-tanpa-bruto/tara: bentuk yang PERSIS sama dengan kedua
    // baris fixture e2e hari ini.
    kdLaporanDispatch($record, $this->day1, [
        'gross_weight_mt' => null,
        'tare_weight_mt' => null,
        'net_weight_mt' => 18.5,
    ]);

    kdLaporanDispatch($record, $this->day1, [
        'gross_weight_mt' => 30.0,
        'tare_weight_mt' => 8.0,
        'net_weight_mt' => 17.0,
    ]);

    $dispatches = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA)
        ->viewData('summary')['dispatches'];

    // 35.5 — baris pertama IKUT (tidak dilewati karena bruto/tara kosong), dan
    // baris kedua menyumbang 17.0 BUKAN 22. Turunan terjadi di
    // KernelDispatchRecordService, bukan di laporan.
    expect($dispatches['net_weight_total'])->toBe(35.5);
    expect($dispatches['net_weight_dispatch_count'])->toBe(2);
    $this->assertNotEquals(40.5, $dispatches['net_weight_total']);
    $this->assertNotEquals(22.0, $dispatches['net_weight_total']);
});

it('skenario 15 — hitungan transaksi dan ukuran mutu dirender BERDAMPINGAN di payload yang sama dan di markup yang sama', function () {
    $record = kdLaporanRecord($this->stationA, $this->day1);

    kdLaporanDispatch($record, $this->day1, [
        'net_weight_mt' => 20.0,
        'kernel_moisture_percent' => 7.0,
        'dirt_impurities_percent' => 0.02,
        'ffa_percent' => 3.0,
        'broken_kernel_percent' => 5.0,
    ]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA);

    $summary = $component->viewData('summary');

    // KEDUA BLOK terbit di PAYLOAD YANG SAMA — bentuk transaksi dari
    // screen-162 DAN bentuk mutu per-ukuran dari screen-160. Menyalin dari
    // satu preseden saja akan kehilangan separuh bentuknya.
    $this->assertArrayHasKey('dispatches', $summary);
    $this->assertArrayHasKey('quality', $summary);
    expect(array_keys($summary['dispatches']))->toBe([
        'dispatch_count', 'net_weight_total', 'net_weight_avg',
        'net_weight_dispatch_count', 'missing_net_weight_dispatch_count',
    ]);
    expect(array_keys($summary['quality']['kernel_moisture_percent']))
        ->toBe(['min', 'avg', 'max', 'measured_count']);

    // DAN KEDUANYA TERRENDER, di dalam WADAH YANG SAMA.
    $kotak = kdLaporanSlice($component->html(), 'dispatch-quality-card', '</section>');

    $this->assertNotSame('', $kotak, 'wadah tonase+mutu tidak dirender');
    $this->assertStringContainsString('data-testid="dispatch-count"', $kotak);

    foreach (KernelDispatchReportService::QUALITY_MEASURES as $measure) {
        $this->assertStringContainsString('data-measure="'.$measure.'"', $kotak, "kartu {$measure} di luar wadah tonase");
    }
});

it('skenario 16 — empat ukuran mutu dengan terendah, rata-rata, tertinggi dan penyebutnya MASING-MASING', function () {
    $record = kdLaporanRecord($this->stationA, $this->day1);

    foreach ([
        ['kernel_moisture_percent' => 6.2, 'dirt_impurities_percent' => 0.01],
        ['kernel_moisture_percent' => 7.8, 'dirt_impurities_percent' => 0.03],
        ['kernel_moisture_percent' => 7.0, 'dirt_impurities_percent' => null],
    ] as $mutu) {
        kdLaporanDispatch($record, $this->day1, $mutu);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA);

    $quality = $component->viewData('summary')['quality'];

    foreach (KernelDispatchReportService::QUALITY_MEASURES as $measure) {
        foreach (['min', 'avg', 'max', 'measured_count'] as $kunci) {
            $this->assertArrayHasKey($kunci, $quality[$measure], "{$measure} tidak menerbitkan {$kunci}");
        }
    }

    $this->assertEqualsWithDelta(6.2, $quality['kernel_moisture_percent']['min'], 0.0005);
    $this->assertEqualsWithDelta(7.0, $quality['kernel_moisture_percent']['avg'], 0.0005);
    $this->assertEqualsWithDelta(7.8, $quality['kernel_moisture_percent']['max'], 0.0005);
    expect($quality['kernel_moisture_percent']['measured_count'])->toBe(3);

    $this->assertEqualsWithDelta(0.01, $quality['dirt_impurities_percent']['min'], 0.0005);
    $this->assertEqualsWithDelta(0.02, $quality['dirt_impurities_percent']['avg'], 0.0005);
    $this->assertEqualsWithDelta(0.03, $quality['dirt_impurities_percent']['max'], 0.0005);
    expect($quality['dirt_impurities_percent']['measured_count'])->toBe(2);

    $html = $component->html();

    // KEEMPAT KARTU PUNYA PENANDA MASING-MASING, dan penyebut tiap ukuran
    // tercetak DI DALAM kartunya sendiri — label sependek '3' WAJIB menyasar
    // elemen, bukan dicari di seluruh halaman.
    $kadarAir = kdLaporanQualityCard($html, 'kernel_moisture_percent');
    $kotoran = kdLaporanQualityCard($html, 'dirt_impurities_percent');

    $this->assertStringContainsString('>3</b>', $kadarAir);
    $this->assertStringContainsString('>2</b>', $kotoran);
    // Dan JANGAN tertukar: penyebut 2 bukan milik kartu kadar air.
    $this->assertStringNotContainsString('>2</b>', $kadarAir);
    $this->assertStringNotContainsString('>3</b>', $kotoran);

    // TIGA desimal pada kartu mutu, sama dengan pembulatan service.
    $this->assertStringContainsString('6,200', $kadarAir);
    $this->assertStringContainsString('7,800', $kadarAir);
    $this->assertStringContainsString('0,010', $kotoran);
    $this->assertStringContainsString('0,030', $kotoran);
});

it('skenario 17 — penyebut keempat ukuran mutu dapat BERBEDA satu dengan lainnya DAN berbeda dari jumlah pengiriman', function () {
    $record = kdLaporanRecord($this->stationA, $this->day1);

    $pola = [
        ['kernel_moisture_percent' => 6.0, 'dirt_impurities_percent' => null,  'ffa_percent' => null, 'broken_kernel_percent' => null],
        ['kernel_moisture_percent' => 6.0, 'dirt_impurities_percent' => 0.01,  'ffa_percent' => null, 'broken_kernel_percent' => null],
        ['kernel_moisture_percent' => 6.0, 'dirt_impurities_percent' => 0.01,  'ffa_percent' => null, 'broken_kernel_percent' => null],
        ['kernel_moisture_percent' => 6.0, 'dirt_impurities_percent' => 0.01,  'ffa_percent' => 3.0,  'broken_kernel_percent' => 5.0],
        ['kernel_moisture_percent' => 6.0, 'dirt_impurities_percent' => 0.01,  'ffa_percent' => 3.0,  'broken_kernel_percent' => 5.0],
        ['kernel_moisture_percent' => 6.0, 'dirt_impurities_percent' => 0.01,  'ffa_percent' => 3.0,  'broken_kernel_percent' => 5.0],
        ['kernel_moisture_percent' => null, 'dirt_impurities_percent' => null, 'ffa_percent' => 3.0,  'broken_kernel_percent' => null],
        ['kernel_moisture_percent' => null, 'dirt_impurities_percent' => null, 'ffa_percent' => null, 'broken_kernel_percent' => null],
        ['kernel_moisture_percent' => null, 'dirt_impurities_percent' => null, 'ffa_percent' => null, 'broken_kernel_percent' => null],
    ];

    foreach ($pola as $mutu) {
        kdLaporanDispatch($record, $this->day1, $mutu);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA);

    $summary = $component->viewData('summary');

    // LIMA ANGKA YANG SEMUANYA BERBEDA, diasersikan satu per satu.
    expect($summary['quality']['kernel_moisture_percent']['measured_count'])->toBe(6);
    expect($summary['quality']['dirt_impurities_percent']['measured_count'])->toBe(5);
    expect($summary['quality']['ffa_percent']['measured_count'])->toBe(4);
    expect($summary['quality']['broken_kernel_percent']['measured_count'])->toBe(3);
    expect($summary['dispatches']['dispatch_count'])->toBe(9);

    $html = $component->html();

    // KEEMPAT PENYEBUT TERRENDER DI KARTUNYA MASING-MASING.
    foreach ([
        'kernel_moisture_percent' => 6,
        'dirt_impurities_percent' => 5,
        'ffa_percent' => 4,
        'broken_kernel_percent' => 3,
    ] as $measure => $penyebut) {
        $kartu = kdLaporanQualityCard($html, $measure);

        $this->assertNotSame('', $kartu, "kartu {$measure} tidak dirender");
        $this->assertStringContainsString(">{$penyebut}</b>", $kartu, "kartu {$measure} tidak mencetak penyebut {$penyebut}");
    }

    // quality._note DIRENDER UTUH — tanpa kalimat itu, pembaca yang melihat
    // kadar air atas 6 pengiriman dan ALB atas 4 akan menyimpulkan ada data
    // yang hilang.
    $this->assertStringContainsString(
        e(KernelDispatchReportService::NOTE_QUALITY_DENOMINATORS),
        kdLaporanSlice($html, 'quality-note', '</div>'),
    );
});

it("skenario 18 — satu ukuran mutu tanpa hasil uji tampil '—' TANPA mengosongkan ukuran lain", function () {
    $record = kdLaporanRecord($this->stationA, $this->day1);

    foreach ([
        ['kernel_moisture_percent' => 6.0, 'dirt_impurities_percent' => 0.01, 'broken_kernel_percent' => 2.0],
        ['kernel_moisture_percent' => 7.0, 'dirt_impurities_percent' => 0.02, 'broken_kernel_percent' => 4.0],
        ['kernel_moisture_percent' => 8.0, 'dirt_impurities_percent' => 0.03, 'broken_kernel_percent' => 6.0],
        ['kernel_moisture_percent' => 9.0, 'dirt_impurities_percent' => 0.04, 'broken_kernel_percent' => 8.0],
    ] as $mutu) {
        kdLaporanDispatch($record, $this->day1, array_merge(['ffa_percent' => null], $mutu));
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA);

    $quality = $component->viewData('summary')['quality'];

    expect($quality['ffa_percent']['measured_count'])->toBe(0);
    $this->assertNull($quality['ffa_percent']['min']);
    $this->assertNull($quality['ffa_percent']['avg']);
    $this->assertNull($quality['ffa_percent']['max']);

    $html = $component->html();

    // KARTU ALB mencetak '—' dan MENYATAKAN sebabnya.
    $kartuAlb = kdLaporanQualityCard($html, 'ffa_percent');

    $this->assertStringContainsString('—', $kartuAlb);
    $this->assertStringContainsString('data-testid="quality-unmeasured"', $kartuAlb);
    $this->assertStringContainsString('>0</b>', $kartuAlb);

    // SEKALIGUS, DI MARKUP YANG SAMA: ketiga kartu lain BERANGKA dengan
    // measured_count 4. Satu penjaga global 'ada data mutu atau tidak' akan
    // mengosongkan KEEMPAT kartu dan test ini memerah — empat asersi per
    // kartu lewat penandanya.
    foreach (['kernel_moisture_percent', 'dirt_impurities_percent', 'broken_kernel_percent'] as $measure) {
        expect($quality[$measure]['measured_count'])->toBe(4);
        $this->assertNotNull($quality[$measure]['avg'], "{$measure}.avg ikut dikosongkan");

        $kartu = kdLaporanQualityCard($html, $measure);

        $this->assertNotSame('', $kartu, "kartu {$measure} tidak dirender");
        $this->assertStringNotContainsString(
            'data-testid="quality-unmeasured"',
            $kartu,
            "kartu {$measure} ikut dinyatakan belum diuji",
        );
        $this->assertStringContainsString('>4</b>', $kartu, "kartu {$measure} kehilangan penyebutnya");
    }
});

it('skenario 19 — keempat ukuran mutu kosong SEMENTARA tonasenya ada, dan varian terbaliknya', function () {
    // VARIAN 1 — netto terisi, keempat kolom mutu NULL.
    $recordA = kdLaporanRecord($this->stationA, $this->day1);

    foreach (range(1, 5) as $i) {
        kdLaporanDispatch($recordA, $this->day1, ['net_weight_mt' => 10.0]);
    }

    $satu = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA);

    $a = $satu->viewData('summary');
    $htmlA = $satu->html();

    foreach (KernelDispatchReportService::QUALITY_MEASURES as $measure) {
        expect($a['quality'][$measure]['measured_count'])->toBe(0);

        // PERNYATAAN DIRENDER, bukan blok yang dihilangkan.
        $kartu = kdLaporanQualityCard($htmlA, $measure);

        $this->assertNotSame('', $kartu, "kartu {$measure} dihilangkan alih-alih menyatakan belum ada hasil uji");
        $this->assertStringContainsString('data-testid="quality-unmeasured"', $kartu);
        $this->assertStringContainsString('Belum ada satu hasil uji pun', $kartu);
    }

    // SEMENTARA ketiga angka berat tetap BERANGKA.
    expect($a['dispatches']['net_weight_total'])->toBe(50.0);
    expect($a['dispatches']['net_weight_avg'])->toBe(10.0);
    expect($a['dispatches']['net_weight_dispatch_count'])->toBe(5);
    $this->assertStringContainsString('50,0', kdLaporanSlice($htmlA, 'net-weight-total', '</p>'));

    // VARIAN 2 (TERBALIK) — mutu terisi, netto null pada semuanya.
    KernelDispatchDetail::query()->delete();
    KernelDispatchRecord::query()->delete();

    $recordB = kdLaporanRecord($this->stationA, $this->day2);

    foreach (range(1, 5) as $i) {
        kdLaporanDispatch($recordB, $this->day2, [
            'net_weight_mt' => null,
            'kernel_moisture_percent' => 6.0 + $i,
            'dirt_impurities_percent' => 0.01 * $i,
            'ffa_percent' => 1.0 * $i,
            'broken_kernel_percent' => 2.0 * $i,
        ]);
    }

    $dua = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA);

    $b = $dua->viewData('summary');
    $htmlB = $dua->html();

    foreach (KernelDispatchReportService::QUALITY_MEASURES as $measure) {
        expect($b['quality'][$measure]['measured_count'])->toBe(5);

        $kartu = kdLaporanQualityCard($htmlB, $measure);

        $this->assertStringNotContainsString('data-testid="quality-unmeasured"', $kartu);
    }

    $this->assertNull($b['dispatches']['net_weight_total']);
    $this->assertNull($b['dispatches']['net_weight_avg']);

    foreach (['net-weight-total', 'net-weight-avg'] as $testid) {
        $this->assertStringContainsString('—', kdLaporanSlice($htmlB, $testid, '</p>'));
    }
});

// =====================================================================
// REKAP KATEGORIS (skenario 20-27)
// =====================================================================

it('skenario 20 — tiga rekap teks bebas diterbitkan apa adanya dan EMPAT penjumlahan terpisah menjumlah ke jumlah pengiriman', function () {
    $record = kdLaporanRecord($this->stationA, $this->day1);

    // POLA NULL BERBEDA PER KOLOM supaya keempat asersi tidak saling
    // menumpang.
    $baris = [
        ['transporter_contractor' => null, 'silo_source_id' => 'S1',  'destination_buyer' => 'B1',  'remarks_gate_status' => 'released',     'net_weight_mt' => 2.0],
        ['transporter_contractor' => 'T1', 'silo_source_id' => null,  'destination_buyer' => 'B1',  'remarks_gate_status' => 'released',     'net_weight_mt' => 2.0],
        ['transporter_contractor' => 'T1', 'silo_source_id' => null,  'destination_buyer' => null,  'remarks_gate_status' => null,           'net_weight_mt' => null],
        ['transporter_contractor' => 'T2', 'silo_source_id' => null,  'destination_buyer' => null,  'remarks_gate_status' => 'not_released', 'net_weight_mt' => 2.0],
        ['transporter_contractor' => 'T2', 'silo_source_id' => 'S2',  'destination_buyer' => null,  'remarks_gate_status' => 'released',     'net_weight_mt' => 2.0],
        ['transporter_contractor' => 'T2', 'silo_source_id' => 'S2',  'destination_buyer' => null,  'remarks_gate_status' => null,           'net_weight_mt' => null],
        ['transporter_contractor' => 'T3', 'silo_source_id' => 'S2',  'destination_buyer' => null,  'remarks_gate_status' => 'released',     'net_weight_mt' => 2.0],
        ['transporter_contractor' => 'T3', 'silo_source_id' => 'S3',  'destination_buyer' => 'B2',  'remarks_gate_status' => 'not_released', 'net_weight_mt' => 2.0],
    ];

    foreach ($baris as $nilai) {
        kdLaporanDispatch($record, $this->day1, $nilai);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA);

    $summary = $component->viewData('summary');
    $html = $component->html();

    expect($summary['dispatches']['dispatch_count'])->toBe(8);

    // EMPAT ASERSI PENJUMLAHAN TERPISAH — ketiga rekap teks DAN
    // by_gate_status.
    foreach (['by_transporter', 'by_silo_source', 'by_destination_buyer', 'by_gate_status'] as $rekap) {
        $this->assertSame(
            8,
            array_sum(array_column($summary[$rekap], 'dispatch_count')),
            "rekap {$rekap} tidak menjumlah ke dispatch_count",
        );

        // TIAP BARIS REKAP MENERBITKAN PENYEBUTNYA SENDIRI.
        foreach ($summary[$rekap] as $row) {
            $this->assertArrayHasKey('net_weight_dispatch_count', $row);
            $this->assertArrayHasKey('net_weight_avg', $row);
        }
    }

    // KETIGANYA TERRENDER, masing-masing dengan baris 'tidak tercatat' SEBAGAI
    // BARIS TERSENDIRI — disasarkan ke testid baris null-nya, bukan dicari
    // sebagai teks di halaman.
    foreach ([
        'by-transporter' => 1,
        'by-silo-source' => 3,
        'by-destination-buyer' => 5,
    ] as $testid => $jumlahNull) {
        $tabel = kdLaporanTable($html, $testid.'-table');

        $this->assertNotSame('', $tabel, "tabel {$testid} tidak dirender");
        $this->assertStringContainsString('data-testid="'.$testid.'-row-null"', $tabel);

        $barisNull = array_values(array_filter(
            kdLaporanRows($tabel),
            fn (string $row) => str_contains($row, $testid.'-row-null'),
        ));

        expect($barisNull)->toHaveCount(1);
        $this->assertStringContainsString('tidak tercatat', $barisNull[0]);
        expect(kdLaporanCell($barisNull[0], 2))->toBe((string) $jumlahNull);
    }
});

it('skenario 21 — silo sumber diperlakukan sebagai TEKS meski nama kolomnya menyesatkan', function () {
    $uuid = '9f1c2a44-5e21-4b6f-8c0d-7a3e51b9d012';
    $record = kdLaporanRecord($this->stationA, $this->day1);

    kdLaporanDispatch($record, $this->day1, ['silo_source_id' => $uuid]);
    kdLaporanDispatch($record, $this->day1, ['silo_source_id' => $uuid]);
    kdLaporanDispatch($record, $this->day1, ['silo_source_id' => 'Silo Timur 2']);

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = strtolower($query->sql);
    });

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA);

    $html = $component->html();
    $rekap = $component->viewData('summary')['by_silo_source'];

    // KEDUA NILAI VERBATIM di payload...
    $this->assertContains($uuid, array_column($rekap, 'value'));
    $this->assertContains('Silo Timur 2', array_column($rekap, 'value'));
    expect(kdLaporanEntry($rekap, $uuid)['dispatch_count'])->toBe(2);

    // ...DAN di layar: string berbentuk uuid DICETAK APA ADANYA, tidak
    // ditukar dengan nama apa pun, tidak dipotong, tidak dibuang.
    $tabel = kdLaporanTable($html, 'by-silo-source-table');

    $this->assertStringContainsString($uuid, $tabel);
    $this->assertStringContainsString('Silo Timur 2', $tabel);

    // ASERSI NEGATIF ATAS QUERY LOG: nol join dan nol kueri ke tabel mana pun
    // bernama silo. Kolomnya string nullable di migrasi 2026_08_31, tidak ada
    // tabel silo di satu migrasi pun, dan form merendernya <input type="text">.
    foreach ($queries as $sql) {
        $this->assertStringNotContainsString('silo', $sql, "kueri menyentuh tabel bernama silo: {$sql}");
    }

    // Dan layar MENYATAKAN kenapa nama kolomnya menyesatkan.
    $this->assertStringContainsString(
        'Nama kolomnya menyesatkan',
        kdLaporanSlice($html, 'by-silo-source-note', '</details>'),
    );
});

it('skenario 22 — nilai kategoris yang berbeda hanya pada huruf besar-kecil atau spasi TETAP dua baris', function () {
    $record = kdLaporanRecord($this->stationA, $this->day1);

    foreach (['PT Pembeli', 'pt pembeli', ' PT Pembeli '] as $pembeli) {
        kdLaporanDispatch($record, $this->day1, ['destination_buyer' => $pembeli]);
    }

    foreach ([null, '', '   ', 'PT Angkut Jaya'] as $transporter) {
        kdLaporanDispatch($record, $this->day1, ['transporter_contractor' => $transporter]);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA);

    $summary = $component->viewData('summary');

    // DUA BARIS, beda huruf TIDAK disatukan.
    $pembeli = $summary['by_destination_buyer'];

    expect(kdLaporanEntry($pembeli, 'PT Pembeli')['dispatch_count'])->toBe(2);
    expect(kdLaporanEntry($pembeli, 'pt pembeli')['dispatch_count'])->toBe(1);
    $this->assertNotContains(' PT Pembeli ', array_column($pembeli, 'value'));

    // by_transporter: TEPAT DUA baris bernilai — null (menyatu setelah trim)
    // dan 'PT Angkut Jaya'. Tiga pengiriman pembeli di atas tidak punya
    // transporter, jadi baris null-nya berisi 3 + 3 = 6.
    $transporter = $summary['by_transporter'];

    expect($transporter)->toHaveCount(2);
    expect(kdLaporanEntry($transporter, 'PT Angkut Jaya')['dispatch_count'])->toBe(1);
    expect(kdLaporanEntry($transporter, null)['dispatch_count'])->toBe(6);
    $this->assertNotContains('', array_column($transporter, 'value'));
    $this->assertNotContains('   ', array_column($transporter, 'value'));

    // KEMUNGKINAN DUA-BARIS-MIRIP DINYATAKAN DI LAYAR supaya pembaca
    // mengenalinya.
    $catatan = kdLaporanSlice($component->html(), 'by-destination-buyer-note', '</details>');

    $this->assertStringContainsString('besar-kecil', $catatan);
    $this->assertStringContainsString('perlu data master', $catatan);
});

it('skenario 23 — rekap status pelepasan gerbang: KODE MENTAH di payload, LABEL di markup, dan blade MENDELEGASIKAN', function () {
    $record = kdLaporanRecord($this->stationA, $this->day1);

    kdLaporanDispatch($record, $this->day1, ['remarks_gate_status' => 'released', 'net_weight_mt' => 5.0]);
    kdLaporanDispatch($record, $this->day1, ['remarks_gate_status' => 'not_released', 'net_weight_mt' => 3.0]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA);

    $summary = $component->viewData('summary');
    $html = $component->html();

    // (a) PAYLOAD BERISI KODE MENTAH.
    $nilai = array_column($summary['by_gate_status'], 'value');

    $this->assertContains('released', $nilai);
    $this->assertContains('not_released', $nilai);

    // Dan TIDAK ADA label di state — menerbitkan label di payload akan membuat
    // screen-165 menerima label yang tidak dapat ia ubah.
    $this->assertNotContains('Released', $nilai);
    $this->assertNotContains('Not Released', $nilai);
    $serialized = json_encode($summary, JSON_UNESCAPED_UNICODE);
    $this->assertStringNotContainsString('"Released"', $serialized);
    $this->assertStringNotContainsString('"Not Released"', $serialized);

    // (b) MARKUP MENAMPILKAN LABEL, disasarkan ke SEL barisnya.
    $tabel = kdLaporanTable($html, 'by-gate-status-table');
    $sel = [];

    foreach (kdLaporanRows($tabel) as $row) {
        if (str_contains($row, 'data-gate-status=')) {
            $sel[] = kdLaporanCell($row, 1);
        }
    }

    $this->assertContains('Released', $sel);
    $this->assertContains('Not Released', $sel);
    // Dan kode mentahnya TIDAK dicetak kepada manusia.
    $this->assertNotContains('released', $sel);
    $this->assertNotContains('not_released', $sel);

    // ASERSI PENDAMPING: BLADE MENDELEGASIKAN, tidak menyalin pemetaannya —
    // keputusan proyek 2026-10-14.
    foreach ([
        'resources/views/livewire/dashboard/laporan-kernel-dispatch.blade.php',
        'resources/views/livewire/data/detail-kernel-dispatch.blade.php',
    ] as $path) {
        $penuh = base_path($path);

        if (! is_file($penuh)) {
            continue;
        }

        $isi = (string) file_get_contents($penuh);

        $this->assertDoesNotMatchRegularExpression("/'released'\s*=>\s*'Released'/", $isi, "{$path} menyalin pemetaan");
        $this->assertDoesNotMatchRegularExpression("/'not_released'\s*=>\s*'Not Released'/", $isi, "{$path} menyalin pemetaan");
    }

    // EKSPOR MEMAKAI option() YANG SAMA: satu kosakata, bukan dua.
    $body = kdLaporanStreamed(kdLaporanExport(
        (string) $this->periodA->id,
        $this->lineA,
        'csv',
    ));

    $this->assertStringContainsString('Released', $body);
    $this->assertStringContainsString('Not Released', $body);
    $this->assertStringNotContainsString('not_released', $body);
});

it('skenario 24 — nilai status di luar kedua pilihan yang dikenal ditampilkan APA ADANYA', function () {
    // Lewat service-ganda in-memory: remarks_gate_status adalah
    // enum(['released','not_released'])->nullable() di migrasi
    // 2026_08_31_000007 — sebuah CHECK CONSTRAINT di SQLite DAN PostgreSQL —
    // sehingga 'HOLD_BY_SECURITY' DITOLAK pada INSERT lewat factory.
    $dispatches = [];

    foreach (['released', 'released', 'not_released', 'HOLD_BY_SECURITY', 'HOLD_BY_SECURITY', null] as $status) {
        $dispatches[] = kdLaporanMemoryDispatch($this->day1, ['gate_status' => $status]);
    }

    LaporanKdInMemoryReportService::$dispatches = collect($dispatches);
    $this->app->bind(KernelDispatchReportService::class, fn () => new LaporanKdInMemoryReportService);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $this->periodA->id);

    $summary = $component->viewData('summary');

    // EMPAT BARIS.
    expect($summary['by_gate_status'])->toHaveCount(4);

    $tidakDikenal = kdLaporanEntry($summary['by_gate_status'], 'HOLD_BY_SECURITY');

    // TIDAK DIBUANG, nilai MENTAH apa adanya.
    $this->assertNotNull($tidakDikenal, 'nilai tak dikenal dibuang dari rekap');
    expect($tidakDikenal['value'])->toBe('HOLD_BY_SECURITY');
    expect($tidakDikenal['dispatch_count'])->toBe(2);

    // TIDAK DILIPAT KE BARIS NULL dan TIDAK DIPAKSA MASUK SALAH SATU PILIHAN.
    expect(kdLaporanEntry($summary['by_gate_status'], null)['dispatch_count'])->toBe(1);
    expect(kdLaporanEntry($summary['by_gate_status'], 'released')['dispatch_count'])->toBe(2);
    expect(kdLaporanEntry($summary['by_gate_status'], 'not_released')['dispatch_count'])->toBe(1);

    // not_released_count TETAP 1.
    expect($summary['not_released_count'])->toBe(1);

    // ASERSI PENGUNCI: penjumlahan rekap === dispatch_count === 6. Itulah
    // yang menangkap implementasi yang 'merapikan' dengan membuang nilai tak
    // dikenal, karena rekapnya langsung tidak lagi berjumlah.
    $this->assertSame(
        6,
        array_sum(array_column($summary['by_gate_status'], 'dispatch_count')),
    );
    expect($summary['dispatches']['dispatch_count'])->toBe(6);

    // DAN TERRENDER dengan nilai mentahnya — Display::option() jatuh ke
    // mentah untuk yang tak dikenal, dan itu yang diinginkan.
    $tabel = kdLaporanTable($component->html(), 'by-gate-status-table');

    $this->assertStringContainsString('data-gate-status="HOLD_BY_SECURITY"', $tabel);

    $selMentah = array_values(array_filter(
        array_map(
            fn (string $row) => str_contains($row, 'HOLD_BY_SECURITY') ? kdLaporanCell($row, 1) : null,
            kdLaporanRows($tabel),
        ),
    ));

    expect($selMentah)->toBe(['HOLD_BY_SECURITY']);
});

it("skenario 25 — 'tidak tercatat' dan 'tidak dilepas' adalah DUA KEADAAN BERBEDA, dan tidak dilepas ditonjolkan", function () {
    $record = kdLaporanRecord($this->stationA, $this->day1);

    foreach (['released', 'released', 'released', 'not_released', 'not_released', null, null, null, null] as $status) {
        kdLaporanDispatch($record, $this->day1, ['remarks_gate_status' => $status]);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA);

    $summary = $component->viewData('summary');
    $html = $component->html();

    // 2 — bukan 6 dan bukan 0.
    expect($summary['not_released_count'])->toBe(2);
    $this->assertNotSame(6, $summary['not_released_count']);

    // DAN PADA SAAT YANG SAMA baris value null ADA dengan dispatch_count 4 —
    // assertNull atas value-nya, bukan pencarian string. Justru pasangan dua
    // asersi itu yang mencegah tuduhan.
    $barisNull = kdLaporanEntry($summary['by_gate_status'], null);

    $this->assertNotNull($barisNull);
    $this->assertNull($barisNull['value']);
    expect($barisNull['dispatch_count'])->toBe(4);

    // not_released_count TERRENDER SEBAGAI ANGKA TERSENDIRI di luar tabel
    // rekapnya, disasarkan ke testid elemennya.
    $kartu = kdLaporanCard($html, 'kpi-not-released');

    $this->assertNotSame('', $kartu);
    $this->assertStringContainsString('data-testid="not-released-count"', $kartu);
    $this->assertStringContainsString('2 <span>dari 9 pengiriman</span>', $kartu);
    $this->assertStringContainsString('tidak tercatat', $kartu);

    // Dan kartu 'status tercatat' membaca baris null dari rekapnya sendiri:
    // 9 − 4 = 5.
    $this->assertStringContainsString(
        '5 <span>pengiriman</span>',
        kdLaporanCard($html, 'kpi-gate-status-recorded'),
    );

    // Baris 'tidak tercatat' ADA di rekap sementara ia TIDAK masuk hitungan
    // tidak-dilepas.
    $tabel = kdLaporanTable($html, 'by-gate-status-table');
    $this->assertStringContainsString('data-testid="by-gate-status-row-null"', $tabel);

    // VARIAN KEDUA — nol 'not_released': angka 0 dan kuncinya TETAP
    // diterbitkan.
    KernelDispatchDetail::query()->where('remarks_gate_status', 'not_released')
        ->update(['remarks_gate_status' => 'released']);

    $kedua = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA);

    $this->assertArrayHasKey('not_released_count', $kedua->viewData('summary'));
    expect($kedua->viewData('summary')['not_released_count'])->toBe(0);
    $this->assertStringContainsString(
        'data-testid="not-released-count"',
        kdLaporanCard($kedua->html(), 'kpi-not-released'),
    );
});

it('skenario 26 — TIGA penghitung kepatuhan terpisah dan segel atas serta bawah TIDAK PERNAH dijumlahkan', function () {
    $record = kdLaporanRecord($this->stationA, $this->day1);

    // Satu pengiriman kehilangan KEDUA segel — itu DUA FAKTA, bukan
    // penghitungan ganda: satu kendaraan kehilangan satu segel != dua
    // kendaraan masing-masing kehilangan satu.
    kdLaporanDispatch($record, $this->day1, ['waybill_number' => 'SJ-1', 'security_seal_no_top' => null, 'security_seal_no_bottom' => null]);
    kdLaporanDispatch($record, $this->day1, ['waybill_number' => 'SJ-2', 'security_seal_no_top' => null, 'security_seal_no_bottom' => 'B2']);
    kdLaporanDispatch($record, $this->day1, ['waybill_number' => null, 'security_seal_no_top' => 'T3', 'security_seal_no_bottom' => 'B3']);
    kdLaporanDispatch($record, $this->day1, ['waybill_number' => '  ', 'security_seal_no_top' => 'T4', 'security_seal_no_bottom' => 'B4']);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA);

    $summary = $component->viewData('summary');
    $html = $component->html();

    expect($summary['compliance']['missing_waybill_count'])->toBe(2);
    expect($summary['compliance']['missing_seal_top_count'])->toBe(2);
    expect($summary['compliance']['missing_seal_bottom_count'])->toBe(1);

    // TIGA ANGKA TERRENDER DI TIGA ELEMEN BERTESTID BERBEDA.
    foreach ([
        'kpi-missing-waybill' => ['missing-waybill-count', '2 <span>dari 4 pengiriman</span>'],
        'kpi-missing-seal-top' => ['missing-seal-top-count', '2 <span>dari 4 pengiriman</span>'],
        'kpi-missing-seal-bottom' => ['missing-seal-bottom-count', '1 <span>dari 4 pengiriman</span>'],
    ] as $kartuTestid => [$angkaTestid, $isi]) {
        $kartu = kdLaporanCard($html, $kartuTestid);

        $this->assertNotSame('', $kartu, "kartu {$kartuTestid} tidak dirender");
        $this->assertStringContainsString('data-testid="'.$angkaTestid.'"', $kartu);
        $this->assertStringContainsString($isi, $kartu);
    }

    // ASERSI NEGATIF ATAS NAMA KUNCI (array_key_exists, bukan pencarian
    // teks): payload TIDAK punya nama gabungan apa pun.
    foreach ([
        'missing_seal_count', 'missing_seals_count', 'seal_count',
        'total_missing_seal', 'seal_missing_total',
    ] as $gabungan) {
        $this->assertArrayNotHasKey($gabungan, $summary['compliance']);
        $this->assertNotContains($gabungan, kdLaporanAllKeys($summary));
    }

    // Dan tidak ada ANGKA GABUNGAN segel (2 + 1 = 3) yang terrender sebagai
    // satu penghitung.
    $this->assertStringNotContainsString('3 <span>dari 4 pengiriman</span>', $html);

    // PROSA DI LAYAR MENYATAKAN ketiganya jejak kepatuhan, BUKAN ukuran.
    $this->assertStringContainsString(
        e(KernelDispatchReportService::NOTE_COMPLIANCE),
        kdLaporanSlice($html, 'compliance-note', '</div>'),
    );

    // VARIAN LENGKAP: ketiganya 0 dengan ketiga elemen TETAP terrender.
    KernelDispatchDetail::query()->update([
        'waybill_number' => 'SJ-X',
        'security_seal_no_top' => 'T-X',
        'security_seal_no_bottom' => 'B-X',
    ]);

    $lengkap = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA);

    $htmlLengkap = $lengkap->html();

    foreach (['missing_waybill_count', 'missing_seal_top_count', 'missing_seal_bottom_count'] as $kunci) {
        expect($lengkap->viewData('summary')['compliance'][$kunci])->toBe(0);
    }

    foreach (['missing-waybill-count', 'missing-seal-top-count', 'missing-seal-bottom-count'] as $testid) {
        $this->assertStringContainsString('data-testid="'.$testid.'"', $htmlLengkap);
        $this->assertStringContainsString('0 <span>dari 4 pengiriman</span>', kdLaporanSlice($htmlLengkap, $testid, '</p>'));
    }
});

it('skenario 27 — rekap diurutkan dari total netto terbesar, dengan pemutus ketiga BENAR-BENAR dieksekusi', function () {
    $record = kdLaporanRecord($this->stationA, $this->day1);

    // SERI SEMPURNA pada kedua kunci pertama — tanpa itu pemutus ketiga
    // (value ASC) tidak pernah dieksekusi dan asersi urutannya VAKUM.
    // Disemai dalam urutan Charlie, Alpha, Bravo supaya urutan masuk != keluar.
    foreach (['Charlie', 'Alpha', 'Bravo'] as $nama) {
        kdLaporanDispatch($record, $this->day1, ['transporter_contractor' => $nama, 'net_weight_mt' => 20.0]);
        kdLaporanDispatch($record, $this->day1, ['transporter_contractor' => $nama, 'net_weight_mt' => 20.0]);
    }

    kdLaporanDispatch($record, $this->day1, ['transporter_contractor' => 'Delta', 'net_weight_mt' => 100.0]);

    // by_silo_source: baris null bertonase BESAR, 'SILO-C' tanpa netto.
    foreach (range(1, 5) as $i) {
        kdLaporanDispatch($record, $this->day1, ['silo_source_id' => null, 'net_weight_mt' => 100.0]);
    }

    kdLaporanDispatch($record, $this->day1, ['silo_source_id' => 'SILO-A', 'net_weight_mt' => 40.0]);
    kdLaporanDispatch($record, $this->day1, ['silo_source_id' => 'SILO-A', 'net_weight_mt' => 40.0]);

    foreach (range(1, 4) as $i) {
        kdLaporanDispatch($record, $this->day1, ['silo_source_id' => 'SILO-B', 'net_weight_mt' => 20.0]);
    }

    foreach (range(1, 2) as $i) {
        kdLaporanDispatch($record, $this->day1, ['silo_source_id' => 'SILO-C', 'net_weight_mt' => null]);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA);

    $summary = $component->viewData('summary');

    // URUTAN LENGKAP, bukan assertContains yang lolos pada urutan apa pun.
    $transporter = array_values(array_filter(
        array_column($summary['by_transporter'], 'value'),
        fn ($value) => $value !== null,
    ));

    expect($transporter)->toBe(['Delta', 'Alpha', 'Bravo', 'Charlie']);

    foreach (['Alpha', 'Bravo', 'Charlie'] as $nama) {
        expect(kdLaporanEntry($summary['by_transporter'], $nama)['net_weight_total'])->toBe(40.0);
        expect(kdLaporanEntry($summary['by_transporter'], $nama)['dispatch_count'])->toBe(2);
    }

    // by_silo_source: baris null di INDEKS 0 karena TONASENYA.
    $silo = $summary['by_silo_source'];

    expect(kdLaporanNullIndex($silo))->toBe(0);
    // ASERSI NEGATIF: ia BUKAN entri terakhir.
    $this->assertNotSame(count($silo) - 1, kdLaporanNullIndex($silo));

    // 'SILO-C' diurutkan sebagai 0.0 sehingga TERAKHIR, SEMENTARA
    // net_weight_total-nya di payload TETAP null.
    expect(array_column($silo, 'value')[count($silo) - 1])->toBe('SILO-C');
    $this->assertNull(kdLaporanEntry($silo, 'SILO-C')['net_weight_total']);
    $this->assertNull(kdLaporanEntry($silo, 'SILO-C')['net_weight_avg']);

    // Dan urutan ITU yang dirender — dibaca dari baris tabelnya.
    $tabel = kdLaporanTable($component->html(), 'by-silo-source-table');
    $urutMarkup = [];

    foreach (kdLaporanRows($tabel) as $row) {
        if (str_contains($row, 'by-silo-source-row-null')) {
            $urutMarkup[] = 'NULL';
        } elseif (str_contains($row, 'by-silo-source-row"')) {
            $urutMarkup[] = kdLaporanCell($row, 1);
        }
    }

    expect($urutMarkup)->toBe(['NULL', 'SILO-B', 'SILO-A', 'SILO-C']);
});

// =====================================================================
// KETIADAAN YANG DINYATAKAN (skenario 28-31)
// =====================================================================

it('skenario 28 — tanpa sebaran per jam dan tanpa waktu putar kendaraan, dengan sebabnya DINYATAKAN', function () {
    $record = kdLaporanRecord($this->stationA, $this->day1);

    kdLaporanDispatch($record, $this->day1, [
        'net_weight_mt' => 4.0,
        'kernel_moisture_percent' => 7.0,
        'shift' => 'Shift 1',
    ]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA);

    $summary = $component->viewData('summary');
    $keys = kdLaporanAllKeys($summary);

    // PENELUSUR REKURSIF ATAS SELURUH NAMA KUNCI.
    foreach ([
        'hourly', 'hourly_distribution', 'by_hour', 'per_hour', 'hour', 'hours',
        'busiest_hour', 'empty_hour_count', 'time_slot', 'time_slots',
        'turnaround', 'turnaround_minutes', 'time_in', 'time_out',
    ] as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
    }

    // WAJIB ATAS NAMA KUNCI DAN BUKAN ATAS TEKS — baris di bawah MEMBUKTIKAN
    // jebakannya nyata, bukan teoretis: prosanya memuat kata 'jam' DAN
    // menyebut 'CPO Dispatch'.
    $this->assertStringContainsString('jam', $summary['_note_no_hourly']);
    $this->assertStringContainsString('CPO Dispatch', $summary['_note_no_hourly']);
    $this->assertStringContainsString('screen-166', $summary['_note_no_hourly']);

    $html = $component->html();

    // NOL BLOK PER JAM DI MARKUP — diasersikan atas TESTID yang dipakai
    // laporan yang MEMANG punya blok itu, supaya daftarnya bukan karangan.
    foreach ([
        'hourly-block', 'hourly-table', 'hourly-chart', 'hourly-distribution',
        'busiest-hour', 'empty-hour-count', 'by-shift-table', 'kpi-shift',
        'turnaround-card', 'kpi-turnaround',
    ] as $testid) {
        $this->assertStringNotContainsString('data-testid="'.$testid.'"', $html, "blok {$testid} dirender");
    }

    // _note_no_hourly DIRENDER UTUH.
    $this->assertStringContainsString(
        e(KernelDispatchReportService::NOTE_NO_HOURLY),
        kdLaporanSlice($html, 'no-hourly-note', '</div>'),
    );

    // ASERSI NEGATIF TAMBAHAN: tidak ada jam yang ditebak dari created_at.
    //
    // DIASERSIKAN ATAS BENTUK KOLOM, BUKAN ATAS KATANYA. Service ini MENYEBUT
    // `created_at` di tiga tempat — dua docblock dan SATU KALIMAT DI DALAM
    // konstanta NOTE_NO_HOURLY — justru untuk menyatakan bahwa menurunkan jam
    // dari kolom itu berarti menebak dari waktu INPUT. Memindai kata-nya
    // karena itu memerahkan implementasi yang benar, dan membuang komentar
    // saja tidak cukup karena kalimat ketiga ada di dalam sebuah string.
    // Yang dicari adalah kolomnya DIPAKAI: literal ber-kutip, pemanggilan
    // Eloquent, atau fungsi jam SQL.
    $service = (string) file_get_contents(base_path('app/Services/KernelDispatchReportService.php'));

    $this->assertStringContainsString(
        'created_at',
        $service,
        'prosa yang menjelaskan sebab created_at tidak dipakai hilang — asersi di bawah jadi vakum',
    );

    foreach (["'created_at'", '"created_at"', '->created_at', 'created_at)', 'created_at,'] as $pemakaian) {
        $this->assertStringNotContainsString(
            $pemakaian,
            $service,
            "service memakai created_at ({$pemakaian})",
        );
    }

    foreach (['strftime', 'HOUR(', 'hour(', 'EXTRACT(', 'extract('] as $fungsiJam) {
        $this->assertStringNotContainsString($fungsiJam, $service, "service memakai fungsi jam {$fungsiJam}");
    }
    // Satu-satunya sumbu waktu yang tersedia adalah tanggal, yaitu rekap
    // harian.
    $this->assertStringContainsString('data-testid="daily-table"', $html);
});

it('skenario 29 — tanpa penandaan nilai mutu di luar batas; nilai ekstrem dan NEGATIF terbit apa adanya', function () {
    $record = kdLaporanRecord($this->stationA, $this->day1);

    kdLaporanDispatch($record, $this->day1, ['kernel_moisture_percent' => 0.1, 'ffa_percent' => -1.2]);
    kdLaporanDispatch($record, $this->day1, ['kernel_moisture_percent' => 99.0]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA);

    $summary = $component->viewData('summary');
    $keys = kdLaporanAllKeys($summary);

    // NOL KUNCI PENANDAAN — penelusur rekursif atas nama kunci.
    foreach ([
        'flag', 'flags', 'out_of_range', 'is_out_of_range', 'threshold',
        'thresholds', 'target', 'targets', 'exceeds', 'violation',
        'violations', 'severity', 'warning', 'warnings', 'breach',
        'critical_limit', 'safe_range', 'anomaly', 'anomalies',
    ] as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
    }

    // NILAI EKSTREM DAN NEGATIF TERBIT APA ADANYA, tidak disaring dan tidak
    // dijadikan 0.
    $this->assertEqualsWithDelta(0.1, $summary['quality']['kernel_moisture_percent']['min'], 0.0005);
    $this->assertEqualsWithDelta(99.0, $summary['quality']['kernel_moisture_percent']['max'], 0.0005);
    $this->assertEqualsWithDelta(-1.2, $summary['quality']['ffa_percent']['min'], 0.0005);
    $this->assertLessThan(0, $summary['quality']['ffa_percent']['min']);

    $html = $component->html();

    // NOL KELAS/BADGE PERINGATAN di markup — diasersikan atas ketidakhadiran
    // testid badge-nya, bukan atas frasa: kalimat yang menyatakan ketiadaan
    // penandaan itu sendiri memuat frasa 'di luar batas'.
    foreach ([
        'quality-flag', 'quality-badge', 'threshold-badge', 'out-of-range-badge',
        'quality-warning', 'threshold-note-card',
    ] as $testid) {
        $this->assertStringNotContainsString('data-testid="'.$testid.'"', $html, "badge {$testid} dirender");
    }

    // Dan nilai negatifnya memang DICETAK di kartunya.
    $this->assertStringContainsString('-1,200', kdLaporanQualityCard($html, 'ffa_percent'));

    // _note_no_quality_thresholds DIRENDER UTUH — laporan mutu TANPA
    // penandaan terlihat seperti laporan yang lupa membandingkan, jadi
    // sebabnya harus tertulis.
    $this->assertStringContainsString(
        e(KernelDispatchReportService::NOTE_NO_QUALITY_THRESHOLDS),
        kdLaporanSlice($html, 'no-quality-thresholds-note', '</div>'),
    );
    $this->assertStringContainsString('batas', $summary['_note_no_quality_thresholds']);
});

it('skenario 30 — temuan adalah SATU-SATUNYA kolom catatan kejadian', function () {
    $recordAwal = kdLaporanRecord($this->stationA, $this->day1);
    $record = kdLaporanRecord($this->stationA, $this->day2);

    kdLaporanDispatch($recordAwal, $this->day1, ['findings' => 'Ceceran di gerbang']);
    kdLaporanDispatch($record, $this->day2, ['findings' => 'Segel bawah terlepas']);
    kdLaporanDispatch($record, $this->day2, ['findings' => '   ']);
    kdLaporanDispatch($record, $this->day2, ['findings' => null]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA);

    $textEntries = $component->viewData('summary')['text_entries'];

    // SATU DAFTAR — struktur text_entries dua-daftar dari Solid Waste
    // Disposal TIDAK boleh disalin ke sini.
    expect(array_keys($textEntries))->toBe(['findings']);
    $this->assertArrayNotHasKey('remarks', $textEntries);

    expect($textEntries['findings'])->toHaveCount(2);
    expect(array_column($textEntries['findings'], 'value'))
        ->toBe(['Ceceran di gerbang', 'Segel bawah terlepas']);
    expect(array_column($textEntries['findings'], 'date'))->toBe([$this->day1, $this->day2]);

    $html = $component->html();

    // LAYAR MERENDER SATU BAGIAN CATATAN, bukan dua — asersi negatif atas
    // ketidakhadiran testid bagian kedua.
    $this->assertStringContainsString('data-testid="findings-card"', $html);
    $this->assertStringContainsString('Rekap Temuan', $html);

    foreach (['remarks-card', 'remarks-entry', 'remarks-empty', 'remarks-note'] as $testid) {
        $this->assertStringNotContainsString('data-testid="'.$testid.'"', $html, "bagian {$testid} dirender");
    }

    $this->assertStringNotContainsString('Rekap Catatan', $html);

    $kartu = kdLaporanSlice($html, 'findings-card', '</section>');

    $this->assertStringContainsString('Ceceran di gerbang', $kartu);
    $this->assertStringContainsString('Segel bawah terlepas', $kartu);
    expect(substr_count($kartu, 'data-testid="findings-entry"'))->toBe(2);

    // VARIAN KEDUA — nol findings: [] dengan kunci TETAP ADA, dan layar
    // mencetaknya sebagai PERNYATAAN 'tidak ada', bukan menghilangkan
    // bagiannya.
    KernelDispatchDetail::query()->update(['findings' => null]);

    $kosong = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA);

    $this->assertArrayHasKey('findings', $kosong->viewData('summary')['text_entries']);
    expect($kosong->viewData('summary')['text_entries']['findings'])->toBe([]);

    $htmlKosong = $kosong->html();

    $this->assertStringContainsString('data-testid="findings-card"', $htmlKosong);
    $this->assertStringContainsString('data-testid="findings-empty"', $htmlKosong);
});

it('skenario 31 — giliran kerja TIDAK di layar tetapi ADA di ekspor bersama lima kolom lain', function () {
    $record = kdLaporanRecord($this->stationA, $this->day1);

    kdLaporanDispatch($record, $this->day1, [
        'shift' => 'Shift 1',
        'weighbridge_ticket_no' => 'WT-001',
        'waybill_number' => 'SJ-001',
        'vehicle_plate_no' => 'B 1234 XX',
        'driver_name' => 'Pak Sopir',
        'weighbridge_operator_id' => 'OP-007',
        'net_weight_mt' => 20.0,
    ]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $this->periodA->id);

    // (a) PENELUSUR REKURSIF atas nama kunci payload TIDAK menemukan 'shift'.
    $summary = $component->viewData('summary');
    $keys = kdLaporanAllKeys($summary);

    foreach (['shift', 'shifts', 'by_shift', 'per_shift', 'shift_distribution', 'shift_id'] as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
    }

    $html = $component->html();

    foreach (['shift-select', 'shift-column', 'by-shift-table', 'kpi-shift'] as $testid) {
        $this->assertStringNotContainsString('data-testid="'.$testid.'"', $html, "{$testid} dirender");
    }

    // _note_shift_not_published DIRENDER UTUH — dan prosanya MEMUAT kata
    // 'shift', yang justru sebab asersi di atas berjalan atas nama kunci dan
    // testid.
    $this->assertStringContainsString(
        e(KernelDispatchReportService::NOTE_SHIFT_NOT_PUBLISHED),
        kdLaporanSlice($html, 'shift-not-published-note', '</div>'),
    );

    // (b) HEADER EKSPOR MEMUAT kolom shift DAN kelima kolom yang tidak muncul
    //     di satu angka pun di layar — diasersikan EKSPLISIT satu per satu
    //     beserta nilainya, karena kelimanya paling mudah tertinggal justru
    //     karena layar tidak memakainya.
    $body = kdLaporanStreamed(kdLaporanExport((string) $this->periodA->id, $this->lineA, 'csv'));
    $lines = array_values(array_filter(explode("\n", trim($body)), fn ($l) => trim($l) !== ''));
    $header = str_getcsv(trim($lines[0], "\r"), ',', '"', '\\');
    $baris = str_getcsv(trim($lines[1], "\r"), ',', '"', '\\');

    // $this->assertContains() — bukan expect()->toContain(), yang membaca
    // argumen kedua sebagai needle kedua.
    foreach ([
        'Shift' => 'Shift 1',
        'Weighbridge Ticket No' => 'WT-001',
        'Waybill Number' => 'SJ-001',
        'Vehicle Plate No' => 'B 1234 XX',
        'Driver Name' => 'Pak Sopir',
        'Weighbridge Operator ID' => 'OP-007',
    ] as $label => $nilai) {
        $this->assertContains($label, $header, "header ekspor kehilangan kolom {$label}");

        $kolom = array_search($label, $header, true);

        $this->assertSame($nilai, $baris[$kolom], "nilai kolom {$label} tidak terbawa ke ekspor");
    }

    // Dan ekspor dari TOMBOL 'excel' diterjemahkan menjadi 'xlsx' oleh
    // komponen sebelum sampai ke service, yang daftar formatnya tertutup.
    $excel = kdLaporanExport((string) $this->periodA->id, $this->lineA, 'excel');

    expect($excel->headers->get('Content-Type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

// =====================================================================
// STATUS PERIODE, REKAP HARIAN, KELENGKAPAN, PINTU MASUK (skenario 33-35)
//
// Skenario 32 MURNI HTTP — default `format` ditetapkan CONTROLLER, bukan
// service, dan separuh perilakunya tidak dapat dilihat dari komponen sama
// sekali. Ia hidup di tests/Feature/Api/LaporanKernelDispatchTest.php.
// =====================================================================

it('skenario 33 — status periode TIDAK disaring dan pengiriman draft tetap terhitung', function () {
    $draftPeriod = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelDispatch->value)
        ->range(now()->subDays(80)->toDateString(), now()->subDays(60)->toDateString())
        ->draft()
        ->named('Periode Draft')
        ->create();

    $closedPeriod = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelDispatch->value)
        ->range(now()->subDays(120)->toDateString(), now()->subDays(100)->toDateString())
        ->closed()
        ->named('Periode Closed')
        ->create();

    $mutu = [
        'kernel_moisture_percent' => 7.0,
        'dirt_impurities_percent' => 0.02,
        'ffa_percent' => 3.0,
        'broken_kernel_percent' => 5.0,
    ];

    $draftRecord = kdLaporanRecord($this->stationA, $this->day1, ['status' => RecordStatus::DraftOngoing]);
    $finalRecord = kdLaporanRecord($this->stationA, $this->day2, ['status' => RecordStatus::Saved]);

    foreach (range(1, 3) as $i) {
        kdLaporanDispatch($draftRecord, $this->day1, array_merge(['net_weight_mt' => 2.0], $mutu));
    }

    foreach (range(1, 2) as $i) {
        kdLaporanDispatch($finalRecord, $this->day2, array_merge(['net_weight_mt' => 2.0], $mutu));
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $this->periodA->id);

    // KETIGA PERIODE muncul di pemilih — status TIDAK menyaring. Diasersikan
    // dengan $this->assertContains() atas kumpulan id.
    $ids = array_column($component->viewData('periods'), 'id');

    $this->assertContains((string) $this->periodA->id, $ids);
    $this->assertContains((string) $draftPeriod->id, $ids);
    $this->assertContains((string) $closedPeriod->id, $ids);

    $statusById = array_column($component->viewData('periods'), 'status', 'id');

    expect($statusById[(string) $draftPeriod->id])->toBe(PeriodStatus::Draft->value);
    expect($statusById[(string) $closedPeriod->id])->toBe(PeriodStatus::Closed->value);

    $dengan = $component->viewData('summary');

    expect($dengan['dispatches']['dispatch_count'])->toBe(5);
    expect($dengan['draft_dispatch_count'])->toBe(3);

    foreach (KernelDispatchReportService::QUALITY_MEASURES as $measure) {
        expect($dengan['quality'][$measure]['measured_count'])->toBe(5);
    }

    // draft_dispatch_count TERRENDER SEBAGAI ANGKA TERSENDIRI: ia ADALAH
    // pernyataan bahwa draft ikut, bukan penyaring.
    $kartu = kdLaporanCard($component->html(), 'kpi-draft-dispatch-count');

    $this->assertNotSame('', $kartu);
    $this->assertStringContainsString('data-testid="draft-dispatch-count"', $kartu);
    $this->assertStringContainsString('3 <span>dari 5 pengiriman</span>', $kartu);

    // DIBUKTIKAN DENGAN MEMBANDINGKAN terhadap varian yang draft-nya dihapus:
    // keempat measured_count turun dari 5 ke 2.
    KernelDispatchDetail::query()->where('kernel_dispatch_record_id', $draftRecord->id)->delete();
    $draftRecord->delete();

    $tanpa = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $this->periodA->id)
        ->viewData('summary');

    expect($tanpa['dispatches']['dispatch_count'])->toBe(2);
    expect($tanpa['draft_dispatch_count'])->toBe(0);

    foreach (KernelDispatchReportService::QUALITY_MEASURES as $measure) {
        expect($tanpa['quality'][$measure]['measured_count'])->toBe(2);
    }
});

it('skenario 34 — rekap harian per hari yang ADA pengirimannya, dan kelengkapan atas hari yang sudah berjalan', function () {
    // VARIAN A — periode 31 hari dengan pengiriman pada 9 tanggal EKSPLISIT.
    $offsets = [31, 39, 20, 35, 12, 28, 17, 24, 14];
    $tanggal = array_map(fn (int $o) => now()->subDays($o)->toDateString(), $offsets);

    foreach ($tanggal as $index => $hari) {
        $record = kdLaporanRecord($this->stationA, $hari);

        kdLaporanDispatch($record, $hari, ['net_weight_mt' => 3.0]);

        if ($index % 3 === 0) {
            kdLaporanDispatch($record, $hari, ['net_weight_mt' => null]);
        }
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $this->periodA->id);

    $summary = $component->viewData('summary');

    // 9 — bukan 31 (hari kalender) dan bukan jumlah pengiriman.
    expect($summary['daily'])->toHaveCount(9);
    expect($summary['completeness']['days_in_period'])->toBe(31);

    $urut = $tanggal;
    sort($urut);
    expect(array_column($summary['daily'], 'date'))->toBe($urut);

    // daily_total DIJUMLAHKAN dari daily[], bukan disalin dari blok dispatches.
    $this->assertSame(
        array_sum(array_column($summary['daily'], 'dispatch_count')),
        $summary['daily_total']['dispatch_count'],
    );
    $this->assertSame(
        array_sum(array_column($summary['daily'], 'net_weight_dispatch_count')),
        $summary['daily_total']['net_weight_dispatch_count'],
    );

    $html = $component->html();

    // BARIS KAKI TERRENDER, dan setiap tanggal punya barisnya sendiri.
    $this->assertStringContainsString('data-testid="daily-row-total"', $html);

    foreach ($urut as $hari) {
        $this->assertStringContainsString('data-testid="daily-row-'.$hari.'"', $html, "baris harian {$hari} tidak dirender");
    }

    expect(substr_count($html, 'data-testid="daily-row-'))->toBe(10); // 9 baris + 1 kaki

    // VARIAN B — periode BELUM MULAI: days_counted 0, percent NULL → '—'
    // dengan kalimat 'periode belum dimulai'.
    $belumMulai = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelDispatch->value)
        ->range(now()->addDays(10)->toDateString(), now()->addDays(40)->toDateString())
        ->open()
        ->named('Periode Masa Depan')
        ->create();

    $b = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $belumMulai->id);

    $completenessB = $b->viewData('summary')['completeness'];

    expect($completenessB['days_counted'])->toBe(0);
    $this->assertNull($completenessB['percent']);
    expect($completenessB['days_with_dispatch'])->toBe(0);
    // days_in_period tetap PANJANG PENUH periode.
    expect($completenessB['days_in_period'])->toBe(31);

    $htmlB = $b->html();

    $this->assertStringContainsString(
        '—',
        kdLaporanSlice($htmlB, 'days-with-dispatch-percent', '</span>'),
    );
    $this->assertStringContainsString('data-testid="period-not-started-note"', $htmlB);
    $this->assertStringNotContainsString('data-testid="period-running-note"', $htmlB);

    // VARIAN C — periode BERJALAN: days_counted = start..hari ini inklusif
    // sementara days_in_period tetap panjang penuh periode. Periode berjalan
    // TIDAK DIHUKUM atas hari yang belum datang.
    $berjalan = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelDispatch->value)
        ->range(now()->subDays(9)->toDateString(), now()->addDays(20)->toDateString())
        ->open()
        ->named('Periode Berjalan')
        ->create();

    foreach ([8, 6, 3] as $offset) {
        $hari = now()->subDays($offset)->toDateString();
        kdLaporanDispatch(kdLaporanRecord($this->stationA, $hari), $hari, ['net_weight_mt' => 1.0]);
    }

    $c = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $berjalan->id);

    $completenessC = $c->viewData('summary')['completeness'];

    expect($completenessC['days_counted'])->toBe(10);
    expect($completenessC['days_in_period'])->toBe(30);
    expect($completenessC['days_with_dispatch'])->toBe(3);
    $this->assertEqualsWithDelta(30.0, $completenessC['percent'], 0.05);

    $htmlC = $c->html();

    $this->assertStringContainsString('data-testid="period-running-note"', $htmlC);
    // Keterangan 'dihitung sampai ...' menyebut HARI INI, BUKAN end_date
    // periode — end_date yang belum tiba akan terbaca sebagai hari yang sudah
    // ikut dihitung. countedUntil diserahkan komponen, bukan dibaca dari jam
    // di dalam blade.
    expect($c->viewData('countedUntil'))->toBe(now()->toDateString());

    // Penyebut '10' disasarkan ke SELNYA sendiri — angka sependek itu cocok di
    // mana saja pada halaman ini.
    $this->assertStringContainsString('10', kdLaporanSlice($htmlC, 'days-counted', '</span>'));
    $this->assertStringContainsString('3', kdLaporanSlice($htmlC, 'days-with-dispatch', '</strong>'));

    // Dan keterangan periode-berjalan menyebut TANGGAL HARI INI, bukan
    // end_date yang belum tiba.
    $keterangan = kdLaporanSlice($htmlC, 'period-running-note', '</p>');

    $this->assertStringContainsString('periode masih berjalan', $keterangan);
    $this->assertStringNotContainsString($berjalan->end_date->toDateString(), $keterangan);
});

it('skenario 35 — PINTU MASUK: tile Kernel Dispatch pada Laporan Stasiun AKTIF dan REPORT_ROUTES tetap urut sort_order', function () {
    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanStasiun::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // TILE AKTIF dan TERTAUT ke route name 'reports.kernel-dispatch' — tanpa
    // satu baris REPORT_ROUTES itu, laporannya ada, seluruh test-nya sendiri
    // lolos, dan tile-nya tetap kelabu.
    // TAG PEMBUKANYA diambil utuh — `class` ditulis SEBELUM `data-testid` di
    // markup, jadi memotong ke DEPAN dari testid tidak akan pernah memuat
    // kelasnya, dan memotong sampai '</span>' justru melewati batas tile ini
    // dan masuk ke tile berikutnya (yang kelabu). Yang ditanyakan adalah
    // kelas pada ELEMEN INI.
    $posisi = strpos($html, 'data-testid="station-tile-kernel-dispatch"');

    $this->assertNotFalse($posisi, 'tile kernel-dispatch tidak dirender sama sekali');

    $pembuka = strrpos(substr($html, 0, $posisi), '<');
    $tutup = strpos($html, '>', $posisi);
    $tag = substr($html, $pembuka, $tutup - $pembuka + 1);

    // AKTIF, bukan kelabu — dan sebuah <a>, bukan <span>: tile yang belum
    // berlaporan dirender sebagai <span class="station-tile disabled">.
    $this->assertStringStartsWith('<a', $tag, "tile dirender sebagai elemen non-tautan: {$tag}");
    $this->assertStringContainsString('station-tile active', $tag);
    $this->assertStringNotContainsString('station-tile disabled', $tag);
    $this->assertStringContainsString(route('reports.kernel-dispatch'), $tag);

    // KUNCINYA BERTANDA HUBUNG.
    $this->assertArrayHasKey('kernel-dispatch', StationReportService::REPORT_ROUTES);
    expect(StationReportService::REPORT_ROUTES['kernel-dispatch'])->toBe('reports.kernel-dispatch');
    $this->assertArrayNotHasKey('kernel_dispatch', StationReportService::REPORT_ROUTES);

    // URUTAN LENGKAP terhadap sort_order station_types — diverifikasi dari
    // TABELNYA, BUKAN dari urutan deklarasi enum.
    $berlaporan = StationType::query()
        ->orderBy('sort_order')
        ->pluck('sort_order', 'code')
        ->filter(fn ($sortOrder, $code) => array_key_exists($code, StationReportService::REPORT_ROUTES));

    expect($berlaporan->keys()->all())->toBe(array_keys(StationReportService::REPORT_ROUTES));

    $keys = array_keys(StationReportService::REPORT_ROUTES);

    // kernel-dispatch TIDAK lagi terakhir sejak screen-166 menambahkan
    // cpo-dispatch (170), dan sejak screen-168 menambahkan
    // process-quality-control (180) ia menjadi KETIGA DARI BELAKANG — dengan
    // tetangga di kedua sisi.
    expect(array_search('kernel-dispatch', $keys, true))->toBe(count($keys) - 3);
    expect($keys[count($keys) - 4])->toBe('solid-waste-disposal');
    expect($keys[count($keys) - 2])->toBe('cpo-dispatch');
    expect($keys[count($keys) - 1])->toBe('process-quality-control');
    expect($berlaporan['solid-waste-disposal'])->toBe(150);
    expect($berlaporan['kernel-dispatch'])->toBe(160);
    expect($berlaporan['cpo-dispatch'])->toBe(170);
    expect($berlaporan['process-quality-control'])->toBe(180);

    // ASERSI PENGUNCI RIAK, DIBALIK PADA screen-168. Baris ini dulu berbunyi
    // "REPORT_ROUTES belum punya process-quality-control" dan ia MEMANG memerah
    // di screen-168 persis sebagaimana dimaksud. Sekarang ia menyatakan keadaan
    // AKHIR, yang tidak akan pernah basi lagi: seluruh jenis stasiun nyata
    // berlaporan, jadi sebuah contoh 'laporan belum dibangun' WAJIB memakai kode
    // FIKTIF yang disuntikkan — dan kode fiktif itu tidak akan pernah mendapat
    // entri.
    $this->assertArrayHasKey('process-quality-control', StationReportService::REPORT_ROUTES);
    expect(StationType::query()->where('code', 'process-quality-control')->value('sort_order'))->toBe(180);

    $tanpaLaporan = StationType::query()
        ->where('code', '<>', StationReportService::EXCLUDED_STATION_TYPE)
        ->orderBy('sort_order')
        ->pluck('code')
        ->reject(fn (string $code) => array_key_exists($code, StationReportService::REPORT_ROUTES));

    expect($tanpaLaporan->all())->toBe([]);
    $this->assertArrayNotHasKey('zz-stasiun-fiktif', StationReportService::REPORT_ROUTES);
});

// =====================================================================
// unit_test_case 62 — DUA TEST TERPISAH, BUKAN SATU TEST DUA CABANG
// =====================================================================

it('case 62a — undated: kuncinya SELALU ada di payload, tetapi kotak keteranganNYA TIDAK dirender ketika 0', function () {
    // Keadaan SATU-SATUNYA yang mungkin di DB: `event_date` NOT NULL di
    // migrasi 2026_08_31_000007.
    $record = kdLaporanRecord($this->stationA, $this->day1);

    foreach (range(1, 3) as $i) {
        kdLaporanDispatch($record, $this->day1, ['net_weight_mt' => 2.0]);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA);

    $summary = $component->viewData('summary');

    // KUNCINYA SELALU DITERBITKAN.
    $this->assertArrayHasKey('undated_dispatch_count', $summary);
    expect($summary['undated_dispatch_count'])->toBe(0);

    $html = $component->html();

    // BLOK PERINGATAN ABSEN — diasersikan atas KETIDAKHADIRAN TESTID BLOKNYA,
    // bukan atas teks: prosa lain di halaman ini menyebut tanggal berkali-kali.
    // Mencetak 'nol pengiriman tanpa tanggal' pada setiap laporan berarti
    // mengarang kekhawatiran atas keadaan yang DILARANG SKEMA — pelajaran yang
    // sudah dibayar sekali di screen-162.
    $this->assertStringNotContainsString('data-testid="undated-note"', $html);
    $this->assertStringNotContainsString('data-testid="undated-dispatch-count"', $html);

    // Dan penjumlahan harian memang SAMA dengan jumlah pengiriman, jadi tidak
    // ada selisih yang perlu dijelaskan.
    $this->assertSame(
        $summary['dispatches']['dispatch_count'],
        array_sum(array_column($summary['daily'], 'dispatch_count')),
    );
});

it('case 62b — undated: ketika angkanya > 0 kotaknya DIRENDER dan menamai selisihnya', function () {
    // Lewat service-ganda in-memory: `event_date` NOT NULL, jadi cabang ini
    // tidak dapat dicapai lewat factory. Ia tetap diuji karena ia PENJAGA
    // INVARIAN — penjumlahan daily[] yang lebih kecil daripada dispatch_count
    // harus punya satu sebab yang diterbitkan.
    $dispatches = [];

    foreach ([$this->day1, $this->day1, $this->day2, $this->day2, $this->day3] as $tanggal) {
        $dispatches[] = kdLaporanMemoryDispatch($tanggal, ['net_weight' => 2.0]);
    }

    foreach (range(1, 2) as $i) {
        $dispatches[] = kdLaporanMemoryDispatch(null, ['net_weight' => 2.0]);
    }

    LaporanKdInMemoryReportService::$dispatches = collect($dispatches);
    $this->app->bind(KernelDispatchReportService::class, fn () => new LaporanKdInMemoryReportService);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $this->periodA->id);

    $summary = $component->viewData('summary');

    expect($summary['undated_dispatch_count'])->toBe(2);
    expect($summary['dispatches']['dispatch_count'])->toBe(7);
    expect(array_sum(array_column($summary['daily'], 'dispatch_count')))->toBe(5);

    $html = $component->html();

    // BLOK ITU HADIR dan MENAMPILKAN 2 — disasarkan ke elemen angkanya.
    $this->assertStringContainsString('data-testid="undated-note"', $html);

    $kotak = kdLaporanSlice($html, 'undated-note', '</div>');

    $this->assertStringContainsString('data-testid="undated-dispatch-count"', $kotak);
    $this->assertStringContainsString('>2</b>', $kotak);
    // Dan ia MENAMAI SELISIHNYA: 7 pengiriman periode, 5 di rekap harian.
    $this->assertStringContainsString('7 pengiriman', $kotak);
    $this->assertStringContainsString('5 pengiriman', $kotak);
});

// =====================================================================
// INVARIAN LAYAR HANYA-BACA
// =====================================================================

it('layar hanya membaca: nol kontrol tulis dan nol metode publik yang menulis', function () {
    kdLaporanDispatch(kdLaporanRecord($this->stationA, $this->day1), $this->day1, ['net_weight_mt' => 2.0]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelDispatch::class)
        ->set('productionLineId', $this->lineA);

    $html = $component->html();

    // Nol <form>, nol tombol simpan/hapus/kirim.
    $this->assertStringNotContainsString('<form', $html);

    foreach (['wire:submit', 'wire:click="save', 'wire:click="delete', 'wire:click="store'] as $tulis) {
        $this->assertStringNotContainsString($tulis, $html, "kontrol tulis {$tulis} dirender");
    }

    // Dan metode publik komponen hanya yang membaca/memilih/mengekspor.
    $publik = array_values(array_filter(
        array_map(
            fn (ReflectionMethod $m) => $m->getName(),
            (new ReflectionClass(LaporanKernelDispatch::class))->getMethods(ReflectionMethod::IS_PUBLIC),
        ),
        fn (string $name) => (new ReflectionMethod(LaporanKernelDispatch::class, $name))->getDeclaringClass()->getName()
            === LaporanKernelDispatch::class,
    ));

    sort($publik);

    expect($publik)->toBe([
        'exportCsv', 'mount', 'render', 'updatedBusinessUnitId',
        'updatedPeriodId', 'updatingBusinessUnitId',
    ]);
});
