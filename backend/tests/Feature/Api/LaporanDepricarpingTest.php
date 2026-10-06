<?php

/**
 * LaporanDepricarpingTest (API) — screen-152--laporan-depricarping-web /
 * screen-153--laporan-depricarping-mobile, the four /api/depricarping-reports/*
 * endpoints.
 *
 * One test per test_scenarios[].api_test on screen-152's tech spec. The
 * aggregation rules themselves are proven in
 * tests/Unit/Services/DepricarpingReportServiceTest.php against the service;
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
use App\Models\DepricarpingDetail;
use App\Models\DepricarpingOperationalTarget;
use App\Models\DepricarpingRecord;
use App\Models\User;
use App\Services\StationReportService;
use App\Services\DepricarpingRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function laporanDepricarpingUrl(string $path, array $query = []): string
{
    return '/api/depricarping-reports/'.$path.($query === [] ? '' : '?'.http_build_query($query));
}

function laporanDepricarpingRecord(
    Station $station,
    string $date,
    string $presserId = 'PR-1',
    array $attributes = [],
): DepricarpingRecord {
    return DepricarpingRecord::factory()
        ->forStation($station)
        ->onDate($date)
        ->create(array_merge(['presser_id' => $presserId, 'note' => 'Catatan harian'], $attributes));
}

function laporanDepricarpingSlot(DepricarpingRecord $record, string $timeSlot, array $values = []): DepricarpingDetail
{
    return DepricarpingDetail::factory()
        ->forRecord($record)
        ->timeSlot($timeSlot)
        ->create($values);
}

function laporanDepricarpingSeedTargets(): void
{
    // Ketujuh baris master, persis seperti DepricarpingOperationalTargetSeeder.
    // TUJUH parameter untuk LIMA kolom ukur: 'Nut Breakage Rate' dan 'Press
    // Cake Moisture' tidak punya kolom pengukuran di mana pun pada skema ini.
    $rows = [
        ['Fan Static Pressure', '40 - 50 mmH2O', '< 35 or > 55 mmH2O', 'Low pressure drops fibre early (heavy losses). High pressure sucks clean small nuts into the fibre cyclone.'],
        ['Polishing Drum Speed', '20 - 24 RPM', '< 18 or > 26 RPM', 'Slower speeds fail to detach residual mesocarp fibre from nuts. Higher speeds cause premature mechanical wear.'],
        ['Air Velocity (Aspirator)', '12 - 14 m/s', '< 10 or > 16 m/s', 'Controls the pneumatic separation gap. Must cleanly lift light fiber hulls while letting heavy polished nuts sink.'],
        ['Fibre Moisture Content', '33% - 37%', '> 40%', 'High moisture reduces downstream boiler combustion efficiency and indicates poor press station performance.'],
        ['Kernel Loss in Fibre', '< 0.50%', '> 1.00%', 'Direct operational revenue loss. Signifies an unstable pneumatic lifting balance or unstripped cake clumps.'],
        ['Nut Silo Temperature', '60C - 70C', '< 55C or > 75C', 'Crucial for nut conditioning. Correct heat shrinks the kernel inside the shell, enabling high-efficiency cracking.'],
    ];

    foreach ($rows as $index => [$parameter, $range, $limit, $justification]) {
        DepricarpingOperationalTarget::create([
            'parameter_metric' => $parameter,
            'target_range' => $range,
            'critical_limit' => $limit,
            'operational_consequence_justification' => $justification,
            'sort_order' => $index + 1,
        ]);
    }
}

/** The body of a StreamedResponse, captured. STREAM ONCE. */
function laporanDepricarpingStreamed($response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

function laporanDepricarpingCsvLinesOf(string $body): array
{
    return array_values(array_filter(explode("\n", trim($body))));
}

/** The metrics entry for one column. */
function laporanDepricarpingMetric($response, string $column): array
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

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->depricarping()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->depricarping()->create();

    $this->lineA = (string) $this->stationA->production_line_id;
    $this->lineB = (string) $this->stationB->production_line_id;

    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    $this->periodA = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('depricarping')
        ->range('2026-09-01', '2026-09-10')->open()->named('Periode September Alpha')->create();

    $this->slots = DepricarpingRecordService::canonicalTimeSlots();
});

