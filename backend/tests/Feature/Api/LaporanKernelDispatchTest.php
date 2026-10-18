<?php

/**
 * LaporanKernelDispatchTest (API) — screen-164--laporan-kernel-dispatch-web
 * (+ kembaran mobile screen-165), keempat endpoint
 * /api/kernel-dispatch-reports/*.
 *
 * BERKAS INI WAJIB ADA, bukan opsional: KEENAM BELAS laporan saudaranya punya
 * padanannya, dan screen-162 hampir melewatkannya. Yang dibuktikan di sini
 * BUKAN aturan agregasinya (itu di
 * tests/Unit/Services/KernelDispatchReportServiceTest.php) dan bukan
 * tampilannya (itu di tests/Feature/Livewire/LaporanKernelDispatchTest.php),
 * melainkan KONTRAK HTTP-nya: kode status, BENTUK jawaban galatnya, siapa yang
 * diterima rute mana, apa yang benar-benar dibawa payload-nya, dan bagaimana
 * bentuk berkas ekspornya.
 *
 * ────────────────────────────────────────────────────────────────────────
 * TIGA JAWABAN YANG BERBEDA UNTUK OPERATOR, DAN KETIGANYA DISENGAJA
 * ────────────────────────────────────────────────────────────────────────
 *   - /periods, /summary, /export: 200 SEJAK COMMIT PERTAMA — kembaran mobile
 *     screen-165 dibangun dalam seri yang SAMA, jadi pemanggilnya sudah ada.
 *   - /business-units/options: 403, diangkat DARI DALAM service justru karena
 *     middleware prefix ini menerima EMPAT peran demi ketiga rute data,
 *     sehingga penjaga yang ditempatkan di middleware tidak akan menutup rute
 *     itu sama sekali.
 *   - rute WEB /reports/kernel-dispatch: 403 — dibuktikan di berkas Livewire
 *     (skenario 2) dan TIDAK diulang di sini. Yang diasersikan di sini adalah
 *     bentuk STRUKTURAL dari perbedaan itu: DAFTAR MIDDLEWARE kedua kelompok
 *     rute (uji 'daftar middleware' paling bawah), karena perbedaan itulah
 *     yang menjaga admisi API tidak pernah merembes menjadi admisi layar web.
 *
 * ────────────────────────────────────────────────────────────────────────
 * TRIM + DEFAULT `format` HANYA TERLIHAT DARI HTTP
 * ────────────────────────────────────────────────────────────────────────
 * KernelDispatchReportController::queryString() memulangkan null untuk nilai
 * yang kosong SETELAH trim, dan export() menetapkan default 'csv' ATAS NILAI
 * YANG SUDAH DI-TRIM itu. Di tingkat SERVICE daftar tertutup {csv,xlsx}
 * menolak '', '  ' dan ' csv ' dengan 422 — jadi separuh perilaku skenario 32
 * / case 56 TIDAK DAPAT dilihat dari uji unit sama sekali. Uji 'format — TRIM
 * + DEFAULT' di bawah adalah satu-satunya tempat ia dibuktikan.
 *
 * ────────────────────────────────────────────────────────────────────────
 * KETIADAAN DIASERSIKAN ATAS NAMA KUNCI, TIDAK PERNAH ATAS PROSA
 * ────────────────────────────────────────────────────────────────────────
 * Payload /summary memuat TIGA KALIMAT MANUSIA — `_note_no_hourly` (memuat
 * kata 'jam' DAN menyebut 'CPO Dispatch'), `_note_no_quality_thresholds`
 * (memuat 'batas') dan `_note_shift_not_published` (memuat 'shift') — dan
 * ketiganya ada justru untuk MENYATAKAN ketiadaan hal-hal itu. Memindai teks
 * respons karena itu menjawab pertanyaan yang salah: ia bisa memerahkan
 * implementasi yang benar, atau lulus semata karena implementasinya memilih
 * kata lain. Setiap asersi KETIADAAN di berkas ini berjalan atas NAMA KUNCI
 * lewat kdApiAllKeys().
 *
 * ────────────────────────────────────────────────────────────────────────
 * FIXTURE: TANGGAL SELALU DIPAKU, DAN SELALU RELATIF
 * ────────────────────────────────────────────────────────────────────────
 * KernelDispatchRecordFactory men-default `date` ke
 * dateTimeBetween('-30 days','now') dan KernelDispatchDetailFactory
 * men-default `event_date` dengan cara yang sama; StationFactory bersarang pun
 * membuat ProductionLine sendiri. SETIAP record dan SETIAP pengiriman di bawah
 * memaku tanggalnya lewat helper.
 *
 * `event_date` NOT NULL DI SKEMA dan `remarks_gate_status` ber-CHECK
 * CONSTRAINT enum(['released','not_released']) — keduanya migrasi
 * 2026_08_31_000007 — jadi `undated_dispatch_count` SELALU 0 dan nilai gate
 * status di luar kedua pilihan TIDAK DAPAT disemai di sini. Cabang keduanya
 * diuji lewat koleksi di memori pada uji unit (case 33, 34, 48). Yang
 * diasersikan di sini: kunci undated SELALU diterbitkan, bernilai 0.
 */

use App\Enums\RecordStatus;
use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\KernelDispatchDetail;
use App\Models\KernelDispatchRecord;
use App\Models\Period;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use App\Services\KernelDispatchReportService;
use App\Support\Display;
use App\Support\SheetWriter;
use Illuminate\Support\Str;

/**
 * Batas ekspor yang diturunkan, supaya 422 'batas baris terlampaui' dapat
 * dibuktikan DI TINGKAT HTTP tanpa menyemai 50.001 baris.
 *
 * Hanya mungkin karena service membaca batasnya lewat `static::`, bukan
 * `self::`. Diikat ke container di uji yang memakainya, sehingga controller
 * yang sama persis me-resolve subclass ini — bukan service dipanggil langsung.
 */
class KernelDispatchReportApiTinyExportService extends KernelDispatchReportService
{
    public const EXPORT_ROW_LIMIT = 5;
}

function kdApiUrl(string $path, array $query = []): string
{
    return '/api/kernel-dispatch-reports/'.$path.($query === [] ? '' : '?'.http_build_query($query));
}

/** Satu record induk; TANGGALNYA argumen posisional. */
function kdApiRecord(Station $station, string $date, array $attributes = []): KernelDispatchRecord
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

/** Satu baris detail = SATU PENGIRIMAN, seluruh kolom berarti di-default NULL. */
function kdApiDispatch(
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

/** Isi sebuah StreamedResponse, ditangkap. ALIRKAN SEKALI SAJA per response. */
function kdApiStreamed($response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

/** Body yang sudah ditangkap, dipecah menjadi baris non-kosong. */
function kdApiCsvLines(string $body): array
{
    return array_values(array_filter(explode("\n", trim($body)), fn ($line) => trim($line) !== ''));
}

/** Satu baris CSV, diurai. */
function kdApiCsvRow(string $line): array
{
    return str_getcsv(trim($line, "\r"), ',', '"', '\\');
}

/**
 * Seluruh nama KUNCI di mana pun dalam struktur, serapa pun dalamnya.
 *
 * DIPAKAI ALIH-ALIH MEMINDAI TEKS — lihat docblock berkas.
 *
 * @return list<string>
 */
function kdApiAllKeys(mixed $value): array
{
    if (! is_array($value)) {
        return [];
    }

    $keys = [];

    foreach ($value as $key => $child) {
        if (is_string($key)) {
            $keys[] = $key;
        }

        $keys = array_merge($keys, kdApiAllKeys($child));
    }

    return array_values(array_unique($keys));
}

/**
 * Nama kunci yang TIDAK BOLEH ADA di mana pun pada payload /summary.
 *
 * Empat rumpun, masing-masing dengan sebabnya sendiri: sumbu waktu per jam dan
 * waktu putar kendaraan (tabel detailnya hanya punya event_date — tidak ada
 * time_in/time_out, berbeda dari CPO Dispatch), dimensi giliran kerja (kolom
 * shift teks bebas), penandaan di luar batas (tidak ada master ambang mutu),
 * dan pengelompokan per unit (pembaca membaca satu angka per line).
 *
 * @return list<string>
 */
function kdApiForbiddenKeys(): array
{
    return [
        // Sumbu waktu per jam + waktu putar kendaraan.
        'hourly', 'hourly_distribution', 'by_hour', 'per_hour', 'hour', 'hours',
        'busiest_hour', 'empty_hour_count', 'time_slot', 'time_slots', 'slots',
        'turnaround', 'turnaround_minutes', 'turnaround_avg', 'avg_turnaround',
        'time_in', 'time_out',
        // Giliran kerja.
        'shift', 'shifts', 'by_shift', 'per_shift', 'shift_distribution', 'shift_id',
        // Penandaan di luar batas.
        'out_of_range', 'is_out_of_range', 'threshold', 'thresholds', 'target',
        'targets', 'violation', 'violations', 'severity', 'flag', 'flags',
        'warning', 'warnings', 'breach', 'exceeds', 'critical_limit',
        'safe_range', 'anomaly', 'anomalies',
        // Pengelompokan per unit stasiun.
        'by_unit', 'units', 'by_station', 'stations',
        // Penghitung segel GABUNGAN — dua fakta, bukan satu angka.
        'missing_seal_count', 'missing_seals_count', 'total_missing_seal',
        // Penyebut mutu BERSAMA — keempat ukuran punya penyebutnya sendiri.
        'quality_measured_count', 'measured_dispatch_count', 'quality_dispatch_count',
    ];
}

/** Satu baris rekap untuk satu nilai (null = kelompok 'tidak tercatat'). */
function kdApiEntry(array $rows, ?string $value): ?array
{
    foreach ($rows as $row) {
        if ($row['value'] === $value) {
            return $row;
        }
    }

    return null;
}

/** Posisi baris 'tidak tercatat' di dalam satu rekap, atau -1. */
function kdApiNullIndex(array $rows): int
{
    foreach ($rows as $index => $row) {
        if ($row['value'] === null) {
            return $index;
        }
    }

    return -1;
}

/** Kunci tingkat atas payload /summary, dalam urutan terbitnya. */
function kdApiSummaryKeys(): array
{
    return [
        'business_unit', 'production_line', 'period', 'dispatches', 'quality',
        'by_transporter', 'by_silo_source', 'by_destination_buyer',
        'by_gate_status', 'not_released_count', 'compliance',
        'draft_dispatch_count', 'undated_dispatch_count', 'daily',
        'daily_total', 'completeness', 'text_entries', '_note_no_hourly',
        '_note_no_quality_thresholds', '_note_shift_not_published',
    ];
}

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

    // PERIODE YANG SUDAH SELESAI, 31 hari: days_counted === days_in_period ===
    // 31 selamanya.
    $this->periodStart = now()->subDays(40)->toDateString();
    $this->periodEnd = now()->subDays(10)->toDateString();

    $this->day1 = now()->subDays(39)->toDateString();
    $this->day2 = now()->subDays(38)->toDateString();
    $this->day3 = now()->subDays(37)->toDateString();
    $this->dayBeforePeriod = now()->subDays(41)->toDateString();
    $this->dayAfterPeriod = now()->subDays(9)->toDateString();

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelDispatch->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode KD Alpha')
        ->create();
});

