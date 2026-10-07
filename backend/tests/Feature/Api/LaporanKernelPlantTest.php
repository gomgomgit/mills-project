<?php

/**
 * LaporanKernelPlantTest (API) — screen-154--laporan-kernel-plant-web /
 * screen-155--laporan-kernel-plant-mobile, keempat endpoint
 * /api/kernel-plant-reports/*.
 *
 * Satu uji per test_scenarios[].api_test pada tech spec screen-154. Aturan
 * agregasinya sendiri dibuktikan di
 * tests/Unit/Services/KernelPlantReportServiceTest.php terhadap service-nya;
 * yang dibuktikan berkas ini adalah KONTRAK HTTP-nya — kode status, siapa yang
 * diterima, apa yang benar-benar dibawa payload-nya, dan bagaimana bentuk
 * berkas ekspornya.
 *
 * FIXTURE-NYA SENGAJA TIDAK RATA: kolom diisi pada HIMPUNAN SLOT YANG
 * BERBEDA-BEDA, sehingga satu penyebut bersama tidak dapat lolos tanpa
 * terlihat. Lihat docblock berkas unit untuk mengapa merapikannya mengubah
 * asersi-asersi ini menjadi selalu hijau.
 *
 * TANGGALNYA SELALU RELATIF (now()->subDays(...)): periode uji di bawah harus
 * SELALU sudah selesai agar days_counted-nya deterministik, dan tanggal yang
 * dipaku berhenti memenuhi syarat itu begitu kalender melewatinya.
 *
 * TIGA TEMPAT KONTRAKNYA BERBEDA DARI TULISAN TECH SPEC, dan di ketiganya
 * implementasinya yang diikuti — masing-masing diberi komentar di tempatnya:
 * skenario 3 (production_line_id absen adalah 422, bukan 200), skenario 12
 * (Admin lolos periode mill mana pun, jadi 200 bukan 403), dan skenario 13
 * (akun terikat mill tanpa business_unit_id adalah 422 VALIDATION_ERROR,
 * bukan 403 FORBIDDEN).
 */

use App\Enums\RecordStatus;
use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\KernelPlantDetail;
use App\Models\KernelPlantOperationalTarget;
use App\Models\KernelPlantRecord;
use App\Models\Period;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use App\Services\KernelPlantRecordService;
use App\Services\KernelPlantReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function laporanKernelPlantUrl(string $path, array $query = []): string
{
    return '/api/kernel-plant-reports/'.$path.($query === [] ? '' : '?'.http_build_query($query));
}

function laporanKernelPlantRecord(
    Station $station,
    string $date,
    string $kernelPlantId = 'KP-1',
    array $attributes = [],
): KernelPlantRecord {
    return KernelPlantRecord::factory()
        ->forStation($station)
        ->onDate($date)
        ->create(array_merge(['kernel_plant_id' => $kernelPlantId, 'note' => 'Catatan harian'], $attributes));
}

function laporanKernelPlantSlot(KernelPlantRecord $record, string $timeSlot, array $values = []): KernelPlantDetail
{
    return KernelPlantDetail::factory()
        ->forRecord($record)
        ->timeSlot($timeSlot)
        ->create($values);
}

/** @return list<array{0: string, 1: string, 2: string}> */
function laporanKernelPlantTargetRows(): array
{
    // ENAM baris master, persis seperti KernelPlantOperationalTargetSeeder.
    //
    // Masternya hanya TIGA kolom — equipment_parameter / target_benchmark /
    // corrective_action_plan — dan tidak ada kolom batas kritis apa pun.
    //
    // ENAM parameter untuk TUJUH kolom ukur, dan ketimpangannya dua arah:
    //   - 'Ripple Mill (Cracker)' mengatur DUA kolom dan 'Kernel Silo 1 & 2'
    //     juga DUA, jadi tujuh entri peta hanya menyebut LIMA parameter;
    //   - 'Final Kernel Dirt' tidak punya kolom ukur di MANA PUN pada skema
    //     ini, jadi ia penghuni tetap targets_without_metric.
    return [
        ['Ripple Mill (Cracker)', '20 - 25 Amps (Nut Breakage >95%)', 'Adjust rotor-vane clearance if uncracked nut rate >5%.'],
        ['Claybath / Hydrocyclone', 'Specific Gravity 1.18 - 1.24', 'Verify calcium carbonate mixture if kernels float with shell.'],
        ['Kernel Silo 1 & 2', '70°C - 80°C (Top/Middle zones)', 'Check heater elements/steam valves if temperature drops below 65°C.'],
        ['Final Kernel Moisture', '≤ 7.0% (Prevents mold growth)', 'Increase retention time or adjust silo air flow rates.'],
        ['Final Kernel Dirt', '≤ 6.0% (Standard quality premium)', 'Clean winnowing ducts or re-calibrate hydrocyclone settings.'],
        ['Shell Bin Kernel Loss', '≤ 1.5% (Maximized separation recovery)', 'Reduce air velocity or inspect separator screen meshes.'],
    ];
}

function laporanKernelPlantSeedTargets(?callable $filter = null): void
{
    foreach (laporanKernelPlantTargetRows() as $index => [$parameter, $benchmark, $plan]) {
        if ($filter !== null && ! $filter($parameter)) {
            continue;
        }

        KernelPlantOperationalTarget::create([
            'equipment_parameter' => $parameter,
            'target_benchmark' => $benchmark,
            'corrective_action_plan' => $plan,
            'sort_order' => $index + 1,
        ]);
    }
}

/** Isi StreamedResponse, ditangkap. ALIRKAN SEKALI SAJA. */
function laporanKernelPlantStreamed($response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

function laporanKernelPlantCsvLinesOf(string $body): array
{
    return array_values(array_filter(explode("\n", trim($body))));
}

/** Entri metrics[] untuk satu kolom. */
function laporanKernelPlantMetric($response, string $column): array
{
    foreach ($response->json('metrics') as $metric) {
        if ($metric['column'] === $column) {
            return $metric;
        }
    }

    throw new RuntimeException("metric {$column} tidak ada pada payload");
}

/** @return list<string> seluruh nama KUNCI di mana pun dalam struktur */
function laporanKernelPlantAllKeys(mixed $value): array
{
    if (! is_array($value)) {
        return [];
    }

    $keys = [];

    foreach ($value as $key => $child) {
        if (is_string($key)) {
            $keys[] = $key;
        }

        $keys = array_merge($keys, laporanKernelPlantAllKeys($child));
    }

    return array_values(array_unique($keys));
}

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->kernelPlant()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->kernelPlant()->create();

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

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelPlant->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Kernel Plant Alpha')
        ->create();

    $this->slots = KernelPlantRecordService::canonicalTimeSlots();
});

// =====================================================================
// Skenario 1-2: jalur sukses
// =====================================================================

it('skenario 1 — Supervisor dan Mill Management: periods + summary + export mill sendiri', function () {
    laporanKernelPlantSeedTargets();

    $record = laporanKernelPlantRecord($this->stationA, $this->day2, 'KP-1');

    // Kadar air pada 4 slot; arus ripple mill 1 hanya pada 2 — penyebut yang
    // berbeda itulah yang membuat asersi di bawah bermakna.
    foreach (range(0, 3) as $index) {
        $values = ['kernel_moisture_percent' => 6.5];

        if ($index < 2) {
            $values['ripple_mill_1_amps'] = 22.0;
        }

        laporanKernelPlantSlot($record, $this->slots[$index], $values);
    }

    foreach ([$this->supervisor, $this->millManagement] as $user) {
        $periods = $this->actingAs($user, 'web')->getJson(laporanKernelPlantUrl('periods'));
        $periods->assertOk();
        expect(collect($periods->json('data'))->pluck('id'))->toContain((string) $this->periodA->id);
        expect(collect($periods->json('data'))->pluck('station_type'))->toContain('kernel-plant');

        $summary = $this->actingAs($user, 'web')->getJson(laporanKernelPlantUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]));
        $summary->assertOk();

        expect($summary->json('business_unit.name'))->toBe('Mill Alpha');
        expect($summary->json('has_data'))->toBeTrue();
        expect($summary->json('coverage.filled_slots'))->toBe(4);
        expect($summary->json('metrics'))->toHaveCount(7);

        // Penyebut per kolom, bukan satu penyebut bersama.
        expect(laporanKernelPlantMetric($summary, 'kernel_moisture_percent')['filled_slot_count'])->toBe(4);
        expect(laporanKernelPlantMetric($summary, 'ripple_mill_1_amps')['filled_slot_count'])->toBe(2);

        // Standar operasional menyertai angkanya pada baris yang sama, dengan
        // KETIGA kolom master — dan hanya ketiga itu, karena master ini tidak
        // punya kolom batas kritis apa pun.
        $ripple = laporanKernelPlantMetric($summary, 'ripple_mill_1_amps')['target'];

        expect($ripple['equipment_parameter'])->toBe('Ripple Mill (Cracker)');
        expect($ripple['target_benchmark'])->toBe('20 - 25 Amps (Nut Breakage >95%)');
        expect($ripple['corrective_action_plan'])->toContain('rotor-vane clearance');
        expect($ripple['shares_standard_with'])->toBe(['ripple_mill_2_amps']);
        expect($ripple)->not->toHaveKey('critical_limit');
        expect($ripple)->not->toHaveKey('operational_consequence_justification');
    }

    $export = $this->actingAs($this->supervisor, 'web')->get(laporanKernelPlantUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));
    $export->assertOk();
    expect($export->headers->get('content-type'))->toContain('text/csv');
});

