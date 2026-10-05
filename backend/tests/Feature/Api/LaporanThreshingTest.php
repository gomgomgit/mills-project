<?php

/**
 * LaporanThreshingTest (API) — screen-148--laporan-threshing-web /
 * screen-149--laporan-threshing-mobile, the four /api/threshing-reports/*
 * endpoints.
 *
 * One test per test_scenarios[].api_test on screen-148's tech spec. The
 * aggregation rules themselves are proven in
 * tests/Unit/Services/ThreshingReportServiceTest.php against the service;
 * what this file proves is the HTTP contract — status codes, who is admitted,
 * what the payload actually carries, and what the exported file looks like.
 *
 * THE FIXTURES ARE DELIBERATELY UNEVEN: columns are filled on DIFFERENT
 * subsets of slots, so a shared denominator cannot pass unnoticed. See the
 * docblock of the unit test for why tidying them would turn these assertions
 * into always-green ones.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\Station;
use App\Models\ThreshingDetail;
use App\Models\ThreshingOperationalTarget;
use App\Models\ThreshingRecord;
use App\Models\User;
use App\Services\StationReportService;
use App\Services\ThreshingRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function laporanThreshingUrl(string $path, array $query = []): string
{
    return '/api/threshing-reports/'.$path.($query === [] ? '' : '?'.http_build_query($query));
}

function laporanThreshingRecord(
    Station $station,
    string $date,
    string $thresherId = 'TH-1',
    array $attributes = [],
): ThreshingRecord {
    return ThreshingRecord::factory()
        ->forStation($station)
        ->onDate($date)
        ->create(array_merge(['thresher_id' => $thresherId, 'note' => 'Catatan harian'], $attributes));
}

function laporanThreshingSlot(ThreshingRecord $record, string $timeSlot, array $values = []): ThreshingDetail
{
    return ThreshingDetail::factory()
        ->forRecord($record)
        ->timeSlot($timeSlot)
        ->create($values);
}

function laporanThreshingSeedTargets(): void
{
    $rows = [
        ['FFB Throughput', 'As per mill capacity design (e.g., 30-60 MT/hr)', 'Adjust feeder conveyor speed.'],
        ['Thresher Drum Speed', '21 - 23 RPM (optimal for separation)', 'Inspect drive belt tension and gearbox alignment.'],
        ['Motor Current', 'Within motor rated full-load current (FLC)', 'Check for drum overloading or wedged bunches.'],
        ['Bearing Temperature', 'Below 70C (Check if >75C)', 'Lubricate bearings / check for mechanical wear.'],
        ['Unstripped Bunch Rate', 'Target: 0% (Action required if >2%)', 'Verify autoclaved sterilization pressure and duration.'],
        ['Empty Bunch (EB) Oil Loss', 'Target: <0.50% on dry basis', 'Check thresher drum bars and inner lifting paddles.'],
    ];

    foreach ($rows as $index => [$parameter, $standard, $action]) {
        ThreshingOperationalTarget::create([
            'parameter' => $parameter,
            'standard_operational_target' => $standard,
            'action_plan_on_deviation' => $action,
            'sort_order' => $index + 1,
        ]);
    }
}

/** The body of a StreamedResponse, captured. STREAM ONCE. */
function laporanThreshingStreamed($response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

function laporanThreshingCsvLinesOf(string $body): array
{
    return array_values(array_filter(explode("\n", trim($body))));
}

/** The metrics entry for one column. */
function laporanThreshingMetric($response, string $column): array
{
    foreach ($response->json('metrics') as $metric) {
        if ($metric['column'] === $column) {
            return $metric;
        }
    }

    throw new RuntimeException("metric {$column} tidak ada pada payload");
}

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->threshing()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->threshing()->create();

    $this->lineA = (string) $this->stationA->production_line_id;
    $this->lineB = (string) $this->stationB->production_line_id;

    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    $this->periodA = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('threshing')
        ->range('2026-09-01', '2026-09-10')->open()->named('Periode September Alpha')->create();

    $this->slots = ThreshingRecordService::canonicalTimeSlots();
});

// =====================================================================
// Skenario 1-2: jalur sukses
// =====================================================================