// =====================================================================
// JALUR SUKSES DAN PERAN (skenario 1, 2, 3, 4, 5, 6)
// =====================================================================

it('skenario 1 — Supervisor dan Mill Management: periods + summary + export mill sendiri', function () {
    $record = kdApiRecord($this->stationA, $this->day2);

    // Fixture SENGAJA TIDAK RATA: netto terisi pada 2 dari 3, keempat kolom
    // mutu BERPOLA BERBEDA sehingga keempat penyebut berbeda, jejak kepatuhan
    // hilang pada kolom yang BERBEDA pula, dan event_date-nya dua hari.
    kdApiDispatch($record, $this->day2, [
        'net_weight_mt' => 2.0,
        'kernel_moisture_percent' => 6.0,
        'dirt_impurities_percent' => 0.01,
        'ffa_percent' => 3.0,
        'broken_kernel_percent' => 5.0,
        'transporter_contractor' => 'PT Angkut Jaya',
        'silo_source_id' => 'SILO-A',
        'destination_buyer' => 'PT Pembeli',
        'remarks_gate_status' => 'released',
        'waybill_number' => 'SJ-0001',
        'security_seal_no_top' => 'SST-0001',
        'security_seal_no_bottom' => 'SSB-0001',
    ]);
    kdApiDispatch($record, $this->day2, [
        'net_weight_mt' => 4.0,
        'kernel_moisture_percent' => 8.0,
        'dirt_impurities_percent' => 0.03,
        'ffa_percent' => 5.0,
        'broken_kernel_percent' => null,
        'transporter_contractor' => 'PT Angkut Jaya',
        'silo_source_id' => 'SILO-B',
        'destination_buyer' => 'PT Pembeli',
        'remarks_gate_status' => 'not_released',
        'waybill_number' => null,
        'security_seal_no_top' => 'SST-0002',
        'security_seal_no_bottom' => null,
    ]);
    kdApiDispatch($record, $this->day3, [
        'net_weight_mt' => null,
        'kernel_moisture_percent' => 7.0,
        'dirt_impurities_percent' => null,
        'ffa_percent' => null,
        'broken_kernel_percent' => null,
        'transporter_contractor' => null,
        'silo_source_id' => 'SILO-A',
        'destination_buyer' => null,
        'remarks_gate_status' => null,
        'waybill_number' => 'SJ-0003',
        'security_seal_no_top' => 'SST-0003',
        'security_seal_no_bottom' => 'SSB-0003',
    ]);

    foreach ([$this->supervisor, $this->millManagement] as $user) {
        $periods = $this->actingAs($user, 'web')->getJson(kdApiUrl('periods'));
        $periods->assertOk();

        expect(collect($periods->json('data'))->pluck('id'))->toContain((string) $this->periodA->id);
        // BERTANDA HUBUNG: kunci snake_case akan ter-compile, tampak benar, dan
        // tidak pernah cocok dengan satu baris period_stations pun.
        expect(collect($periods->json('data'))->pluck('station_type'))->toContain('kernel-dispatch');

        $summary = $this->actingAs($user, 'web')->getJson(kdApiUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]));
        $summary->assertOk();

        // BENTUK PAYLOAD-NYA, diasersikan sebagai daftar kunci yang LENGKAP:
        // sebuah blok yang hilang tidak boleh lolos hanya karena tak satu
        // asersi pun menyebutnya.
        expect(array_keys($summary->json()))->toBe(kdApiSummaryKeys());

        expect($summary->json('business_unit.name'))->toBe('Mill Alpha');
        expect($summary->json('production_line.id'))->toBe($this->lineA);
        expect($summary->json('period.id'))->toBe((string) $this->periodA->id);
        expect($summary->json('period.status'))->toBe('open');

        // SATUAN HITUNGNYA BARIS DETAIL, BUKAN RECORD: satu record induk
        // membawa tiga pengiriman, dan 1 di sini adalah cacat yang dicari.
        expect($summary->json('dispatches.dispatch_count'))->toBe(3);
        expect($summary->json('dispatches.net_weight_dispatch_count'))->toBe(2);
        expect($summary->json('dispatches.missing_net_weight_dispatch_count'))->toBe(1);
        expect($summary->json('dispatches.net_weight_total'))->toEqual(6.0);
        expect($summary->json('dispatches.net_weight_avg'))->toEqual(3.0);

        // ANGKA YANG WAJIB DAPAT DIREKONSILIASI PEMBACA.
        expect($summary->json('dispatches.net_weight_dispatch_count') + $summary->json('dispatches.missing_net_weight_dispatch_count'))
            ->toBe($summary->json('dispatches.dispatch_count'));

        // EMPAT PENYEBUT MUTU YANG BERBEDA — 3 / 2 / 2 / 1, dan dispatch_count
        // 3. Satu penyebut tunggal akan membuat keempatnya sama.
        expect($summary->json('quality.kernel_moisture_percent.measured_count'))->toBe(3);
        expect($summary->json('quality.dirt_impurities_percent.measured_count'))->toBe(2);
        expect($summary->json('quality.ffa_percent.measured_count'))->toBe(2);
        expect($summary->json('quality.broken_kernel_percent.measured_count'))->toBe(1);

        foreach (KernelDispatchReportService::QUALITY_MEASURES as $measure) {
            expect(array_keys($summary->json('quality.'.$measure)))
                ->toBe(['min', 'avg', 'max', 'measured_count']);
        }

        $this->assertArrayHasKey('_note', $summary->json('quality'));

        // KEEMPAT rekap: penjumlahannya SAMA DENGAN dispatch_count.
        foreach (['by_transporter', 'by_silo_source', 'by_destination_buyer', 'by_gate_status'] as $recap) {
            expect(collect($summary->json($recap))->sum('dispatch_count'))
                ->toBe($summary->json('dispatches.dispatch_count'));
        }

        // KODE MENTAH di payload, bukan label.
        expect(collect($summary->json('by_gate_status'))->pluck('value')->filter()->all())
            ->toEqualCanonicalizing(['released', 'not_released']);

        // DITONJOLKAN TERSENDIRI, dan hanya 'not_released' PERSIS.
        expect($summary->json('not_released_count'))->toBe(1);

        // TIGA penghitung kepatuhan TERPISAH, tiga angka berbeda.
        expect($summary->json('compliance.missing_waybill_count'))->toBe(1);
        expect($summary->json('compliance.missing_seal_top_count'))->toBe(0);
        expect($summary->json('compliance.missing_seal_bottom_count'))->toBe(1);

        expect($summary->json('draft_dispatch_count'))->toBe(0);
        expect($summary->json('undated_dispatch_count'))->toBe(0);

        // SATU ENTRI PER HARI BERPENGIRIMAN — bukan 31 baris nol.
        expect(collect($summary->json('daily'))->pluck('date')->all())->toBe([$this->day2, $this->day3]);
        expect(collect($summary->json('daily'))->sum('dispatch_count'))
            ->toBe($summary->json('dispatches.dispatch_count') - $summary->json('undated_dispatch_count'));

        expect($summary->json('completeness.days_in_period'))->toBe(31);
        expect($summary->json('completeness.days_counted'))->toBe(31);
        expect($summary->json('completeness.days_with_dispatch'))->toBe(2);

        // text_entries SATU DAFTAR — stasiun ini tidak punya kolom catatan
        // kedua.
        expect(array_keys($summary->json('text_entries')))->toBe(['findings']);

        // KETIGA PROSA KETIADAAN DITERBITKAN UTUH.
        expect($summary->json('_note_no_hourly'))->toBe(KernelDispatchReportService::NOTE_NO_HOURLY);
        expect($summary->json('_note_no_quality_thresholds'))->toBe(KernelDispatchReportService::NOTE_NO_QUALITY_THRESHOLDS);
        expect($summary->json('_note_shift_not_published'))->toBe(KernelDispatchReportService::NOTE_SHIFT_NOT_PUBLISHED);

        // DAN NOL kunci terlarang di seluruh payload — atas NAMA KUNCI.
        $keys = kdApiAllKeys($summary->json());

        foreach (kdApiForbiddenKeys() as $forbidden) {
            $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
        }
    }

    $export = $this->actingAs($this->supervisor, 'web')->get(kdApiUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));

    $export->assertOk();
    $this->assertStringContainsString('text/csv', (string) $export->headers->get('content-type'));
});