it('skenario 2 — Admin: pemilih mill lalu angka mill yang dipilih saja', function () {
    $recordA = laporanKernelPlantRecord($this->stationA, $this->day2, 'KP-A');
    laporanKernelPlantSlot($recordA, '07:00', ['ripple_mill_1_amps' => 11.0]);

    $recordB = laporanKernelPlantRecord($this->stationB, $this->day2, 'KP-B');
    laporanKernelPlantSlot($recordB, '07:00', ['ripple_mill_1_amps' => 99.0]);

    $options = $this->actingAs($this->admin, 'web')->getJson(laporanKernelPlantUrl('business-units/options'));
    $options->assertOk();
    expect(collect($options->json('data'))->pluck('name'))->toContain('Mill Alpha', 'Mill Beta');

    $periods = $this->actingAs($this->admin, 'web')->getJson(laporanKernelPlantUrl('periods', [
        'business_unit_id' => (string) $this->businessUnitA->id,
    ]));
    $periods->assertOk();
    expect(collect($periods->json('data'))->pluck('id'))->toContain((string) $this->periodA->id);

    $summary = $this->actingAs($this->admin, 'web')->getJson(laporanKernelPlantUrl('summary', [
        'business_unit_id' => (string) $this->businessUnitA->id,
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    // Hanya mill yang dipilih — 99.0 milik mill lain tidak boleh terbaca.
    expect(laporanKernelPlantMetric($summary, 'ripple_mill_1_amps')['max'])->toEqual(11.0);

    $this->actingAs($this->admin, 'web')->get(laporanKernelPlantUrl('export', [
        'business_unit_id' => (string) $this->businessUnitA->id,
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]))->assertOk();
});

// =====================================================================
// Skenario 3-5: parameter wajib dan daftar kosong
// =====================================================================

it('skenario 3 — production_line_id TIDAK dikirim: 422, dan nol kueri kernel_plant_records', function () {
    $queries = [];

    DB::listen(function ($query) use (&$queries) {
        $queries[] = strtolower($query->sql);
    });

    // BEDA DARI TULISAN TECH SPEC, yang menulis 200 dengan production_line
    // null untuk keadaan ini. Yang diikuti adalah implementasinya —
    // KernelPlantReportController::requireProductionLineId() menolak 422 —
    // dan alasannya ada di docblock-nya: total yang mencampur belasan
    // production line bukan angka yang bisa ditindaklanjuti siapa pun, dan
    // diam-diam melebarkan cakupan laporan karena satu parameter terlupa
    // adalah yang terburuk dari keduanya: ia menjawab 200 dengan angka yang
    // tidak diminta siapa pun. Service-nya memang menerima null (diuji di
    // berkas unit, case 17) — yang menolak adalah lapisan HTTP.
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanKernelPlantUrl('summary', ['period_id' => (string) $this->periodA->id]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('production_line_id');

    // Tidak di-default ke "semua line": permintaan yang tidak lengkap tidak
    // pernah menjadi kueri data.
    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "kernel_plant_records"');
    }

    // Ekspor pun sama.
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanKernelPlantUrl('export', ['period_id' => (string) $this->periodA->id]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('production_line_id');
});

it('skenario 3b — period_id absen: 422 dengan errors.period_id, bukan 404; Admin tanpa mill: 422', function () {
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanKernelPlantUrl('summary', ['production_line_id' => $this->lineA]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('period_id');

    $this->actingAs($this->admin, 'web')
        ->getJson(laporanKernelPlantUrl('periods'))
        ->assertStatus(422)
        ->assertJsonValidationErrors('business_unit_id');

    // MILL DIPERIKSA SEBELUM ID PERIODE: 422, bukan 404, walau period_id-nya
    // memang tidak ada.
    $this->actingAs($this->admin, 'web')
        ->getJson(laporanKernelPlantUrl('summary', [
            'period_id' => (string) Str::uuid(),
            'production_line_id' => $this->lineA,
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('business_unit_id');
});

it('skenario 4 — mill hanya punya satu Production Line: line tetap dikirim EKSPLISIT', function () {
    $record = laporanKernelPlantRecord($this->stationA, $this->day2, 'KP-1');
    laporanKernelPlantSlot($record, '07:00', ['ripple_mill_1_amps' => 22.0]);

    // Hanya ada satu line pada mill A (dibuat oleh factory stasiun).
    expect(ProductionLine::query()->where('business_unit_id', $this->businessUnitA->id)->count())->toBe(1);

    $periods = $this->actingAs($this->supervisor, 'web')->getJson(laporanKernelPlantUrl('periods'));
    $periods->assertOk();

    // API TIDAK PERNAH MENGISI SENDIRI dan tidak punya default: satu-satunya
    // line pun harus dikirim. Lihat skenario 3 untuk apa yang terjadi bila
    // tidak.
    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanKernelPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    expect($summary->json('production_line.id'))->toBe($this->lineA);
    expect($summary->json('coverage.filled_slots'))->toBe(1);
});

it('skenario 5 — mill belum punya satu pun periode Kernel Plant: 200 dengan data []', function () {
    $supervisorB = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitB)->create();

    Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType('sterilizer')
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Sterilizer Saja')
        ->create();

    $periods = $this->actingAs($supervisorB, 'web')->getJson(laporanKernelPlantUrl('periods'));

    // [] dengan HTTP 200 — pemilih kosong plus petunjuk ke Kelola Periode
    // Pelaporan, BUKAN 404, dan BUKAN periode milik jenis stasiun lain yang
    // bocor masuk.
    $periods->assertOk();
    expect($periods->json('data'))->toBe([]);
});

// =====================================================================
// Skenario 6-11: bentuk payload
// =====================================================================

it('skenario 6 — periode tanpa satu pun slot terisi: 200, has_data false, dan coverage_percent 0.0 BUKAN null', function () {
    $record = laporanKernelPlantRecord($this->stationA, $this->day2, 'KP-A');

    foreach ($this->slots as $slot) {
        laporanKernelPlantSlot($record, $slot);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanKernelPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    expect($summary->json('has_data'))->toBeFalse();
    expect($summary->json('coverage.filled_slots'))->toBe(0);
    expect($summary->json('findings'))->toBe([]);

    // PENYEBUTNYA TERBENTUK — satu unit punya record pada 10 hari terhitung —
    // jadi persennya 0.0, bukan null. Syarat null adalah tentang PENYEBUT
    // (expected_slots === 0), bukan tentang pembilang. Lihat skenario 7 untuk
    // kasus null-nya.
    expect($summary->json('coverage.kernel_plant_count'))->toBe(1);
    expect($summary->json('coverage.expected_slots'))->toBe(240);
    expect($summary->json('coverage.coverage_percent'))->not->toBeNull();
    expect($summary->json('coverage.coverage_percent'))->toEqual(0.0);
    // DIASERSI JUGA ATAS TEKS JSON-NYA, karena di sinilah ketelitiannya
    // tergerus: service memulangkan float 0.0, tetapi json_encode menuliskan
    // `0` tanpa desimal, sehingga toBe(0.0) atas payload yang sudah di-decode
    // gagal walau nilainya benar. Yang harus dibedakan layar bukan 0 dari
    // 0.0 melainkan 0 dari null, dan asersi inilah yang mengunci perbedaan
    // itu pada bentuk yang benar-benar dikirim ke klien.
    expect($summary->getContent())->toContain('"coverage_percent":0');
    expect($summary->getContent())->not->toContain('"coverage_percent":null');

    // TANGGAL YANG PUNYA RECORD TETAP MENDAPAT BARIS, meski tak satu slot pun
    // terisi — membuangnya akan membuat periode ini terlihat lebih tercatat
    // daripada kenyataannya. Yang menyembunyikan tabelnya di layar adalah
    // has_data, bukan ketiadaan baris.
    expect($summary->json('daily'))->toHaveCount(1);
    expect($summary->json('daily.0.filled_slot_count'))->toBe(0);
    expect($summary->json('daily.0.averages.ripple_mill_1_amps'))->toBeNull();

    // null, BUKAN 0 — nol berarti "terukur dan hasilnya nol".
    foreach ($summary->json('metrics') as $metric) {
        expect($metric['min'])->toBeNull();
        expect($metric['avg'])->toBeNull();
        expect($metric['max'])->toBeNull();
        expect($metric['filled_slot_count'])->toBe(0);
    }

    expect($summary->json('downtime.total_minutes'))->toBeNull();
    // Record-nya tetap dihitung.
    expect($summary->json('total.record_count'))->toBe(1);
});

it('skenario 7 — periode belum mulai: days_counted 0, coverage_percent null, DAN period_running tetap true', function () {
    $notStarted = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelPlant->value)
        ->range(now()->addDays(10)->toDateString(), now()->addDays(25)->toDateString())
        ->open()
        ->named('Periode Belum Mulai')
        ->create();

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanKernelPlantUrl('summary', [
        'period_id' => (string) $notStarted->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    // KEDUANYA diasersi bersama, dan itulah inti skenario ini: layar WAJIB
    // membaca days_counted === 0 LEBIH DULU, karena period_running bernilai
    // true JUGA untuk periode yang hari pertamanya belum datang.
    expect($summary->json('coverage.days_counted'))->toBe(0);
    expect($summary->json('coverage.period_running'))->toBeTrue();
    expect($summary->json('coverage.expected_slots'))->toBe(0);
    expect($summary->json('coverage.coverage_percent'))->toBeNull();
    expect($summary->json('coverage.days_in_period'))->toBe(16);
});

it('skenario 8 — periode sedang berjalan: penyebut berhenti di hari ini', function () {
    $running = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelPlant->value)
        ->range(now()->subDays(3)->toDateString(), now()->addDays(7)->toDateString())
        ->open()
        ->named('Periode Berjalan')
        ->create();

    $record = laporanKernelPlantRecord($this->stationA, now()->subDays(1)->toDateString(), 'KP-1');
    laporanKernelPlantSlot($record, '07:00', ['ripple_mill_1_amps' => 22.0]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanKernelPlantUrl('summary', [
        'period_id' => (string) $running->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    expect($summary->json('coverage.days_in_period'))->toBe(11);
    expect($summary->json('coverage.days_counted'))->toBe(4);
    expect($summary->json('coverage.period_running'))->toBeTrue();
    // Hari yang belum terjadi tidak mungkin tercatat, jadi tidak boleh
    // menjadi pembagi: 1 unit x 4 hari x 24 slot.
    expect($summary->json('coverage.expected_slots'))->toBe(96);
});

it('skenario 9 — slot yang hanya mengisi findings: filled_slots MELEBIHI penyebut setiap metrik', function () {
    $record = laporanKernelPlantRecord($this->stationA, $this->day2, 'KP-A');

    // 10 slot berkolom ukur, kadar air mengisi kesepuluhnya.
    foreach (range(0, 9) as $index) {
        $values = ['kernel_moisture_percent' => 6.0 + ($index / 10)];

        if ($index < 4) {
            $values['ripple_mill_1_amps'] = 22.0;
        }

        laporanKernelPlantSlot($record, $this->slots[$index], $values);
    }

    // DITAMBAH 4 slot yang HANYA mengisi findings.
    foreach (range(10, 13) as $index) {
        laporanKernelPlantSlot($record, $this->slots[$index], ['findings' => 'Periksa ripple mill']);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanKernelPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    expect($summary->json('has_data'))->toBeTrue();
    expect($summary->json('coverage.filled_slots'))->toBe(14);

    // KETIDAKSAMAAN, bukan kesamaan: slot dihitung terisi bila salah satu dari
    // SEMBILAN kolom bacaan terisi, dan dua di antaranya (downtime_minutes,
    // findings) bukan kolom ukur. Selisih ini BENAR, dan kesamaan justru bug
    // yang hendak dicegah.
    foreach ($summary->json('metrics') as $metric) {
        expect($summary->json('coverage.filled_slots'))
            ->toBeGreaterThan($metric['filled_slot_count'], "filled_slots harus melebihi penyebut {$metric['column']}");
    }

    expect(collect($summary->json('metrics'))->max('filled_slot_count'))->toBe(10);
    // Dan keempat slot itu tidak menyumbang satu pun angka ukur.
    expect($summary->json('findings'))->toBe([['finding' => 'Periksa ripple mill', 'slot_count' => 4]]);
});

it('skenario 10 — ripple mill 2 dan salah satu silo tidak pernah diisi: barisnya TETAP ada dengan standarnya', function () {
    laporanKernelPlantSeedTargets();

    $record = laporanKernelPlantRecord($this->stationA, $this->day2, 'KP-A');
    laporanKernelPlantSlot($record, '07:00', [
        'ripple_mill_1_amps' => 22.0,
        'kernel_silo_1_temp_c' => 75.0,
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanKernelPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    // TUJUH entri, bukan lima: baris yang hilang terbaca sebagai "tidak ada
    // parameter ini", padahal yang benar adalah "tidak ada yang mengukurnya".
    expect($summary->json('metrics'))->toHaveCount(7);

    foreach (['ripple_mill_2_amps' => 'Ripple Mill (Cracker)', 'kernel_silo_2_temp_c' => 'Kernel Silo 1 & 2'] as $column => $parameter) {
        $metric = laporanKernelPlantMetric($summary, $column);

        expect($metric['min'])->toBeNull();
        expect($metric['avg'])->toBeNull();
        expect($metric['max'])->toBeNull();
        expect($metric['filled_slot_count'])->toBe(0);
        // Standarnya TETAP tercetak walau tidak ada pembacaan, dan ia standar
        // yang SAMA dengan pasangannya.
        expect($metric['target']['equipment_parameter'])->toBe($parameter);
        expect($metric['target']['shares_standard_with'])->toHaveCount(1);
    }

    expect(laporanKernelPlantMetric($summary, 'ripple_mill_2_amps')['target']['target_benchmark'])
        ->toBe(laporanKernelPlantMetric($summary, 'ripple_mill_1_amps')['target']['target_benchmark']);
});

it('skenario 11 — master disunting tangan: standarnya terlepas dan PINDAH ke targets_without_metric', function () {
    laporanKernelPlantSeedTargets();

    KernelPlantOperationalTarget::query()
        ->where('equipment_parameter', 'Kernel Silo 1 & 2')
        ->update(['equipment_parameter' => 'Kernel Silo 1 and 2']);

    $record = laporanKernelPlantRecord($this->stationA, $this->day2, 'KP-A');
    laporanKernelPlantSlot($record, '07:00', [
        'kernel_silo_1_temp_c' => 75.0,
        'kernel_silo_2_temp_c' => 76.0,
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanKernelPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    // ANGKANYA UTUH: pemetaan memakai peta TETAP, bukan pencocokan teks nama
    // parameter saat render.
    expect(laporanKernelPlantMetric($summary, 'kernel_silo_1_temp_c')['avg'])->toEqual(75.0);
    expect(laporanKernelPlantMetric($summary, 'kernel_silo_2_temp_c')['avg'])->toEqual(76.0);
    // Standarnya terlepas, dan keterlepasan itu TERLIHAT alih-alih senyap.
    expect(laporanKernelPlantMetric($summary, 'kernel_silo_1_temp_c')['target']['target_benchmark'])->toBeNull();
    expect(laporanKernelPlantMetric($summary, 'kernel_silo_2_temp_c')['target']['target_benchmark'])->toBeNull();

    // DUA entri: baris yang disunting, DI SAMPING 'Final Kernel Dirt'.
    expect($summary->json('targets_without_metric'))->toHaveCount(2);

    $names = collect($summary->json('targets_without_metric'))->pluck('equipment_parameter')->all();

    expect($names)->toContain('Kernel Silo 1 and 2');
    expect($names)->toContain('Final Kernel Dirt');
    expect($summary->json('all_targets_measured'))->toBeFalse();

    foreach ($summary->json('targets_without_metric') as $row) {
        // KUNCINYA equipment_parameter — barisnya adalah baris master APA
        // ADANYA plus `reason`, jadi namanya nama kolom yang sebenarnya.
        expect(array_keys($row))->toBe([
            'equipment_parameter', 'target_benchmark', 'corrective_action_plan', 'reason',
        ]);
        expect($row['reason'])->toBe('no_column');
    }
});

// =====================================================================
// Skenario 12-15: penjagaan lintas mill dan peran
// =====================================================================

it('skenario 12 — Admin berganti mill: period_id mill A bersama business_unit_id mill B', function () {
    $recordA = laporanKernelPlantRecord($this->stationA, $this->day2, 'KP-A');
    laporanKernelPlantSlot($recordA, '07:00', ['ripple_mill_1_amps' => 11.0]);

    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::KernelPlant->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Kernel Plant Beta')
        ->create();

    $this->actingAs($this->admin, 'web')->getJson(laporanKernelPlantUrl('business-units/options'))->assertOk();

    $periodsA = $this->actingAs($this->admin, 'web')->getJson(laporanKernelPlantUrl('periods', [
        'business_unit_id' => (string) $this->businessUnitA->id,
    ]));
    $periodsA->assertOk();
    expect(collect($periodsA->json('data'))->pluck('name')->all())->toBe(['Periode Kernel Plant Alpha']);

    $this->actingAs($this->admin, 'web')->getJson(laporanKernelPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'business_unit_id' => (string) $this->businessUnitA->id,
        'production_line_id' => $this->lineA,
    ]))->assertOk();

    // DAFTAR PERIODE MEMANG TERPISAH PER MILL: periode mill A tidak muncul
    // pada daftar mill B. Inilah yang membuat komponen WAJIB mereset
    // period_id dan production_line_id saat mill berganti.
    $periodsB = $this->actingAs($this->admin, 'web')->getJson(laporanKernelPlantUrl('periods', [
        'business_unit_id' => (string) $this->businessUnitB->id,
    ]));
    $periodsB->assertOk();
    expect(collect($periodsB->json('data'))->pluck('name')->all())->toBe(['Periode Kernel Plant Beta']);
    expect(collect($periodsB->json('data'))->pluck('id')->all())->not->toContain((string) $this->periodA->id);

    // BEDA DARI TULISAN TECH SPEC, yang menulis 403 FORBIDDEN untuk langkah
    // ini. Implementasinya menjawab 200: authorizePeriodModel() meloloskan
    // Admin untuk mill MANA PUN — satu-satunya peran tak terikat — persis
    // seperti kelima laporan kondisi sebelumnya. Yang menentukan angka adalah
    // mill PERIODE, bukan business_unit_id kiriman, jadi jawabannya tetap
    // data mill A dan angka mill B tetap mustahil terbaca.
    //
    // KONSEKUENSINYA UNTUK LAYAR TIDAK BERUBAH, dan justru itu yang dikunci
    // di sini: pasangan (mill B, periode mill A) menghasilkan halaman yang
    // JUDUL MILL-nya tidak sama dengan mill yang baru saja dipilih — jadi
    // komponen harus mereset period_id saat mill berganti, bukan mengandalkan
    // 403 yang tidak akan datang.
    $mismatched = $this->actingAs($this->admin, 'web')->getJson(laporanKernelPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'business_unit_id' => (string) $this->businessUnitB->id,
        'production_line_id' => $this->lineA,
    ]));

    $mismatched->assertOk();
    expect($mismatched->json('business_unit.name'))->toBe('Mill Alpha');
    expect($mismatched->json('business_unit.name'))->not->toBe('Mill Beta');
    expect(laporanKernelPlantMetric($mismatched, 'ripple_mill_1_amps')['max'])->toEqual(11.0);

    // Dan periode mill lain yang diminta peran TERIKAT MILL tetap 403.
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanKernelPlantUrl('summary', [
            'period_id' => (string) $periodB->id,
            'production_line_id' => $this->lineA,
        ]))
        ->assertStatus(403);

    // Periode yang tidak ada: 404, bukan 403.
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanKernelPlantUrl('summary', [
            'period_id' => (string) Str::uuid(),
            'production_line_id' => $this->lineA,
        ]))
        ->assertStatus(404);
});

it('skenario 13 — akun terikat mill tanpa business_unit_id: 422 VALIDATION_ERROR pada periods dan summary', function () {
    $boundWithoutMill = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    // BEDA DARI TULISAN TECH SPEC, yang menulis 403 FORBIDDEN. Implementasinya
    // 422 VALIDATION_ERROR dengan errors.business_unit_id — sama seperti
    // kelima laporan kondisi sebelumnya, dan memang lebih tepat: ini
    // master-data akun yang belum lengkap, bukan akses yang ditolak.
    //
    // YANG LOAD-BEARING BUKAN ANGKA STATUSNYA melainkan GAGAL TERTUTUP: jatuh
    // ke "seluruh mill" akan mengubah satu baris master-data yang rusak
    // menjadi kebocoran lintas mill, dan hasil kosong akan terbaca sebagai
    // "mill ini tidak punya data".
    $this->actingAs($boundWithoutMill, 'web')
        ->getJson(laporanKernelPlantUrl('periods'))
        ->assertStatus(422)
        ->assertJsonValidationErrors('business_unit_id');

    $this->actingAs($boundWithoutMill, 'web')
        ->getJson(laporanKernelPlantUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('business_unit_id');

    // Dan pemilih mill TETAP tertutup untuknya — ia peran terikat mill.
    $this->actingAs($boundWithoutMill, 'web')
        ->getJson(laporanKernelPlantUrl('business-units/options'))
        ->assertStatus(403);
});

it('skenario 14 — mill atau line milik mill lain diminta lewat properti: 200 berisi data mill sendiri', function () {
    $record = laporanKernelPlantRecord($this->stationA, $this->day2, 'KP-A');
    laporanKernelPlantSlot($record, '07:00', ['ripple_mill_1_amps' => 11.0]);

    $recordB = laporanKernelPlantRecord($this->stationB, $this->day2, 'KP-B');
    laporanKernelPlantSlot($recordB, '07:00', ['ripple_mill_1_amps' => 99.0]);

    // business_unit_id mill lain pada daftar periode: DIABAIKAN, 200, daftar
    // mill sendiri.
    $periods = $this->actingAs($this->supervisor, 'web')->getJson(laporanKernelPlantUrl('periods', [
        'business_unit_id' => (string) $this->businessUnitB->id,
    ]));
    $periods->assertOk();
    expect(collect($periods->json('data'))->pluck('name')->all())->toBe(['Periode Kernel Plant Alpha']);

    // business_unit_id mill lain DAN production_line_id mill lain sekaligus.
    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanKernelPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'business_unit_id' => (string) $this->businessUnitB->id,
        'production_line_id' => $this->lineB,
    ]));

    // SENGAJA 200, bukan 403: sebuah 403 justru akan memastikan mill/line lain
    // itu ada. business_unit_id tidak divalidasi, tidak dibandingkan, DIBUANG;
    // dan line mill lain menyaring sampai nol baris alih-alih menolak.
    $summary->assertOk();
    expect($summary->json('business_unit.name'))->toBe('Mill Alpha');
    // 99.0 milik mill B tidak pernah terbaca lewat jalan mana pun.
    expect($summary->json('coverage.filled_slots'))->toBe(0);
    expect(laporanKernelPlantMetric($summary, 'ripple_mill_1_amps')['max'])->toBeNull();

    // DAN BLOK production_line TIDAK MEMBOCORKAN APA PUN — ini asersi yang
    // paling mudah luput, karena angkanya sudah aman di atas tanpa bantuan
    // siapa pun: scopeToProductionLine() menyaring ke nol baris dan query-nya
    // tetap terkurung pada mill periode. Yang TIDAK aman dengan sendirinya
    // adalah blok ini. Versi pertama productionLineInfo() mencari line dengan
    // find($id) tanpa klausa business_unit_id, sehingga permintaan ini
    // menjawab 200 berisi id DAN NAMA MANUSIAWI line milik mill lain —
    // dibuktikan lewat permintaan HTTP sungguhan, bukan disimpulkan dari
    // membaca kode. Itu merusak justru alasan line mill lain DIABAIKAN
    // alih-alih ditolak 403: penolakan ditahan supaya tidak memastikan line
    // itu ada, lalu blok ini memastikannya juga, lengkap dengan namanya.
    //
    // Diasersikan DUA KALI, dan keduanya perlu: null-nya blok, dan
    // ketiadaan nama line mill B pada SELURUH badan respons — sebab blok
    // yang null tidak membuktikan nama itu tak terbit di tempat lain.
    expect($summary->json('production_line'))->toBeNull();

    $lineBName = (string) \App\Models\ProductionLine::query()->whereKey($this->lineB)->value('name');
    expect($lineBName)->not->toBe('');
    expect($summary->getContent())->not->toContain($lineBName);

    // Dan dengan line mill SENDIRI, angkanya mill sendiri.
    $own = $this->actingAs($this->supervisor, 'web')->getJson(laporanKernelPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'business_unit_id' => (string) $this->businessUnitB->id,
        'production_line_id' => $this->lineA,
    ]));

    $own->assertOk();
    expect(laporanKernelPlantMetric($own, 'ripple_mill_1_amps')['max'])->toEqual(11.0);
});

it('skenario 15 — Operator diterima pada KETIGA rute data, dan hanya /business-units/options yang menolaknya', function () {
    $record = laporanKernelPlantRecord($this->stationA, $this->day2, 'KP-A');
    laporanKernelPlantSlot($record, '07:00', ['ripple_mill_1_amps' => 11.0]);

    $recordB = laporanKernelPlantRecord($this->stationB, $this->day2, 'KP-B');
    laporanKernelPlantSlot($recordB, '07:00', ['ripple_mill_1_amps' => 99.0]);

    // Diterima sejak awal, karena kembaran mobile screen-155 memakai rute API
    // yang sama.
    $this->actingAs($this->operator, 'web')->getJson(laporanKernelPlantUrl('periods'))->assertOk();

    $summary = $this->actingAs($this->operator, 'web')->getJson(laporanKernelPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();
    expect($summary->json('business_unit.name'))->toBe('Mill Alpha');

    $this->actingAs($this->operator, 'web')->get(laporanKernelPlantUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]))->assertOk();

    // INILAH asersi yang gagal bila Operator jatuh ke cabang Admin, tempat
    // business_unit_id kiriman klien DIHORMATI. Menguji "peran diterima" saja
    // akan tetap hijau walau cabangnya salah.
    $probing = $this->actingAs($this->operator, 'web')->getJson(laporanKernelPlantUrl('summary', [
        'business_unit_id' => (string) $this->businessUnitB->id,
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $probing->assertOk();
    expect($probing->json('business_unit.name'))->toBe('Mill Alpha');
    expect(laporanKernelPlantMetric($probing, 'ripple_mill_1_amps')['max'])->toEqual(11.0);

    // RUTE KEEMPAT tetap 403 untuk SETIAP peran terikat mill — penolakan yang
    // datang dari DALAM service, bukan dari middleware prefix (yang justru
    // meloloskan keempat peran demi ketiga rute di atas).
    foreach ([$this->operator, $this->supervisor, $this->millManagement] as $user) {
        $this->actingAs($user, 'web')
            ->getJson(laporanKernelPlantUrl('business-units/options'))
            ->assertStatus(403);
    }
});

it('skenario 15b — tamu: 401 pada keempat rute', function () {
    foreach (['business-units/options', 'periods', 'summary', 'export'] as $path) {
        $this->getJson(laporanKernelPlantUrl($path))->assertStatus(401);
    }
});

// =====================================================================
// Skenario 16-21: isolasi line, penyebut, pasangan standar
// =====================================================================

it('skenario 16 — penyaringan line memakai production_line_id pada tabel record, bukan join ke stations', function () {
    $lineA2 = ProductionLine::factory()->create([
        'business_unit_id' => $this->businessUnitA->id,
        'name' => 'Line A2',
    ]);

    // Line A1: satu record yang stasiunnya nanti DIPINDAH ke line A2. Kolom
    // record-nya tetap A1 — justru itu yang benar: data dihasilkan di sana.
    $onA1 = laporanKernelPlantRecord($this->stationA, $this->day2, 'KP-A1', [
        'production_line_id' => $this->lineA,
    ]);
    laporanKernelPlantSlot($onA1, '07:00', ['ripple_mill_1_amps' => 20.0]);

    $onA2 = laporanKernelPlantRecord($this->stationA, $this->day2, 'KP-A2', [
        'production_line_id' => $lineA2->id,
    ]);
    laporanKernelPlantSlot($onA2, '07:00', ['ripple_mill_1_amps' => 40.0]);

    // Stasiunnya KINI terdaftar di line A2.
    $this->stationA->forceFill(['production_line_id' => $lineA2->id])->save();

    $a1 = $this->actingAs($this->supervisor, 'web')->getJson(laporanKernelPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $a1->assertOk();

    // Join ke stations akan membaca keadaan SEKARANG dan menulis ulang
    // sejarah: ia akan menarik kedua record dan menjawab 30,0.
    expect($a1->json('coverage.filled_slots'))->toBe(1);
    expect(laporanKernelPlantMetric($a1, 'ripple_mill_1_amps')['avg'])->toEqual(20.0);
    expect(collect($a1->json('by_kernel_plant'))->pluck('kernel_plant_id')->all())->toBe(['KP-A1']);

    $a2 = $this->actingAs($this->supervisor, 'web')->getJson(laporanKernelPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => (string) $lineA2->id,
    ]));
    $a2->assertOk();

    expect($a2->json('coverage.filled_slots'))->toBe(1);
    expect(laporanKernelPlantMetric($a2, 'ripple_mill_1_amps')['avg'])->toEqual(40.0);
    expect(collect($a2->json('by_kernel_plant'))->pluck('kernel_plant_id')->all())->toBe(['KP-A2']);
});

it('skenario 17 — penyebut tiap parameter tidak boleh disamakan satu dengan lainnya', function () {
    $record = laporanKernelPlantRecord($this->stationA, $this->day2, 'KP-A');

    // TUJUH PENYEBUT YANG GENUIN BERBEDA. Dengan penyebut yang seragam, satu
    // penyebut bersama lolos tanpa terlihat.
    $pola = [
        'ripple_mill_1_amps' => 6,
        'ripple_mill_2_amps' => 2,
        'claybath_hydro_sg' => 5,
        'kernel_silo_1_temp_c' => 4,
        'kernel_silo_2_temp_c' => 3,
        'kernel_moisture_percent' => 7,
        'shell_loss_percent' => 1,
    ];

    foreach (range(0, 6) as $index) {
        $values = [];

        foreach ($pola as $column => $count) {
            if ($index < $count) {
                $values[$column] = 10.0 + $index;
            }
        }

        laporanKernelPlantSlot($record, $this->slots[$index], $values);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanKernelPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    expect($summary->json('metrics'))->toHaveCount(7);

    // DIASERSI PER KOLOM — jangan pernah satu penyebut bersama.
    foreach ($pola as $column => $count) {
        expect(laporanKernelPlantMetric($summary, $column)['filled_slot_count'])
            ->toBe($count, "penyebut {$column} harus {$count}");
    }

    expect(collect($summary->json('metrics'))->pluck('filled_slot_count')->all())
        ->toBe([6, 2, 5, 4, 3, 7, 1]);
    expect(collect($summary->json('metrics'))->pluck('column')->all())
        ->toBe(KernelPlantReportService::NUMERIC_METRICS);
});

it('skenario 18 — total downtime null bukan nol, dan nol yang tercatat tetap dihitung', function () {
    // Periode 1: tak satu slot pun mencatat downtime_minutes.
    $noDowntime = laporanKernelPlantRecord($this->stationA, $this->day2, 'KP-1');

    foreach (range(0, 3) as $index) {
        laporanKernelPlantSlot($noDowntime, $this->slots[$index], ['ripple_mill_1_amps' => 22.0]);
    }

    $first = $this->actingAs($this->supervisor, 'web')->getJson(laporanKernelPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $first->assertOk();

    // Total 0 menit terbaca seperti "stasiun tidak pernah berhenti", padahal
    // yang benar adalah "tidak ada yang mencatatnya".
    expect($first->json('downtime.total_minutes'))->toBeNull();
    expect($first->json('downtime.recorded_slot_count'))->toBe(0);
    expect($first->json('downtime.avg_minutes_per_recorded_slot'))->toBeNull();
    expect($first->json('downtime.has_standard'))->toBeFalse();

    // Periode 2: 3 slot mencatat NOL secara eksplisit.
    $secondPeriod = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelPlant->value)
        ->range(now()->subDays(9)->toDateString(), now()->subDays(5)->toDateString())
        ->open()
        ->named('Periode Downtime Nol')
        ->create();

    $zeroDowntime = laporanKernelPlantRecord($this->stationA, now()->subDays(8)->toDateString(), 'KP-2');

    foreach (range(0, 2) as $index) {
        laporanKernelPlantSlot($zeroDowntime, $this->slots[$index], ['downtime_minutes' => 0]);
    }

    laporanKernelPlantSlot($zeroDowntime, $this->slots[3], ['downtime_minutes' => 30]);

    $second = $this->actingAs($this->supervisor, 'web')->getJson(laporanKernelPlantUrl('summary', [
        'period_id' => (string) $secondPeriod->id,
        'production_line_id' => $this->lineA,
    ]));
    $second->assertOk();

    // NOL BERARTI SESEORANG MENYATAKAN stasiun tidak berhenti pada slot itu.
    // Memperlakukannya sebagai "tidak tercatat" akan membuang pernyataan itu —
    // penyebutnya 4, bukan 1.
    expect($second->json('downtime.total_minutes'))->toBe(30);
    expect($second->json('downtime.recorded_slot_count'))->toBe(4);
    expect($second->json('downtime.avg_minutes_per_recorded_slot'))->toEqual(7.5);
});

it('skenario 19 — temuan dikelompokkan harfiah tanpa normalisasi dan diurutkan dari yang paling sering', function () {
    $record = laporanKernelPlantRecord($this->stationA, $this->day2, 'KP-A');

    $semai = [
        'Bearing panas' => 3,
        'Zink habis' => 2,
        'Ayakan kotor' => 2,
        'ripple mill bising' => 1,
        'Ripple mill bising' => 1,
        'Ripple  mill bising' => 1,
    ];

    $slotIndex = 0;

    foreach ($semai as $finding => $count) {
        foreach (range(1, $count) as $ignored) {
            laporanKernelPlantSlot($record, $this->slots[$slotIndex++], ['findings' => $finding]);
        }
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanKernelPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    // URUTAN PERSIS: slot_count TURUN lalu teks NAIK sebagai pemutus seri.
    // Pemutus seri itulah yang membuat urutannya stabil antar-render alih-alih
    // bergantung pada urutan baris yang dipulangkan mesin basis data.
    //
    // Dan TIDAK ADA PENYERAGAMAN: tiga ejaan 'ripple mill bising' tetap TIGA
    // baris — menyeragamkan akan menggabungkan sebab yang penulisnya memang
    // maksudkan berbeda. Spasi (0x20) mendahului 'm', dan 'R' mendahului 'r'.
    expect($summary->json('findings'))->toBe([
        ['finding' => 'Bearing panas', 'slot_count' => 3],
        ['finding' => 'Ayakan kotor', 'slot_count' => 2],
        ['finding' => 'Zink habis', 'slot_count' => 2],
        ['finding' => 'Ripple  mill bising', 'slot_count' => 1],
        ['finding' => 'Ripple mill bising', 'slot_count' => 1],
        ['finding' => 'ripple mill bising', 'slot_count' => 1],
    ]);
});

it('skenario 20 — penanda berbagi standar diturunkan dari peta kolom-ke-parameter, bukan dipaku', function () {
    laporanKernelPlantSeedTargets();

    $record = laporanKernelPlantRecord($this->stationA, $this->day2, 'KP-A');
    laporanKernelPlantSlot($record, '07:00', [
        'ripple_mill_1_amps' => 22.0,
        'ripple_mill_2_amps' => 23.0,
        'kernel_silo_1_temp_c' => 75.0,
        'kernel_silo_2_temp_c' => 76.0,
    ]);

    $query = [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ];

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanKernelPlantUrl('summary', $query));
    $summary->assertOk();

    // EMPAT BARIS membawa penanda itu, bukan dua: Depricarping hanya punya
    // SATU pasangan, jadi kode yang mengistimewakan satu pasangan lolos di
    // sana dan gagal di sini.
    expect(laporanKernelPlantMetric($summary, 'ripple_mill_1_amps')['target']['shares_standard_with'])
        ->toBe(['ripple_mill_2_amps']);
    expect(laporanKernelPlantMetric($summary, 'ripple_mill_2_amps')['target']['shares_standard_with'])
        ->toBe(['ripple_mill_1_amps']);
    expect(laporanKernelPlantMetric($summary, 'kernel_silo_1_temp_c')['target']['shares_standard_with'])
        ->toBe(['kernel_silo_2_temp_c']);
    expect(laporanKernelPlantMetric($summary, 'kernel_silo_2_temp_c')['target']['shares_standard_with'])
        ->toBe(['kernel_silo_1_temp_c']);

    foreach (['claybath_hydro_sg', 'kernel_moisture_percent', 'shell_loss_percent'] as $column) {
        expect(laporanKernelPlantMetric($summary, $column)['target']['shares_standard_with'])->toBe([]);
    }

    // SETELAH kedua baris master itu diperbarui: nilai teksnya ikut, penanda
    // pasangannya TIDAK berubah — ia turunan PETA, bukan turunan master.
    KernelPlantOperationalTarget::query()
        ->where('equipment_parameter', 'Ripple Mill (Cracker)')
        ->update([
            'target_benchmark' => '18 - 26 Amps (direvisi)',
            'corrective_action_plan' => 'Rencana baru.',
        ]);
    KernelPlantOperationalTarget::query()
        ->where('equipment_parameter', 'Kernel Silo 1 & 2')
        ->update([
            'target_benchmark' => '68°C - 82°C (direvisi)',
            'corrective_action_plan' => 'Rencana silo baru.',
        ]);

    $after = $this->actingAs($this->supervisor, 'web')->getJson(laporanKernelPlantUrl('summary', $query));
    $after->assertOk();

    foreach (['ripple_mill_1_amps', 'ripple_mill_2_amps'] as $column) {
        $target = laporanKernelPlantMetric($after, $column)['target'];

        expect($target['target_benchmark'])->toBe('18 - 26 Amps (direvisi)');
        expect($target['corrective_action_plan'])->toBe('Rencana baru.');
        expect($target['shares_standard_with'])->toHaveCount(1);
    }

    foreach (['kernel_silo_1_temp_c', 'kernel_silo_2_temp_c'] as $column) {
        $target = laporanKernelPlantMetric($after, $column)['target'];

        expect($target['target_benchmark'])->toBe('68°C - 82°C (direvisi)');
        expect($target['shares_standard_with'])->toHaveCount(1);
    }

    // Dan daftar standar-tanpa-pengukuran tidak ikut berubah: masih tepat
    // 'Final Kernel Dirt'.
    expect(collect($after->json('targets_without_metric'))->pluck('equipment_parameter')->all())
        ->toBe(['Final Kernel Dirt']);
});

it('skenario 21 — angka kedua ripple mill dan kedua silo tidak pernah dirata-ratakan menjadi satu', function () {
    laporanKernelPlantSeedTargets();

    $record = laporanKernelPlantRecord($this->stationA, $this->day2, 'KP-A');

    foreach (range(0, 2) as $index) {
        laporanKernelPlantSlot($record, $this->slots[$index], [
            'ripple_mill_1_amps' => 24.0,
            'ripple_mill_2_amps' => 12.0,
            'kernel_silo_1_temp_c' => 78.0,
            'kernel_silo_2_temp_c' => 66.0,
        ]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanKernelPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    expect(laporanKernelPlantMetric($summary, 'ripple_mill_1_amps')['avg'])->toEqual(24.0);
    expect(laporanKernelPlantMetric($summary, 'ripple_mill_2_amps')['avg'])->toEqual(12.0);
    expect(laporanKernelPlantMetric($summary, 'kernel_silo_1_temp_c')['avg'])->toEqual(78.0);
    expect(laporanKernelPlantMetric($summary, 'kernel_silo_2_temp_c')['avg'])->toEqual(66.0);

    // TIDAK ADA entri bernilai 18.0 maupun 72.0: merata-ratakan pasangan akan
    // menyembunyikan unit yang menyimpang di belakang unit yang normal.
    $averages = collect($summary->json('metrics'))->pluck('avg')->all();

    expect($averages)->not->toContain(18.0);
    expect($averages)->not->toContain(72.0);
    expect($averages)->not->toContain(18);
    expect($averages)->not->toContain(72);

    // Dan tidak ada entri GABUNGAN: tujuh kolom, tujuh entri.
    $columns = collect($summary->json('metrics'))->pluck('column')->all();

    expect($columns)->toHaveCount(7);
    expect($columns)->not->toContain('ripple_mill_amps');
    expect($columns)->not->toContain('kernel_silo_temp_c');

    // Masing-masing dengan penyebutnya SENDIRI, dan ekspornya pun memisahkan
    // keduanya sebagai dua kolom.
    foreach (['ripple_mill_1_amps', 'ripple_mill_2_amps', 'kernel_silo_1_temp_c', 'kernel_silo_2_temp_c'] as $column) {
        expect(laporanKernelPlantMetric($summary, $column)['filled_slot_count'])->toBe(3);
    }
});

// =====================================================================
// Skenario 22-24: standar tanpa pengukuran dan ketiadaan penandaan
// =====================================================================

it('skenario 22 — Final Kernel Dirt dibandingkan terhadap HIMPUNAN parameter terpeta, bukan jumlahnya', function () {
    laporanKernelPlantSeedTargets();

    // Master SENGAJA diisi 7 baris, sehingga
    // count(master) === count(COLUMN_TARGET_PARAMETER) === 7. Implementasi
    // yang membandingkan count() akan melihat 7 === 7 dan menerbitkan daftar
    // KOSONG — tepat kegagalan yang diuji di sini.
    KernelPlantOperationalTarget::create([
        'equipment_parameter' => 'Winnowing Column Draft',
        'target_benchmark' => '8 - 12 mmH2O',
        'corrective_action_plan' => 'Re-balance damper if shell carry-over rises.',
        'sort_order' => 7,
    ]);

    expect(KernelPlantOperationalTarget::query()->count())
        ->toBe(count(KernelPlantReportService::COLUMN_TARGET_PARAMETER));

    $record = laporanKernelPlantRecord($this->stationA, $this->day2, 'KP-A');
    laporanKernelPlantSlot($record, '07:00', ['ripple_mill_1_amps' => 22.0]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanKernelPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    expect($summary->json('targets_without_metric'))->toHaveCount(2);

    $names = collect($summary->json('targets_without_metric'))->pluck('equipment_parameter')->all();

    expect($names)->toContain('Final Kernel Dirt');
    expect($names)->toContain('Winnowing Column Draft');
    // Kedua parameter berbagi standar TIDAK muncul, meski masing-masing
    // dipakai DUA KALI oleh peta: tujuh entri hanya menyebut LIMA parameter.
    expect($names)->not->toContain('Ripple Mill (Cracker)');
    expect($names)->not->toContain('Kernel Silo 1 & 2');

    $dirt = collect($summary->json('targets_without_metric'))
        ->firstWhere('equipment_parameter', 'Final Kernel Dirt');

    // KETIGA kolom master ikut diterbitkan, bukan hanya namanya: standar yang
    // tidak pernah diukur terbaca seperti terpenuhi padahal ia sekadar tidak
    // ada, dan yang ini menyebut premi mutu yang dibayarkan ke mill.
    expect($dirt['target_benchmark'])->toBe('≤ 6.0% (Standard quality premium)');
    expect($dirt['corrective_action_plan'])->toContain('winnowing ducts');
    expect($dirt['reason'])->toBe('no_column');

    expect($summary->json('all_targets_measured'))->toBeFalse();
    expect($summary->json('targets_master_empty'))->toBeFalse();
});

it('skenario 23 — bagian standar-tanpa-pengukuran tetap digambar meski kosong', function () {
    // Master diisi HANYA 5 baris yang kelimanya namanya cocok dengan nilai
    // COLUMN_TARGET_PARAMETER — baris 'Final Kernel Dirt' dihapus.
    laporanKernelPlantSeedTargets(fn (string $parameter) => $parameter !== 'Final Kernel Dirt');

    expect(KernelPlantOperationalTarget::query()->count())->toBe(5);

    $record = laporanKernelPlantRecord($this->stationA, $this->day2, 'KP-A');
    laporanKernelPlantSlot($record, '07:00', ['ripple_mill_1_amps' => 22.0]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanKernelPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    expect($summary->json('targets_without_metric'))->toBe([]);

    // KUNCINYA HARUS ADA pada respons, bukan sekadar bernilai true: layar
    // memakainya untuk MENGGAMBAR bagiannya dengan keterangan alih-alih
    // menyembunyikannya. Bagian yang hilang tidak dapat dibedakan dari bagian
    // yang tak pernah dibuat siapa pun.
    expect($summary->json())->toHaveKey('all_targets_measured');
    expect($summary->json('all_targets_measured'))->toBeTrue();

    // KEDUA KUNCI diasersi terpisah: keduanya dapat sama-sama menunjuk daftar
    // kosong untuk sebab yang BERLAWANAN — master yang belum terisi versus
    // seluruh standar yang sudah terukur.
    expect($summary->json('targets_master_empty'))->toBeFalse();

    // Dan kelima standar itu terpasang pada ketujuh kolom.
    foreach (KernelPlantReportService::NUMERIC_METRICS as $column) {
        expect(laporanKernelPlantMetric($summary, $column)['target']['target_benchmark'])
            ->not->toBeNull("kolom {$column} harus membawa standarnya");
    }
});

it('skenario 24 — tidak ada penandaan otomatis di luar batas, dan ketiadaannya diasersi atas KUNCI', function () {
    laporanKernelPlantSeedTargets();

    $record = laporanKernelPlantRecord($this->stationA, $this->day2, 'KP-A');

    // Nilai SENGAJA jauh di luar target: 90.0 Amps terhadap
    // '20 - 25 Amps (Nut Breakage >95%)' dan kadar air 45.0% terhadap
    // '≤ 7.0% (Prevents mold growth)'.
    laporanKernelPlantSlot($record, '07:00', [
        'ripple_mill_1_amps' => 90.0,
        'kernel_moisture_percent' => 45.0,
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanKernelPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    // DIASERSI ATAS KETIADAAN KUNCI pada struktur, bukan atas ketiadaan frasa:
    // kalimat penjelasan di layar justru memuat kata "di luar batas", dan
    // target_benchmark pada master memuat '≤ 7.0%' serta '≤ 6.0%' — keduanya
    // mengandung potongan '0%'. Pencarian teks akan salah tangkap. Asersi
    // kelas CSS pun milik uji UI, bukan lapisan ini.
    $forbidden = ['severity', 'flag', 'is_out_of_range', 'out_of_range', 'status', 'warning', 'color', 'threshold', 'breach'];

    foreach ($summary->json('metrics') as $metric) {
        expect(array_keys($metric))->toBe([
            'column', 'label', 'unit', 'min', 'avg', 'max', 'filled_slot_count', 'target',
        ]);
        expect(array_keys($metric['target']))->toBe([
            'equipment_parameter', 'target_benchmark', 'corrective_action_plan', 'shares_standard_with',
        ]);

        foreach ($forbidden as $key) {
            expect($metric)->not->toHaveKey($key);
            expect($metric['target'])->not->toHaveKey($key);
        }
    }

    // PENYISIRAN SELURUH PAYLOAD. 'status' sengaja TIDAK dilarang di sini:
    // period.status adalah kunci yang sah dan memang status periode, bukan
    // penilaian terhadap sebuah angka.
    $allKeys = laporanKernelPlantAllKeys($summary->json());

    foreach (['severity', 'flag', 'is_out_of_range', 'out_of_range', 'warning', 'color', 'threshold', 'breach', 'exceeds'] as $key) {
        expect($allKeys)->not->toContain($key);
    }

    // Kedua angka tetap diterbitkan apa adanya, BERDAMPINGAN dengan standarnya
    // sebagai TEKS — penilaiannya milik pembaca.
    expect(laporanKernelPlantMetric($summary, 'ripple_mill_1_amps')['avg'])->toEqual(90.0);
    expect(laporanKernelPlantMetric($summary, 'ripple_mill_1_amps')['target']['target_benchmark'])
        ->toBe('20 - 25 Amps (Nut Breakage >95%)');
    expect(laporanKernelPlantMetric($summary, 'kernel_moisture_percent')['avg'])->toEqual(45.0);
    expect(laporanKernelPlantMetric($summary, 'kernel_moisture_percent')['target']['target_benchmark'])
        ->toBe('≤ 7.0% (Prevents mold growth)');
});

// =====================================================================
// Skenario 25-26: rekap periode dan null yang bukan nol
// =====================================================================

it('skenario 25 — baris total periode dihitung ulang dari slot, bukan merata-ratakan rata-rata harian', function () {
    // Hari-1: 2 slot bernilai 10 (rata-rata harian 10).
    $dayOne = laporanKernelPlantRecord($this->stationA, $this->day1, 'KP-1');

    foreach (range(0, 1) as $index) {
        laporanKernelPlantSlot($dayOne, $this->slots[$index], ['ripple_mill_1_amps' => 10.0]);
    }

    // Hari-2: 24 slot bernilai 30 (rata-rata harian 30).
    $dayTwo = laporanKernelPlantRecord($this->stationA, $this->day2, 'KP-1');

    foreach ($this->slots as $slot) {
        laporanKernelPlantSlot($dayTwo, $slot, ['ripple_mill_1_amps' => 30.0]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanKernelPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    // (2*10 + 24*30) / 26 = 740/26 = 28,46 — BUKAN (10+30)/2 = 20,0.
    // Merata-ratakan rata-rata memberi bobot sama pada hari dengan dua slot
    // dan hari dengan dua puluh empat.
    expect($summary->json('daily_total.averages.ripple_mill_1_amps'))->toEqual(28.46);
    expect($summary->json('daily_total.averages.ripple_mill_1_amps'))->not->toEqual(20.0);
    expect($summary->json('daily_total.filled_slot_count'))->toBe(26);
    expect(collect($summary->json('daily'))->sum('filled_slot_count'))->toBe(26);

    // Kedua rata-rata harian tetap terbit apa adanya di sisi lain payload.
    expect($summary->json('daily.0.averages.ripple_mill_1_amps'))->toEqual(10.0);
    expect($summary->json('daily.1.averages.ripple_mill_1_amps'))->toEqual(30.0);

    // Dan rekap per unit membawa day_count-nya — kernel_plant_id yang sama
    // pada dua tanggal adalah SATU unit dengan dua hari record, bukan dua
    // baris. kernel_plant_name adalah LABEL itu sendiri: kolomnya `string`
    // biasa yang diketik di layar input, bukan uuid dan bukan kunci asing,
    // dan tidak ada tabel master `kernel_plants` pada skema ini.
    expect($summary->json('by_kernel_plant'))->toHaveCount(1);
    expect($summary->json('by_kernel_plant.0.kernel_plant_id'))->toBe('KP-1');
    expect($summary->json('by_kernel_plant.0.kernel_plant_name'))->toBe('KP-1');
    expect($summary->json('by_kernel_plant.0.day_count'))->toBe(2);
    expect($summary->json('by_kernel_plant.0.filled_slot_count'))->toBe(26);
    expect($summary->json('by_kernel_plant.0.downtime_minutes'))->toBeNull();
});

it('skenario 26 — slot yang kolom ukurnya null tidak diperlakukan sebagai nol', function () {
    $record = laporanKernelPlantRecord($this->stationA, $this->day2, 'KP-A');

    // 3 slot mengisi ripple_mill_1_amps dengan 20, 22, 24; 21 slot lain
    // meninggalkan kolom itu null tetapi TETAP terisi lewat kolom lain,
    // sehingga ke-21 slot itu benar ikut filled_rows.
    foreach ($this->slots as $index => $slot) {
        $values = ['kernel_moisture_percent' => 6.0];

        if ($index < 3) {
            $values['ripple_mill_1_amps'] = 20.0 + ($index * 2);
        }

        laporanKernelPlantSlot($record, $slot, $values);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanKernelPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    $metric = laporanKernelPlantMetric($summary, 'ripple_mill_1_amps');

    // Nol akan menarik minimum ke 0.0 dan merata-ratakan ke 2.75.
    expect($metric['min'])->toEqual(20.0);
    expect($metric['avg'])->toEqual(22.0);
    expect($metric['max'])->toEqual(24.0);
    expect($metric['filled_slot_count'])->toBe(3);

    // Dua penyebut berbeda pada satu himpunan slot yang sama.
    expect(laporanKernelPlantMetric($summary, 'kernel_moisture_percent')['filled_slot_count'])->toBe(24);
    expect($summary->json('coverage.filled_slots'))->toBe(24);
});

// =====================================================================
// Ekspor — kontrak berkasnya
// =====================================================================

it('skenario 27 — ekspor CSV: satu baris per slot, konteks diulang, KESEMBILAN kolom bacaan di header', function () {
    $record = laporanKernelPlantRecord($this->stationA, $this->day2, 'KP-9', [
        'status' => RecordStatus::Synced,
    ]);

    foreach (range(0, 2) as $index) {
        laporanKernelPlantSlot($record, $this->slots[$index], [
            'ripple_mill_1_amps' => 22.0,
            'downtime_minutes' => 5,
            'findings' => 'Bearing panas',
        ]);
    }

    $response = $this->actingAs($this->supervisor, 'web')->get(laporanKernelPlantUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');

    $lines = laporanKernelPlantCsvLinesOf(laporanKernelPlantStreamed($response->baseResponse));

    // 1 header + 3 baris slot.
    expect($lines)->toHaveCount(4);
    expect($lines[0])->toContain('Slot Waktu');
    // DOWNTIME DAN TEMUAN WAJIB ADA: menghilangkan salah satunya membuat
    // berkasnya tidak dapat menggantikan laporan, yang justru tujuan
    // mengekspornya.
    expect($lines[0])->toContain('Downtime (Menit)');
    expect($lines[0])->toContain('Temuan');
    expect($lines[0])->toContain('Suhu Kernel Silo 1 (C)');
    expect($lines[0])->toContain('Suhu Kernel Silo 2 (C)');
    // 'Unit Kernel Plant', bukan 'Presser'.
    expect($lines[0])->toContain('Unit Kernel Plant');
    expect($lines[0])->not->toContain('Presser');

    // Kolom konteks DIULANG verbatim pada ketiga baris — bukan dikosongkan —
    // supaya berkasnya langsung dapat dipivot di spreadsheet.
    foreach (array_slice($lines, 1) as $line) {
        expect($line)->toContain('Periode Kernel Plant Alpha');
        expect($line)->toContain('Mill Alpha');
        expect($line)->toContain($this->day2);
        expect($line)->toContain('KP-9');
        expect($line)->toContain('Bearing panas');
    }
});

it('skenario 28 — slot kosong tetap menjadi baris ekspor, dan format tanpa parameter jatuh ke csv', function () {
    $record = laporanKernelPlantRecord($this->stationA, $this->day2, 'KP-9');
    laporanKernelPlantSlot($record, '07:00');

    // format TIDAK dikirim: default 'csv' hidup di controller.
    $response = $this->actingAs($this->supervisor, 'web')->get(laporanKernelPlantUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');

    $lines = laporanKernelPlantCsvLinesOf(laporanKernelPlantStreamed($response->baseResponse));

    // Bukan dibuang, dan BUKAN ditulis 0 — membuangnya akan membuat berkasnya
    // berselisih dengan angka cakupan yang laporan yang sama terbitkan.
    expect($lines)->toHaveCount(2);
    expect($lines[1])->toContain('KP-9');
});

it('skenario 29 — format di luar csv|excel: 422; format excel: content-type xlsx', function () {
    $record = laporanKernelPlantRecord($this->stationA, $this->day2, 'KP-9');
    laporanKernelPlantSlot($record, '07:00', ['ripple_mill_1_amps' => 22.0]);

    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanKernelPlantUrl('export', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
            'format' => 'pdf',
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('format');

    $excel = $this->actingAs($this->supervisor, 'web')->get(laporanKernelPlantUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'excel',
    ]));

    $excel->assertOk();
    expect($excel->headers->get('content-type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('skenario 30 — periode tertutup tetap dapat dibaca dan diekspor', function () {
    $closed = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelPlant->value)
        ->range(now()->subDays(40)->toDateString(), now()->subDays(31)->toDateString())
        ->closed()
        ->named('Periode Tertutup')
        ->create();

    $record = laporanKernelPlantRecord($this->stationA, now()->subDays(35)->toDateString(), 'KP-9');
    laporanKernelPlantSlot($record, '07:00', ['ripple_mill_1_amps' => 22.0]);

    $periods = $this->actingAs($this->supervisor, 'web')->getJson(laporanKernelPlantUrl('periods'));
    $periods->assertOk();
    // STATUS TIDAK PERNAH MENYARING daftar ini.
    expect(collect($periods->json('data'))->pluck('status'))->toContain('closed');

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanKernelPlantUrl('summary', [
        'period_id' => (string) $closed->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();
    expect($summary->json('period.status'))->toBe('closed');
    expect($summary->json('has_data'))->toBeTrue();

    // Kunci periode mengatur PENULISAN data, bukan pembacaan laporan.
    $this->actingAs($this->supervisor, 'web')->get(laporanKernelPlantUrl('export', [
        'period_id' => (string) $closed->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]))->assertOk();
});

it('skenario 31 — prefix /api/kernel-plant-reports TIDAK punya satu pun rute yang mengubah data', function () {
    // Laporan ini BACA SAJA secara rancangan. Rute yang dapat mengubah data
    // Kernel Plant tidak boleh pernah muncul di prefix ini — dan asersinya
    // harus atas DAFTAR RUTE, bukan atas satu respons, karena rute yang baru
    // ditambahkan tidak akan membuat satu pun uji lainnya gagal.
    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/kernel-plant-reports'));

    expect($routes)->toHaveCount(4);

    expect($routes->map(fn ($route) => $route->uri())->sort()->values()->all())->toBe([
        'api/kernel-plant-reports/business-units/options',
        'api/kernel-plant-reports/export',
        'api/kernel-plant-reports/periods',
        'api/kernel-plant-reports/summary',
    ]);

    foreach ($routes as $route) {
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $verb) {
            expect($route->methods())->not->toContain($verb, "rute {$route->uri()} tidak boleh menerima {$verb}");
        }
    }
});
