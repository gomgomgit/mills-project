<?php

/**
 * LaporanProcessWaterTest (API) — screen-160--laporan-process-water-web,
 * keempat endpoint /api/process-water-reports/*.
 *
 * Satu uji per entri `test_scenarios[].api_test` pada tech spec screen-160
 * (33 skenario). Aturan agregasinya sendiri dibuktikan di
 * tests/Unit/Services/ProcessWaterReportServiceTest.php terhadap service-nya;
 * yang dibuktikan berkas ini adalah KONTRAK HTTP-nya — kode status, kode galat
 * yang dapat dibaca mesin, siapa yang diterima, apa yang benar-benar dibawa
 * payload-nya, dan bagaimana bentuk berkas ekspornya.
 *
 * ────────────────────────────────────────────────────────────────────────
 * TIGA JAWABAN YANG BERBEDA UNTUK OPERATOR, DAN KETIGANYA DISENGAJA
 * ────────────────────────────────────────────────────────────────────────
 *   - /periods, /summary, /export: 200 SEJAK COMMIT PERTAMA (presedens
 *     Grading / Kernel Plant / Engine Room — kembaran mobile screen-161
 *     dibangun dalam seri yang sama, jadi pemanggilnya sudah ada).
 *   - /business-units/options: 403, diangkat DARI DALAM service justru karena
 *     middleware prefix ini menerima EMPAT peran demi ketiga rute data.
 *   - rute WEB /reports/process-water: 403 — dibuktikan di
 *     tests/Feature/Livewire/LaporanProcessWaterTest.php, karena akses API
 *     untuk kembaran mobile tidak boleh melebarkan layar web.
 *
 * SKENARIO 23 ADALAH PENJAGA KEBOCORAN LINTAS MILL: business_unit_id kiriman
 * klien DIBUANG bagi peran terikat mill (200 berisi data mill akun, SENGAJA
 * bukan 403 — 403 justru akan menegaskan mill lain itu ada), sementara
 * production_line_id dan period_id milik mill lain memang 403.
 *
 * ────────────────────────────────────────────────────────────────────────
 * TIGA KETIADAAN YANG DIASERSI ATAS NAMA KUNCI, BUKAN ATAS PROSA
 * ────────────────────────────────────────────────────────────────────────
 * Payload ini memuat DUA kalimat manusia (`coverage._note_filled_rule` dan
 * `_note_absences`) yang justru membahas ketiadaan sebaran kategoris,
 * downtime, ambang batas, dan dimensi giliran kerja. Memindai teks respons
 * akan menjawab pertanyaan yang salah — ia bisa memerahkan asersi yang benar,
 * atau lulus semata karena implementasinya memilih kata lain. Setiap asersi
 * KETIADAAN di berkas ini dijalankan atas NAMA KUNCI lewat
 * laporanProcessWaterAllKeys().
 *
 * FIXTURE-NYA SENGAJA TIDAK RATA: kolom diisi pada HIMPUNAN SLOT YANG
 * BERBEDA-BEDA, sehingga satu penyebut bersama tidak dapat lolos tanpa
 * terlihat.
 *
 * TANGGALNYA SELALU RELATIF (now()->subDays(...)): periode uji di bawah harus
 * SELALU sudah selesai agar days_counted-nya deterministik, dan tanggal yang
 * dipaku berhenti memenuhi syarat itu begitu kalender melewatinya. Tanggal
 * record pun SELALU DIPAKU lewat ->onDate(): default
 * ProcessWaterRecordFactory adalah dateTimeBetween('-30 days','now'), dan
 * asersi "tepat N baris" yang menumpang default itu gagal sesekali karena
 * alasan yang tidak ada hubungannya dengan layar ini.
 */

use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\ProcessWaterDetail;
use App\Models\ProcessWaterRecord;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use App\Services\ProcessWaterRecordService;
use App\Services\ProcessWaterReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function laporanProcessWaterUrl(string $path, array $query = []): string
{
    return '/api/process-water-reports/'.$path.($query === [] ? '' : '?'.http_build_query($query));
}

function laporanProcessWaterRecord(
    Station $station,
    string $date,
    string $processWaterId = 'PW-1',
    array $attributes = [],
): ProcessWaterRecord {
    return ProcessWaterRecord::factory()
        ->forStation($station)
        ->onDate($date)
        ->create(array_merge(['process_water_id' => $processWaterId, 'note' => 'Catatan harian'], $attributes));
}

function laporanProcessWaterSlot(ProcessWaterRecord $record, string $timeSlot, array $values = []): ProcessWaterDetail
{
    return ProcessWaterDetail::factory()
        ->forRecord($record)
        ->timeSlot($timeSlot)
        ->create($values);
}

/** Isi StreamedResponse, ditangkap. ALIRKAN SEKALI SAJA. */
function laporanProcessWaterStreamed($response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

function laporanProcessWaterCsvLinesOf(string $body): array
{
    return array_values(array_filter(explode("\n", trim($body))));
}

/**
 * Seluruh nama KUNCI di mana pun dalam struktur.
 *
 * DIPAKAI ALIH-ALIH MEMINDAI TEKS: `_note_absences` dan
 * `coverage._note_filled_rule` pada payload ini adalah kalimat manusia yang
 * membahas ketiadaan sebaran, downtime, ambang, dan giliran kerja. Yang
 * ditanyakan adalah apakah sebuah KUNCI ada.
 *
 * @return list<string>
 */
function laporanProcessWaterAllKeys(mixed $value): array
{
    if (! is_array($value)) {
        return [];
    }

    $keys = [];

    foreach ($value as $key => $child) {
        if (is_string($key)) {
            $keys[] = $key;
        }

        $keys = array_merge($keys, laporanProcessWaterAllKeys($child));
    }

    return array_values(array_unique($keys));
}

/**
 * Nama kunci yang TIDAK BOLEH ADA di mana pun pada payload /summary.
 *
 * 'action_taken_status' SENGAJA TIDAK DI SINI — ia memang ada, sebagai daftar
 * teks di dalam `text_entries`. Yang dilarang adalah kunci yang mengubahnya
 * menjadi sebaran.
 *
 * @return list<string>
 */
function laporanProcessWaterForbiddenKeys(): array
{
    return [
        'status_distribution', 'known_values', 'counts', 'count',
        'distribution', 'distributions', 'value_counts', 'status_counts',
        'by_status', 'by_value', 'categories', 'category_counts',
        'action_taken_distribution', 'findings_distribution',
        'share', 'shares', 'grouped',
        'out_of_range', 'is_out_of_range', 'threshold', 'thresholds', 'target',
        'targets', 'violation', 'violations', 'severity', 'flag', 'flags',
        'warning', 'warnings', 'breach', 'exceeds', 'critical_limit',
        'nominal', 'safe_range', 'anomaly', 'anomalies', 'operational_target',
        'downtime', 'downtime_minutes', 'downtime_events', 'total_minutes',
        'stop_events', 'stoppages',
        'shift', 'shifts', 'by_shift', 'shift_distribution', 'per_shift',
        'inspector_name', 'inspector_names', 'inspector_user_id', 'inspectors',
    ];
}

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->processWater()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->processWater()->create();

    $this->lineA = (string) $this->stationA->production_line_id;
    $this->lineB = (string) $this->stationB->production_line_id;

    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    // Periode yang SUDAH SELESAI: 10 hari, days_counted === days_in_period.
    $this->periodStart = now()->subDays(20)->toDateString();
    $this->periodEnd = now()->subDays(11)->toDateString();

    $this->day1 = now()->subDays(19)->toDateString();
    $this->day2 = now()->subDays(18)->toDateString();
    $this->day3 = now()->subDays(17)->toDateString();
    $this->dayBeforePeriod = now()->subDays(21)->toDateString();
    $this->dayAfterPeriod = now()->subDays(10)->toDateString();

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::ProcessWater->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Process Water Alpha')
        ->create();

    $this->slots = ProcessWaterRecordService::canonicalTimeSlots();
});