// =====================================================================
// Skenario 1-2: jalur sukses
// =====================================================================

it('skenario 1 — Supervisor dan Mill Management: periods + summary + export mill sendiri', function () {
    laporanDepricarpingSeedTargets();

    $record = laporanDepricarpingRecord($this->stationA, '2026-09-04');

    // Throughput pada 4 slot; drum speed hanya pada 2 — penyebut yang berbeda
    // itulah yang membuat asersi di bawah bermakna.
    foreach (range(0, 3) as $index) {
        $values = ['fan_static_pressure_mmh2o' => 30.0];

        if ($index < 2) {
            $values['polishing_drum_speed_rpm'] = 22.0;
        }

        laporanDepricarpingSlot($record, $this->slots[$index], $values);
    }

    foreach ([$this->supervisor, $this->millManagement] as $user) {
        $periods = $this->actingAs($user, 'web')->getJson(laporanDepricarpingUrl('periods'));
        $periods->assertOk();
        expect(collect($periods->json('data'))->pluck('id'))->toContain((string) $this->periodA->id);

        $summary = $this->actingAs($user, 'web')->getJson(laporanDepricarpingUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]));
        $summary->assertOk();

        expect($summary->json('business_unit.name'))->toBe('Mill Alpha');
        expect($summary->json('has_data'))->toBeTrue();
        expect($summary->json('coverage.filled_slots'))->toBe(4);
        expect($summary->json('metrics'))->toHaveCount(7);

        // Penyebut per kolom, bukan satu penyebut bersama.
        expect(laporanDepricarpingMetric($summary, 'fan_static_pressure_mmh2o')['filled_slot_count'])->toBe(4);
        expect(laporanDepricarpingMetric($summary, 'polishing_drum_speed_rpm')['filled_slot_count'])->toBe(2);

        // Standar operasional menyertai angkanya pada baris yang sama.
        expect(laporanDepricarpingMetric($summary, 'polishing_drum_speed_rpm')['target']['target_range'])
            ->toBe('20 - 24 RPM');
        // KETIGA kolom target, termasuk yang keempat pada master yang tidak
        // ada padanannya pada master Threshing maupun Pressing.
        expect(laporanDepricarpingMetric($summary, 'polishing_drum_speed_rpm')['target']['critical_limit'])
            ->toBe('< 18 or > 26 RPM');
        expect(laporanDepricarpingMetric($summary, 'polishing_drum_speed_rpm')['target']['operational_consequence_justification'])
            ->toContain('premature mechanical wear');
    }

    $export = $this->actingAs($this->supervisor, 'web')->get(laporanDepricarpingUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));
    $export->assertOk();
});