it('skenario 1 — Supervisor dan Mill Management: periods + summary + export mill sendiri', function () {
    laporanThreshingSeedTargets();

    $record = laporanThreshingRecord($this->stationA, '2026-09-04');

    // Throughput pada 4 slot; drum speed hanya pada 2 — penyebut yang berbeda
    // itulah yang membuat asersi di bawah bermakna.
    foreach (range(0, 3) as $index) {
        $values = ['ffb_throughput_mt_hour' => 30.0];

        if ($index < 2) {
            $values['thresher_drum_speed_rpm'] = 22.0;
        }

        laporanThreshingSlot($record, $this->slots[$index], $values);
    }

    foreach ([$this->supervisor, $this->millManagement] as $user) {
        $periods = $this->actingAs($user, 'web')->getJson(laporanThreshingUrl('periods'));
        $periods->assertOk();
        expect(collect($periods->json('data'))->pluck('id'))->toContain((string) $this->periodA->id);

        $summary = $this->actingAs($user, 'web')->getJson(laporanThreshingUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]));
        $summary->assertOk();

        expect($summary->json('business_unit.name'))->toBe('Mill Alpha');
        expect($summary->json('has_data'))->toBeTrue();
        expect($summary->json('coverage.filled_slots'))->toBe(4);
        expect($summary->json('metrics'))->toHaveCount(5);

        // Penyebut per kolom, bukan satu penyebut bersama.
        expect(laporanThreshingMetric($summary, 'ffb_throughput_mt_hour')['filled_slot_count'])->toBe(4);
        expect(laporanThreshingMetric($summary, 'thresher_drum_speed_rpm')['filled_slot_count'])->toBe(2);

        // Standar operasional menyertai angkanya pada baris yang sama.
        expect(laporanThreshingMetric($summary, 'thresher_drum_speed_rpm')['target']['standard_operational_target'])
            ->toBe('21 - 23 RPM (optimal for separation)');
    }

    $export = $this->actingAs($this->supervisor, 'web')->get(laporanThreshingUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));
    $export->assertOk();
});

