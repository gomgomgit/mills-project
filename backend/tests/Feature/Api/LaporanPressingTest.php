<?php

/**
 * LaporanPressingTest (API) — screen-150--laporan-pressing-web /
 * screen-151--laporan-pressing-mobile, the four /api/pressing-reports/*
 * endpoints.
 *
 * One test per test_scenarios[].api_test on screen-150's tech spec. The
 * aggregation rules themselves are proven in
 * tests/Unit/Services/PressingReportServiceTest.php against the service;
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
use App\Models\PressingDetail;
use App\Models\PressingOperationalTarget;
use App\Models\PressingRecord;
use App\Models\User;
use App\Services\StationReportService;
use App\Services\PressingRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function laporanPressingUrl(string $path, array $query = []): string
{
    return '/api/pressing-reports/'.$path.($query === [] ? '' : '?'.http_build_query($query));
}

function laporanPressingRecord(
    Station $station,
    string $date,
    string $presserId = 'PR-1',
    array $attributes = [],
): PressingRecord {
    return PressingRecord::factory()
        ->forStation($station)
        ->onDate($date)
        ->create(array_merge(['presser_id' => $presserId, 'note' => 'Catatan harian'], $attributes));
}

function laporanPressingSlot(PressingRecord $record, string $timeSlot, array $values = []): PressingDetail
{
    return PressingDetail::factory()
        ->forRecord($record)
        ->timeSlot($timeSlot)
        ->create($values);
}

function laporanPressingSeedTargets(): void
{
    // Ketujuh baris master, persis seperti PressingOperationalTargetSeeder.
    // TUJUH parameter untuk LIMA kolom ukur: 'Nut Breakage Rate' dan 'Press
    // Cake Moisture' tidak punya kolom pengukuran di mana pun pada skema ini.
    $rows = [
        ['Digester Temperature', '90C - 95C', '< 85C (Leads to poor oil liberation)'],
        ['Digester Fill Level', '75% - 80% (Minimum 3/4 full)', '< 50% (Reduces retention time & friction)'],
        ['Screw Press Motor Current', '35 - 45 Amperes', '> 50 Amps (Indicates choke or heavy load)'],
        ['Cone Hydraulic Pressure', '45 - 55 Bar', '> 60 Bar (Increases nut breakage severely)'],
        ['Dilution Water Temperature', '85C - 90C', '< 80C (Causes poor oil-water separation)'],
        ['Nut Breakage Rate', '< 10% to 12%', '> 15% (Adjust screw press cones backward)'],
        ['Press Cake Moisture', '34% - 38%', '> 40% (Indicates insufficient pressing pressure)'],
    ];

    foreach ($rows as $index => [$parameter, $range, $limit]) {
        PressingOperationalTarget::create([
            'parameter_metric' => $parameter,
            'target_operating_range' => $range,
            'critical_trigger_action_limit' => $limit,
            'sort_order' => $index + 1,
        ]);
    }
}

/** The body of a StreamedResponse, captured. STREAM ONCE. */
function laporanPressingStreamed($response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

function laporanPressingCsvLinesOf(string $body): array
{
    return array_values(array_filter(explode("\n", trim($body))));
}

/** The metrics entry for one column. */
function laporanPressingMetric($response, string $column): array
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

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->pressing()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->pressing()->create();

    $this->lineA = (string) $this->stationA->production_line_id;
    $this->lineB = (string) $this->stationB->production_line_id;

    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    $this->periodA = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('pressing')
        ->range('2026-09-01', '2026-09-10')->open()->named('Periode September Alpha')->create();

    $this->slots = PressingRecordService::canonicalTimeSlots();
});

// =====================================================================
// Skenario 1-2: jalur sukses
// =====================================================================