it('skenario 2 — Admin: pemilih mill lalu angka mill yang dipilih saja', function () {
    $recordA = laporanDepricarpingRecord($this->stationA, '2026-09-04', 'PR-A');
    laporanDepricarpingSlot($recordA, '07:00', ['fan_static_pressure_mmh2o' => 11.0]);

    $recordB = laporanDepricarpingRecord($this->stationB, '2026-09-04', 'PR-B');
    laporanDepricarpingSlot($recordB, '07:00', ['fan_static_pressure_mmh2o' => 99.0]);

    $options = $this->actingAs($this->admin, 'web')->getJson(laporanDepricarpingUrl('business-units/options'));
    $options->assertOk();
    expect(collect($options->json('data'))->pluck('name'))->toContain('Mill Alpha', 'Mill Beta');

    $summary = $this->actingAs($this->admin, 'web')->getJson(laporanDepricarpingUrl('summary', [
        'business_unit_id' => (string) $this->businessUnitA->id,
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    // Hanya mill yang dipilih — 99.0 milik mill lain tidak boleh terbaca.
    expect(laporanDepricarpingMetric($summary, 'fan_static_pressure_mmh2o')['max'])->toEqual(11.0);
});

// =====================================================================
// Skenario 3-5: parameter wajib
// =====================================================================

it('skenario 3 — Admin tanpa business_unit_id: 422 pada periods dan summary', function () {
    $this->actingAs($this->admin, 'web')
        ->getJson(laporanDepricarpingUrl('periods'))
        ->assertStatus(422)
        ->assertJsonValidationErrors('business_unit_id');

    $this->actingAs($this->admin, 'web')
        ->getJson(laporanDepricarpingUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('business_unit_id');
});

it('skenario 4 — production_line_id absen: 422 dan nol kueri depricarping_records', function () {
    $queries = [];

    DB::listen(function ($query) use (&$queries) {
        $queries[] = strtolower($query->sql);
    });

    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanDepricarpingUrl('summary', ['period_id' => (string) $this->periodA->id]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('production_line_id');

    // Tidak di-default ke "semua line": permintaan yang tidak lengkap tidak
    // boleh menjawab 200 dengan angka yang tidak diminta siapa pun.
    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "depricarping_records"');
    }
});

it('skenario 5 — period_id absen: 422 dengan errors.period_id, bukan 404', function () {
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanDepricarpingUrl('summary', ['production_line_id' => $this->lineA]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('period_id');
});

// =====================================================================
// Skenario 6-9: penjagaan lintas mill
// =====================================================================

it('skenario 6 — periode milik mill lain: 403', function () {
    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('depricarping')
        ->range('2026-09-01', '2026-09-30')->open()->create();

    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanDepricarpingUrl('summary', [
            'period_id' => (string) $periodB->id,
            'production_line_id' => $this->lineA,
        ]))
        ->assertStatus(403);
});

it('skenario 7 — periode tidak ada: 404', function () {
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanDepricarpingUrl('summary', [
            'period_id' => (string) Str::uuid(),
            'production_line_id' => $this->lineA,
        ]))
        ->assertStatus(404);
});

it('skenario 8 — business_unit_id mill lain dari peran terikat mill: 200 berisi data mill sendiri', function () {
    $record = laporanDepricarpingRecord($this->stationA, '2026-09-04', 'PR-A');
    laporanDepricarpingSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 11.0]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanDepricarpingUrl('summary', [
        'business_unit_id' => (string) $this->businessUnitB->id,
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    // SENGAJA 200, bukan 403: sebuah 403 justru akan memastikan mill lain itu
    // ada. Parameternya tidak divalidasi, tidak dibandingkan, dibuang.
    $summary->assertOk();
    expect($summary->json('business_unit.name'))->toBe('Mill Alpha');
    expect(laporanDepricarpingMetric($summary, 'fan_static_pressure_mmh2o')['max'])->toEqual(11.0);
});

it('skenario 9 — urutan penjagaan: line diperiksa sebelum periode', function () {
    // Line mill lain DAN period_id yang tidak ada. Jawabannya harus tentang
    // LINE, supaya pemohon tidak belajar apa pun tentang periode.
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanDepricarpingUrl('summary', [
            'period_id' => (string) Str::uuid(),
            'production_line_id' => $this->lineB,
        ]))
        ->assertStatus(404);

    // Catatan: resolveProductionLine() memulangkan null untuk line mill lain
    // (bukan 403), jadi yang tersisa adalah 404 periode. Yang dibuktikan di
    // sini adalah bahwa TIDAK ADA data mill lain yang pernah terbaca — dan
    // itu dikunci oleh skenario berikutnya.
    $record = laporanDepricarpingRecord($this->stationB, '2026-09-04', 'PR-B');
    laporanDepricarpingSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 99.0]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanDepricarpingUrl('summary', [
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
    $record = laporanDepricarpingRecord($this->stationA, '2026-09-04', 'PR-A');
    laporanDepricarpingSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 11.0]);

    $this->actingAs($this->operator, 'web')->getJson(laporanDepricarpingUrl('periods'))->assertOk();

    $summary = $this->actingAs($this->operator, 'web')->getJson(laporanDepricarpingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();
    expect($summary->json('business_unit.name'))->toBe('Mill Alpha');

    $this->actingAs($this->operator, 'web')->get(laporanDepricarpingUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]))->assertOk();
});

it('skenario 11 — Operator yang mengirim business_unit_id mill lain tetap menerima data mill sendiri', function () {
    $record = laporanDepricarpingRecord($this->stationA, '2026-09-04', 'PR-A');
    laporanDepricarpingSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 11.0]);

    $recordB = laporanDepricarpingRecord($this->stationB, '2026-09-04', 'PR-B');
    laporanDepricarpingSlot($recordB, '07:00', ['fan_static_pressure_mmh2o' => 99.0]);

    $summary = $this->actingAs($this->operator, 'web')->getJson(laporanDepricarpingUrl('summary', [
        'business_unit_id' => (string) $this->businessUnitB->id,
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    // INILAH asersi yang gagal bila Operator jatuh ke cabang Admin, tempat
    // business_unit_id kiriman klien DIHORMATI. Menguji "peran diterima"
    // saja akan tetap hijau walau cabangnya salah.
    $summary->assertOk();
    expect($summary->json('business_unit.name'))->toBe('Mill Alpha');
    expect(laporanDepricarpingMetric($summary, 'fan_static_pressure_mmh2o')['max'])->toEqual(11.0);
});

it('skenario 12 — /business-units/options tetap 403 untuk setiap peran terikat mill', function () {
    foreach ([$this->operator, $this->supervisor, $this->millManagement] as $user) {
        $this->actingAs($user, 'web')
            ->getJson(laporanDepricarpingUrl('business-units/options'))
            ->assertStatus(403);
    }
});

it('skenario 13 — tamu: 401 pada keempat rute', function () {
    foreach (['business-units/options', 'periods', 'summary', 'export'] as $path) {
        $this->getJson(laporanDepricarpingUrl($path))->assertStatus(401);
    }
});

// =====================================================================
// Skenario 14-18: bentuk payload
// =====================================================================

it('skenario 14 — periode tanpa slot terisi: 200, has_data false, seluruh angka null', function () {
    $record = laporanDepricarpingRecord($this->stationA, '2026-09-04', 'PR-A');
    laporanDepricarpingSlot($record, '07:00');

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanDepricarpingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    expect($summary->json('has_data'))->toBeFalse();
    expect($summary->json('coverage.filled_slots'))->toBe(0);
    expect($summary->json('findings'))->toBe([]);

    // TANGGAL YANG PUNYA RECORD TETAP MENDAPAT BARIS, meski tak satu slot pun
    // terisi — membuangnya akan membuat periode ini terlihat lebih tercatat
    // daripada kenyataannya. Yang menyembunyikan tabelnya di layar adalah
    // has_data, bukan ketiadaan baris.
    expect($summary->json('daily'))->toHaveCount(1);
    expect($summary->json('daily.0.filled_slot_count'))->toBe(0);
    expect($summary->json('daily.0.averages.fan_static_pressure_mmh2o'))->toBeNull();

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
        $record = laporanDepricarpingRecord($this->stationA, '2026-09-04', $presser);
        laporanDepricarpingSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 30.0]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanDepricarpingUrl('summary', [
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
    $notStarted = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('depricarping')
        ->range(now()->addDays(5)->toDateString(), now()->addDays(20)->toDateString())->open()->create();

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanDepricarpingUrl('summary', [
        'period_id' => (string) $notStarted->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    expect($summary->json('coverage.coverage_percent'))->toBeNull();
    expect($summary->json('coverage.days_counted'))->toBe(0);
});

it('skenario 17 — target tanpa kolom ukur diterbitkan, bukan dibuang', function () {
    laporanDepricarpingSeedTargets();

    $record = laporanDepricarpingRecord($this->stationA, '2026-09-04', 'PR-A');
    laporanDepricarpingSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 30.0]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanDepricarpingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    expect($summary->json('targets_master_empty'))->toBeFalse();
    // SATU, bukan dua seperti pada Pressing — dan alasannya berbeda jenisnya.
    // Master memuat ENAM parameter untuk TUJUH kolom ukur, dan ketimpangannya
    // berjalan ke dua arah: satu parameter mengatur dua kolom nut silo, dan
    // satu parameter ('Kernel Loss in Fibre') tidak dipetakan ke kolom mana
    // pun walau kolom bernama mirip ADA.
    expect($summary->json('targets_without_metric'))->toHaveCount(1);

    $unmeasured = collect($summary->json('targets_without_metric'))->pluck('parameter_metric')->all();

    expect($unmeasured)->toBe(['Kernel Loss in Fibre']);

    // KEEMPAT kolom master ikut diterbitkan, bukan hanya namanya — DAN
    // alasannya, karena 'ada kolomnya tapi arahnya belum pasti' menuntut
    // tindakan yang berbeda dari 'tidak ada kolomnya'.
    expect($summary->json('targets_without_metric.0.target_range'))->toBe('< 0.50%');
    expect($summary->json('targets_without_metric.0.critical_limit'))->toBe('> 1.00%');
    expect($summary->json('targets_without_metric.0.operational_consequence_justification'))->not->toBeNull();
    expect($summary->json('targets_without_metric.0.reason'))->toBe('direction_unresolved');
    expect($summary->json('all_targets_measured'))->toBeFalse();

    // Dan angka kolomnya TETAP diterbitkan, dengan targetnya null dan
    // alasannya terisi: tidak dipetakan bukan berarti tidak dilaporkan.
    $kernel = laporanDepricarpingMetric($summary, 'kernel_recovery_in_fibre_percent');

    expect($kernel['target']['parameter_metric'])->toBeNull();
    expect($kernel['target']['unmapped_reason'])->toBe('direction_unresolved');
});

it('skenario 18 — payload tidak memuat satu pun kunci penilaian terhadap standar', function () {
    laporanDepricarpingSeedTargets();

    $record = laporanDepricarpingRecord($this->stationA, '2026-09-04', 'PR-A');
    // 27,4 RPM terhadap standar '21 - 23 RPM'.
    laporanDepricarpingSlot($record, '07:00', ['polishing_drum_speed_rpm' => 27.4]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanDepricarpingUrl('summary', [
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
    $record = laporanDepricarpingRecord($this->stationA, '2026-09-04', 'PR-A');

    foreach (range(0, 2) as $index) {
        laporanDepricarpingSlot($record, $this->slots[$index], ['findings' => 'Belt kendur']);
    }

    laporanDepricarpingSlot($record, $this->slots[3], ['findings' => 'belt kendur']);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanDepricarpingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    expect($summary->json('findings'))->toBe([
        ['finding' => 'Belt kendur', 'slot_count' => 3],
        ['finding' => 'belt kendur', 'slot_count' => 1],
    ]);
});

// =====================================================================
// Skenario 20-24: ekspor
// =====================================================================

it('skenario 20 — ekspor CSV: satu baris per slot, konteks diulang, header lengkap', function () {
    $record = laporanDepricarpingRecord($this->stationA, '2026-09-04', 'PR-9');

    foreach (range(0, 2) as $index) {
        laporanDepricarpingSlot($record, $this->slots[$index], ['fan_static_pressure_mmh2o' => 30.0]);
    }

    $response = $this->actingAs($this->supervisor, 'web')->get(laporanDepricarpingUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');

    $lines = laporanDepricarpingCsvLinesOf(laporanDepricarpingStreamed($response->baseResponse));

    // 1 header + 3 baris slot.
    expect($lines)->toHaveCount(4);
    expect($lines[0])->toContain('Slot Waktu');
    // KESEMBILAN kolom bacaan, termasuk dua yang tidak ada padanannya pada
    // ekspor Threshing/Pressing: menit downtime (angka) dan temuan (teks).
    expect($lines[0])->toContain('Downtime (Menit)');
    expect($lines[0])->toContain('Temuan');

    foreach (array_slice($lines, 1) as $line) {
        expect($line)->toContain('Periode September Alpha');
        expect($line)->toContain('Mill Alpha');
        expect($line)->toContain('PR-9');
    }
});

it('skenario 21 — slot kosong tetap menjadi baris ekspor', function () {
    $record = laporanDepricarpingRecord($this->stationA, '2026-09-04', 'PR-9');
    laporanDepricarpingSlot($record, '07:00');

    $response = $this->actingAs($this->supervisor, 'web')->get(laporanDepricarpingUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));

    $response->assertOk();

    $lines = laporanDepricarpingCsvLinesOf(laporanDepricarpingStreamed($response->baseResponse));

    expect($lines)->toHaveCount(2);
    expect($lines[1])->toContain('PR-9');
});

it('skenario 22 — format di luar csv|excel: 422', function () {
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanDepricarpingUrl('export', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
            'format' => 'pdf',
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('format');
});

it('skenario 23 — ekspor Excel memulangkan content-type xlsx', function () {
    $record = laporanDepricarpingRecord($this->stationA, '2026-09-04', 'PR-9');
    laporanDepricarpingSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 30.0]);

    $response = $this->actingAs($this->supervisor, 'web')->get(laporanDepricarpingUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'excel',
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('skenario 24 — periode tertutup tetap dapat dibaca dan diekspor', function () {
    $closed = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('depricarping')
        ->range('2026-08-01', '2026-08-31')->closed()->named('Periode Agustus Tertutup')->create();

    $record = laporanDepricarpingRecord($this->stationA, '2026-08-05', 'PR-9');
    laporanDepricarpingSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 30.0]);

    $periods = $this->actingAs($this->supervisor, 'web')->getJson(laporanDepricarpingUrl('periods'));
    $periods->assertOk();
    expect(collect($periods->json('data'))->pluck('status'))->toContain('closed');

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanDepricarpingUrl('summary', [
        'period_id' => (string) $closed->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();
    expect($summary->json('period.status'))->toBe('closed');

    // Kunci periode mengatur PENULISAN data, bukan pembacaan laporan.
    $this->actingAs($this->supervisor, 'web')->get(laporanDepricarpingUrl('export', [
        'period_id' => (string) $closed->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]))->assertOk();
});

// =====================================================================
// Skenario 25: satu baris yang menentukan layar dapat dicapai
// =====================================================================

it('skenario 25 — REPORT_ROUTES memetakan depricarping, menaruhnya DI ANTARA threshing dan clarification', function () {
    // Tanpa entri 'depricarping' pada StationReportService::REPORT_ROUTES, layar
    // laporan ada, seluruh test lainnya lolos, dan tile-nya tetap kelabu.
    expect(StationReportService::REPORT_ROUTES)->toHaveKey('depricarping');
    expect(StationReportService::REPORT_ROUTES['depricarping'])->toBe('reports.depricarping');
    expect(route(StationReportService::REPORT_ROUTES['depricarping'], [], false))->toBe('/reports/depricarping');

    // URUTANNYA LOAD-BEARING: peta ini harus tetap urut menurut
    // station_types.sort_order, karena layar pemilih stasiun membandingkan
    // urutannya dengan urutan master. Asersi ber-urutan, bukan sekadar
    // "memuat".
    //
    // DEPRICARPING ADALAH 110, BUKAN 65. Alur proses menempatkannya tepat
    // setelah Pressing, dan urutan deklarasi enum pun begitu — tetapi
    // sort_order menaruhnya di BELAKANG clarification (70) dan boiler-room
    // (90), di depan storage-tank (140). Terverifikasi lewat kueri langsung ke
    // tabel station_types, bukan disimpulkan dari enum.
    $codes = array_keys(StationReportService::REPORT_ROUTES);
    $at = array_search('depricarping', $codes, true);

    expect($codes[$at - 1])->toBe('boiler-room');
    expect($codes[$at + 1])->toBe('storage-tank');
});