it('skenario 2 — Operator: 200 pada ketiga rute DATA, 403 pada /business-units/options', function () {
    $record = kdApiRecord($this->stationA, $this->day2);
    kdApiDispatch($record, $this->day2, ['net_weight_mt' => 3.0, 'kernel_moisture_percent' => 7.0]);

    // Langkah 2 — /periods: 200.
    $periods = $this->actingAs($this->operator, 'web')->getJson(kdApiUrl('periods'));
    $periods->assertOk();

    $periodId = collect($periods->json('data'))->pluck('id')->first();

    expect($periodId)->toBe((string) $this->periodA->id);

    // Langkah 3 — /summary: 200, dengan payload yang IDENTIK dengan yang
    // dilihat Supervisor. Operator berada di cabang TERIKAT MILL
    // resolveBusinessUnit(), bukan di cabang Admin.
    $asOperator = $this->actingAs($this->operator, 'web')->getJson(kdApiUrl('summary', [
        'period_id' => $periodId,
        'production_line_id' => $this->lineA,
    ]));
    $asOperator->assertOk();

    $asSupervisor = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('summary', [
        'period_id' => $periodId,
        'production_line_id' => $this->lineA,
    ]));
    $asSupervisor->assertOk();

    expect($asOperator->json())->toEqual($asSupervisor->json());
    expect($asOperator->json('business_unit.id'))->toBe((string) $this->businessUnitA->id);

    // Langkah 4 — /export: 200 juga.
    $this->actingAs($this->operator, 'web')
        ->get(kdApiUrl('export', [
            'period_id' => $periodId,
            'production_line_id' => $this->lineA,
            'format' => 'csv',
        ]))
        ->assertOk();

    // Langkah 5 — /business-units/options: 403, diangkat DARI DALAM service
    // justru karena middleware prefix ini menerima KEEMPAT peran. BENTUK
    // jawabannya ikut diasersikan: dua kunci, bukan tiga.
    $forbidden = $this->actingAs($this->operator, 'web')->getJson(kdApiUrl('business-units/options'));

    $forbidden->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
    expect(array_keys($forbidden->json()))->toBe(['message', 'code']);
});