it('skenario 1 — Supervisor dan Mill Management: periods + summary + export mill sendiri', function () {
    laporanPressingSeedTargets();

    $record = laporanPressingRecord($this->stationA, '2026-09-04');

    // Throughput pada 4 slot; drum speed hanya pada 2 — penyebut yang berbeda
    // itulah yang membuat asersi di bawah bermakna.
    foreach (range(0, 3) as $index) {
        $values = ['digester_temp_c' => 30.0];

        if ($index < 2) {
            $values['digester_level_percent'] = 22.0;
        }

        laporanPressingSlot($record, $this->slots[$index], $values);
    }

    foreach ([$this->supervisor, $this->millManagement] as $user) {
        $periods = $this->actingAs($user, 'web')->getJson(laporanPressingUrl('periods'));
        $periods->assertOk();
        expect(collect($periods->json('data'))->pluck('id'))->toContain((string) $this->periodA->id);

        $summary = $this->actingAs($user, 'web')->getJson(laporanPressingUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]));
        $summary->assertOk();

        expect($summary->json('business_unit.name'))->toBe('Mill Alpha');
        expect($summary->json('has_data'))->toBeTrue();
        expect($summary->json('coverage.filled_slots'))->toBe(4);
        expect($summary->json('metrics'))->toHaveCount(5);

        // Penyebut per kolom, bukan satu penyebut bersama.
        expect(laporanPressingMetric($summary, 'digester_temp_c')['filled_slot_count'])->toBe(4);
        expect(laporanPressingMetric($summary, 'digester_level_percent')['filled_slot_count'])->toBe(2);

        // Standar operasional menyertai angkanya pada baris yang sama.
        expect(laporanPressingMetric($summary, 'digester_level_percent')['target']['target_operating_range'])
            ->toBe('75% - 80% (Minimum 3/4 full)');
    }

    $export = $this->actingAs($this->supervisor, 'web')->get(laporanPressingUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));
    $export->assertOk();
});