// =====================================================================
// Skenario 1-2: jalur sukses
// =====================================================================

it('skenario 1 — Supervisor dan Mill Management: periods + summary + export mill sendiri', function () {
    $record = laporanProcessWaterRecord($this->stationA, $this->day2, 'PW-1');

    // Aliran air baku pada 4 slot; pH masuk softener hanya pada 2 — penyebut
    // yang BERBEDA itulah yang membuat asersi di bawah bermakna.
    foreach (range(0, 3) as $index) {
        $values = ['raw_water_flow_m3h' => 100.0 + $index];

        if ($index < 2) {
            $values['softener_inlet_ph'] = 7.0 + $index / 10;
        }

        laporanProcessWaterSlot($record, $this->slots[$index], $values);
    }

    foreach ([$this->supervisor, $this->millManagement] as $user) {
        $periods = $this->actingAs($user, 'web')->getJson(laporanProcessWaterUrl('periods'));
        $periods->assertOk();
        expect(collect($periods->json('data'))->pluck('id'))->toContain((string) $this->periodA->id);
        // BERTANDA HUBUNG: kunci snake_case akan ter-compile, tampak benar,
        // dan tidak pernah cocok dengan satu baris master pun.
        expect(collect($periods->json('data'))->pluck('station_type'))->toContain('process-water');

        $summary = $this->actingAs($user, 'web')->getJson(laporanProcessWaterUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]));
        $summary->assertOk();

        expect($summary->json('business_unit.name'))->toBe('Mill Alpha');
        expect($summary->json('production_line.id'))->toBe($this->lineA);
        expect($summary->json('has_data'))->toBeTrue();
        expect($summary->json('coverage.filled_slots'))->toBe(4);
        expect($summary->json('coverage.slots_per_unit_per_day'))->toBe(24);

        // SEMBILAN kunci metrik — kedua kolom teks TIDAK di antaranya.
        expect($summary->json('metrics'))->toHaveCount(9);
        expect($summary->json('metrics.action_taken_status'))->toBeNull();
        expect($summary->json('metrics.findings'))->toBeNull();

        // PENYEBUT PER KOLOM, bukan satu penyebut bersama.
        expect($summary->json('metrics.raw_water_flow_m3h.reading_count'))->toBe(4);
        expect($summary->json('metrics.softener_inlet_ph.reading_count'))->toBe(2);
        expect($summary->json('metrics.boiler_feed_tds_ppm.reading_count'))->toBe(0);
        expect($summary->json('metrics.raw_water_flow_m3h.group'))->toBe('water_flow');
        expect($summary->json('metrics.softener_inlet_ph.group'))->toBe('softener');

        // DUA daftar teks bebas TERPISAH, selalu ada sebagai dua kunci.
        expect($summary->json('text_entries'))->toHaveKey('action_taken_status');
        expect($summary->json('text_entries'))->toHaveKey('findings');
        // Rekap per inspector DITERBITKAN — penyimpangan sadar dari preseden
        // Storage Tank, diminta pemilik produk 2026-10-16.
        expect($summary->json())->toHaveKey('inspector_recap');

        // DAN NOL kunci sebaran kategoris di seluruh payload.
        $keys = laporanProcessWaterAllKeys($summary->json());

        foreach (laporanProcessWaterForbiddenKeys() as $forbidden) {
            $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
        }
    }

    $export = $this->actingAs($this->supervisor, 'web')->get(laporanProcessWaterUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));
    $export->assertOk();
    expect($export->headers->get('content-type'))->toContain('text/csv');
});

it('skenario 2 — Admin: pemilih mill lalu angka mill yang DIPILIH saja', function () {
    $recordA = laporanProcessWaterRecord($this->stationA, $this->day2, 'PW-A');
    laporanProcessWaterSlot($recordA, '07:00', ['raw_water_flow_m3h' => 120.0]);

    $recordB = laporanProcessWaterRecord($this->stationB, $this->day2, 'PW-B');
    laporanProcessWaterSlot($recordB, '07:00', ['raw_water_flow_m3h' => 9999.0]);

    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::ProcessWater->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Process Water Beta')
        ->create();

    $options = $this->actingAs($this->admin, 'web')->getJson(laporanProcessWaterUrl('business-units/options'));
    $options->assertOk();

    $ids = collect($options->json('data'))->pluck('id');

    expect($ids)->toContain((string) $this->businessUnitA->id);
    expect($ids)->toContain((string) $this->businessUnitB->id);

    $periods = $this->actingAs($this->admin, 'web')->getJson(laporanProcessWaterUrl('periods', [
        'business_unit_id' => (string) $this->businessUnitB->id,
    ]));
    $periods->assertOk();
    expect(collect($periods->json('data'))->pluck('id')->all())->toBe([(string) $periodB->id]);

    $summary = $this->actingAs($this->admin, 'web')->getJson(laporanProcessWaterUrl('summary', [
        'business_unit_id' => (string) $this->businessUnitB->id,
        'period_id' => (string) $periodB->id,
        'production_line_id' => $this->lineB,
    ]));
    $summary->assertOk();

    // Cabang Admin TETAP menghormati business_unit_id kiriman.
    expect($summary->json('business_unit.name'))->toBe('Mill Beta');
    // JSON memulangkan float bulat sebagai int, jadi dibandingkan secara
    // longgar — yang load-bearing angkanya, bukan tipe transportnya.
    expect($summary->json('metrics.raw_water_flow_m3h.avg'))->toEqual(9999.0);
    expect(collect($summary->json('by_unit'))->pluck('process_water_id')->all())->toBe(['PW-B']);

    $export = $this->actingAs($this->admin, 'web')->get(laporanProcessWaterUrl('export', [
        'business_unit_id' => (string) $this->businessUnitB->id,
        'period_id' => (string) $periodB->id,
        'production_line_id' => $this->lineB,
        'format' => 'csv',
    ]));
    $export->assertOk();

    $csv = laporanProcessWaterStreamed($export->baseResponse);

    expect($csv)->toContain('Mill Beta');
    expect($csv)->toContain('PW-B');
    expect($csv)->not->toContain('Mill Alpha');
    expect($csv)->not->toContain('PW-A');
});