it('tamu — 401 UNAUTHENTICATED pada keempat rute, dengan BENTUK yang sama', function () {
    // BERKAS/UJI TERPISAH DENGAN SENGAJA: $this->actingAs() bertahan sepanjang
    // satu test, jadi memeriksa 'tanpa sesi' di dalam test yang sudah masuk
    // sebagai Operator justru akan mengukur 403-nya Operator dan lulus sebagai
    // 'ditolak' tanpa membuktikan apa pun tentang 401.
    foreach ([
        kdApiUrl('business-units/options'),
        kdApiUrl('periods'),
        kdApiUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]),
        kdApiUrl('export', [
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
    $record = kdApiRecord($this->stationA, $this->day2);
    kdApiDispatch($record, $this->day2, ['net_weight_mt' => 7.0]);

    // DUA mill, KEDUANYA punya periode kernel-dispatch: implementasi yang
    // memilih 'mill pertama' akan terlihat.
    Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::KernelDispatch->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode KD Beta')
        ->create();

    // Langkah 1 — pemilih mill: ADMIN SAJA.
    $options = $this->actingAs($this->admin, 'web')->getJson(kdApiUrl('business-units/options'));

    $options->assertOk();
    expect(collect($options->json('data'))->pluck('name'))->toContain('Mill Alpha', 'Mill Beta');
    expect(array_keys($options->json('data')[0]))->toBe(['id', 'name']);

    // Langkah 2 — /periods TANPA business_unit_id: 422 VALIDATION_ERROR, dan
    // NOL periode terbit.
    $tanpaMill = $this->actingAs($this->admin, 'web')->getJson(kdApiUrl('periods'));

    $tanpaMill->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
    $tanpaMill->assertJsonPath('errors.business_unit_id.0', 'Pilih mill terlebih dahulu untuk menampilkan laporan.');
    $this->assertArrayNotHasKey('data', $tanpaMill->json());

    // Langkah 3 — dengan mill: 200.
    $dengan = $this->actingAs($this->admin, 'web')->getJson(kdApiUrl('periods', [
        'business_unit_id' => (string) $this->businessUnitA->id,
    ]));

    $dengan->assertOk();
    expect($dengan->json('data'))->toHaveCount(1);
    expect($dengan->json('data.0.id'))->toBe((string) $this->periodA->id);

    // Langkah 4 — /summary: 200 dan business_unit.id = mill YANG DIPILIH.
    $summary = $this->actingAs($this->admin, 'web')->getJson(kdApiUrl('summary', [
        'business_unit_id' => (string) $this->businessUnitA->id,
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    expect($summary->json('business_unit.id'))->toBe((string) $this->businessUnitA->id);
    expect($summary->json('dispatches.dispatch_count'))->toBe(1);
});

it('skenario 4 — Admin mengganti mill: pilihan LINE yang basi ditolak 403, sementara periode basi TIDAK ditolak karena Admin boleh membaca periode mana pun', function () {
    $periodeB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::KernelDispatch->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode KD Beta')
        ->create();

    kdApiDispatch(kdApiRecord($this->stationA, $this->day2), $this->day2, ['net_weight_mt' => 7.0]);

    // LANGKAH 1 — keadaan awal: mill-A + periode mill-A + line mill-A → 200.
    $this->actingAs($this->admin, 'web')
        ->getJson(kdApiUrl('summary', [
            'business_unit_id' => (string) $this->businessUnitA->id,
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]))
        ->assertOk();

    // LANGKAH 2 — Admin mengganti mill TANPA mengganti periode dan line →
    // 403. YANG MENOLAK adalah LINE-nya: resolveProductionLine() memeriksa
    // line terhadap mill yang BERLAKU, dan line mill-A bukan milik mill-B.
    $this->actingAs($this->admin, 'web')
        ->getJson(kdApiUrl('summary', [
            'business_unit_id' => (string) $this->businessUnitB->id,
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]))
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');

    // MEKANISMENYA DIPISAH, bukan diasumsikan — dan hasilnya BUKAN apa yang
    // tampak masuk akal: dengan line yang SAH bagi mill-B, periode mill-A yang
    // basi TETAP 200, karena authorizePeriodModel() meloloskan Admin untuk
    // mill MANA PUN (docblock authorizePeriod(): "Admin lolos untuk mill mana
    // pun"). Jadi yang menjaga Admin dari membaca kombinasi basi adalah
    // PENJAGA LINE, bukan penjaga periode — dan UI-nya (yang mengosongkan
    // keduanya saat mill berganti, lihat berkas Livewire skenario 4) adalah
    // separuh lain dari jaminan itu. Mengasersikan 403 di sini akan SALAH, dan
    // mengasersikan 200 tanpa kalimat ini akan terbaca seperti kebocoran.
    $this->actingAs($this->admin, 'web')
        ->getJson(kdApiUrl('summary', [
            'business_unit_id' => (string) $this->businessUnitB->id,
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineB,
        ]))
        ->assertOk();

    // Dan peran TERIKAT MILL tidak punya celah itu sama sekali: periode mill
    // lain adalah 403 baginya, karena business_unit_id kiriman tidak pernah
    // mengubah mill yang berlaku.
    $this->actingAs($this->supervisor, 'web')
        ->getJson(kdApiUrl('summary', [
            'business_unit_id' => (string) $this->businessUnitB->id,
            'period_id' => (string) $periodeB->id,
            'production_line_id' => $this->lineA,
        ]))
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');

    // LANGKAH 3 — /periods sesudah mengganti mill: 200, dan HANYA periode
    // mill-B.
    $periods = $this->actingAs($this->admin, 'web')->getJson(kdApiUrl('periods', [
        'business_unit_id' => (string) $this->businessUnitB->id,
    ]));

    $periods->assertOk();
    expect(array_column($periods->json('data'), 'id'))->toBe([(string) $periodeB->id]);
    $this->assertNotContains((string) $this->periodA->id, array_column($periods->json('data'), 'id'));
});

it('skenario 5 — business_unit_id kiriman DIABAIKAN bagi peran terikat mill: 200 berisi mill akun, SENGAJA bukan 403', function () {
    $periodeB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::KernelDispatch->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('P-B')
        ->create();

    $this->periodA->update(['name' => 'P-A']);

    kdApiDispatch(kdApiRecord($this->stationA, $this->day2), $this->day2, ['net_weight_mt' => 5.0]);

    $periods = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('periods', [
        'business_unit_id' => (string) $this->businessUnitB->id,
    ]));

    $periods->assertOk();
    expect($periods->json('data'))->toHaveCount(1);
    expect($periods->json('data.0.id'))->toBe((string) $this->periodA->id);
    expect($periods->json('data.0.name'))->toBe('P-A');

    // ASERSI NEGATIF EKSPLISIT atas id DAN atas nama. Menolak dengan 403 akan
    // membocorkan keberadaan mill-B kepada orang yang tidak berhak
    // mengetahuinya.
    $this->assertNotContains((string) $periodeB->id, array_column($periods->json('data'), 'id'));
    $this->assertNotContains('P-B', array_column($periods->json('data'), 'name'));

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('summary', [
        'business_unit_id' => (string) $this->businessUnitB->id,
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    expect($summary->json('business_unit.id'))->toBe((string) $this->businessUnitA->id);
    expect($summary->json('business_unit.name'))->toBe('Mill Alpha');

    // KONTRASNYA, DALAM UJI YANG SAMA: production_line_id mill lain TETAP 403
    // — ia parameter yang DIPILIH SADAR, bukan konteks yang diturunkan dari
    // akun.
    $this->actingAs($this->supervisor, 'web')
        ->getJson(kdApiUrl('summary', [
            'business_unit_id' => (string) $this->businessUnitB->id,
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineB,
        ]))
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');
});

it('skenario 6 — akun terikat mill tanpa business_unit_id: 422, dan daftar mill TIDAK ditawarkan', function () {
    $orphan = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    // 422 VALIDATION_ERROR — master yang rusak, BUKAN izin yang kurang.
    foreach ([
        kdApiUrl('periods'),
        kdApiUrl('summary', ['period_id' => (string) $this->periodA->id, 'production_line_id' => $this->lineA]),
    ] as $url) {
        $response = $this->actingAs($orphan, 'web')->getJson($url);

        $response->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
        $response->assertJsonPath('errors.business_unit_id.0', 'Akun Anda belum terhubung ke mill. Hubungi Admin.');
        // ASERSI NEGATIF: bukan 403.
        $this->assertNotSame(403, $response->status());
    }

    // GAGAL TERTUTUP: daftar seluruh mill TIDAK ditawarkan sebagai gantinya —
    // 403, bukan daftar tersaring.
    $this->actingAs($orphan, 'web')
        ->getJson(kdApiUrl('business-units/options'))
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');
});

// =====================================================================
// PARAMETER WAJIB DAN CAKUPAN (skenario 7-11)
// =====================================================================

it("skenario 7 — Production Line belum dipilih: 422 pada summary DAN export, nol angka, tanpa 'line pertama'", function () {
    // DUA LINE yang MASING-MASING berpengiriman: implementasi yang diam-diam
    // memilih 'line pertama' atau menjumlahkan semua line akan mengembalikan
    // 200 berisi angka.
    $lineKedua = Station::factory()->forBusinessUnit($this->businessUnitA)->kernelDispatch()->create();

    kdApiDispatch(kdApiRecord($this->stationA, $this->day2), $this->day2, ['net_weight_mt' => 10.0]);
    kdApiDispatch(kdApiRecord($lineKedua, $this->day2), $this->day2, ['net_weight_mt' => 100.0]);

    foreach (['summary', 'export'] as $path) {
        $response = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl($path, [
            'period_id' => (string) $this->periodA->id,
        ]));

        $response->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
        $response->assertJsonPath(
            'errors.production_line_id.0',
            'Production Line wajib dipilih untuk menampilkan laporan.',
        );
        // NOL ANGKA: tidak ada blok laporan yang terbit sebagian.
        $this->assertArrayNotHasKey('dispatches', $response->json());
        $this->assertArrayNotHasKey('quality', $response->json());
    }

    // production_line_id KOSONG-SETELAH-TRIM diperlakukan sama dengan absen —
    // dan itu HANYA terlihat dari HTTP, karena queryString() yang
    // memulangkan null.
    foreach (['', '  '] as $kosong) {
        $this->actingAs($this->supervisor, 'web')
            ->getJson(kdApiUrl('summary', [
                'period_id' => (string) $this->periodA->id,
                'production_line_id' => $kosong,
            ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.production_line_id.0', 'Production Line wajib dipilih untuk menampilkan laporan.');
    }
});

it('period_id belum dipilih: 422 pada summary DAN export, bukan 404 untuk string kosong', function () {
    kdApiDispatch(kdApiRecord($this->stationA, $this->day2), $this->day2);

    foreach (['summary', 'export'] as $path) {
        foreach ([[], ['period_id' => ''], ['period_id' => '   ']] as $query) {
            $response = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl(
                $path,
                array_merge(['production_line_id' => $this->lineA], $query),
            ));

            $response->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
            $response->assertJsonPath('errors.period_id.0', 'Periode Pelaporan wajib dipilih.');
            // ASERSI NEGATIF: bukan 404, dan bukan laporan kosong diam-diam.
            $this->assertNotSame(404, $response->status());
        }
    }
});

it('skenario 9 — mill belum punya periode yang mencakup Kernel Dispatch: data [] dengan 200', function () {
    $this->periodA->stations()->delete();
    $this->periodA->delete();

    $lain = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::Weighbridge->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Weighbridge')
        ->create();

    $response = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('periods'));

    // Array KOSONG dengan 200 — bukan 404 dan bukan exception.
    $response->assertOk();
    expect($response->json('data'))->toBe([]);
    $this->assertNotContains((string) $lain->id, array_column($response->json('data'), 'id'));
});

it('skenario 11 — periode tanpa data: KERANGKA PENUH dengan nol dan null, bukan 404', function () {
    $response = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $response->assertOk();

    expect(array_keys($response->json()))->toBe(kdApiSummaryKeys());
    expect($response->json('dispatches.dispatch_count'))->toBe(0);
    // NULL, BUKAN 0.
    $this->assertNull($response->json('dispatches.net_weight_total'));
    $this->assertNull($response->json('dispatches.net_weight_avg'));

    foreach (KernelDispatchReportService::QUALITY_MEASURES as $measure) {
        expect($response->json('quality.'.$measure.'.measured_count'))->toBe(0);
        $this->assertNull($response->json('quality.'.$measure.'.min'));
        $this->assertNull($response->json('quality.'.$measure.'.avg'));
        $this->assertNull($response->json('quality.'.$measure.'.max'));
    }

    foreach (['by_transporter', 'by_silo_source', 'by_destination_buyer', 'by_gate_status', 'daily'] as $kosong) {
        expect($response->json($kosong))->toBe([]);
    }

    // KETIGA PENGHITUNG 0 dengan KETIGA KUNCINYA TETAP ADA.
    foreach (['missing_waybill_count', 'missing_seal_top_count', 'missing_seal_bottom_count'] as $kunci) {
        $this->assertArrayHasKey($kunci, $response->json('compliance'));
        expect($response->json('compliance.'.$kunci))->toBe(0);
    }

    expect($response->json('not_released_count'))->toBe(0);
    expect($response->json('draft_dispatch_count'))->toBe(0);
    expect($response->json('undated_dispatch_count'))->toBe(0);
    expect($response->json('text_entries.findings'))->toBe([]);
});

it('skenario 12 / 13 — penyebut berat DITERBITKAN, dan rata-rata memakai penyebut itu bukan dispatch_count', function () {
    $record = kdApiRecord($this->stationA, $this->day2);

    foreach ([10.0, 20.0, 30.0, 40.0, null, null, null] as $netto) {
        kdApiDispatch($record, $this->day2, [
            'net_weight_mt' => $netto,
            // Mutu terisi pada SEMUANYA: blok mutu dan blok berat independen.
            'kernel_moisture_percent' => 7.0,
            'dirt_impurities_percent' => 0.02,
            'ffa_percent' => 3.0,
            'broken_kernel_percent' => 5.0,
        ]);
    }

    $response = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $response->assertOk();

    expect($response->json('dispatches.dispatch_count'))->toBe(7);
    expect($response->json('dispatches.net_weight_total'))->toEqual(100.0);
    expect($response->json('dispatches.net_weight_dispatch_count'))->toBe(4);
    // 25, BUKAN 100/7.
    expect($response->json('dispatches.net_weight_avg'))->toEqual(25.0);
    expect($response->json('dispatches.missing_net_weight_dispatch_count'))->toBe(3);

    // KEEMPAT ukuran mutu TETAP berangka dengan measured_count 7 — penimbangan
    // yang belum selesai tidak mengosongkan mutu.
    foreach (KernelDispatchReportService::QUALITY_MEASURES as $measure) {
        expect($response->json('quality.'.$measure.'.measured_count'))->toBe(7);
        $this->assertNotNull($response->json('quality.'.$measure.'.avg'));
    }

    // VARIAN — SELURUH pengiriman belum ditimbang: total dan rata-rata NULL
    // sementara keempat ukuran mutu tetap berangka.
    KernelDispatchDetail::query()->update(['net_weight_mt' => null]);

    $kosong = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $this->assertNull($kosong->json('dispatches.net_weight_total'));
    $this->assertNull($kosong->json('dispatches.net_weight_avg'));
    expect($kosong->json('dispatches.net_weight_dispatch_count'))->toBe(0);
    expect($kosong->json('dispatches.missing_net_weight_dispatch_count'))->toBe(7);

    foreach (KernelDispatchReportService::QUALITY_MEASURES as $measure) {
        expect($kosong->json('quality.'.$measure.'.measured_count'))->toBe(7);
    }
});

it('skenario 14 — net_weight_mt DIBACA APA ADANYA, tidak pernah dihitung ulang dari bruto dikurangi tara', function () {
    $record = kdApiRecord($this->stationA, $this->day2);

    kdApiDispatch($record, $this->day2, [
        'gross_weight_mt' => null,
        'tare_weight_mt' => null,
        'net_weight_mt' => 18.5,
    ]);
    kdApiDispatch($record, $this->day2, [
        'gross_weight_mt' => 30.0,
        'tare_weight_mt' => 8.0,
        'net_weight_mt' => 17.0,
    ]);

    $response = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $response->assertOk();

    // 35.5 — bukan 40.5 (18.5 + 22) dan bukan 22.
    expect($response->json('dispatches.net_weight_total'))->toEqual(35.5);
    expect($response->json('dispatches.net_weight_dispatch_count'))->toBe(2);
    $this->assertNotEquals(40.5, $response->json('dispatches.net_weight_total'));

    // Dan payload TIDAK menerbitkan bruto/tara sama sekali — keduanya hanya
    // ada di ekspor.
    $keys = kdApiAllKeys($response->json());

    foreach (['gross_weight_mt', 'tare_weight_mt', 'gross_weight_total', 'tare_weight_total'] as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload menerbitkan {$forbidden}");
    }
});

// =====================================================================
// MUTU PER UKURAN LEWAT HTTP (skenario 17-19)
// =====================================================================

it('skenario 17 / 18 — KEEMPAT penyebut mutu BERBEDA lewat HTTP, dan penyebut nol pada satu ukuran tidak mengosongkan ukuran lain', function () {
    $record = kdApiRecord($this->stationA, $this->day2);

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
        kdApiDispatch($record, $this->day2, $mutu);
    }

    $response = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $response->assertOk();

    // LIMA ANGKA, SEMUANYA BERBEDA, diasersikan satu per satu.
    expect($response->json('quality.kernel_moisture_percent.measured_count'))->toBe(6);
    expect($response->json('quality.dirt_impurities_percent.measured_count'))->toBe(5);
    expect($response->json('quality.ffa_percent.measured_count'))->toBe(4);
    expect($response->json('quality.broken_kernel_percent.measured_count'))->toBe(3);
    expect($response->json('dispatches.dispatch_count'))->toBe(9);

    $angka = [
        $response->json('quality.kernel_moisture_percent.measured_count'),
        $response->json('quality.dirt_impurities_percent.measured_count'),
        $response->json('quality.ffa_percent.measured_count'),
        $response->json('quality.broken_kernel_percent.measured_count'),
        $response->json('dispatches.dispatch_count'),
    ];

    expect(array_values(array_unique($angka)))->toHaveCount(5);

    // PENJAGANYA PER UKURAN — satu ukuran kosong, tiga lainnya berangka DI
    // PAYLOAD YANG SAMA.
    KernelDispatchDetail::query()->update([
        'ffa_percent' => null,
        'kernel_moisture_percent' => 7.0,
        'dirt_impurities_percent' => 0.02,
        'broken_kernel_percent' => 5.0,
    ]);

    $perUkuran = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    expect($perUkuran->json('quality.ffa_percent.measured_count'))->toBe(0);
    $this->assertNull($perUkuran->json('quality.ffa_percent.min'));
    $this->assertNull($perUkuran->json('quality.ffa_percent.avg'));
    $this->assertNull($perUkuran->json('quality.ffa_percent.max'));

    foreach (['kernel_moisture_percent', 'dirt_impurities_percent', 'broken_kernel_percent'] as $measure) {
        expect($perUkuran->json('quality.'.$measure.'.measured_count'))->toBe(9);
        $this->assertNotNull($perUkuran->json('quality.'.$measure.'.avg'), "{$measure} ikut dikosongkan");
    }
});

// =====================================================================
// REKAP KATEGORIS DAN STATUS GERBANG (skenario 20-27)
// =====================================================================

it('skenario 20 / 22 — rekap teks bebas: nilai apa adanya, baris null HARFIAH, dan empat penjumlahan terpisah', function () {
    $record = kdApiRecord($this->stationA, $this->day2);

    $baris = [
        ['transporter_contractor' => null,              'silo_source_id' => 'S1',  'destination_buyer' => 'PT Pembeli',   'remarks_gate_status' => 'released'],
        ['transporter_contractor' => 'PT Angkut Jaya',  'silo_source_id' => null,  'destination_buyer' => 'pt pembeli',   'remarks_gate_status' => 'released'],
        ['transporter_contractor' => '',                'silo_source_id' => null,  'destination_buyer' => ' PT Pembeli ', 'remarks_gate_status' => null],
        ['transporter_contractor' => '   ',             'silo_source_id' => null,  'destination_buyer' => null,           'remarks_gate_status' => 'not_released'],
        ['transporter_contractor' => 'PT Angkut Jaya',  'silo_source_id' => 'S2',  'destination_buyer' => null,           'remarks_gate_status' => 'released'],
        ['transporter_contractor' => 'PT Angkut Jaya',  'silo_source_id' => 'S2',  'destination_buyer' => null,           'remarks_gate_status' => null],
        ['transporter_contractor' => 'pt angkut jaya',  'silo_source_id' => 'S2',  'destination_buyer' => null,           'remarks_gate_status' => 'released'],
        ['transporter_contractor' => 'pt angkut jaya',  'silo_source_id' => 'S3',  'destination_buyer' => 'PT Lain',      'remarks_gate_status' => 'not_released'],
    ];

    foreach ($baris as $nilai) {
        kdApiDispatch($record, $this->day2, $nilai);
    }

    $response = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $response->assertOk();
    expect($response->json('dispatches.dispatch_count'))->toBe(8);

    // EMPAT ASERSI PENJUMLAHAN TERPISAH. Pola null berbeda antar-kolom
    // membuat keempatnya tidak saling menumpang.
    foreach (['by_transporter', 'by_silo_source', 'by_destination_buyer', 'by_gate_status'] as $rekap) {
        $this->assertSame(
            8,
            (int) collect($response->json($rekap))->sum('dispatch_count'),
            "rekap {$rekap} tidak menjumlah ke dispatch_count",
        );
    }

    // NULL, '' dan '   ' MENYATU setelah trim menjadi SATU baris value null
    // HARFIAH.
    $transporter = $response->json('by_transporter');
    $barisNull = kdApiEntry($transporter, null);

    $this->assertNotNull($barisNull);
    $this->assertNull($barisNull['value']);
    expect($barisNull['dispatch_count'])->toBe(3);
    $this->assertNotContains('', array_column($transporter, 'value'));
    $this->assertNotContains('   ', array_column($transporter, 'value'));

    // BEDA HURUF BESAR-KECIL TETAP DUA BARIS; spasi di ujung di-trim.
    expect(kdApiEntry($transporter, 'PT Angkut Jaya')['dispatch_count'])->toBe(3);
    expect(kdApiEntry($transporter, 'pt angkut jaya')['dispatch_count'])->toBe(2);

    $pembeli = $response->json('by_destination_buyer');

    expect(kdApiEntry($pembeli, 'PT Pembeli')['dispatch_count'])->toBe(2);
    expect(kdApiEntry($pembeli, 'pt pembeli')['dispatch_count'])->toBe(1);
    $this->assertNotContains(' PT Pembeli ', array_column($pembeli, 'value'));

    // TIAP BARIS MENERBITKAN PENYEBUTNYA SENDIRI.
    foreach (['by_transporter', 'by_silo_source', 'by_destination_buyer', 'by_gate_status'] as $rekap) {
        foreach ($response->json($rekap) as $row) {
            expect(array_keys($row))->toBe([
                'value', 'dispatch_count', 'net_weight_total',
                'net_weight_dispatch_count', 'net_weight_avg',
            ]);
        }
    }
});

it('skenario 21 — silo_source_id dikelompokkan sebagai TEKS; nilai berbentuk uuid terbit VERBATIM', function () {
    $uuid = '9f1c2a44-5e21-4b6f-8c0d-7a3e51b9d012';
    $record = kdApiRecord($this->stationA, $this->day2);

    kdApiDispatch($record, $this->day2, ['silo_source_id' => $uuid]);
    kdApiDispatch($record, $this->day2, ['silo_source_id' => $uuid]);
    kdApiDispatch($record, $this->day2, ['silo_source_id' => 'Silo Timur 2']);

    $response = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $response->assertOk();

    $nilai = array_column($response->json('by_silo_source'), 'value');

    $this->assertContains($uuid, $nilai);
    $this->assertContains('Silo Timur 2', $nilai);
    expect(kdApiEntry($response->json('by_silo_source'), $uuid)['dispatch_count'])->toBe(2);

    // Dan payload TIDAK menerbitkan nama turunan apa pun untuk nilai itu —
    // tidak ada tabel silo di satu migrasi pun.
    $keys = kdApiAllKeys($response->json());

    foreach (['silo', 'silo_name', 'silo_source_name', 'silos'] as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload menerbitkan {$forbidden}");
    }
});

it("skenario 25 — not_released_count hanya 'not_released' PERSIS, sementara baris null ADA di rekapnya", function () {
    $record = kdApiRecord($this->stationA, $this->day2);

    foreach (['released', 'released', 'released', 'not_released', 'not_released', null, null, null, null] as $status) {
        kdApiDispatch($record, $this->day2, ['remarks_gate_status' => $status]);
    }

    $response = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $response->assertOk();

    // 2 — bukan 6 (null tidak digabung) dan bukan 0.
    expect($response->json('not_released_count'))->toBe(2);
    $this->assertNotSame(6, $response->json('not_released_count'));

    // SEKALIGUS: baris value null ADA dengan dispatch_count 4 — assertNull
    // atas value-nya, bukan pencarian string 'tidak tercatat'.
    $barisNull = kdApiEntry($response->json('by_gate_status'), null);

    $this->assertNotNull($barisNull, "by_gate_status kehilangan baris 'tidak tercatat'");
    $this->assertNull($barisNull['value']);
    expect($barisNull['dispatch_count'])->toBe(4);

    // KODE MENTAH, bukan label — label adalah kosakata lapisan tampilan, dan
    // menerbitkannya di payload membuat screen-165 menerima label yang tidak
    // dapat ia ubah.
    expect(kdApiEntry($response->json('by_gate_status'), 'not_released')['dispatch_count'])->toBe(2);
    expect(kdApiEntry($response->json('by_gate_status'), 'released')['dispatch_count'])->toBe(3);

    $body = (string) $response->getContent();

    $this->assertStringNotContainsString('"Not Released"', $body);
    $this->assertStringNotContainsString('"Released"', $body);
    // Dan labelnya MEMANG berbeda dari kodenya.
    expect(Display::option('not_released'))->toBe('Not Released');

    // VARIAN KEDUA — nol 'not_released': kuncinya TETAP diterbitkan bernilai 0.
    KernelDispatchDetail::query()->where('remarks_gate_status', 'not_released')
        ->update(['remarks_gate_status' => 'released']);

    $kedua = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $this->assertArrayHasKey('not_released_count', $kedua->json());
    expect($kedua->json('not_released_count'))->toBe(0);
});

it('skenario 26 — TIGA penghitung kepatuhan terpisah, nol kunci gabungan, dan nomornya TIDAK diterbitkan', function () {
    $record = kdApiRecord($this->stationA, $this->day2);

    kdApiDispatch($record, $this->day2, ['waybill_number' => 'SJ-1', 'security_seal_no_top' => null, 'security_seal_no_bottom' => null]);
    kdApiDispatch($record, $this->day2, ['waybill_number' => 'SJ-2', 'security_seal_no_top' => null, 'security_seal_no_bottom' => 'B2']);
    kdApiDispatch($record, $this->day2, ['waybill_number' => null, 'security_seal_no_top' => 'T3', 'security_seal_no_bottom' => 'B3']);
    kdApiDispatch($record, $this->day2, ['waybill_number' => '  ', 'security_seal_no_top' => 'T4', 'security_seal_no_bottom' => 'B4']);

    $response = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $response->assertOk();

    expect($response->json('compliance.missing_waybill_count'))->toBe(2);
    expect($response->json('compliance.missing_seal_top_count'))->toBe(2);
    expect($response->json('compliance.missing_seal_bottom_count'))->toBe(1);

    // ASERSI NEGATIF ATAS NAMA KUNCI.
    foreach ([
        'missing_seal_count', 'missing_seals_count', 'seal_count',
        'total_missing_seal', 'seal_missing_total',
    ] as $gabungan) {
        $this->assertArrayNotHasKey($gabungan, $response->json('compliance'));
    }

    // NOMORNYA SENDIRI TIDAK DITERBITKAN di payload — ia hanya ada di ekspor.
    $body = (string) $response->getContent();

    foreach (['SJ-1', 'SJ-2', 'T3', 'B3', 'T4', 'B4'] as $nomor) {
        $this->assertStringNotContainsString('"'.$nomor.'"', $body, "payload menerbitkan nomor {$nomor}");
    }

    // Dan tidak pernah dirata-rata: nol kunci avg untuk ketiganya.
    $keys = kdApiAllKeys($response->json());

    foreach (['avg_waybill', 'avg_seal', 'seal_avg', 'waybill_avg'] as $forbidden) {
        $this->assertNotContains($forbidden, $keys);
    }

    $this->assertArrayHasKey('_note', $response->json('compliance'));
});

it('skenario 27 — urutan rekap: total netto DESC, lalu dispatch_count DESC, lalu value ASC', function () {
    $record = kdApiRecord($this->stationA, $this->day2);

    foreach (['Charlie', 'Alpha', 'Bravo'] as $nama) {
        kdApiDispatch($record, $this->day2, ['transporter_contractor' => $nama, 'net_weight_mt' => 20.0]);
        kdApiDispatch($record, $this->day2, ['transporter_contractor' => $nama, 'net_weight_mt' => 20.0]);
    }

    kdApiDispatch($record, $this->day2, ['transporter_contractor' => 'Delta', 'net_weight_mt' => 100.0]);

    foreach (range(1, 5) as $i) {
        kdApiDispatch($record, $this->day2, ['silo_source_id' => null, 'net_weight_mt' => 100.0]);
    }

    kdApiDispatch($record, $this->day2, ['silo_source_id' => 'SILO-A', 'net_weight_mt' => 40.0]);
    kdApiDispatch($record, $this->day2, ['silo_source_id' => 'SILO-A', 'net_weight_mt' => 40.0]);

    foreach (range(1, 4) as $i) {
        kdApiDispatch($record, $this->day2, ['silo_source_id' => 'SILO-B', 'net_weight_mt' => 20.0]);
    }

    foreach (range(1, 2) as $i) {
        kdApiDispatch($record, $this->day2, ['silo_source_id' => 'SILO-C', 'net_weight_mt' => null]);
    }

    $response = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $response->assertOk();

    // URUTAN LENGKAP, bukan assertContains yang lolos pada urutan apa pun.
    $transporter = array_values(array_filter(
        array_column($response->json('by_transporter'), 'value'),
        fn ($value) => $value !== null,
    ));

    expect($transporter)->toBe(['Delta', 'Alpha', 'Bravo', 'Charlie']);

    // Baris null di INDEKS 0 karena TONASENYA, dan ASERSI NEGATIF bahwa ia
    // bukan entri terakhir.
    $silo = $response->json('by_silo_source');

    expect(kdApiNullIndex($silo))->toBe(0);
    $this->assertNotSame(count($silo) - 1, kdApiNullIndex($silo));

    // 'SILO-C' diurutkan sebagai 0.0 sehingga TERAKHIR, SEMENTARA
    // net_weight_total-nya di payload TETAP null.
    expect(array_column($silo, 'value')[count($silo) - 1])->toBe('SILO-C');
    $this->assertNull(kdApiEntry($silo, 'SILO-C')['net_weight_total']);
    $this->assertNull(kdApiEntry($silo, 'SILO-C')['net_weight_avg']);
});

// =====================================================================
// KETIADAAN YANG DINYATAKAN (skenario 28, 29, 30, 31)
// =====================================================================

it('skenario 28 / 29 — nol kunci per jam, nol waktu putar kendaraan, nol penandaan ambang, diasersikan ATAS NAMA KUNCI', function () {
    $record = kdApiRecord($this->stationA, $this->day2);

    kdApiDispatch($record, $this->day2, [
        'net_weight_mt' => 4.0,
        'kernel_moisture_percent' => 0.1,
        'ffa_percent' => -1.2,
        'shift' => 'Shift 1',
    ]);
    kdApiDispatch($record, $this->day2, ['kernel_moisture_percent' => 99.0]);

    $response = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $response->assertOk();

    $keys = kdApiAllKeys($response->json());

    foreach (kdApiForbiddenKeys() as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
    }

    // DAN KUNCI YANG MEMANG HARUS ADA — tanpa baris ini, daftar larangan di
    // atas akan hijau pada payload kosong.
    foreach ([
        'dispatches', 'quality', 'by_transporter', 'by_silo_source',
        'by_destination_buyer', 'by_gate_status', 'not_released_count',
        'compliance', 'draft_dispatch_count', 'undated_dispatch_count',
        'daily', 'daily_total', 'completeness', 'text_entries',
        '_note_no_hourly', '_note_no_quality_thresholds',
        '_note_shift_not_published',
    ] as $required) {
        $this->assertContains($required, $keys, "payload kehilangan kunci {$required}");
    }

    // JEBAKANNYA NYATA, BUKAN TEORETIS: ketiga prosa MEMUAT kata yang akan
    // dicocokkan sebuah pemindaian teks, dan ketiganya ada justru untuk
    // MENYATAKAN ketiadaan hal-hal itu.
    $this->assertStringContainsString('jam', $response->json('_note_no_hourly'));
    $this->assertStringContainsString('CPO Dispatch', $response->json('_note_no_hourly'));
    $this->assertStringContainsString('batas', $response->json('_note_no_quality_thresholds'));
    $this->assertStringContainsString('shift', $response->json('_note_shift_not_published'));

    // NILAI EKSTREM DAN NEGATIF TERBIT APA ADANYA — tidak disaring, tidak
    // dijadikan 0, dan tidak ditandai.
    expect($response->json('quality.kernel_moisture_percent.min'))->toEqual(0.1);
    expect($response->json('quality.kernel_moisture_percent.max'))->toEqual(99.0);
    expect($response->json('quality.ffa_percent.min'))->toEqual(-1.2);
    $this->assertLessThan(0, $response->json('quality.ffa_percent.min'));
});

it('skenario 30 — text_entries HANYA punya findings; entri kosong dibuang, daftar kosong tetap diterbitkan', function () {
    $recordAwal = kdApiRecord($this->stationA, $this->day2);
    $record = kdApiRecord($this->stationA, $this->day3);

    kdApiDispatch($recordAwal, $this->day2, ['findings' => 'Ceceran di gerbang']);
    kdApiDispatch($record, $this->day3, ['findings' => 'Segel bawah terlepas']);
    kdApiDispatch($record, $this->day3, ['findings' => '   ']);
    kdApiDispatch($record, $this->day3, ['findings' => null]);

    $response = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $response->assertOk();

    // SATU DAFTAR — stasiun ini TIDAK punya kolom catatan kedua, berbeda dari
    // Solid Waste Disposal yang punya remarks juga.
    expect(array_keys($response->json('text_entries')))->toBe(['findings']);
    $this->assertArrayNotHasKey('remarks', $response->json('text_entries'));
    $this->assertNotContains('remarks', kdApiAllKeys($response->json()));

    expect($response->json('text_entries.findings'))->toHaveCount(2);
    expect(array_column($response->json('text_entries.findings'), 'value'))
        ->toBe(['Ceceran di gerbang', 'Segel bawah terlepas']);
    expect(array_column($response->json('text_entries.findings'), 'date'))
        ->toBe([$this->day2, $this->day3]);

    // VARIAN KEDUA — nol findings: [] dengan kunci TETAP ADA.
    KernelDispatchDetail::query()->update(['findings' => null]);

    $kosong = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $this->assertArrayHasKey('findings', $kosong->json('text_entries'));
    expect($kosong->json('text_entries.findings'))->toBe([]);
});

it('skenario 31 — kolom shift ABSEN dari payload tetapi ADA di ekspor csv DAN xlsx, bersama kelima kolom lain', function () {
    $record = kdApiRecord($this->stationA, $this->day2);

    kdApiDispatch($record, $this->day2, [
        'shift' => 'Shift 1',
        'weighbridge_ticket_no' => 'WT-001',
        'waybill_number' => 'SJ-001',
        'vehicle_plate_no' => 'B 1234 XX',
        'driver_name' => 'Pak Sopir',
        'weighbridge_operator_id' => 'OP-007',
        'net_weight_mt' => 20.0,
    ]);

    // (a) PAYLOAD: nol kunci shift di level mana pun.
    $summary = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    $keys = kdApiAllKeys($summary->json());

    foreach (['shift', 'shifts', 'by_shift', 'per_shift', 'shift_distribution', 'shift_id'] as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
    }

    // Kelima kolom yang hanya ada di ekspor pun TIDAK terbit di payload.
    foreach ([
        'weighbridge_ticket_no', 'waybill_number', 'vehicle_plate_no',
        'driver_name', 'weighbridge_operator_id',
    ] as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload menerbitkan kolom ekspor {$forbidden}");
    }

    // (b) EKSPOR CSV: header memuat keenam kolom, dan nilainya terbawa.
    $csv = $this->actingAs($this->supervisor, 'web')->get(kdApiUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));

    $csv->assertOk();

    $lines = kdApiCsvLines(kdApiStreamed($csv->baseResponse));
    $header = kdApiCsvRow($lines[0]);
    $baris = kdApiCsvRow($lines[1]);

    // KESAMAAN ARRAY PENUH terhadap konstantanya — bukan `contains` atas satu
    // atau dua kolom. 27 kolom: 6 konteks + 21 detail.
    expect($header)->toBe(KernelDispatchReportService::EXPORT_HEADER);
    expect($header)->toHaveCount(27);

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

    // (c) EKSPOR XLSX: header yang SAMA, dibaca dari isi arsipnya.
    $xlsx = $this->actingAs($this->supervisor, 'web')->get(kdApiUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'xlsx',
    ]));

    $xlsx->assertOk();

    $rows = xlsxRows(kdApiStreamed($xlsx->baseResponse));

    expect($rows[0])->toBe(KernelDispatchReportService::EXPORT_HEADER);
    $this->assertContains('Shift', $rows[0]);
});

// =====================================================================
// EKSPOR LEWAT HTTP — skenario 32 / case 56, SATU-SATUNYA TEMPAT TRIM +
// DEFAULT `format` DAPAT DIBUKTIKAN
// =====================================================================

it('skenario 32 — format: TRIM + DEFAULT ditetapkan CONTROLLER, dan hanya terlihat dari HTTP', function () {
    $r1 = kdApiRecord($this->stationA, $this->day2);
    $r2 = kdApiRecord($this->stationA, $this->day3);

    kdApiDispatch($r1, $this->day2, ['net_weight_mt' => 1.0, 'shift' => 'Shift 1']);
    kdApiDispatch($r1, $this->day2, ['net_weight_mt' => 2.0, 'shift' => 'Shift 2']);
    kdApiDispatch($r2, $this->day3, ['net_weight_mt' => 3.0, 'shift' => 'Shift 3']);

    $base = [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ];

    // ABSEN / '' / '  ' / ' csv ' → 200 CSV. Default ditetapkan atas nilai
    // yang SUDAH DI-TRIM: queryString() memulangkan null untuk kosong-setelah-
    // trim, dan 'csv' untuk ' csv '. Di tingkat SERVICE keempatnya ADALAH 422
    // (daftar tertutupnya tidak memaafkan apa pun), jadi separuh perilaku ini
    // tidak dapat dilihat dari uji unit sama sekali.
    foreach ([null, '', '  ', ' csv '] as $format) {
        $query = $format === null ? $base : array_merge($base, ['format' => $format]);

        $response = $this->actingAs($this->supervisor, 'web')->get(kdApiUrl('export', $query));

        $label = $format === null ? '(absen)' : "'{$format}'";

        $response->assertOk();
        $this->assertStringContainsString(
            'text/csv',
            (string) $response->headers->get('content-type'),
            "format {$label} seharusnya jatuh ke csv",
        );
        $this->assertStringContainsString('.csv', (string) $response->headers->get('content-disposition'));
    }

    // 'CSV' → 422: daftarnya PEKA HURUF — yang dilakukan controller hanya
    // trim, bukan lowercase.
    // 'pdf' → 422 juga.
    foreach (['CSV', 'XLSX', 'pdf', 'excel', 'json'] as $format) {
        $response = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('export', array_merge($base, [
            'format' => $format,
        ])));

        $response->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
        $response->assertJsonPath('errors.format.0', 'Format ekspor harus csv atau xlsx.');

        // TIDAK ADA HEADER UNDUHAN YANG TERKIRIM — galat sesudah header
        // mendarat di komputer orang sebagai berkas rusak, bukan sebagai pesan.
        $this->assertNull(
            $response->headers->get('content-disposition'),
            "format {$format} mengirim header unduhan sebelum ditolak",
        );
        $this->assertStringNotContainsString('text/csv', (string) $response->headers->get('content-type'));
    }

    // 'xlsx' → 200, dan ARSIP ZIP SUNGGUHAN yang memuat xl/workbook.xml —
    // diperiksa dari ISI BERKASNYA, bukan dari nama berkas maupun
    // content-type.
    $xlsx = $this->actingAs($this->supervisor, 'web')->get(kdApiUrl('export', array_merge($base, [
        'format' => 'xlsx',
    ])));

    $xlsx->assertOk();

    $body = kdApiStreamed($xlsx->baseResponse);

    $this->assertStringStartsWith("PK\x03\x04", $body);
    $this->assertStringContainsString('xl/workbook.xml', $body);
    $this->assertStringContainsString(
        SheetWriter::XLSX_CONTENT_TYPE,
        (string) $xlsx->headers->get('content-type'),
    );
    $this->assertStringContainsString('.xlsx', (string) $xlsx->headers->get('content-disposition'));

    // ISI CSV: 3 baris data — SATU PER PENGIRIMAN, bukan satu per record
    // induk (ada DUA record induk di fixture).
    $csv = $this->actingAs($this->supervisor, 'web')->get(kdApiUrl('export', array_merge($base, [
        'format' => 'csv',
    ])));

    $lines = kdApiCsvLines(kdApiStreamed($csv->baseResponse));

    expect($lines)->toHaveCount(4);
    expect(KernelDispatchRecord::query()->count())->toBe(2);

    $header = kdApiCsvRow($lines[0]);
    $rows = array_map(fn (string $line) => kdApiCsvRow($line), array_slice($lines, 1));

    expect($header)->toBe(KernelDispatchReportService::EXPORT_HEADER);
    expect(KernelDispatchReportService::EXPORT_CONTEXT_COLUMN_COUNT)->toBe(6);
    expect(KernelDispatchReportService::DETAIL_EXPORT_FIELDS)->toHaveCount(21);

    // KOLOM KONTEKS DIULANG VERBATIM dan DIBANDINGKAN ANTAR-BARIS — bukan
    // hanya dicek keberadaannya.
    $konteks = array_slice($rows[0], 0, 4);

    expect($konteks[0])->toBe('Periode KD Alpha');
    expect($konteks[1])->toBe($this->periodStart.' s/d '.$this->periodEnd);
    expect($konteks[2])->toBe('Mill Alpha');
    expect($konteks[3])->toBe((string) ProductionLine::find($this->lineA)->name);

    foreach ($rows as $row) {
        expect(array_slice($row, 0, 4))->toBe($konteks);
    }

    // Dan kedua kolom konteks terakhir memang BERBEDA antar-record — tanggal
    // dan status RECORD INDUK.
    expect($rows[0][4])->toBe($this->day2);
    expect($rows[2][4])->toBe($this->day3);
    $this->assertNotSame($rows[0][4], $rows[2][4]);
});

it('skenario 32b — ekspor: batas baris terlampaui adalah 422 SEBELUM aliran dimulai, dan batasnya INKLUSIF', function () {
    // Batas sungguhan tetap 50.000; subclass uji menurunkannya, dan itu HANYA
    // mungkin karena service membaca batasnya lewat `static::`. Diikat ke
    // CONTAINER sehingga controller yang sama persis me-resolve subclass ini.
    expect(KernelDispatchReportService::EXPORT_ROW_LIMIT)->toBe(50000);

    $this->app->bind(
        KernelDispatchReportService::class,
        fn () => new KernelDispatchReportApiTinyExportService,
    );

    // ENAM pengiriman pada DUA record induk: batasnya menghitung BARIS YANG
    // DITERBITKAN, bukan record. Implementasi yang menghitung record melihat 2
    // dan meloloskannya.
    $r1 = kdApiRecord($this->stationA, $this->day2);
    $r2 = kdApiRecord($this->stationA, $this->day3);

    foreach (range(1, 3) as $i) {
        kdApiDispatch($r1, $this->day2);
        kdApiDispatch($r2, $this->day3);
    }

    expect(KernelDispatchRecord::query()->count())->toBe(2);
    expect(KernelDispatchDetail::query()->count())->toBe(6);

    $base = [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ];

    $lewat = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('export', $base));

    $lewat->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
    $this->assertStringContainsString('5 baris pengiriman', $lewat->json('errors.period_id.0'));
    $this->assertStringContainsString('6 baris', $lewat->json('errors.period_id.0'));
    $this->assertStringContainsString('Persempit rentang periode', $lewat->json('errors.period_id.0'));

    // NOL BYTE ISI dan TIDAK ADA content-disposition.
    $this->assertNull($lewat->headers->get('content-disposition'));
    $this->assertStringNotContainsString('text/csv', (string) $lewat->headers->get('content-type'));

    // TEPAT DI BATAS: 200 beraliran — perbandingannya strictly-greater.
    KernelDispatchDetail::query()->orderBy('id')->limit(1)->get()->each->delete();

    expect(KernelDispatchDetail::query()->count())->toBe(5);

    $tepat = $this->actingAs($this->supervisor, 'web')->get(kdApiUrl('export', $base));

    $tepat->assertOk();
    expect(kdApiCsvLines(kdApiStreamed($tepat->baseResponse)))->toHaveCount(6);
});