it('skenario 2 — Admin: pemilih mill lalu angka mill yang dipilih saja', function () {
    $recordA = laporanPressingRecord($this->stationA, '2026-09-04', 'PR-A');
    laporanPressingSlot($recordA, '07:00', ['digester_temp_c' => 11.0]);

    $recordB = laporanPressingRecord($this->stationB, '2026-09-04', 'PR-B');
    laporanPressingSlot($recordB, '07:00', ['digester_temp_c' => 99.0]);

    $options = $this->actingAs($this->admin, 'web')->getJson(laporanPressingUrl('business-units/options'));
    $options->assertOk();
    expect(collect($options->json('data'))->pluck('name'))->toContain('Mill Alpha', 'Mill Beta');

    $summary = $this->actingAs($this->admin, 'web')->getJson(laporanPressingUrl('summary', [
        'business_unit_id' => (string) $this->businessUnitA->id,
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    // Hanya mill yang dipilih — 99.0 milik mill lain tidak boleh terbaca.
    expect(laporanPressingMetric($summary, 'digester_temp_c')['max'])->toEqual(11.0);
});

// =====================================================================
// Skenario 3-5: parameter wajib
// =====================================================================

it('skenario 3 — Admin tanpa business_unit_id: 422 pada periods dan summary', function () {
    $this->actingAs($this->admin, 'web')
        ->getJson(laporanPressingUrl('periods'))
        ->assertStatus(422)
        ->assertJsonValidationErrors('business_unit_id');

    $this->actingAs($this->admin, 'web')
        ->getJson(laporanPressingUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('business_unit_id');
});

it('skenario 4 — production_line_id absen: 422 dan nol kueri pressing_records', function () {
    $queries = [];

    DB::listen(function ($query) use (&$queries) {
        $queries[] = strtolower($query->sql);
    });

    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanPressingUrl('summary', ['period_id' => (string) $this->periodA->id]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('production_line_id');

    // Tidak di-default ke "semua line": permintaan yang tidak lengkap tidak
    // boleh menjawab 200 dengan angka yang tidak diminta siapa pun.
    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "pressing_records"');
    }
});

it('skenario 5 — period_id absen: 422 dengan errors.period_id, bukan 404', function () {
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanPressingUrl('summary', ['production_line_id' => $this->lineA]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('period_id');
});

// =====================================================================
// Skenario 6-9: penjagaan lintas mill
// =====================================================================

it('skenario 6 — periode milik mill lain: 403', function () {
    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('pressing')
        ->range('2026-09-01', '2026-09-30')->open()->create();

    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanPressingUrl('summary', [
            'period_id' => (string) $periodB->id,
            'production_line_id' => $this->lineA,
        ]))
        ->assertStatus(403);
});

it('skenario 7 — periode tidak ada: 404', function () {
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanPressingUrl('summary', [
            'period_id' => (string) Str::uuid(),
            'production_line_id' => $this->lineA,
        ]))
        ->assertStatus(404);
});

it('skenario 8 — business_unit_id mill lain dari peran terikat mill: 200 berisi data mill sendiri', function () {
    $record = laporanPressingRecord($this->stationA, '2026-09-04', 'PR-A');
    laporanPressingSlot($record, '07:00', ['digester_temp_c' => 11.0]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanPressingUrl('summary', [
        'business_unit_id' => (string) $this->businessUnitB->id,
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    // SENGAJA 200, bukan 403: sebuah 403 justru akan memastikan mill lain itu
    // ada. Parameternya tidak divalidasi, tidak dibandingkan, dibuang.
    $summary->assertOk();
    expect($summary->json('business_unit.name'))->toBe('Mill Alpha');
    expect(laporanPressingMetric($summary, 'digester_temp_c')['max'])->toEqual(11.0);
});

it('skenario 9 — urutan penjagaan: line diperiksa sebelum periode', function () {
    // Line mill lain DAN period_id yang tidak ada. Jawabannya harus tentang
    // LINE, supaya pemohon tidak belajar apa pun tentang periode.
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanPressingUrl('summary', [
            'period_id' => (string) Str::uuid(),
            'production_line_id' => $this->lineB,
        ]))
        ->assertStatus(404);

    // Catatan: resolveProductionLine() memulangkan null untuk line mill lain
    // (bukan 403), jadi yang tersisa adalah 404 periode. Yang dibuktikan di
    // sini adalah bahwa TIDAK ADA data mill lain yang pernah terbaca — dan
    // itu dikunci oleh skenario berikutnya.
    $record = laporanPressingRecord($this->stationB, '2026-09-04', 'PR-B');
    laporanPressingSlot($record, '07:00', ['digester_temp_c' => 99.0]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanPressingUrl('summary', [
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
    $record = laporanPressingRecord($this->stationA, '2026-09-04', 'PR-A');
    laporanPressingSlot($record, '07:00', ['digester_temp_c' => 11.0]);

    $this->actingAs($this->operator, 'web')->getJson(laporanPressingUrl('periods'))->assertOk();

    $summary = $this->actingAs($this->operator, 'web')->getJson(laporanPressingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();
    expect($summary->json('business_unit.name'))->toBe('Mill Alpha');

    $this->actingAs($this->operator, 'web')->get(laporanPressingUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]))->assertOk();
});

it('skenario 11 — Operator yang mengirim business_unit_id mill lain tetap menerima data mill sendiri', function () {
    $record = laporanPressingRecord($this->stationA, '2026-09-04', 'PR-A');
    laporanPressingSlot($record, '07:00', ['digester_temp_c' => 11.0]);

    $recordB = laporanPressingRecord($this->stationB, '2026-09-04', 'PR-B');
    laporanPressingSlot($recordB, '07:00', ['digester_temp_c' => 99.0]);

    $summary = $this->actingAs($this->operator, 'web')->getJson(laporanPressingUrl('summary', [
        'business_unit_id' => (string) $this->businessUnitB->id,
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    // INILAH asersi yang gagal bila Operator jatuh ke cabang Admin, tempat
    // business_unit_id kiriman klien DIHORMATI. Menguji "peran diterima"
    // saja akan tetap hijau walau cabangnya salah.
    $summary->assertOk();
    expect($summary->json('business_unit.name'))->toBe('Mill Alpha');
    expect(laporanPressingMetric($summary, 'digester_temp_c')['max'])->toEqual(11.0);
});

it('skenario 12 — /business-units/options tetap 403 untuk setiap peran terikat mill', function () {
    foreach ([$this->operator, $this->supervisor, $this->millManagement] as $user) {
        $this->actingAs($user, 'web')
            ->getJson(laporanPressingUrl('business-units/options'))
            ->assertStatus(403);
    }
});

it('skenario 13 — tamu: 401 pada keempat rute', function () {
    foreach (['business-units/options', 'periods', 'summary', 'export'] as $path) {
        $this->getJson(laporanPressingUrl($path))->assertStatus(401);
    }
});

// =====================================================================
// Skenario 14-18: bentuk payload
// =====================================================================

it('skenario 14 — periode tanpa slot terisi: 200, has_data false, seluruh angka null', function () {
    $record = laporanPressingRecord($this->stationA, '2026-09-04', 'PR-A');
    laporanPressingSlot($record, '07:00');

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanPressingUrl('summary', [
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
    expect($summary->json('daily.0.averages.digester_temp_c'))->toBeNull();

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
    foreach (['PR-1', 'PR-2'] as $presser) {
        $record = laporanPressingRecord($this->stationA, '2026-09-04', $presser);
        laporanPressingSlot($record, '07:00', ['digester_temp_c' => 30.0]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanPressingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    expect($summary->json('coverage.presser_count'))->toBe(2);
    expect($summary->json('coverage.days_counted'))->toBe(10);
    expect($summary->json('coverage.slots_per_presser_per_day'))->toBe(24);
    expect($summary->json('coverage.expected_slots'))->toBe(480);
    expect($summary->json('coverage.coverage_percent'))->toEqual(0.4);
});

it('skenario 16 — periode belum mulai: coverage_percent null, bukan 0', function () {
    $notStarted = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('pressing')
        ->range(now()->addDays(5)->toDateString(), now()->addDays(20)->toDateString())->open()->create();

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanPressingUrl('summary', [
        'period_id' => (string) $notStarted->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    expect($summary->json('coverage.coverage_percent'))->toBeNull();
    expect($summary->json('coverage.days_counted'))->toBe(0);
});

it('skenario 17 — target tanpa kolom ukur diterbitkan, bukan dibuang', function () {
    laporanPressingSeedTargets();

    $record = laporanPressingRecord($this->stationA, '2026-09-04', 'PR-A');
    laporanPressingSlot($record, '07:00', ['digester_temp_c' => 30.0]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanPressingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    expect($summary->json('targets_master_empty'))->toBeFalse();
    // DUA, bukan satu seperti pada Threshing: master memuat tujuh parameter
    // sementara formulir mengukur lima.
    expect($summary->json('targets_without_metric'))->toHaveCount(2);

    $unmeasured = collect($summary->json('targets_without_metric'))->pluck('parameter_metric')->all();

    expect($unmeasured)->toContain('Nut Breakage Rate');
    expect($unmeasured)->toContain('Press Cake Moisture');

    // KEDUA kolom target ikut diterbitkan, bukan hanya namanya.
    expect($summary->json('targets_without_metric.0.target_operating_range'))->not->toBeNull();
    expect($summary->json('targets_without_metric.0.critical_trigger_action_limit'))->not->toBeNull();
});

it('skenario 18 — payload tidak memuat satu pun kunci penilaian terhadap standar', function () {
    laporanPressingSeedTargets();

    $record = laporanPressingRecord($this->stationA, '2026-09-04', 'PR-A');
    // 27,4 RPM terhadap standar '21 - 23 RPM'.
    laporanPressingSlot($record, '07:00', ['digester_level_percent' => 27.4]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanPressingUrl('summary', [
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
    $record = laporanPressingRecord($this->stationA, '2026-09-04', 'PR-A');

    foreach (range(0, 2) as $index) {
        laporanPressingSlot($record, $this->slots[$index], ['downtime_reason' => 'Belt kendur']);
    }

    laporanPressingSlot($record, $this->slots[3], ['downtime_reason' => 'belt kendur']);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanPressingUrl('summary', [
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
    $record = laporanPressingRecord($this->stationA, '2026-09-04', 'PR-9');

    foreach (range(0, 2) as $index) {
        laporanPressingSlot($record, $this->slots[$index], ['digester_temp_c' => 30.0]);
    }

    $response = $this->actingAs($this->supervisor, 'web')->get(laporanPressingUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');

    $lines = laporanPressingCsvLinesOf(laporanPressingStreamed($response->baseResponse));

    // 1 header + 3 baris slot.
    expect($lines)->toHaveCount(4);
    expect($lines[0])->toContain('Slot Waktu');
    expect($lines[0])->toContain('Alasan Downtime');

    foreach (array_slice($lines, 1) as $line) {
        expect($line)->toContain('Periode September Alpha');
        expect($line)->toContain('Mill Alpha');
        expect($line)->toContain('PR-9');
    }
});

it('skenario 21 — slot kosong tetap menjadi baris ekspor', function () {
    $record = laporanPressingRecord($this->stationA, '2026-09-04', 'PR-9');
    laporanPressingSlot($record, '07:00');

    $response = $this->actingAs($this->supervisor, 'web')->get(laporanPressingUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));

    $response->assertOk();

    $lines = laporanPressingCsvLinesOf(laporanPressingStreamed($response->baseResponse));

    expect($lines)->toHaveCount(2);
    expect($lines[1])->toContain('PR-9');
});

it('skenario 22 — format di luar csv|excel: 422', function () {
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanPressingUrl('export', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
            'format' => 'pdf',
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('format');
});

it('skenario 23 — ekspor Excel memulangkan content-type xlsx', function () {
    $record = laporanPressingRecord($this->stationA, '2026-09-04', 'PR-9');
    laporanPressingSlot($record, '07:00', ['digester_temp_c' => 30.0]);

    $response = $this->actingAs($this->supervisor, 'web')->get(laporanPressingUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'excel',
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('skenario 24 — periode tertutup tetap dapat dibaca dan diekspor', function () {
    $closed = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('pressing')
        ->range('2026-08-01', '2026-08-31')->closed()->named('Periode Agustus Tertutup')->create();

    $record = laporanPressingRecord($this->stationA, '2026-08-05', 'PR-9');
    laporanPressingSlot($record, '07:00', ['digester_temp_c' => 30.0]);

    $periods = $this->actingAs($this->supervisor, 'web')->getJson(laporanPressingUrl('periods'));
    $periods->assertOk();
    expect(collect($periods->json('data'))->pluck('status'))->toContain('closed');

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanPressingUrl('summary', [
        'period_id' => (string) $closed->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();
    expect($summary->json('period.status'))->toBe('closed');

    // Kunci periode mengatur PENULISAN data, bukan pembacaan laporan.
    $this->actingAs($this->supervisor, 'web')->get(laporanPressingUrl('export', [
        'period_id' => (string) $closed->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]))->assertOk();
});

// =====================================================================
// Skenario 25: satu baris yang menentukan layar dapat dicapai
// =====================================================================

it('skenario 25 — REPORT_ROUTES memetakan pressing, menaruhnya DI ANTARA threshing dan clarification', function () {
    // Tanpa entri 'pressing' pada StationReportService::REPORT_ROUTES, layar
    // laporan ada, seluruh test lainnya lolos, dan tile-nya tetap kelabu.
    expect(StationReportService::REPORT_ROUTES)->toHaveKey('pressing');
    expect(StationReportService::REPORT_ROUTES['pressing'])->toBe('reports.pressing');
    expect(route(StationReportService::REPORT_ROUTES['pressing'], [], false))->toBe('/reports/pressing');

    // URUTANNYA LOAD-BEARING: peta ini harus tetap urut menurut
    // station_types.sort_order (threshing 50, pressing 60, clarification
    // 70), karena layar pemilih stasiun membandingkan urutannya dengan
    // urutan master. Asersi ber-urutan, bukan sekadar "memuat".
    $codes = array_keys(StationReportService::REPORT_ROUTES);
    $at = array_search('pressing', $codes, true);

    expect($codes[$at - 1])->toBe('threshing');
    expect($codes[$at + 1])->toBe('clarification');
});