// =====================================================================
// Skenario 3-6: prasyarat pemilihan
// =====================================================================

it('skenario 3 — Production Line belum dipilih: 422 VALIDATION_ERROR dan NOL angka', function () {
    laporanProcessWaterSlot(
        laporanProcessWaterRecord($this->stationA, $this->day2, 'PW-1'),
        '07:00',
        ['raw_water_flow_m3h' => 120.0],
    );

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanProcessWaterUrl('summary', [
        'period_id' => (string) $this->periodA->id,
    ]));

    $summary->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonValidationErrors('production_line_id');

    // TIDAK PERNAH di-default ke "seluruh line mill": payload galat tidak
    // membawa satu angka laporan pun.
    expect($summary->json())->not->toHaveKey('coverage');
    expect($summary->json())->not->toHaveKey('metrics');

    // Dan ekspornya menolak dengan alasan yang sama.
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanProcessWaterUrl('export', ['period_id' => (string) $this->periodA->id]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('production_line_id');
});

it('skenario 4 — Admin memilih mill lebih dulu: 422 pada periods dan pada summary', function () {
    $this->actingAs($this->admin, 'web')
        ->getJson(laporanProcessWaterUrl('periods'))
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonValidationErrors('business_unit_id');

    $this->actingAs($this->admin, 'web')
        ->getJson(laporanProcessWaterUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('business_unit_id');
});

it('skenario 5 — Admin mengganti mill: daftar periode mengikuti mill yang BARU', function () {
    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::ProcessWater->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Process Water Beta')
        ->create();

    $first = $this->actingAs($this->admin, 'web')->getJson(laporanProcessWaterUrl('periods', [
        'business_unit_id' => (string) $this->businessUnitA->id,
    ]));
    $first->assertOk();
    expect(collect($first->json('data'))->pluck('id')->all())->toBe([(string) $this->periodA->id]);

    $second = $this->actingAs($this->admin, 'web')->getJson(laporanProcessWaterUrl('periods', [
        'business_unit_id' => (string) $this->businessUnitB->id,
    ]));
    $second->assertOk();

    // Periode mill LAMA tidak pernah ikut terbawa — kedua daftar tidak
    // beririsan sama sekali.
    expect(collect($second->json('data'))->pluck('id')->all())->toBe([(string) $periodB->id]);
    expect(collect($second->json('data'))->pluck('id'))->not->toContain((string) $this->periodA->id);
});

it('skenario 6 — mill belum punya periode Process Water: data [] dengan 200, bukan 404', function () {
    // Mill A punya periode jenis LAIN, dan tidak satu pun yang mencakup
    // Process Water.
    Period::query()->delete();

    Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::EngineRoom->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Engine Room')
        ->create();

    $periods = $this->actingAs($this->supervisor, 'web')->getJson(laporanProcessWaterUrl('periods'));

    $periods->assertOk();
    expect($periods->json('data'))->toBe([]);
});

// =====================================================================
// Skenario 7-10: aturan "terisi" dan penyebut per metrik
// =====================================================================

it('skenario 7 — periode tanpa data: has_data false, kesembilan metrik null dengan penyebut 0', function () {
    $empty = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::ProcessWater->value)
        ->range(now()->subDays(40)->toDateString(), now()->subDays(31)->toDateString())
        ->open()
        ->named('Periode Tanpa Data')
        ->create();

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanProcessWaterUrl('summary', [
        'period_id' => (string) $empty->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    expect($summary->json('has_data'))->toBeFalse();
    // Blok cakupan TETAP diterbitkan — kelengkapan pencatatan adalah ISI
    // laporan, bukan metadata.
    expect($summary->json())->toHaveKey('coverage');
    expect($summary->json('coverage.filled_slots'))->toBe(0);
    expect($summary->json('metrics'))->toHaveCount(9);

    foreach (ProcessWaterReportService::NUMERIC_METRICS as $column) {
        expect($summary->json("metrics.{$column}.avg"))->toBeNull();
        expect($summary->json("metrics.{$column}.min"))->toBeNull();
        expect($summary->json("metrics.{$column}.max"))->toBeNull();
        expect($summary->json("metrics.{$column}.reading_count"))->toBe(0);
    }

    // Tren harian TIDAK menggambar titik nol.
    expect($summary->json('daily_trend.raw_water_flow_m3h'))->toBe([]);
    expect($summary->json('daily'))->toBe([]);
});

it('skenario 8 — sebuah metrik tidak pernah diisi: null dengan penyebut 0, 8 metrik lain tetap berangka', function () {
    $record = laporanProcessWaterRecord($this->stationA, $this->day2, 'PW-1');

    $values = array_fill_keys(ProcessWaterReportService::NUMERIC_METRICS, 7.5);
    unset($values['boiler_feed_tds_ppm']);

    laporanProcessWaterSlot($record, '07:00', $values);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanProcessWaterUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    // Kartunya tetap ADA di payload, dengan null dan penyebut nol — bukan
    // dihapus, dan bukan 0 yang akan terbaca sebagai hasil pengukuran.
    expect($summary->json('metrics'))->toHaveCount(9);
    expect($summary->json('metrics.boiler_feed_tds_ppm.avg'))->toBeNull();
    expect($summary->json('metrics.boiler_feed_tds_ppm.reading_count'))->toBe(0);
    expect($summary->json('metrics.boiler_feed_tds_ppm.group'))->toBe('boiler_feed');

    foreach (ProcessWaterReportService::NUMERIC_METRICS as $column) {
        if ($column === 'boiler_feed_tds_ppm') {
            continue;
        }

        expect($summary->json("metrics.{$column}.reading_count"))->toBe(1);
        expect($summary->json("metrics.{$column}.avg"))->toEqual(7.5);
    }
});

it('skenario 9 — slot yang hanya mengisi kolom teks bebas: slot terisi MELEBIHI tiap penyebut', function () {
    $record = laporanProcessWaterRecord($this->stationA, $this->day2, 'PW-1');

    foreach (array_slice($this->slots, 0, 5) as $index => $slot) {
        laporanProcessWaterSlot($record, $slot, ['findings' => 'Temuan '.$index]);
    }

    foreach (array_slice($this->slots, 5, 2) as $index => $slot) {
        laporanProcessWaterSlot($record, $slot, ['raw_water_flow_m3h' => 100.0 + $index]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanProcessWaterUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    $filled = $summary->json('coverage.filled_slots');
    $denominators = collect($summary->json('metrics'))->pluck('reading_count');

    expect($filled)->toBe(7);
    expect($denominators->max())->toBe(2);
    // KETIDAKSAMAAN-nya yang diasersi.
    expect($filled)->toBeGreaterThan($denominators->max());

    // Dan payload MENYATAKAN sebabnya bersama angkanya.
    expect($summary->json('coverage'))->toHaveKey('_note_filled_rule');
    $this->assertStringContainsString(
        'MELEBIHI',
        $summary->json('coverage._note_filled_rule'),
        'keterangan aturan tidak menyatakan bahwa filled_slots dapat melebihi reading_count',
    );
});

it('skenario 10 — satu slot hanya mengisi inspector_id dan satu slot lain hanya findings: filled_slots 1', function () {
    // JEBAKAN SEBELAS-BUKAN-TIGA-BELAS, pada permukaan HTTP. Keduanya
    // ditanam DI DALAM SATU RECORD: implementasi yang menghitung ke-13 kolom
    // memulangkan 2 dan MEMERAH di sini.
    $record = laporanProcessWaterRecord($this->stationA, $this->day2, 'PW-1');

    laporanProcessWaterSlot($record, '07:00', ['inspector_id' => 'INS-01']);
    laporanProcessWaterSlot($record, '08:00', ['findings' => 'Keruh di bak pengendap']);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanProcessWaterUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    expect($summary->json('coverage.filled_slots'))->toBe(1);
    expect($summary->json('has_data'))->toBeTrue();
    expect($summary->json('text_entries.findings'))->toHaveCount(1);
    expect($summary->json('text_entries.findings.0.time_slot'))->toBe('08:00');
    // Slot ber-inspector_id SAJA bukan slot terisi, jadi ia tidak masuk rekap
    // inspector sama sekali.
    expect($summary->json('inspector_recap.entries'))->toBe([]);
    expect($summary->json('inspector_recap.recorded_slots'))->toBe(1);
    expect($summary->json('inspector_recap.not_recorded'))->toBe(1);

    // Dan slot yang hanya mengisi shift pun tidak terhitung.
    laporanProcessWaterSlot($record, '09:00', ['shift' => 'Shift 1']);

    $again = $this->actingAs($this->supervisor, 'web')->getJson(laporanProcessWaterUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $again->assertOk();
    expect($again->json('coverage.filled_slots'))->toBe(1);
});

// =====================================================================
// Skenario 11-14: dua kolom teks, dan NOL sebaran kategoris
// =====================================================================

it('skenario 11 — catatan tindakan diterbitkan sebagai TEKS, bukan sebaran', function () {
    $record = laporanProcessWaterRecord($this->stationA, $this->day2, 'PW-1');

    foreach ([
        'Dosis alum dinaikkan menjadi 12 kg/jam',
        'Backwash softener dijalankan',
        'Lapor ke supervisor, menunggu arahan',
    ] as $index => $text) {
        laporanProcessWaterSlot($record, $this->slots[$index], ['action_taken_status' => $text]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanProcessWaterUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    $entries = $summary->json('text_entries.action_taken_status');

    expect($entries)->toHaveCount(3);

    foreach ($entries as $entry) {
        expect(array_keys($entry))->toBe(['date', 'time_slot', 'text']);
    }

    // NOL kunci sebaran kategoris di SELURUH payload — diasersi atas NAMA
    // KUNCI. `_note_absences` adalah kalimat manusia tentang ketiadaan itu,
    // jadi pemindaian teks akan menjawab pertanyaan yang salah.
    $keys = laporanProcessWaterAllKeys($summary->json());

    foreach ([
        'status_distribution', 'known_values', 'counts', 'distribution',
        'value_counts', 'status_counts', 'by_status', 'by_value',
        'action_taken_distribution', 'categories',
    ] as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
    }

    // Dan ia tidak pernah menjadi metrik maupun seri tren.
    expect($summary->json('metrics'))->not->toHaveKey('action_taken_status');
    expect($summary->json('daily_trend'))->not->toHaveKey('action_taken_status');
    expect(array_keys($summary->json('daily_trend')))->toBe(['raw_water_flow_m3h']);
});

it('skenario 12 — isi catatan tindakan berulang sama persis tidak dikelompokkan', function () {
    $record = laporanProcessWaterRecord($this->stationA, $this->day2, 'PW-1');

    foreach (array_slice($this->slots, 0, 20) as $slot) {
        laporanProcessWaterSlot($record, $slot, ['action_taken_status' => 'Sudah ditangani']);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanProcessWaterUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    $entries = $summary->json('text_entries.action_taken_status');

    // DUA PULUH entri terpisah, bukan satu entri berjumlah dua puluh.
    expect($entries)->toHaveCount(20);
    expect(collect($entries)->pluck('text')->unique()->all())->toBe(['Sudah ditangani']);
    expect(collect($entries)->pluck('time_slot')->unique())->toHaveCount(20);
});

it('skenario 13 — kedua kolom teks kosong: dua daftar kosong yang tetap dua kunci terpisah', function () {
    $record = laporanProcessWaterRecord($this->stationA, $this->day2, 'PW-1');
    laporanProcessWaterSlot($record, '07:00', ['raw_water_flow_m3h' => 120.0]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanProcessWaterUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    expect(array_keys($summary->json('text_entries')))->toBe(['action_taken_status', 'findings']);
    expect($summary->json('text_entries.action_taken_status'))->toBe([]);
    expect($summary->json('text_entries.findings'))->toBe([]);
});

it('skenario 14 — satu slot mengisi kedua kolom teks: muncul SEKALI di masing-masing daftar', function () {
    $record = laporanProcessWaterRecord($this->stationA, $this->day2, 'PW-1');

    laporanProcessWaterSlot($record, '07:00', [
        'action_taken_status' => 'Backwash softener',
        'findings' => 'Kesadahan keluaran naik',
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanProcessWaterUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    expect($summary->json('text_entries.action_taken_status'))->toHaveCount(1);
    expect($summary->json('text_entries.findings'))->toHaveCount(1);
    expect($summary->json('text_entries.action_taken_status.0.time_slot'))->toBe('07:00');
    expect($summary->json('text_entries.findings.0.time_slot'))->toBe('07:00');
    // BUKAN penghitungan ganda: slotnya tetap satu.
    expect($summary->json('coverage.filled_slots'))->toBe(1);

    // Dan tidak ada daftar KETIGA yang menyatukan keduanya.
    foreach (['text_entries_combined', 'notes', 'remarks', 'all_texts'] as $forbidden) {
        $this->assertNotContains($forbidden, laporanProcessWaterAllKeys($summary->json()));
    }
});

// =====================================================================
// Skenario 15-17: rekap per inspector dan rekap per unit
// =====================================================================

it('skenario 15 — seluruh slot tidak mencatat inspector: entries kosong, not_recorded memuat semuanya', function () {
    $record = laporanProcessWaterRecord($this->stationA, $this->day2, 'PW-1');

    foreach (array_slice($this->slots, 0, 18) as $index => $slot) {
        laporanProcessWaterSlot($record, $slot, ['raw_water_flow_m3h' => 100.0 + $index]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanProcessWaterUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    // Rekapnya TETAP diterbitkan sebagai tiga kunci, supaya layar dapat
    // MENYATAKAN keadaannya alih-alih merender rekap kosong tanpa penjelasan.
    expect(array_keys($summary->json('inspector_recap')))->toBe(['entries', 'not_recorded', 'recorded_slots']);
    expect($summary->json('inspector_recap.entries'))->toBe([]);
    expect($summary->json('inspector_recap.not_recorded'))->toBe(18);
    expect($summary->json('inspector_recap.recorded_slots'))->toBe(18);
});

it('skenario 16 — satu inspector mengisi seluruh periode: satu entri, kode MENTAH, tanpa nama', function () {
    $record = laporanProcessWaterRecord($this->stationA, $this->day2, 'PW-1');

    // Kode yang tidak dikenal master mana pun, dan sengaja TIDAK menyerupai
    // nama pengguna: ia diterbitkan apa adanya.
    foreach (array_slice($this->slots, 0, 6) as $index => $slot) {
        laporanProcessWaterSlot($record, $slot, [
            'inspector_id' => '1180734',
            'raw_water_flow_m3h' => 100.0 + $index,
        ]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanProcessWaterUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    expect($summary->json('inspector_recap.entries'))->toBe([
        ['inspector_id' => '1180734', 'filled_slots' => 6],
    ]);
    expect($summary->json('inspector_recap.not_recorded'))->toBe(0);
    expect($summary->json('inspector_recap.recorded_slots'))->toBe(6);

    // Tidak ada nama yang dipulangkan, dan tidak ada penanda apa pun — satu
    // inspector mengisi seluruh periode adalah keadaan SAH.
    foreach ($summary->json('inspector_recap.entries') as $entry) {
        expect(array_keys($entry))->toBe(['inspector_id', 'filled_slots']);
    }

    foreach (['inspector_name', 'inspector_names', 'inspector_user_id', 'name'] as $forbidden) {
        $this->assertNotContains(
            $forbidden,
            laporanProcessWaterAllKeys($summary->json('inspector_recap')),
            "rekap inspector tidak boleh punya kunci {$forbidden}",
        );
    }
});

it('skenario 17 — line punya beberapa unit: satu baris per unit, unit tanpa pembacaan tetap muncul', function () {
    $satu = laporanProcessWaterRecord($this->stationA, $this->day2, 'PW-1');
    laporanProcessWaterSlot($satu, '07:00', ['raw_water_flow_m3h' => 100.0]);
    laporanProcessWaterSlot($satu, '08:00', ['raw_water_flow_m3h' => 140.0]);

    $dua = laporanProcessWaterRecord($this->stationA, $this->day2, 'PW-2');
    laporanProcessWaterSlot($dua, '07:00', ['shift' => 'Shift 1']);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanProcessWaterUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    $byUnit = collect($summary->json('by_unit'))->keyBy('process_water_id');

    expect($byUnit)->toHaveCount(2);
    expect($byUnit['PW-1']['reading_rows'])->toBe(2);
    expect($byUnit['PW-2']['reading_rows'])->toBe(0);
    expect($byUnit['PW-2']['metrics'])->toHaveCount(9);

    // Unit tanpa pembacaan ikut menambah unit_count, sehingga expected_slots
    // tetap benar.
    expect($summary->json('coverage.process_water_unit_count'))->toBe(2);
    expect($summary->json('coverage.expected_slots'))->toBe(2 * 10 * 24);

    // Satu definisi, satu jawaban.
    expect(collect($summary->json('by_unit'))->sum('reading_rows'))
        ->toBe($summary->json('coverage.filled_slots'));
    expect(collect($summary->json('daily'))->sum('reading_rows'))
        ->toBe($summary->json('coverage.filled_slots'));
});

// =====================================================================
// Skenario 18-21: kelengkapan pencatatan dan ketiadaan penandaan
// =====================================================================

it('skenario 18 — pencatatan sangat tidak lengkap: angka tetap diterbitkan beserta persen kecilnya', function () {
    $record = laporanProcessWaterRecord($this->stationA, $this->day2, 'PW-1');
    laporanProcessWaterSlot($record, '07:00', ['raw_water_flow_m3h' => 120.0]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanProcessWaterUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    expect($summary->json('coverage.filled_slots'))->toBe(1);
    expect($summary->json('coverage.expected_slots'))->toBe(240);
    expect($summary->json('coverage.coverage_percent'))->toEqual(round(100 / 240, 1));
    // Angkanya TETAP diterbitkan — kelengkapan rendah bukan alasan menahan
    // laporannya.
    expect($summary->json('metrics.raw_water_flow_m3h.avg'))->toEqual(120.0);
    expect($summary->json('metrics.raw_water_flow_m3h.reading_count'))->toBe(1);
});

it('skenario 19 — periode masih berjalan: days_counted berhenti di hari ini', function () {
    $running = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::ProcessWater->value)
        ->range(now()->subDays(9)->toDateString(), now()->addDays(20)->toDateString())
        ->open()
        ->named('Periode Berjalan')
        ->create();

    laporanProcessWaterSlot(
        laporanProcessWaterRecord($this->stationA, now()->subDays(8)->toDateString(), 'PW-1'),
        '07:00',
        ['raw_water_flow_m3h' => 120.0],
    );

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanProcessWaterUrl('summary', [
        'period_id' => (string) $running->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    expect($summary->json('coverage.period_running'))->toBeTrue();
    expect($summary->json('coverage.days_in_period'))->toBe(30);
    // BERHENTI DI HARI INI: 30 hari periode, 10 hari yang sudah lewat.
    expect($summary->json('coverage.days_counted'))->toBe(10);
    expect($summary->json('coverage.expected_slots'))->toBe(240);
});

it('skenario 20 — tidak ada slot yang diharapkan: coverage_percent 0.0 dari service', function () {
    $future = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::ProcessWater->value)
        ->range(now()->addDays(40)->toDateString(), now()->addDays(50)->toDateString())
        ->open()
        ->named('Periode Belum Mulai')
        ->create();

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanProcessWaterUrl('summary', [
        'period_id' => (string) $future->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    expect($summary->json('coverage.days_counted'))->toBe(0);
    expect($summary->json('coverage.expected_slots'))->toBe(0);
    // 0.0 dari service, TANPA pembagian nol. Layar menampilkan '—' pada
    // keadaan ini — dan ITU dibuktikan di berkas Livewire, bukan di sini.
    expect($summary->json('coverage.coverage_percent'))->toEqual(0.0);
});

it('skenario 21 — pH dan TDS jauh di luar batas umum: diterbitkan apa adanya, NOL kunci penandaan', function () {
    $record = laporanProcessWaterRecord($this->stationA, $this->day2, 'PW-1');

    laporanProcessWaterSlot($record, '07:00', ['boiler_feed_water_ph' => 4.2, 'boiler_feed_tds_ppm' => 6000.0]);
    laporanProcessWaterSlot($record, '08:00', ['boiler_feed_water_ph' => 11.8, 'boiler_feed_tds_ppm' => 12.0]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanProcessWaterUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    expect($summary->json('metrics.boiler_feed_water_ph.min'))->toEqual(4.2);
    expect($summary->json('metrics.boiler_feed_tds_ppm.max'))->toEqual(6000.0);

    $keys = laporanProcessWaterAllKeys($summary->json());

    foreach ([
        'out_of_range', 'is_out_of_range', 'threshold', 'thresholds', 'target',
        'targets', 'violation', 'violations', 'severity', 'flag', 'flags',
        'warning', 'warnings', 'breach', 'exceeds', 'critical_limit',
        'nominal', 'safe_range', 'anomaly', 'anomalies',
    ] as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
    }

    // Bentuk entri metriknya pun TETAP lima kunci.
    foreach ($summary->json('metrics') as $metric) {
        expect(array_keys($metric))->toBe(['min', 'avg', 'max', 'reading_count', 'group']);
    }
});

// =====================================================================
// Skenario 22-25: akses
// =====================================================================

it('skenario 22 — akun terikat mill tanpa business_unit_id: 422 dan daftar mill tidak pernah ditawarkan', function () {
    $supervisorNoMill = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $this->actingAs($supervisorNoMill, 'web')
        ->getJson(laporanProcessWaterUrl('periods'))
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonValidationErrors('business_unit_id');

    // GAGAL TERTUTUP: /business-units/options tetap 403 baginya, jadi daftar
    // seluruh mill tidak pernah ditawarkan sebagai gantinya.
    $this->actingAs($supervisorNoMill, 'web')
        ->getJson(laporanProcessWaterUrl('business-units/options'))
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');
});

it('skenario 23 — mencoba melihat mill lain: business_unit_id DIBUANG (200), periode mill lain 403', function () {
    $recordA = laporanProcessWaterRecord($this->stationA, $this->day2, 'PW-A');
    laporanProcessWaterSlot($recordA, '07:00', ['raw_water_flow_m3h' => 120.0]);

    $recordB = laporanProcessWaterRecord($this->stationB, $this->day2, 'PW-B');
    laporanProcessWaterSlot($recordB, '07:00', ['raw_water_flow_m3h' => 9999.0]);

    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::ProcessWater->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Process Water Beta')
        ->create();

    // Langkah 1 — business_unit_id mill B DIBUANG: 200 berisi angka mill A.
    // SENGAJA bukan 403: 403 justru akan menegaskan mill lain itu ada.
    $leak = $this->actingAs($this->supervisor, 'web')->getJson(laporanProcessWaterUrl('summary', [
        'business_unit_id' => (string) $this->businessUnitB->id,
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $leak->assertOk();
    expect($leak->json('business_unit.id'))->toBe((string) $this->businessUnitA->id);
    expect($leak->json('business_unit.name'))->toBe('Mill Alpha');
    expect($leak->json('metrics.raw_water_flow_m3h.max'))->toEqual(120.0);
    expect(collect($leak->json('by_unit'))->pluck('process_water_id')->all())->toBe(['PW-A']);

    // Langkah 2 — periode mill B dengan line mill SENDIRI: 403 FORBIDDEN.
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanProcessWaterUrl('summary', [
            'period_id' => (string) $periodB->id,
            'production_line_id' => $this->lineA,
        ]))
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');

    // Dan period_id yang tidak ada → 404 NOT_FOUND, bukan 403.
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanProcessWaterUrl('summary', [
            'period_id' => '00000000-0000-0000-0000-000000000000',
            'production_line_id' => $this->lineA,
        ]))
        ->assertStatus(404)
        ->assertJsonPath('code', 'NOT_FOUND');
});

it('skenario 24 — mencoba melihat line mill lain: 403 pada summary dan pada export', function () {
    laporanProcessWaterSlot(
        laporanProcessWaterRecord($this->stationB, $this->day2, 'PW-B'),
        '07:00',
        ['raw_water_flow_m3h' => 9999.0],
    );

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanProcessWaterUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineB,
    ]));

    $summary->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
    // NOL angka milik line mill lain yang ikut terbawa.
    expect($summary->json())->not->toHaveKey('coverage');
    expect($summary->json())->not->toHaveKey('metrics');

    // Dan ekspornya ditolak EAGER — bukan berkas separuh berisi header.
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanProcessWaterUrl('export', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineB,
            'format' => 'csv',
        ]))
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');
});

it('skenario 25 — Operator: 200 pada ketiga rute data, 403 pada /business-units/options', function () {
    $record = laporanProcessWaterRecord($this->stationA, $this->day2, 'PW-1');
    laporanProcessWaterSlot($record, '07:00', ['raw_water_flow_m3h' => 120.0, 'inspector_id' => 'INS-01']);

    // Langkah 1 — /periods: 200.
    $periods = $this->actingAs($this->operator, 'web')->getJson(laporanProcessWaterUrl('periods'));
    $periods->assertOk();

    $periodId = collect($periods->json('data'))->pluck('id')->first();

    expect($periodId)->toBe((string) $this->periodA->id);

    // Langkah 2 — /summary: 200, dengan payload yang IDENTIK bentuknya dengan
    // yang dilihat Supervisor.
    $summary = $this->actingAs($this->operator, 'web')->getJson(laporanProcessWaterUrl('summary', [
        'period_id' => $periodId,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    $asSupervisor = $this->actingAs($this->supervisor, 'web')->getJson(laporanProcessWaterUrl('summary', [
        'period_id' => $periodId,
        'production_line_id' => $this->lineA,
    ]));
    $asSupervisor->assertOk();

    expect($summary->json())->toEqual($asSupervisor->json());

    // Langkah 2b — /export: 200 juga.
    $this->actingAs($this->operator, 'web')
        ->get(laporanProcessWaterUrl('export', [
            'period_id' => $periodId,
            'production_line_id' => $this->lineA,
            'format' => 'csv',
        ]))
        ->assertOk();

    // Langkah 3 — /business-units/options: 403, diangkat DARI DALAM service
    // justru karena middleware prefix ini menerima EMPAT peran.
    $this->actingAs($this->operator, 'web')
        ->getJson(laporanProcessWaterUrl('business-units/options'))
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');

});

it('skenario 25b — tamu: 401 UNAUTHENTICATED pada keempat rute', function () {
    // BERKAS TERPISAH DARI SKENARIO 25 DENGAN SENGAJA: $this->actingAs()
    // bertahan sepanjang satu test, jadi memeriksa "tanpa sesi" di dalam test
    // yang sudah masuk sebagai Operator justru akan mengukur 403-nya Operator
    // dan lulus sebagai "ditolak" tanpa membuktikan apa pun tentang 401.
    foreach ([
        laporanProcessWaterUrl('business-units/options'),
        laporanProcessWaterUrl('periods'),
        laporanProcessWaterUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]),
        laporanProcessWaterUrl('export', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]),
    ] as $url) {
        $this->getJson($url)->assertStatus(401);
    }
});

// =====================================================================
// Skenario 26-33: periode tertutup, shift, keanggotaan, penyebut, ekspor
// =====================================================================

it('skenario 26 — periode tertutup: summary dan export tetap 200', function () {
    $closed = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::ProcessWater->value)
        ->range(now()->subDays(40)->toDateString(), now()->subDays(31)->toDateString())
        ->closed($this->admin)
        ->named('Periode Tertutup')
        ->create();

    $record = laporanProcessWaterRecord($this->stationA, now()->subDays(35)->toDateString(), 'PW-TUTUP');
    laporanProcessWaterSlot($record, '07:00', ['raw_water_flow_m3h' => 120.0]);

    // Tetap TERDAFTAR: status tidak pernah menyaring daftar periode.
    $periods = $this->actingAs($this->supervisor, 'web')->getJson(laporanProcessWaterUrl('periods'));
    $periods->assertOk();
    expect(collect($periods->json('data'))->pluck('id'))->toContain((string) $closed->id);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanProcessWaterUrl('summary', [
        'period_id' => (string) $closed->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    expect($summary->json('period.status'))->toBe('closed');
    expect($summary->json('has_data'))->toBeTrue();
    expect($summary->json('metrics'))->toHaveCount(9);

    $export = $this->actingAs($this->supervisor, 'web')->get(laporanProcessWaterUrl('export', [
        'period_id' => (string) $closed->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));

    $export->assertOk();
    expect(laporanProcessWaterStreamed($export->baseResponse))->toContain('PW-TUTUP');
});

it('skenario 27 — kolom shift ABSEN dari payload tetapi ADA di ekspor', function () {
    $record = laporanProcessWaterRecord($this->stationA, $this->day2, 'PW-1');

    // `shift` TERISI di basis data, justru supaya ketiadaannya di payload
    // tidak lolos hanya karena kolomnya kosong.
    laporanProcessWaterSlot($record, '07:00', [
        'shift' => 'Shift 1',
        'inspector_id' => 'INS-01',
        'raw_water_flow_m3h' => 120.0,
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanProcessWaterUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    // ABSEN DARI PAYLOAD — diasersi atas NAMA KUNCI, bukan atas prosa:
    // `_note_absences` membahas ketiadaan dimensi giliran kerja dengan
    // kalimat manusia.
    $keys = laporanProcessWaterAllKeys($summary->json());

    foreach (['shift', 'shifts', 'by_shift', 'shift_distribution', 'per_shift'] as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
    }

    // ADA DI EKSPOR, karena ekspor adalah rincian MENTAH: mengecualikan
    // sebuah kolom dari pemeriksaan slot terisi bukan alasan membuangnya dari
    // berkas.
    $export = $this->actingAs($this->supervisor, 'web')->get(laporanProcessWaterUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));

    $export->assertOk();

    $lines = laporanProcessWaterCsvLinesOf(laporanProcessWaterStreamed($export->baseResponse));
    $header = str_getcsv($lines[0]);
    $row = str_getcsv($lines[1]);

    expect($header)->toBe(ProcessWaterReportService::EXPORT_HEADER);
    expect($header[8])->toBe('Shift');
    expect($header[9])->toBe('Inspector ID');
    expect($row[8])->toBe('Shift 1');
    expect($row[9])->toBe('INS-01');
});

it('skenario 28 — rentang periode inklusif di kedua ujung, pada summary DAN pada ekspor', function () {
    // TANGGALNYA DIPAKU: default ProcessWaterRecordFactory adalah
    // dateTimeBetween('-30 days','now'), dan asersi "tepat N baris" yang
    // menumpang default itu gagal sesekali karena alasan yang tidak ada
    // hubungannya dengan layar ini.
    $awal = laporanProcessWaterRecord($this->stationA, $this->periodStart, 'PW-AWAL');
    laporanProcessWaterSlot($awal, '07:00', ['raw_water_flow_m3h' => 100.0]);

    $akhir = laporanProcessWaterRecord($this->stationA, $this->periodEnd, 'PW-AKHIR');
    laporanProcessWaterSlot($akhir, '07:00', ['raw_water_flow_m3h' => 200.0]);

    $sebelum = laporanProcessWaterRecord($this->stationA, $this->dayBeforePeriod, 'PW-SEBELUM');
    laporanProcessWaterSlot($sebelum, '07:00', ['raw_water_flow_m3h' => 9999.0]);

    $sesudah = laporanProcessWaterRecord($this->stationA, $this->dayAfterPeriod, 'PW-SESUDAH');
    laporanProcessWaterSlot($sesudah, '07:00', ['raw_water_flow_m3h' => 8888.0]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanProcessWaterUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    expect($summary->json('coverage.filled_slots'))->toBe(2);
    expect($summary->json('metrics.raw_water_flow_m3h.reading_count'))->toBe(2);
    expect($summary->json('metrics.raw_water_flow_m3h.min'))->toEqual(100.0);
    expect($summary->json('metrics.raw_water_flow_m3h.max'))->toEqual(200.0);
    expect(collect($summary->json('daily'))->pluck('date')->all())
        ->toBe([$this->periodStart, $this->periodEnd]);

    $export = $this->actingAs($this->supervisor, 'web')->get(laporanProcessWaterUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));

    $export->assertOk();

    $csv = laporanProcessWaterStreamed($export->baseResponse);

    expect($csv)->toContain('PW-AWAL');
    expect($csv)->toContain('PW-AKHIR');
    expect($csv)->not->toContain('PW-SEBELUM');
    expect($csv)->not->toContain('PW-SESUDAH');
    // Header + dua baris slot, dan tidak lebih.
    expect(laporanProcessWaterCsvLinesOf($csv))->toHaveCount(3);
});

it('skenario 29 — setiap kartu punya penyebutnya SENDIRI: himpunan penyebut lebih dari satu nilai', function () {
    $record = laporanProcessWaterRecord($this->stationA, $this->day2, 'PW-1');

    // Kolom ALIRAN AIR diisi pada 4 slot; softener dan air umpan boiler
    // DIKOSONGKAN sama sekali.
    foreach (array_slice($this->slots, 0, 4) as $index => $slot) {
        laporanProcessWaterSlot($record, $slot, [
            'raw_water_flow_m3h' => 100.0 + $index,
            'clarified_water_flow_m3h' => $index < 2 ? 90.0 + $index : null,
            'alum_dosing_kgh' => $index < 1 ? 12.0 : null,
        ]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanProcessWaterUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    $denominators = collect($summary->json('metrics'))->pluck('reading_count');

    // PENYEBUT IDENTIK PADA SEMBILAN KARTU ADALAH CACAT YANG DICARI.
    expect($denominators->unique()->values()->sort()->values()->all())->toBe([0, 1, 2, 4]);
    expect($summary->json('metrics.raw_water_flow_m3h.reading_count'))->toBe(4);
    expect($summary->json('metrics.clarified_water_flow_m3h.reading_count'))->toBe(2);
    expect($summary->json('metrics.alum_dosing_kgh.reading_count'))->toBe(1);
    expect($summary->json('metrics.softener_inlet_ph.reading_count'))->toBe(0);
    expect($summary->json('metrics.boiler_feed_tds_ppm.reading_count'))->toBe(0);

    // Dan rata-ratanya memang rata-rata baris non-null MILIK KARTU ITU
    // SENDIRI — dihitung di PHP dari fixture, bukan lewat SQL.
    expect($summary->json('metrics.raw_water_flow_m3h.avg'))
        ->toEqual(round((100.0 + 101.0 + 102.0 + 103.0) / 4, 3));
    expect($summary->json('metrics.clarified_water_flow_m3h.avg'))
        ->toEqual(round((90.0 + 91.0) / 2, 3));
});

it('skenario 30 — ekspor Excel 200 dengan mime xlsx; format pdf 422 VALIDATION_ERROR', function () {
    $record = laporanProcessWaterRecord($this->stationA, $this->day2, 'PW-1');
    laporanProcessWaterSlot($record, '07:00', [
        'shift' => 'Shift 2',
        'inspector_id' => 'OP-07',
        'raw_water_flow_m3h' => 120.0,
    ]);

    $excel = $this->actingAs($this->supervisor, 'web')->get(laporanProcessWaterUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'excel',
    ]));

    $excel->assertOk();
    // .xlsx SUNGGUHAN lewat App\Support\SheetWriter, bukan CSV bernama xlsx.
    expect($excel->headers->get('content-type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect($excel->headers->get('content-disposition'))->toContain('.xlsx');

    $xlsx = laporanProcessWaterStreamed($excel->baseResponse);

    // Berkas zip sungguhan: xlsx dimulai dengan tanda tangan 'PK'.
    expect(substr($xlsx, 0, 2))->toBe('PK');

    // KE-13 kolom detail hadir di KEDUA format, dalam urutan DETAIL_FIELDS.
    $csv = $this->actingAs($this->supervisor, 'web')->get(laporanProcessWaterUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));

    $csv->assertOk();

    $lines = laporanProcessWaterCsvLinesOf(laporanProcessWaterStreamed($csv->baseResponse));

    expect(str_getcsv($lines[0]))->toBe(ProcessWaterReportService::EXPORT_HEADER);
    expect(ProcessWaterRecordService::DETAIL_FIELDS)->toHaveCount(13);
    expect(count(ProcessWaterReportService::EXPORT_HEADER))->toBe(8 + 13);

    // format di luar csv|excel → 422 sebelum streaming dimulai.
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanProcessWaterUrl('export', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
            'format' => 'pdf',
        ]))
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonValidationErrors('format');
});

it('skenario 31 — laporan tidak memuat blok downtime: nol kunci payload dan nol kolom ekspor', function () {
    $record = laporanProcessWaterRecord($this->stationA, $this->day2, 'PW-1');
    laporanProcessWaterSlot($record, '07:00', array_fill_keys(
        ProcessWaterReportService::NUMERIC_METRICS,
        7.5,
    ));

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanProcessWaterUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    $keys = laporanProcessWaterAllKeys($summary->json());

    foreach ([
        'downtime', 'downtime_minutes', 'downtime_events', 'total_minutes',
        'stop_events', 'stoppages',
    ] as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
    }

    $export = $this->actingAs($this->supervisor, 'web')->get(laporanProcessWaterUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));

    $export->assertOk();

    // Header ekspor pun tanpa kolom downtime — diasersi atas DAFTAR HEADER,
    // bukan atas isi berkas.
    foreach (str_getcsv(laporanProcessWaterCsvLinesOf(laporanProcessWaterStreamed($export->baseResponse))[0]) as $column) {
        expect(strtolower($column))->not->toContain('downtime');
        expect(strtolower($column))->not->toContain('lama berhenti');
    }
});

it('skenario 32 — rekap harian panjang: satu baris per tanggal berdata plus total periode', function () {
    $long = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::ProcessWater->value)
        ->range(now()->subDays(60)->toDateString(), now()->subDays(30)->toDateString())
        ->open()
        ->named('Periode Tiga Puluh Satu Hari')
        ->create();

    foreach (range(0, 30) as $offset) {
        $date = now()->subDays(60 - $offset)->toDateString();

        laporanProcessWaterSlot(
            laporanProcessWaterRecord($this->stationA, $date, 'PW-1'),
            '07:00',
            ['raw_water_flow_m3h' => 100.0 + $offset],
        );
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanProcessWaterUrl('summary', [
        'period_id' => (string) $long->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    expect($summary->json('coverage.days_in_period'))->toBe(31);
    expect($summary->json('daily'))->toHaveCount(31);
    expect($summary->json('total.days_with_records'))->toBe(31);
    expect($summary->json('daily_total.reading_rows'))->toBe(31);
    expect($summary->json('coverage.filled_slots'))->toBe(31);

    // Rekap hariannya membawa SEMBILAN kolom rata-rata per baris — lebih
    // sempit daripada Engine Room yang 23, dan itu yang menentukan lebar
    // tabelnya di layar.
    foreach ($summary->json('daily') as $row) {
        expect(array_keys($row))->toBe(['date', 'reading_rows', 'metrics_avg']);
        expect($row['metrics_avg'])->toHaveCount(9);
    }

    // Satu definisi, satu jawaban.
    expect(collect($summary->json('daily'))->sum('reading_rows'))
        ->toBe($summary->json('coverage.filled_slots'));
});

it('skenario 33 — layar hanya membaca: prefix API tidak punya satu rute tulis pun', function () {
    $record = laporanProcessWaterRecord($this->stationA, $this->day2, 'PW-1');
    laporanProcessWaterSlot($record, '07:00', ['raw_water_flow_m3h' => 120.0]);

    $recordCount = ProcessWaterRecord::query()->count();
    $detailCount = ProcessWaterDetail::query()->count();

    // Diasersi atas DAFTAR RUTE TERDAFTAR, bukan dengan menembak URL: rute
    // yang tidak ada menjawab 404/405 yang tidak membuktikan apa pun tentang
    // niat prefix ini.
    $routes = collect(app('router')->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/process-water-reports'));

    expect($routes)->toHaveCount(4);

    foreach ($routes as $route) {
        expect(array_values(array_diff($route->methods(), ['HEAD'])))->toBe(['GET']);
    }

    // Dan keempat rute dibaca tanpa mengubah satu baris pun.
    $this->actingAs($this->supervisor, 'web')->getJson(laporanProcessWaterUrl('periods'))->assertOk();
    $this->actingAs($this->supervisor, 'web')->getJson(laporanProcessWaterUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]))->assertOk();
    $this->actingAs($this->supervisor, 'web')->get(laporanProcessWaterUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]))->assertOk();

    expect(ProcessWaterRecord::query()->count())->toBe($recordCount);
    expect(ProcessWaterDetail::query()->count())->toBe($detailCount);
});