it('skenario 33 — status periode TIDAK menyaring daftar maupun angka, dan pengiriman draft tetap terhitung', function () {
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

    $draftRecord = kdApiRecord($this->stationA, $this->day2, ['status' => RecordStatus::DraftOngoing]);
    $finalRecord = kdApiRecord($this->stationA, $this->day3, ['status' => RecordStatus::Saved]);

    foreach (range(1, 3) as $i) {
        kdApiDispatch($draftRecord, $this->day2, array_merge(['net_weight_mt' => 2.0], $mutu));
    }

    foreach (range(1, 2) as $i) {
        kdApiDispatch($finalRecord, $this->day3, array_merge(['net_weight_mt' => 2.0], $mutu));
    }

    // KETIGA PERIODE terbit — status TIDAK menyaring daftar.
    $periods = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('periods'));

    $periods->assertOk();

    $ids = array_column($periods->json('data'), 'id');

    $this->assertContains((string) $this->periodA->id, $ids);
    $this->assertContains((string) $draftPeriod->id, $ids);
    $this->assertContains((string) $closedPeriod->id, $ids);

    // Dan periode TERTUTUP tetap dapat DIBACA dan DIEKSPOR penuh.
    foreach ([$draftPeriod, $closedPeriod, $this->periodA] as $periode) {
        $this->actingAs($this->supervisor, 'web')
            ->getJson(kdApiUrl('summary', [
                'period_id' => (string) $periode->id,
                'production_line_id' => $this->lineA,
            ]))
            ->assertOk();

        $this->actingAs($this->supervisor, 'web')
            ->get(kdApiUrl('export', [
                'period_id' => (string) $periode->id,
                'production_line_id' => $this->lineA,
                'format' => 'csv',
            ]))
            ->assertOk();
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    expect($summary->json('dispatches.dispatch_count'))->toBe(5);
    expect($summary->json('draft_dispatch_count'))->toBe(3);

    foreach (KernelDispatchReportService::QUALITY_MEASURES as $measure) {
        expect($summary->json('quality.'.$measure.'.measured_count'))->toBe(5);
    }

    // DIBUKTIKAN DENGAN MEMBANDINGKAN terhadap varian yang draft-nya dihapus:
    // keempat measured_count turun dari 5 ke 2.
    KernelDispatchDetail::query()->where('kernel_dispatch_record_id', $draftRecord->id)->delete();
    $draftRecord->delete();

    $tanpa = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    expect($tanpa->json('dispatches.dispatch_count'))->toBe(2);
    expect($tanpa->json('draft_dispatch_count'))->toBe(0);

    foreach (KernelDispatchReportService::QUALITY_MEASURES as $measure) {
        expect($tanpa->json('quality.'.$measure.'.measured_count'))->toBe(2);
    }
});