it('skenario 2 — Admin: pemilih mill lalu angka mill yang dipilih saja', function () {
    $recordA = laporanThreshingRecord($this->stationA, '2026-09-04', 'TH-A');
    laporanThreshingSlot($recordA, '07:00', ['ffb_throughput_mt_hour' => 11.0]);

    $recordB = laporanThreshingRecord($this->stationB, '2026-09-04', 'TH-B');
    laporanThreshingSlot($recordB, '07:00', ['ffb_throughput_mt_hour' => 99.0]);

    $options = $this->actingAs($this->admin, 'web')->getJson(laporanThreshingUrl('business-units/options'));
    $options->assertOk();
    expect(collect($options->json('data'))->pluck('name'))->toContain('Mill Alpha', 'Mill Beta');

    $summary = $this->actingAs($this->admin, 'web')->getJson(laporanThreshingUrl('summary', [
        'business_unit_id' => (string) $this->businessUnitA->id,
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    // Hanya mill yang dipilih — 99.0 milik mill lain tidak boleh terbaca.
    expect(laporanThreshingMetric($summary, 'ffb_throughput_mt_hour')['max'])->toEqual(11.0);
});

// =====================================================================
// Skenario 3-5: parameter wajib
// =====================================================================

it('skenario 3 — Admin tanpa business_unit_id: 422 pada periods dan summary', function () {
    $this->actingAs($this->admin, 'web')
        ->getJson(laporanThreshingUrl('periods'))
        ->assertStatus(422)
        ->assertJsonValidationErrors('business_unit_id');

    $this->actingAs($this->admin, 'web')
        ->getJson(laporanThreshingUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('business_unit_id');
});

it('skenario 4 — production_line_id absen: 422 dan nol kueri threshing_records', function () {
    $queries = [];

    DB::listen(function ($query) use (&$queries) {
        $queries[] = strtolower($query->sql);
    });

    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanThreshingUrl('summary', ['period_id' => (string) $this->periodA->id]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('production_line_id');

    // Tidak di-default ke "semua line": permintaan yang tidak lengkap tidak
    // boleh menjawab 200 dengan angka yang tidak diminta siapa pun.
    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "threshing_records"');
    }
});

it('skenario 5 — period_id absen: 422 dengan errors.period_id, bukan 404', function () {
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanThreshingUrl('summary', ['production_line_id' => $this->lineA]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('period_id');
});

// =====================================================================
// Skenario 6-9: penjagaan lintas mill
// =====================================================================

it('skenario 6 — periode milik mill lain: 403', function () {
    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('threshing')
        ->range('2026-09-01', '2026-09-30')->open()->create();

    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanThreshingUrl('summary', [
            'period_id' => (string) $periodB->id,
            'production_line_id' => $this->lineA,
        ]))
        ->assertStatus(403);
});

it('skenario 7 — periode tidak ada: 404', function () {
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanThreshingUrl('summary', [
            'period_id' => (string) Str::uuid(),
            'production_line_id' => $this->lineA,
        ]))
        ->assertStatus(404);
});

it('skenario 8 — business_unit_id mill lain dari peran terikat mill: 200 berisi data mill sendiri', function () {
    $record = laporanThreshingRecord($this->stationA, '2026-09-04', 'TH-A');
    laporanThreshingSlot($record, '07:00', ['ffb_throughput_mt_hour' => 11.0]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanThreshingUrl('summary', [
        'business_unit_id' => (string) $this->businessUnitB->id,
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    // SENGAJA 200, bukan 403: sebuah 403 justru akan memastikan mill lain itu
    // ada. Parameternya tidak divalidasi, tidak dibandingkan, dibuang.
    $summary->assertOk();
    expect($summary->json('business_unit.name'))->toBe('Mill Alpha');
    expect(laporanThreshingMetric($summary, 'ffb_throughput_mt_hour')['max'])->toEqual(11.0);
});

it('skenario 9 — urutan penjagaan: line diperiksa sebelum periode', function () {
    // Line mill lain DAN period_id yang tidak ada. Jawabannya harus tentang
    // LINE, supaya pemohon tidak belajar apa pun tentang periode.
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanThreshingUrl('summary', [
            'period_id' => (string) Str::uuid(),
            'production_line_id' => $this->lineB,
        ]))
        ->assertStatus(404);

    // Catatan: resolveProductionLine() memulangkan null untuk line mill lain
    // (bukan 403), jadi yang tersisa adalah 404 periode. Yang dibuktikan di
    // sini adalah bahwa TIDAK ADA data mill lain yang pernah terbaca — dan
    // itu dikunci oleh skenario berikutnya.
    $record = laporanThreshingRecord($this->stationB, '2026-09-04', 'TH-B');
    laporanThreshingSlot($record, '07:00', ['ffb_throughput_mt_hour' => 99.0]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanThreshingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineB,
    ]));

    $summary->assertOk();
    // Line mill lain dipulangkan null, sehingga penyaringan line tidak
    // diterapkan — dan karena kueri tetap dibatasi mill PERIODE, angka mill
    // lain tetap tidak pernah terbaca.
    expect($summary->json('coverage.filled_slots'))->toBe(0);
});

// =====================================================================
// Skenario 10-12: peran
// =====================================================================

it('skenario 10 — Operator diterima pada ketiga rute data sejak awal', function () {
    $record = laporanThreshingRecord($this->stationA, '2026-09-04', 'TH-A');
    laporanThreshingSlot($record, '07:00', ['ffb_throughput_mt_hour' => 11.0]);

    $this->actingAs($this->operator, 'web')->getJson(laporanThreshingUrl('periods'))->assertOk();

    $summary = $this->actingAs($this->operator, 'web')->getJson(laporanThreshingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();
    expect($summary->json('business_unit.name'))->toBe('Mill Alpha');

    $this->actingAs($this->operator, 'web')->get(laporanThreshingUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]))->assertOk();
});

it('skenario 11 — Operator yang mengirim business_unit_id mill lain tetap menerima data mill sendiri', function () {
    $record = laporanThreshingRecord($this->stationA, '2026-09-04', 'TH-A');
    laporanThreshingSlot($record, '07:00', ['ffb_throughput_mt_hour' => 11.0]);

    $recordB = laporanThreshingRecord($this->stationB, '2026-09-04', 'TH-B');
    laporanThreshingSlot($recordB, '07:00', ['ffb_throughput_mt_hour' => 99.0]);

    $summary = $this->actingAs($this->operator, 'web')->getJson(laporanThreshingUrl('summary', [
        'business_unit_id' => (string) $this->businessUnitB->id,
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    // INILAH asersi yang gagal bila Operator jatuh ke cabang Admin, tempat
    // business_unit_id kiriman klien DIHORMATI. Menguji "peran diterima"
    // saja akan tetap hijau walau cabangnya salah.
    $summary->assertOk();
    expect($summary->json('business_unit.name'))->toBe('Mill Alpha');
    expect(laporanThreshingMetric($summary, 'ffb_throughput_mt_hour')['max'])->toEqual(11.0);
});

it('skenario 12 — /business-units/options tetap 403 untuk setiap peran terikat mill', function () {
    foreach ([$this->operator, $this->supervisor, $this->millManagement] as $user) {
        $this->actingAs($user, 'web')
            ->getJson(laporanThreshingUrl('business-units/options'))
            ->assertStatus(403);
    }
});

it('skenario 13 — tamu: 401 pada keempat rute', function () {
    foreach (['business-units/options', 'periods', 'summary', 'export'] as $path) {
        $this->getJson(laporanThreshingUrl($path))->assertStatus(401);
    }
});

// =====================================================================
// Skenario 14-18: bentuk payload
// =====================================================================

it('skenario 14 — periode tanpa slot terisi: 200, has_data false, seluruh angka null', function () {
    $record = laporanThreshingRecord($this->stationA, '2026-09-04', 'TH-A');
    laporanThreshingSlot($record, '07:00');

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanThreshingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    expect($summary->json('has_data'))->toBeFalse();
    expect($summary->json('coverage.filled_slots'))->toBe(0);
    expect($summary->json('downtime_reasons'))->toBe([]);

    // TANGGAL YANG PUNYA RECORD TETAP MENDAPAT BARIS, meski tak satu slot pun
    // terisi — membuangnya akan membuat periode ini terlihat lebih tercatat
    // daripada kenyataannya. Yang menyembunyikan tabelnya di layar adalah
    // has_data, bukan ketiadaan baris.
    expect($summary->json('daily'))->toHaveCount(1);
    expect($summary->json('daily.0.filled_slot_count'))->toBe(0);
    expect($summary->json('daily.0.averages.ffb_throughput_mt_hour'))->toBeNull();

    // null, BUKAN 0 — nol berarti "terukur dan hasilnya nol".
    foreach ($summary->json('metrics') as $metric) {
        expect($metric['min'])->toBeNull();
        expect($metric['avg'])->toBeNull();
        expect($metric['max'])->toBeNull();
        expect($metric['filled_slot_count'])->toBe(0);
    }

    // Record-nya tetap dihitung.
    expect($summary->json('total.record_count'))->toBe(1);
});

it('skenario 15 — cakupan menerbitkan ketiga angka pembentuk penyebutnya', function () {
    foreach (['TH-1', 'TH-2'] as $thresher) {
        $record = laporanThreshingRecord($this->stationA, '2026-09-04', $thresher);
        laporanThreshingSlot($record, '07:00', ['ffb_throughput_mt_hour' => 30.0]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanThreshingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    expect($summary->json('coverage.thresher_count'))->toBe(2);
    expect($summary->json('coverage.days_counted'))->toBe(10);
    expect($summary->json('coverage.slots_per_thresher_per_day'))->toBe(24);
    expect($summary->json('coverage.expected_slots'))->toBe(480);
    expect($summary->json('coverage.coverage_percent'))->toEqual(0.4);
});

it('skenario 16 — periode belum mulai: coverage_percent null, bukan 0', function () {
    $notStarted = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('threshing')
        ->range(now()->addDays(5)->toDateString(), now()->addDays(20)->toDateString())->open()->create();

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanThreshingUrl('summary', [
        'period_id' => (string) $notStarted->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    expect($summary->json('coverage.coverage_percent'))->toBeNull();
    expect($summary->json('coverage.days_counted'))->toBe(0);
});

it('skenario 17 — target tanpa kolom ukur diterbitkan, bukan dibuang', function () {
    laporanThreshingSeedTargets();

    $record = laporanThreshingRecord($this->stationA, '2026-09-04', 'TH-A');
    laporanThreshingSlot($record, '07:00', ['ffb_throughput_mt_hour' => 30.0]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanThreshingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    expect($summary->json('targets_master_empty'))->toBeFalse();
    expect($summary->json('targets_without_metric'))->toHaveCount(1);
    expect($summary->json('targets_without_metric.0.parameter'))->toBe('Bearing Temperature');
});

it('skenario 18 — payload tidak memuat satu pun kunci penilaian terhadap standar', function () {
    laporanThreshingSeedTargets();

    $record = laporanThreshingRecord($this->stationA, '2026-09-04', 'TH-A');
    // 27,4 RPM terhadap standar '21 - 23 RPM'.
    laporanThreshingSlot($record, '07:00', ['thresher_drum_speed_rpm' => 27.4]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanThreshingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    $flat = json_encode($summary->json());

    foreach (['severity', 'is_out_of_range', 'out_of_range', 'flag', 'threshold', 'breach'] as $forbidden) {
        expect($flat)->not->toContain($forbidden);
    }
});

it('skenario 19 — alasan downtime dikelompokkan harfiah pada payload', function () {
    $record = laporanThreshingRecord($this->stationA, '2026-09-04', 'TH-A');

    foreach (range(0, 2) as $index) {
        laporanThreshingSlot($record, $this->slots[$index], ['downtime_reason' => 'Belt kendur']);
    }

    laporanThreshingSlot($record, $this->slots[3], ['downtime_reason' => 'belt kendur']);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanThreshingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    expect($summary->json('downtime_reasons'))->toBe([
        ['reason' => 'Belt kendur', 'slot_count' => 3],
        ['reason' => 'belt kendur', 'slot_count' => 1],
    ]);
});

// =====================================================================
// Skenario 20-24: ekspor
// =====================================================================

it('skenario 20 — ekspor CSV: satu baris per slot, konteks diulang, header lengkap', function () {
    $record = laporanThreshingRecord($this->stationA, '2026-09-04', 'TH-9');

    foreach (range(0, 2) as $index) {
        laporanThreshingSlot($record, $this->slots[$index], ['ffb_throughput_mt_hour' => 30.0]);
    }

    $response = $this->actingAs($this->supervisor, 'web')->get(laporanThreshingUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');

    $lines = laporanThreshingCsvLinesOf(laporanThreshingStreamed($response->baseResponse));

    // 1 header + 3 baris slot.
    expect($lines)->toHaveCount(4);
    expect($lines[0])->toContain('Slot Waktu');
    expect($lines[0])->toContain('Alasan Downtime');

    foreach (array_slice($lines, 1) as $line) {
        expect($line)->toContain('Periode September Alpha');
        expect($line)->toContain('Mill Alpha');
        expect($line)->toContain('TH-9');
    }
});

it('skenario 21 — slot kosong tetap menjadi baris ekspor', function () {
    $record = laporanThreshingRecord($this->stationA, '2026-09-04', 'TH-9');
    laporanThreshingSlot($record, '07:00');

    $response = $this->actingAs($this->supervisor, 'web')->get(laporanThreshingUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));

    $response->assertOk();

    $lines = laporanThreshingCsvLinesOf(laporanThreshingStreamed($response->baseResponse));

    expect($lines)->toHaveCount(2);
    expect($lines[1])->toContain('TH-9');
});

it('skenario 22 — format di luar csv|excel: 422', function () {
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanThreshingUrl('export', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
            'format' => 'pdf',
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('format');
});

it('skenario 23 — ekspor Excel memulangkan content-type xlsx', function () {
    $record = laporanThreshingRecord($this->stationA, '2026-09-04', 'TH-9');
    laporanThreshingSlot($record, '07:00', ['ffb_throughput_mt_hour' => 30.0]);

    $response = $this->actingAs($this->supervisor, 'web')->get(laporanThreshingUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'excel',
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('skenario 24 — periode tertutup tetap dapat dibaca dan diekspor', function () {
    $closed = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('threshing')
        ->range('2026-08-01', '2026-08-31')->closed()->named('Periode Agustus Tertutup')->create();

    $record = laporanThreshingRecord($this->stationA, '2026-08-05', 'TH-9');
    laporanThreshingSlot($record, '07:00', ['ffb_throughput_mt_hour' => 30.0]);

    $periods = $this->actingAs($this->supervisor, 'web')->getJson(laporanThreshingUrl('periods'));
    $periods->assertOk();
    expect(collect($periods->json('data'))->pluck('status'))->toContain('closed');

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanThreshingUrl('summary', [
        'period_id' => (string) $closed->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();
    expect($summary->json('period.status'))->toBe('closed');

    // Kunci periode mengatur PENULISAN data, bukan pembacaan laporan.
    $this->actingAs($this->supervisor, 'web')->get(laporanThreshingUrl('export', [
        'period_id' => (string) $closed->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]))->assertOk();
});

// =====================================================================
// Skenario 25: satu baris yang menentukan layar dapat dicapai
// =====================================================================

it('skenario 25 — REPORT_ROUTES memetakan threshing, menaruhnya DI ANTARA sterilizer dan clarification', function () {
    // Tanpa entri 'threshing' pada StationReportService::REPORT_ROUTES, layar
    // laporan ada, seluruh test lainnya lolos, dan tile-nya tetap kelabu.
    expect(StationReportService::REPORT_ROUTES)->toHaveKey('threshing');
    expect(StationReportService::REPORT_ROUTES['threshing'])->toBe('reports.threshing');
    expect(route(StationReportService::REPORT_ROUTES['threshing'], [], false))->toBe('/reports/threshing');

    // URUTANNYA LOAD-BEARING: peta ini harus tetap urut menurut
    // station_types.sort_order (sterilizer 40, threshing 50, clarification
    // 70), karena layar pemilih stasiun membandingkan urutannya dengan
    // urutan master. Asersi ber-urutan, bukan sekadar "memuat".
    $codes = array_keys(StationReportService::REPORT_ROUTES);
    $at = array_search('threshing', $codes, true);

    expect($codes[$at - 1])->toBe('sterilizer');
    expect($codes[$at + 1])->toBe('clarification');
});