it('skenario 34 — rekap harian per HARI BERPENGIRIMAN, kakinya DIJUMLAHKAN, dan kelengkapan berhenti di hari ini', function () {
    $offsets = [31, 39, 20, 35, 12, 28, 17, 24, 14];
    $tanggal = array_map(fn (int $o) => now()->subDays($o)->toDateString(), $offsets);

    foreach ($tanggal as $index => $hari) {
        $record = kdApiRecord($this->stationA, $hari);

        kdApiDispatch($record, $hari, ['net_weight_mt' => 3.0]);

        if ($index % 3 === 0) {
            kdApiDispatch($record, $hari, ['net_weight_mt' => null]);
        }
    }

    $a = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $a->assertOk();

    // 9 — bukan 31 (hari kalender) dan bukan jumlah pengiriman.
    expect($a->json('daily'))->toHaveCount(9);
    expect($a->json('completeness.days_in_period'))->toBe(31);

    $urut = $tanggal;
    sort($urut);
    expect(array_column($a->json('daily'), 'date'))->toBe($urut);

    // KAKINYA DIJUMLAHKAN dari daily[], bukan disalin dari blok dispatches.
    $this->assertSame(
        array_sum(array_column($a->json('daily'), 'dispatch_count')),
        $a->json('daily_total.dispatch_count'),
    );
    $this->assertSame(
        array_sum(array_column($a->json('daily'), 'net_weight_dispatch_count')),
        $a->json('daily_total.net_weight_dispatch_count'),
    );

    // Setiap baris harian menerbitkan PENYEBUTNYA sendiri.
    foreach ($a->json('daily') as $row) {
        expect(array_keys($row))->toBe([
            'date', 'dispatch_count', 'net_weight_total',
            'net_weight_dispatch_count', 'net_weight_avg',
        ]);
    }

    // PERIODE BELUM MULAI — days_counted 0, percent NULL.
    $belumMulai = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelDispatch->value)
        ->range(now()->addDays(10)->toDateString(), now()->addDays(40)->toDateString())
        ->open()
        ->named('Periode Masa Depan')
        ->create();

    $b = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('summary', [
        'period_id' => (string) $belumMulai->id,
        'production_line_id' => $this->lineA,
    ]));

    expect($b->json('completeness.days_counted'))->toBe(0);
    $this->assertNull($b->json('completeness.percent'));
    expect($b->json('completeness.days_with_dispatch'))->toBe(0);
    expect($b->json('completeness.days_in_period'))->toBe(31);

    // PERIODE BERJALAN — penyebutnya berhenti di HARI INI, sementara
    // days_in_period tetap panjang penuh periode.
    $berjalan = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelDispatch->value)
        ->range(now()->subDays(9)->toDateString(), now()->addDays(20)->toDateString())
        ->open()
        ->named('Periode Berjalan')
        ->create();

    foreach ([8, 6, 3] as $offset) {
        $hari = now()->subDays($offset)->toDateString();
        kdApiDispatch(kdApiRecord($this->stationA, $hari), $hari, ['net_weight_mt' => 1.0]);
    }

    $c = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('summary', [
        'period_id' => (string) $berjalan->id,
        'production_line_id' => $this->lineA,
    ]));

    expect($c->json('completeness.days_counted'))->toBe(10);
    expect($c->json('completeness.days_in_period'))->toBe(30);
    expect($c->json('completeness.days_with_dispatch'))->toBe(3);
    expect($c->json('completeness.percent'))->toEqual(30.0);
});

// =====================================================================
// RENTANG, CAKUPAN, DAN BENTUK STRUKTURAL RUTENYA
// =====================================================================

it('rentang periode inklusif di kedua ujung, pada summary DAN pada ekspor', function () {
    foreach ([
        $this->dayBeforePeriod,
        $this->periodStart,
        $this->periodEnd,
        $this->dayAfterPeriod,
    ] as $hari) {
        kdApiDispatch(kdApiRecord($this->stationA, $hari), $hari, ['net_weight_mt' => 1.0]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    // Hari pertama dan hari terakhir IKUT; satu hari sebelum dan sesudah
    // TIDAK. whereDate(), bukan where(): suite di SQLite, produksi/e2e di
    // PostgreSQL.
    expect($summary->json('dispatches.dispatch_count'))->toBe(2);
    expect(array_column($summary->json('daily'), 'date'))->toBe([$this->periodStart, $this->periodEnd]);

    $csv = $this->actingAs($this->supervisor, 'web')->get(kdApiUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));

    $lines = kdApiCsvLines(kdApiStreamed($csv->baseResponse));

    // 1 header + 2 baris.
    expect($lines)->toHaveCount(3);
});

it('lingkup line: record line lain dan stasiun jenis lain tidak pernah bocor ke angka maupun ke ekspor', function () {
    $lineKedua = Station::factory()->forBusinessUnit($this->businessUnitA)->kernelDispatch()->create();

    foreach (range(1, 3) as $i) {
        kdApiDispatch(kdApiRecord($this->stationA, $this->day2), $this->day2, ['net_weight_mt' => 10.0]);
    }

    foreach (range(1, 4) as $i) {
        kdApiDispatch(kdApiRecord($lineKedua, $this->day2), $this->day2, ['net_weight_mt' => 100.0]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    // KEBOCORAN DIBUAT TERLIHAT SEBAGAI ANGKA: netto tiap kelompok disemai
    // berbeda, jadi satu baris yang bocor langsung mengubah totalnya.
    expect($summary->json('dispatches.dispatch_count'))->toBe(3);
    expect($summary->json('dispatches.net_weight_total'))->toEqual(30.0);

    $lines = kdApiCsvLines(kdApiStreamed($this->actingAs($this->supervisor, 'web')->get(kdApiUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]))->baseResponse));

    expect($lines)->toHaveCount(4);
});

it('period_id tidak ada → 404 NOT_FOUND; period_id mill lain → 403 FORBIDDEN, dan keduanya BERBENTUK sama', function () {
    $periodeB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::KernelDispatch->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->create();

    $hilang = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('summary', [
        'period_id' => (string) Str::uuid(),
        'production_line_id' => $this->lineA,
    ]));

    $hilang->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
    expect(array_keys($hilang->json()))->toBe(['message', 'code']);

    // ORACLE LINTAS MILL DITUTUP: periode mill lain menjawab 403, TIDAK
    // PERNAH 404.
    $asing = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl('summary', [
        'period_id' => (string) $periodeB->id,
        'production_line_id' => $this->lineA,
    ]));

    $asing->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
    expect(array_keys($asing->json()))->toBe(['message', 'code']);
});

it('production_line_id milik mill LAIN: 403 pada summary DAN pada export, nol angka', function () {
    kdApiDispatch(kdApiRecord($this->stationB, $this->day2), $this->day2, ['net_weight_mt' => 99.0]);

    foreach (['summary', 'export'] as $path) {
        $response = $this->actingAs($this->supervisor, 'web')->getJson(kdApiUrl($path, [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineB,
        ]));

        $response->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        $this->assertArrayNotHasKey('dispatches', $response->json());
        // Dan nama line mill lain tidak pernah terbit.
        $this->assertStringNotContainsString(
            (string) ProductionLine::find($this->lineB)->name,
            (string) $response->getContent(),
        );
    }
});

it('layar hanya membaca: prefix API ini tidak punya satu rute tulis pun', function () {
    $rutes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/kernel-dispatch-reports'));

    expect($rutes)->toHaveCount(4);

    foreach ($rutes as $route) {
        $methods = array_values(array_diff($route->methods(), ['HEAD']));

        expect($methods)->toBe(['GET']);
    }

    expect($rutes->map(fn ($route) => $route->uri())->sort()->values()->all())->toBe([
        'api/kernel-dispatch-reports/business-units/options',
        'api/kernel-dispatch-reports/export',
        'api/kernel-dispatch-reports/periods',
        'api/kernel-dispatch-reports/summary',
    ]);
});

it('daftar middleware: prefix API menerima Operator sementara rute WEB laporan ini TIDAK', function () {
    $apiRoute = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($route) => $route->uri() === 'api/kernel-dispatch-reports/summary');

    $webRoute = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($route) => $route->uri() === 'reports/kernel-dispatch');

    $this->assertNotNull($apiRoute);
    $this->assertNotNull($webRoute);

    // DAFTAR PERANNYA DIURAI MENJADI ARRAY, bukan dicocokkan sebagai
    // substring: 'operator' adalah substring dari beberapa nama lain yang
    // mungkin muncul kelak, dan asersi substring akan lolos pada daftar yang
    // salah tanpa ada yang menyadarinya.
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

    // PERBEDAAN STRUKTURALNYA — itulah yang menjaga admisi API tidak pernah
    // merembes menjadi admisi layar web. Operator diterima di API (jalur
    // screen-165) dan DITOLAK di web.
    $this->assertContains('operator', $apiRoles, 'rute API tidak menerima Operator');
    $this->assertNotContains('operator', $webRoles, 'rute WEB menerima Operator');

    foreach (['supervisor', 'mill_management', 'admin'] as $role) {
        $this->assertContains($role, $apiRoles, "rute API tidak menerima {$role}");
        $this->assertContains($role, $webRoles, "rute WEB tidak menerima {$role}");
    }

    // Dan perbedaannya TEPAT satu peran — bukan dua daftar yang kebetulan
    // berbeda.
    expect(array_values(array_diff($apiRoles, $webRoles)))->toBe(['operator']);
    expect(array_values(array_diff($webRoles, $apiRoles)))->toBe([]);

    expect($webRoute->getName())->toBe('reports.kernel-dispatch');
});
