<?php

/**
 * LaporanEngineRoomTest (API) — screen-158--laporan-engine-room-web, keempat
 * endpoint /api/engine-room-reports/*.
 *
 * Satu uji per entri `test_scenarios[].api_test` pada tech spec screen-158
 * (35 skenario). Aturan agregasinya sendiri dibuktikan di
 * tests/Unit/Services/EngineRoomReportServiceTest.php terhadap service-nya;
 * yang dibuktikan berkas ini adalah KONTRAK HTTP-nya — kode status, siapa
 * yang diterima, apa yang benar-benar dibawa payload-nya, dan bagaimana
 * bentuk berkas ekspornya.
 *
 * ────────────────────────────────────────────────────────────────────────
 * TIGA JAWABAN YANG BERBEDA UNTUK OPERATOR, DAN KETIGANYA DISENGAJA
 * ────────────────────────────────────────────────────────────────────────
 *   - /periods, /summary, /export: 200 SEJAK COMMIT PERTAMA (presedens
 *     Grading / Kernel Plant — kembaran mobile screen-159 dibangun dalam
 *     seri yang sama, jadi pemanggilnya sudah ada).
 *   - /business-units/options: 403, diangkat DARI DALAM service justru
 *     karena middleware prefix ini menerima EMPAT peran demi ketiga rute
 *     data.
 *   - rute WEB /reports/engine-room: 403 — dibuktikan di
 *     tests/Feature/Livewire/LaporanEngineRoomTest.php, karena akses API
 *     untuk kembaran mobile tidak boleh melebarkan layar web.
 *
 * SKENARIO 23 ADALAH PENJAGA KEBOCORAN LINTAS MILL: business_unit_id kiriman
 * klien DIBUANG bagi peran terikat mill (200 berisi data mill akun, SENGAJA
 * bukan 403 — 403 justru akan menegaskan mill lain itu ada), sementara
 * production_line_id dan period_id milik mill lain memang 403.
 *
 * ────────────────────────────────────────────────────────────────────────
 * DUA PERTENTANGAN TECH SPEC YANG DISELESAIKAN DI SINI
 * ────────────────────────────────────────────────────────────────────────
 *   1. JUMLAH KUNCI METRIK: `metrics` memuat DUA PULUH DUA kunci, bukan dua
 *      puluh tiga. `daily_energy_export_kwh` diterbitkan pada kuncinya
 *      sendiri (`daily_energy_export`) karena ia tidak punya rata-rata
 *      periode sama sekali. Tiga pernyataan otoritatif pada spec yang sama
 *      menyebut 22; hanya judul satu unit test case menyebut 23.
 *   2. NILAI TERAKHIR HARI ITU diambil dari POSISI SLOT pada kisi kanonis
 *      (07:00..06:00 keesokan hari), bukan dari perbandingan angka jam.
 *      Lihat kepala case 44 pada berkas unit untuk diagnosis cacat
 *      implementasi yang masih terbuka pada aturan ini.
 *
 * FIXTURE-NYA SENGAJA TIDAK RATA: kolom diisi pada HIMPUNAN SLOT YANG
 * BERBEDA-BEDA, sehingga satu penyebut bersama tidak dapat lolos tanpa
 * terlihat.
 *
 * TANGGALNYA SELALU RELATIF (now()->subDays(...)): periode uji di bawah harus
 * SELALU sudah selesai agar days_counted-nya deterministik, dan tanggal yang
 * dipaku berhenti memenuhi syarat itu begitu kalender melewatinya.
 */

use App\Enums\RecordStatus;
use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\EngineRoomDetail;
use App\Models\EngineRoomRecord;
use App\Models\Period;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use App\Services\EngineRoomRecordService;
use App\Services\EngineRoomReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function laporanEngineRoomUrl(string $path, array $query = []): string
{
    return '/api/engine-room-reports/'.$path.($query === [] ? '' : '?'.http_build_query($query));
}

function laporanEngineRoomRecord(
    Station $station,
    string $date,
    string $engineRoomId = 'ER-1',
    array $attributes = [],
): EngineRoomRecord {
    return EngineRoomRecord::factory()
        ->forStation($station)
        ->onDate($date)
        ->create(array_merge(['engine_room_id' => $engineRoomId, 'note' => 'Catatan harian'], $attributes));
}

function laporanEngineRoomSlot(EngineRoomRecord $record, string $timeSlot, array $values = []): EngineRoomDetail
{
    return EngineRoomDetail::factory()
        ->forRecord($record)
        ->timeSlot($timeSlot)
        ->create($values);
}

/** Isi StreamedResponse, ditangkap. ALIRKAN SEKALI SAJA. */
function laporanEngineRoomStreamed($response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

function laporanEngineRoomCsvLinesOf(string $body): array
{
    return array_values(array_filter(explode("\n", trim($body))));
}

/**
 * Seluruh nama KUNCI di mana pun dalam struktur.
 *
 * DIPAKAI ALIH-ALIH MEMINDAI TEKS: `_note_absences` pada payload ini
 * MENYEBUTKAN ketiadaan downtime, ambang batas, dan selisih genset dengan
 * kata-kata yang persis sama dengan yang dicari.
 *
 * @return list<string>
 */
function laporanEngineRoomAllKeys(mixed $value): array
{
    if (! is_array($value)) {
        return [];
    }

    $keys = [];

    foreach ($value as $key => $child) {
        if (is_string($key)) {
            $keys[] = $key;
        }

        $keys = array_merge($keys, laporanEngineRoomAllKeys($child));
    }

    return array_values(array_unique($keys));
}

/** @return list<string> */
function laporanEngineRoomForbiddenKeys(): array
{
    return [
        'out_of_range', 'is_out_of_range', 'threshold', 'thresholds', 'target',
        'targets', 'violation', 'violations', 'severity', 'flag', 'flags',
        'warning', 'warnings', 'breach', 'exceeds', 'critical_limit', 'nominal',
        'safe_range', 'anomaly', 'anomalies',
        'downtime', 'downtime_minutes', 'downtime_events', 'total_minutes',
        'stop_events', 'stoppages',
        'delta', 'diff', 'difference', 'imbalance', 'deviation', 'ratio',
        'combined', 'combined_avg', 'genset_total', 'genset_combined',
        'load_share', 'load_ratio', 'shares_standard_with',
        'daily_energy_export_kwh', 'energy_export_avg', 'average_energy_export',
        'total_energy_export', 'energy_export_total',
    ];
}

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->engineRoom()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->engineRoom()->create();

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
        ->stationType(StationTypeEnum::EngineRoom->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Engine Room Alpha')
        ->create();

    $this->slots = EngineRoomRecordService::canonicalTimeSlots();
});

// =====================================================================
// Skenario 1-2: jalur sukses
// =====================================================================

it('skenario 1 — Supervisor dan Mill Management: periods + summary + export mill sendiri', function () {
    $record = laporanEngineRoomRecord($this->stationA, $this->day2, 'ER-1');

    // Putaran turbin pada 4 slot; tekanan masuk hanya pada 2 — penyebut yang
    // BERBEDA itulah yang membuat asersi di bawah bermakna.
    foreach (range(0, 3) as $index) {
        $values = ['steam_turbine_rpm' => 3000.0 + $index];

        if ($index < 2) {
            $values['steam_turbine_inlet_pressure_bar'] = 20.0 + $index;
        }

        laporanEngineRoomSlot($record, $this->slots[$index], $values);
    }

    foreach ([$this->supervisor, $this->millManagement] as $user) {
        $periods = $this->actingAs($user, 'web')->getJson(laporanEngineRoomUrl('periods'));
        $periods->assertOk();
        expect(collect($periods->json('data'))->pluck('id'))->toContain((string) $this->periodA->id);
        // BERTANDA HUBUNG: kunci snake_case akan ter-compile, tampak benar,
        // dan tidak pernah cocok dengan satu baris master pun.
        expect(collect($periods->json('data'))->pluck('station_type'))->toContain('engine-room');

        $summary = $this->actingAs($user, 'web')->getJson(laporanEngineRoomUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]));
        $summary->assertOk();

        expect($summary->json('business_unit.name'))->toBe('Mill Alpha');
        expect($summary->json('production_line.id'))->toBe($this->lineA);
        expect($summary->json('has_data'))->toBeTrue();
        expect($summary->json('coverage.filled_slots'))->toBe(4);
        expect($summary->json('coverage.slots_per_unit_per_day'))->toBe(24);

        // DUA PULUH DUA kunci metrik, dan kolom ke-23 pada kuncinya sendiri.
        expect($summary->json('metrics'))->toHaveCount(22);
        expect($summary->json('metrics.daily_energy_export_kwh'))->toBeNull();
        expect($summary->json())->toHaveKey('daily_energy_export');

        // DUA sebaran status, masing-masing dengan TIGA nilai dikenal.
        expect($summary->json('status_distribution'))->toHaveCount(2);
        expect($summary->json('status_distribution.diesel_gen_1_status.known_values'))
            ->toBe(['run', 'standby', 'off']);

        // PENYEBUT PER KOLOM, bukan satu penyebut bersama.
        expect($summary->json('metrics.steam_turbine_rpm.reading_count'))->toBe(4);
        expect($summary->json('metrics.steam_turbine_inlet_pressure_bar.reading_count'))->toBe(2);
        expect($summary->json('metrics.steam_turbine_rpm.group'))->toBe('steam_turbine');

        // DUA daftar teks bebas yang TERPISAH, selalu ada sebagai kunci.
        expect($summary->json())->toHaveKey('maintenance_remarks');
        expect($summary->json())->toHaveKey('findings');
    }

    $export = $this->actingAs($this->supervisor, 'web')->get(laporanEngineRoomUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));
    $export->assertOk();
    expect($export->headers->get('content-type'))->toContain('text/csv');
});

it('skenario 2 — Admin: pemilih mill lalu angka mill yang DIPILIH saja', function () {
    $recordA = laporanEngineRoomRecord($this->stationA, $this->day2, 'ER-A');
    laporanEngineRoomSlot($recordA, '07:00', ['steam_turbine_rpm' => 3000.0]);

    $recordB = laporanEngineRoomRecord($this->stationB, $this->day2, 'ER-B');
    laporanEngineRoomSlot($recordB, '07:00', ['steam_turbine_rpm' => 9999.0]);

    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::EngineRoom->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Engine Room Beta')
        ->create();

    $options = $this->actingAs($this->admin, 'web')->getJson(laporanEngineRoomUrl('business-units/options'));
    $options->assertOk();

    $ids = collect($options->json('data'))->pluck('id');

    expect($ids)->toContain((string) $this->businessUnitA->id);
    expect($ids)->toContain((string) $this->businessUnitB->id);

    // Periode mill B, dibaca dengan business_unit_id mill B.
    $periods = $this->actingAs($this->admin, 'web')->getJson(laporanEngineRoomUrl('periods', [
        'business_unit_id' => (string) $this->businessUnitB->id,
    ]));
    $periods->assertOk();
    expect(collect($periods->json('data'))->pluck('id')->all())->toBe([(string) $periodB->id]);

    $summary = $this->actingAs($this->admin, 'web')->getJson(laporanEngineRoomUrl('summary', [
        'business_unit_id' => (string) $this->businessUnitB->id,
        'period_id' => (string) $periodB->id,
        'production_line_id' => $this->lineB,
    ]));
    $summary->assertOk();

    // Cabang Admin TETAP menghormati business_unit_id kiriman.
    expect($summary->json('business_unit.name'))->toBe('Mill Beta');
    // JSON memulangkan float bulat sebagai int, jadi dibandingkan secara
    // longgar — yang load-bearing angkanya, bukan tipe transportnya.
    expect($summary->json('metrics.steam_turbine_rpm.avg'))->toEqual(9999.0);
    expect(collect($summary->json('by_unit'))->pluck('engine_room_id')->all())->toBe(['ER-B']);

    $export = $this->actingAs($this->admin, 'web')->get(laporanEngineRoomUrl('export', [
        'business_unit_id' => (string) $this->businessUnitB->id,
        'period_id' => (string) $periodB->id,
        'production_line_id' => $this->lineB,
        'format' => 'csv',
    ]));
    $export->assertOk();

    $csv = laporanEngineRoomStreamed($export->baseResponse);

    expect($csv)->toContain('Mill Beta');
    expect($csv)->toContain('ER-B');
    expect($csv)->not->toContain('Mill Alpha');
    expect($csv)->not->toContain('ER-A');
});

// =====================================================================
// Skenario 3-6: prasyarat pemilihan
// =====================================================================

it('skenario 3 — Admin tanpa business_unit_id: 422 pada periods dan pada summary', function () {
    $this->actingAs($this->admin, 'web')
        ->getJson(laporanEngineRoomUrl('periods'))
        ->assertStatus(422)
        ->assertJsonValidationErrors('business_unit_id');

    $this->actingAs($this->admin, 'web')
        ->getJson(laporanEngineRoomUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('business_unit_id');
});

it('skenario 4 — production_line_id TIDAK dikirim: 422, dan NOL kueri engine_room_records', function () {
    $record = laporanEngineRoomRecord($this->stationA, $this->day2, 'ER-1');
    laporanEngineRoomSlot($record, '07:00', ['steam_turbine_rpm' => 3000.0]);

    $queries = [];

    DB::listen(function ($query) use (&$queries) {
        $queries[] = strtolower($query->sql);
    });

    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanEngineRoomUrl('summary', ['period_id' => (string) $this->periodA->id]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('production_line_id');

    // TIDAK PERNAH di-default ke "seluruh line mill": menjawab 200 dengan
    // angka yang tidak diminta siapa pun adalah yang terburuk dari dua dunia.
    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "engine_room_records"');
    }

    // Dan sama untuk ekspor.
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanEngineRoomUrl('export', ['period_id' => (string) $this->periodA->id]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('production_line_id');

    // period_id yang hilang pun 422, pada fieldnya sendiri.
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanEngineRoomUrl('summary', ['production_line_id' => $this->lineA]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('period_id');
});

it('skenario 5 — Admin berganti mill: daftar periode kedua mill masing-masing 200 dan tidak bercampur', function () {
    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::EngineRoom->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Engine Room Beta')
        ->create();

    $forA = $this->actingAs($this->admin, 'web')->getJson(laporanEngineRoomUrl('periods', [
        'business_unit_id' => (string) $this->businessUnitA->id,
    ]));
    $forA->assertOk();
    expect(collect($forA->json('data'))->pluck('id')->all())->toBe([(string) $this->periodA->id]);

    $forB = $this->actingAs($this->admin, 'web')->getJson(laporanEngineRoomUrl('periods', [
        'business_unit_id' => (string) $this->businessUnitB->id,
    ]));
    $forB->assertOk();
    expect(collect($forB->json('data'))->pluck('id')->all())->toBe([(string) $periodB->id]);
});

it('skenario 6 — mill belum punya satu pun periode Engine Room: 200 dengan data []', function () {
    $supervisorB = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitB)->create();

    // Mill B punya periode untuk jenis stasiun LAIN — tanpa baris ini asersi
    // "hanya Engine Room" selalu hijau karena tidak ada yang dapat bocor.
    Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::BoilerRoom->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Boiler Room Beta')
        ->create();

    $response = $this->actingAs($supervisorB, 'web')->getJson(laporanEngineRoomUrl('periods'));

    // [] dengan HTTP 200, bukan 404: pemilih kosong plus arahan ke Kelola
    // Periode Pelaporan.
    $response->assertOk();
    expect($response->json('data'))->toBe([]);
});

// =====================================================================
// Skenario 7-13: keadaan data
// =====================================================================

it('skenario 7 — periode tanpa satu pun slot terisi: 200, has_data false, 22 metrik null', function () {
    $record = laporanEngineRoomRecord($this->stationA, $this->day2, 'ER-1');
    laporanEngineRoomSlot($record, '07:00');

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEngineRoomUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    expect($summary->json('has_data'))->toBeFalse();
    expect($summary->json('coverage.filled_slots'))->toBe(0);
    expect($summary->json('coverage.coverage_percent'))->toEqual(0.0);
    expect($summary->json('metrics'))->toHaveCount(22);

    // null/null/null dengan penyebut 0 — BUKAN 0/0/0, yang akan terbaca
    // sebagai "kami mengukurnya dan hasilnya nol".
    foreach ($summary->json('metrics') as $column => $metric) {
        expect($metric['min'])->toBeNull($column);
        expect($metric['avg'])->toBeNull($column);
        expect($metric['max'])->toBeNull($column);
        expect($metric['reading_count'])->toBe(0, $column);
    }

    // Seri harian dan tren TIDAK menggambar titik nol.
    expect($summary->json('daily_energy_export.series'))->toBe([]);
    expect($summary->json('daily_energy_export.has_data'))->toBeFalse();
    expect($summary->json('daily'))->toBe([]);
});

it('skenario 8 — satu metrik tidak pernah diisi sepanjang periode, 21 metrik lain tetap berangka', function () {
    $record = laporanEngineRoomRecord($this->stationA, $this->day2, 'ER-1');

    $terisi = array_values(array_diff(
        EngineRoomReportService::NUMERIC_METRICS,
        ['electrical_sync_power_factor'],
    ));

    foreach (range(0, 2) as $index) {
        laporanEngineRoomSlot($record, $this->slots[$index], array_fill_keys($terisi, 7.0 + $index));
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEngineRoomUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    expect($terisi)->toHaveCount(21);

    expect($summary->json('metrics.electrical_sync_power_factor.avg'))->toBeNull();
    expect($summary->json('metrics.electrical_sync_power_factor.reading_count'))->toBe(0);

    foreach ($terisi as $column) {
        expect($summary->json("metrics.{$column}.reading_count"))->toBe(3, $column);
        expect($summary->json("metrics.{$column}.avg"))->toEqual(8.0, $column);
    }

    // Kartu kosong TIDAK hilang dari blok Kelistrikan Tersinkron.
    expect($summary->json('metrics.electrical_sync_power_factor.group'))->toBe('electrical_sync');
});

it('skenario 9 — satu genset tidak pernah menyala: empat metrik null, sebaran tetap dirender', function () {
    $record = laporanEngineRoomRecord($this->stationA, $this->day2, 'ER-1');

    foreach (range(0, 4) as $index) {
        laporanEngineRoomSlot($record, $this->slots[$index], [
            'diesel_gen_1_load_kw' => 700.0,
            'diesel_gen_1_amperage_a' => 1100.0,
            'diesel_gen_1_jacket_water_temp_c' => 80.0,
            'diesel_gen_1_lube_oil_pressure_bar' => 4.2,
            'diesel_gen_1_status' => 'run',
            'diesel_gen_2_status' => 'off',
        ]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEngineRoomUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    foreach ([
        'diesel_gen_2_load_kw', 'diesel_gen_2_amperage_a',
        'diesel_gen_2_jacket_water_temp_c', 'diesel_gen_2_lube_oil_pressure_bar',
    ] as $column) {
        expect($summary->json("metrics.{$column}.avg"))->toBeNull($column);
        expect($summary->json("metrics.{$column}.reading_count"))->toBe(0, $column);
    }

    expect($summary->json('status_distribution.diesel_gen_2_status.counts'))
        ->toBe(['run' => 0, 'standby' => 0, 'off' => 5]);
    expect($summary->json('status_distribution.diesel_gen_1_status.counts.run'))->toBe(5);

    // Tidak ada kunci galat atau peringatan: genset mati sepanjang periode
    // adalah keadaan operasional yang SAH.
    $keys = laporanEngineRoomAllKeys($summary->json());

    foreach (['error', 'errors', 'warning', 'warnings', 'severity'] as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
    }
});

it('skenario 10 — kedua genset terisi dengan angka SANGAT berbeda dan tidak pernah digabung', function () {
    $record = laporanEngineRoomRecord($this->stationA, $this->day2, 'ER-1');

    foreach (range(0, 3) as $index) {
        laporanEngineRoomSlot($record, $this->slots[$index], [
            'diesel_gen_1_load_kw' => 800.0,
            'diesel_gen_2_load_kw' => 40.0,
        ]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEngineRoomUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    expect($summary->json('metrics.diesel_gen_1_load_kw.avg'))->toEqual(800.0);
    expect($summary->json('metrics.diesel_gen_2_load_kw.avg'))->toEqual(40.0);

    // DIASERSI ATAS NAMA KUNCI, bukan atas isi berkas JSON: `_note_absences`
    // justru MENYEBUT "gabungan" dan "ketidakseimbangan" karena ia
    // menyatakan ketiadaannya.
    $keys = laporanEngineRoomAllKeys($summary->json());

    foreach (laporanEngineRoomForbiddenKeys() as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
    }

    // Rata-rata gabungan 420 tidak muncul sebagai nilai metrik mana pun.
    expect(collect($summary->json('metrics'))->pluck('avg')->all())->not->toContain(420.0);
});

it('skenario 11 — satu slot hanya mengisi kolom teks bebas: filled_slots melebihi setiap penyebut', function () {
    $record = laporanEngineRoomRecord($this->stationA, $this->day2, 'ER-1');

    foreach (range(0, 4) as $index) {
        laporanEngineRoomSlot($record, $this->slots[$index], [
            'action_taken_maintenance_remark' => 'Cek level oli',
        ]);
    }

    foreach (range(5, 6) as $index) {
        laporanEngineRoomSlot($record, $this->slots[$index], ['steam_turbine_rpm' => 3000.0]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEngineRoomUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    expect($summary->json('coverage.filled_slots'))->toBe(7);

    $terbesar = collect($summary->json('metrics'))->max('reading_count');

    expect($terbesar)->toBe(2);
    // YANG DIASERSI ADALAH KETIDAKSAMAANNYA — sebuah baris terisi diputuskan
    // atas DUA PULUH TUJUH kolom, bukan dua puluh dua.
    expect($summary->json('coverage.filled_slots'))->toBeGreaterThan($terbesar);
    expect(EngineRoomRecordService::READING_FIELDS)->toHaveCount(27);
    expect($summary->json('maintenance_remarks'))->toHaveCount(5);
});

it('skenario 12 — seluruh slot tidak mencatat status genset: ketiga nilai nol, semuanya not_recorded', function () {
    $record = laporanEngineRoomRecord($this->stationA, $this->day2, 'ER-1');

    foreach (range(0, 17) as $index) {
        laporanEngineRoomSlot($record, $this->slots[$index], ['steam_turbine_rpm' => 3000.0]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEngineRoomUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    expect($summary->json('status_distribution.diesel_gen_1_status.counts'))
        ->toBe(['run' => 0, 'standby' => 0, 'off' => 0]);
    // not_recorded pada kunci TERPISAH, dan tidak satu pun slot dilipat ke
    // run/standby/off.
    expect($summary->json('status_distribution.diesel_gen_1_status.not_recorded'))->toBe(18);
    expect($summary->json('status_distribution.diesel_gen_1_status.recorded_slots'))->toBe(18);
    // Sebarannya TETAP diterbitkan, bukan dihilangkan.
    expect($summary->json('status_distribution'))->toHaveCount(2);
});

it('skenario 13 — nilai status dikenal yang tidak pernah muncul tetap hadir dengan jumlah 0', function () {
    $record = laporanEngineRoomRecord($this->stationA, $this->day2, 'ER-1');

    foreach (range(0, 9) as $index) {
        laporanEngineRoomSlot($record, $this->slots[$index], [
            'diesel_gen_1_status' => $index < 7 ? 'run' : 'off',
        ]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEngineRoomUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    $dist = $summary->json('status_distribution.diesel_gen_1_status');

    // 'standby' TETAP ada dengan 0 — "tidak pernah siaga" harus terbaca
    // BERBEDA dari "tidak dilaporkan", yang muncul pada not_recorded.
    expect($dist['counts'])->toBe(['run' => 7, 'standby' => 0, 'off' => 3]);
    expect($dist['counts'])->toHaveKey('standby');
    expect($dist['not_recorded'])->toBe(0);
    expect($dist['known_values'])->toBe(['run', 'standby', 'off']);
});

// =====================================================================
// Skenario 14-17: ekspor energi harian dan kedua kolom teks bebas
// =====================================================================

it('skenario 14 — ekspor energi harian tidak pernah diisi: seri kosong, bukan titik nol', function () {
    $record = laporanEngineRoomRecord($this->stationA, $this->day2, 'ER-1');

    foreach (range(0, 2) as $index) {
        laporanEngineRoomSlot($record, $this->slots[$index], [
            'steam_turbine_rpm' => 3000.0,
            'daily_energy_export_kwh' => null,
        ]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEngineRoomUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    // Seri KOSONG dengan penanda tidak ada data, dan TIDAK ADA satu pun
    // titik bernilai 0 — titik nol mengklaim angka meter yang tidak pernah
    // dibaca siapa pun.
    expect($summary->json('daily_energy_export.series'))->toBe([]);
    expect($summary->json('daily_energy_export.has_data'))->toBeFalse();

    // Tanggalnya tetap punya baris rekap harian, dengan sel energi null.
    expect($summary->json('daily'))->toHaveCount(1);
    expect($summary->json('daily.0.energy_export.last_value'))->toBeNull();
    expect($summary->json('daily.0.energy_export.reading_count'))->toBe(0);
    expect($summary->json('by_unit.0.energy_export.last_value'))->toBeNull();
});

it('skenario 15 — ekspor energi harian TURUN di dalam satu hari: diterbitkan apa adanya', function () {
    // MERAH PADA IMPLEMENTASI HARI INI karena cacat `sortBy([closure,
    // closure])` di EngineRoomReportService::lastEnergyValueOf() —
    // diagnosis lengkap dan perbaikan satu barisnya ada di kepala case 44
    // pada tests/Unit/Services/EngineRoomReportServiceTest.php.
    //
    // 07:00 = 900 lalu 12:00 = 300 sebagai slot TERAKHIR pada kisi kanonis.
    // Keadaan ini mustahil bila kolomnya angka meter kumulatif, jadi ia
    // justru bukti yang berguna untuk menjawab pertanyaan terbuka — bukan
    // galat.
    $record = laporanEngineRoomRecord($this->stationA, $this->day2, 'ER-1');

    laporanEngineRoomSlot($record, '07:00', ['daily_energy_export_kwh' => 900.5]);
    laporanEngineRoomSlot($record, '12:00', ['daily_energy_export_kwh' => 300.5]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEngineRoomUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    expect($summary->json('daily_energy_export.series.0.last_value'))->toEqual(300.5);
    expect($summary->json('daily_energy_export.series.0.time_slot'))->toBe('12:00');

    // Tanpa koreksi, dan tanpa satu pun kunci anomaly/flag/warning.
    $keys = laporanEngineRoomAllKeys($summary->json());

    foreach (['anomaly', 'anomalies', 'flag', 'flags', 'warning', 'warnings', 'corrected'] as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
    }
});

it('skenario 16 — satu slot mengisi catatan perawatan DAN temuan: muncul di KEDUA daftar', function () {
    $record = laporanEngineRoomRecord($this->stationA, $this->day2, 'ER-1');

    laporanEngineRoomSlot($record, '07:00', [
        'action_taken_maintenance_remark' => 'Kencangkan baut pondasi',
        'findings' => 'Baut pondasi longgar',
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEngineRoomUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    expect($summary->json('maintenance_remarks'))->toHaveCount(1);
    expect($summary->json('findings'))->toHaveCount(1);
    expect($summary->json('maintenance_remarks.0'))->toBe([
        'date' => $this->day2,
        'time_slot' => '07:00',
        'text' => 'Kencangkan baut pondasi',
    ]);
    expect($summary->json('findings.0.text'))->toBe('Baut pondasi longgar');

    // Tidak ada daftar gabungan, dan filled_slots tetap bertambah SATU.
    expect($summary->json())->not->toHaveKey('notes');
    expect($summary->json('coverage.filled_slots'))->toBe(1);
});

it('skenario 17 — kedua kolom teks bebas kosong: dua array kosong yang TERPISAH', function () {
    $record = laporanEngineRoomRecord($this->stationA, $this->day2, 'ER-1');
    laporanEngineRoomSlot($record, '07:00', ['steam_turbine_rpm' => 3000.0]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEngineRoomUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    // DUA kunci terpisah yang tetap diterbitkan — bukan satu kunci null dan
    // bukan daftar yang dihilangkan.
    expect($summary->json())->toHaveKey('maintenance_remarks');
    expect($summary->json())->toHaveKey('findings');
    expect($summary->json('maintenance_remarks'))->toBe([]);
    expect($summary->json('findings'))->toBe([]);
});

// =====================================================================
// Skenario 18-21: rekap per unit dan kelengkapan
// =====================================================================

it('skenario 18 — line punya beberapa unit Engine Room, salah satunya tanpa pembacaan', function () {
    $terisi = laporanEngineRoomRecord($this->stationA, $this->day2, 'ER-TERISI');
    laporanEngineRoomSlot($terisi, '07:00', ['steam_turbine_rpm' => 3000.0]);
    laporanEngineRoomSlot($terisi, '08:00', ['steam_turbine_rpm' => 3100.0]);

    $kosong = laporanEngineRoomRecord($this->stationA, $this->day2, 'ER-KOSONG');
    laporanEngineRoomSlot($kosong, '07:00');

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEngineRoomUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    // Angka periode MENGGABUNGKAN kedua unit ...
    expect($summary->json('metrics.steam_turbine_rpm.reading_count'))->toBe(2);
    expect($summary->json('coverage.engine_room_unit_count'))->toBe(2);
    expect($summary->json('coverage.expected_slots'))->toBe(2 * 10 * 24);

    // ... dan rekap per unit menunjukkan sebarannya, dua baris.
    expect($summary->json('by_unit'))->toHaveCount(2);

    $rows = collect($summary->json('by_unit'))->keyBy('engine_room_id');

    expect($rows)->toHaveKey('ER-TERISI');
    expect($rows)->toHaveKey('ER-KOSONG');
    expect($rows['ER-TERISI']['reading_rows'])->toBe(2);
    expect($rows['ER-TERISI']['days_with_rows'])->toBe(1);
    // Unit tanpa pembacaan TIDAK dihilangkan dari tabel: membuangnya akan
    // menyembunyikan justru unit yang tidak pernah ditulis.
    expect($rows['ER-KOSONG']['reading_rows'])->toBe(0);
    expect($rows['ER-KOSONG']['days_with_rows'])->toBe(0);
    expect($rows['ER-KOSONG']['metrics']['steam_turbine_rpm']['avg'])->toBeNull();
    expect($rows['ER-KOSONG']['metrics'])->toHaveCount(22);

    // Jumlah slot per unit menjumlah ke slot terisi periode.
    expect(collect($summary->json('by_unit'))->sum('reading_rows'))
        ->toBe($summary->json('coverage.filled_slots'));
});

it('skenario 19 — pencatatan sangat tidak lengkap: angka tetap terbit, cakupannya kecil dan nyata', function () {
    // 12 slot dari 720 yang diharapkan (3 unit × 10 hari × 24).
    foreach (['ER-1', 'ER-2', 'ER-3'] as $index => $unit) {
        $record = laporanEngineRoomRecord($this->stationA, $this->day2, $unit);

        foreach (range(0, 3) as $slotIndex) {
            laporanEngineRoomSlot($record, $this->slots[$slotIndex], [
                'steam_turbine_rpm' => 3000.0 + $index,
            ]);
        }
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEngineRoomUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    expect($summary->json('coverage.expected_slots'))->toBe(720);
    expect($summary->json('coverage.filled_slots'))->toBe(12);
    expect($summary->json('coverage.coverage_percent'))->toEqual(1.7);

    // Angka metriknya TETAP diterbitkan beserta penyebut kecilnya, bukan
    // disembunyikan: pembaca harus melihat bahwa angkanya bersandar pada
    // sedikit pembacaan.
    expect($summary->json('has_data'))->toBeTrue();
    expect($summary->json('metrics.steam_turbine_rpm.reading_count'))->toBe(12);
    expect($summary->json('metrics.steam_turbine_rpm.avg'))->toEqual(3001.0);
});

it('skenario 20 — periode masih berjalan: penyebutnya berhenti di HARI INI', function () {
    $running = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::EngineRoom->value)
        ->range(now()->subDays(9)->toDateString(), now()->addDays(20)->toDateString())
        ->open()
        ->named('Periode Berjalan')
        ->create();

    $record = laporanEngineRoomRecord($this->stationA, now()->subDays(5)->toDateString(), 'ER-1');
    laporanEngineRoomSlot($record, '07:00', ['steam_turbine_rpm' => 3000.0]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEngineRoomUrl('summary', [
        'period_id' => (string) $running->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    expect($summary->json('coverage.period_running'))->toBeTrue();
    // 10 hari, bukan 30: hari yang belum terjadi tidak mungkin tercatat,
    // jadi tidak ikut menjadi penyebut dan tidak menurunkan coverage_percent.
    expect($summary->json('coverage.days_counted'))->toBe(10);
    expect($summary->json('coverage.days_in_period'))->toBe(30);
    expect($summary->json('coverage.expected_slots'))->toBe(240);
});

it('skenario 21 — tidak ada slot yang diharapkan: coverage_percent 0.0 tanpa pembagian nol', function () {
    $future = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::EngineRoom->value)
        ->range(now()->addDays(5)->toDateString(), now()->addDays(14)->toDateString())
        ->open()
        ->named('Periode Belum Mulai')
        ->create();

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEngineRoomUrl('summary', [
        'period_id' => (string) $future->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    expect($summary->json('coverage.days_counted'))->toBe(0);
    expect($summary->json('coverage.expected_slots'))->toBe(0);
    // 0.0, BUKAN null — layar membaca expected_slots === 0 untuk memutuskan
    // merender '—', bukan membaca persennya.
    expect($summary->json('coverage.coverage_percent'))->toEqual(0.0);
    expect($summary->json('coverage.coverage_percent'))->not->toBeNull();
    expect($summary->json('has_data'))->toBeFalse();
});

// =====================================================================
// Skenario 22-25: akses dan cakupan mill
// =====================================================================

it('skenario 22 — akun terikat mill tanpa business_unit_id: 422 VALIDATION_ERROR pada periods dan summary', function () {
    $supervisorWithoutMill = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $this->actingAs($supervisorWithoutMill, 'web')
        ->getJson(laporanEngineRoomUrl('periods'))
        ->assertStatus(422)
        ->assertJsonValidationErrors('business_unit_id');

    $this->actingAs($supervisorWithoutMill, 'web')
        ->getJson(laporanEngineRoomUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('business_unit_id');

    // GAGAL TERTUTUP, bukan 403 dan bukan daftar seluruh mill: pesannya
    // menyebut akun yang belum terhubung ke mill.
    $response = $this->actingAs($supervisorWithoutMill, 'web')->getJson(laporanEngineRoomUrl('periods'));

    expect($response->json('errors.business_unit_id.0'))->toContain('belum terhubung ke mill');
});

it('skenario 23 — Supervisor mencoba melihat mill lain: 200 berisi data SENDIRI, periode mill lain 403', function () {
    $recordA = laporanEngineRoomRecord($this->stationA, $this->day2, 'ER-A');
    laporanEngineRoomSlot($recordA, '07:00', ['steam_turbine_rpm' => 3000.5]);

    $recordB = laporanEngineRoomRecord($this->stationB, $this->day2, 'ER-B');
    laporanEngineRoomSlot($recordB, '07:00', ['steam_turbine_rpm' => 9999.5]);

    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::EngineRoom->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Engine Room Beta')
        ->create();

    // business_unit_id mill lain DIBUANG: 200 berisi data mill akun. SENGAJA
    // bukan 403 — 403 justru akan menegaskan bahwa mill lain itu ada, dan
    // tidak ada percobaan akses untuk ditolak karena parameternya memang
    // tidak pernah dipakai bagi peran ini.
    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEngineRoomUrl('summary', [
        'business_unit_id' => (string) $this->businessUnitB->id,
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    expect($summary->json('business_unit.id'))->toBe((string) $this->businessUnitA->id);
    expect($summary->json('business_unit.name'))->toBe('Mill Alpha');
    expect($summary->json('metrics.steam_turbine_rpm.avg'))->toEqual(3000.5);
    expect(collect($summary->json('by_unit'))->pluck('engine_room_id')->all())->toBe(['ER-A']);

    // Sementara PERIODE mill lain memang 403 — penjagaan lintas mill yang
    // sungguhan ada di sana.
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanEngineRoomUrl('summary', [
            'period_id' => (string) $periodB->id,
            'production_line_id' => $this->lineA,
        ]))
        ->assertStatus(403);
});

it('skenario 24 — production_line_id milik mill lain: 403 pada summary DAN pada export', function () {
    $recordB = laporanEngineRoomRecord($this->stationB, $this->day2, 'ER-B');
    laporanEngineRoomSlot($recordB, '07:00', ['steam_turbine_rpm' => 9999.0]);

    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanEngineRoomUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineB,
        ]))
        ->assertStatus(403);

    // Dan ekspornya 403 SEBELUM satu byte pun dialirkan: nama line mill lain
    // pun tidak pernah terbit.
    $export = $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanEngineRoomUrl('export', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineB,
            'format' => 'csv',
        ]));

    $export->assertStatus(403);
    expect($export->headers->get('content-type'))->not->toContain('text/csv');
});

it('skenario 25 — OPERATOR 200 PADA KETIGA RUTE DATA, 403 HANYA PADA PEMILIH MILL', function () {
    $record = laporanEngineRoomRecord($this->stationA, $this->day2, 'ER-1');
    laporanEngineRoomSlot($record, '07:00', ['steam_turbine_rpm' => 3000.5]);

    // 1. /periods — 200, dan hanya periode mill AKUNNYA.
    $periods = $this->actingAs($this->operator, 'web')->getJson(laporanEngineRoomUrl('periods'));
    $periods->assertOk();
    expect(collect($periods->json('data'))->pluck('id')->all())->toBe([(string) $this->periodA->id]);

    // 2. /summary — 200, dengan angka mill akunnya.
    $summary = $this->actingAs($this->operator, 'web')->getJson(laporanEngineRoomUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();
    expect($summary->json('business_unit.name'))->toBe('Mill Alpha');
    expect($summary->json('metrics.steam_turbine_rpm.avg'))->toEqual(3000.5);

    // 3. /export — 200, dengan berkas yang berisi baris mill akunnya.
    $export = $this->actingAs($this->operator, 'web')->get(laporanEngineRoomUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));
    $export->assertOk();
    expect(laporanEngineRoomStreamed($export->baseResponse))->toContain('ER-1');

    // 4. /business-units/options — 403, DARI DALAM service: Operator terikat
    //    SATU mill, jadi meminta daftar seluruh mill bukan "daftar berisi
    //    satu" melainkan 403, dan layar mobile screen-159 memang tidak punya
    //    satu pun jalur kode yang memanggilnya.
    $this->actingAs($this->operator, 'web')
        ->getJson(laporanEngineRoomUrl('business-units/options'))
        ->assertStatus(403);
});

it('skenario 25b — KRITIS: business_unit_id mill lain dari Operator DIBUANG, 200 berisi data mill akun', function () {
    $recordA = laporanEngineRoomRecord($this->stationA, $this->day2, 'ER-A');
    laporanEngineRoomSlot($recordA, '07:00', ['steam_turbine_rpm' => 3000.5]);

    $recordB = laporanEngineRoomRecord($this->stationB, $this->day2, 'ER-B');
    laporanEngineRoomSlot($recordB, '07:00', ['steam_turbine_rpm' => 9999.5]);

    // TEMPAT KETIGA dari tiga tempat pelebaran akses Operator. Tanpa baris
    // Operator di cabang terikat-mill resolveBusinessUnit(), ia jatuh ke
    // cabang ADMIN tempat business_unit_id kiriman klien DIHORMATI — dan
    // jawabannya menjadi 200 berisi data MILL LAIN.
    $summary = $this->actingAs($this->operator, 'web')->getJson(laporanEngineRoomUrl('summary', [
        'business_unit_id' => (string) $this->businessUnitB->id,
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    expect($summary->json('business_unit.id'))->toBe((string) $this->businessUnitA->id);
    expect($summary->json('metrics.steam_turbine_rpm.max'))->toEqual(3000.5);
    expect(collect($summary->json('by_unit'))->pluck('engine_room_id')->all())->toBe(['ER-A']);

    // Dan line mill lain TETAP 403.
    $this->actingAs($this->operator, 'web')
        ->getJson(laporanEngineRoomUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineB,
        ]))
        ->assertStatus(403);
});

it('skenario 25c — tamu: 401 pada keempat rute', function () {
    foreach ([
        laporanEngineRoomUrl('business-units/options'),
        laporanEngineRoomUrl('periods'),
        laporanEngineRoomUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]),
        laporanEngineRoomUrl('export', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]),
    ] as $url) {
        $this->getJson($url)->assertStatus(401);
    }
});

// =====================================================================
// Skenario 26-30: periode tertutup, rekap panjang, baca-saja, daftar
//                 periode, rentang inklusif
// =====================================================================

it('skenario 26 — periode tertutup tetap dapat dibaca dan diekspor penuh', function () {
    $closed = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::EngineRoom->value)
        ->range(now()->subDays(40)->toDateString(), now()->subDays(31)->toDateString())
        ->closed()
        ->named('Periode Tertutup')
        ->create();

    $record = laporanEngineRoomRecord($this->stationA, now()->subDays(35)->toDateString(), 'ER-9');
    laporanEngineRoomSlot($record, '07:00', ['steam_turbine_rpm' => 3000.0]);

    // STATUS TIDAK PERNAH MENYARING daftar periode: kunci periode mengatur
    // PENULISAN data, bukan pembacaan laporan.
    $periods = $this->actingAs($this->supervisor, 'web')->getJson(laporanEngineRoomUrl('periods'));
    $periods->assertOk();
    expect(collect($periods->json('data'))->pluck('id'))->toContain((string) $closed->id);
    expect(collect($periods->json('data'))->firstWhere('id', (string) $closed->id)['status'])->toBe('closed');

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEngineRoomUrl('summary', [
        'period_id' => (string) $closed->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    expect($summary->json('period.status'))->toBe('closed');
    expect($summary->json('has_data'))->toBeTrue();
    expect($summary->json('metrics'))->toHaveCount(22);

    $export = $this->actingAs($this->supervisor, 'web')->get(laporanEngineRoomUrl('export', [
        'period_id' => (string) $closed->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));
    $export->assertOk();
    expect(laporanEngineRoomStreamed($export->baseResponse))->toContain('ER-9');
});

it('skenario 27 — rekap harian panjang: satu baris per tanggal berdata, 31 hari', function () {
    $long = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::EngineRoom->value)
        ->range(now()->subDays(45)->toDateString(), now()->subDays(15)->toDateString())
        ->open()
        ->named('Periode Panjang')
        ->create();

    expect((int) $long->start_date->diffInDays($long->end_date) + 1)->toBe(31);

    // DUA BELAS tanggal berdata di dalam periode 31 hari: tanggal tanpa
    // record tidak mendapat baris sama sekali — baris nol akan terbaca
    // sebagai "terukur nol".
    foreach (range(0, 11) as $offset) {
        $record = laporanEngineRoomRecord(
            $this->stationA,
            now()->subDays(44 - $offset)->toDateString(),
            'ER-1',
        );
        laporanEngineRoomSlot($record, '07:00', [
            'electrical_sync_total_factory_load_kw' => 900.0 + $offset,
        ]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEngineRoomUrl('summary', [
        'period_id' => (string) $long->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    expect($summary->json('coverage.days_in_period'))->toBe(31);
    expect($summary->json('coverage.days_counted'))->toBe(31);
    expect($summary->json('daily'))->toHaveCount(12);
    expect($summary->json('total.days_with_records'))->toBe(12);
    expect($summary->json('daily_trend.electrical_sync_total_factory_load_kw'))->toHaveCount(12);

    // Tanggalnya urut menaik, dan setiap barisnya membawa penyebutnya
    // sendiri.
    $dates = collect($summary->json('daily'))->pluck('date')->all();

    expect($dates)->toBe(collect($dates)->sort()->values()->all());
    expect(collect($summary->json('daily'))->sum('reading_rows'))
        ->toBe($summary->json('coverage.filled_slots'));
});

it('skenario 28 — prefix /api/engine-room-reports TIDAK punya satu pun rute yang mengubah data', function () {
    // Laporan ini BACA SAJA secara rancangan. Asersinya harus atas DAFTAR
    // RUTE, bukan atas satu respons, karena rute yang baru ditambahkan tidak
    // akan membuat satu pun uji lainnya gagal.
    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/engine-room-reports'));

    expect($routes)->toHaveCount(4);

    expect($routes->map(fn ($route) => $route->uri())->sort()->values()->all())->toBe([
        'api/engine-room-reports/business-units/options',
        'api/engine-room-reports/export',
        'api/engine-room-reports/periods',
        'api/engine-room-reports/summary',
    ]);

    foreach ($routes as $route) {
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $verb) {
            $this->assertNotContains($verb, $route->methods(), "rute {$route->uri()} tidak boleh menerima {$verb}");
        }
    }

    // Dan membaca laporannya tidak menggerakkan satu baris pun.
    $record = laporanEngineRoomRecord($this->stationA, $this->day2, 'ER-1');
    laporanEngineRoomSlot($record, '07:00', ['steam_turbine_rpm' => 3000.0]);

    $recordsBefore = EngineRoomRecord::query()->count();
    $detailsBefore = EngineRoomDetail::query()->count();

    $this->actingAs($this->supervisor, 'web')->getJson(laporanEngineRoomUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]))->assertOk();

    expect(EngineRoomRecord::query()->count())->toBe($recordsBefore);
    expect(EngineRoomDetail::query()->count())->toBe($detailsBefore);
});

it('skenario 29 — daftar periode hanya memuat periode yang mencakup Engine Room', function () {
    $keduaEngineRoom = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::EngineRoom->value)
        ->range(now()->subDays(40)->toDateString(), now()->subDays(31)->toDateString())
        ->open()
        ->named('Periode Engine Room Kedua')
        ->create();

    $lain = [];

    foreach ([
        StationTypeEnum::BoilerRoom->value,
        StationTypeEnum::EffluentPlant->value,
        StationTypeEnum::StorageTank->value,
    ] as $index => $type) {
        $lain[] = Period::factory()
            ->forBusinessUnit($this->businessUnitA)
            ->stationType($type)
            ->range(
                now()->subDays(70 - $index * 11)->toDateString(),
                now()->subDays(63 - $index * 11)->toDateString(),
            )
            ->open()
            ->named('Periode Stasiun Lain '.$index)
            ->create();
    }

    $periods = $this->actingAs($this->supervisor, 'web')->getJson(laporanEngineRoomUrl('periods'));
    $periods->assertOk();

    expect($periods->json('data'))->toHaveCount(2);

    $ids = collect($periods->json('data'))->pluck('id');

    expect($ids)->toContain((string) $this->periodA->id);
    expect($ids)->toContain((string) $keduaEngineRoom->id);

    foreach ($lain as $period) {
        expect($ids)->not->toContain((string) $period->id);
    }

    // Setiap entri membawa station_type 'engine-room' BERTANDA HUBUNG
    // beserta station_type_label dari tabel master.
    foreach ($periods->json('data') as $period) {
        expect($period['station_type'])->toBe('engine-room');
        expect($period['station_type_label'])->not->toBe('');
        expect(array_keys($period))->toBe([
            'id', 'name', 'start_date', 'end_date', 'status', 'station_type', 'station_type_label',
        ]);
    }
});

it('skenario 30 — rentang periode inklusif di kedua ujung, dan kedua ujung ada di berkas ekspor', function () {
    foreach ([$this->periodStart, $this->periodEnd] as $index => $date) {
        laporanEngineRoomSlot(
            laporanEngineRoomRecord($this->stationA, $date, 'ER-UJUNG-'.$index),
            '07:00',
            ['steam_turbine_rpm' => 3000.0, 'diesel_gen_1_status' => 'run'],
        );
    }

    foreach ([now()->subDays(21)->toDateString(), now()->subDays(10)->toDateString()] as $index => $date) {
        laporanEngineRoomSlot(
            laporanEngineRoomRecord($this->stationA, $date, 'ER-LUAR-'.$index),
            '07:00',
            ['steam_turbine_rpm' => 9999.0],
        );
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEngineRoomUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    expect($summary->json('coverage.filled_slots'))->toBe(2);
    expect($summary->json('metrics.steam_turbine_rpm.reading_count'))->toBe(2);
    expect($summary->json('metrics.steam_turbine_rpm.max'))->toEqual(3000.0);
    expect($summary->json('status_distribution.diesel_gen_1_status.counts.run'))->toBe(2);
    expect(collect($summary->json('daily'))->pluck('date')->all())
        ->toBe([$this->periodStart, $this->periodEnd]);

    $export = $this->actingAs($this->supervisor, 'web')->get(laporanEngineRoomUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));
    $export->assertOk();

    $csv = laporanEngineRoomStreamed($export->baseResponse);

    expect($csv)->toContain($this->periodStart);
    expect($csv)->toContain($this->periodEnd);
    expect($csv)->toContain('ER-UJUNG-0');
    expect($csv)->toContain('ER-UJUNG-1');
    expect($csv)->not->toContain('ER-LUAR-0');
    expect($csv)->not->toContain('ER-LUAR-1');
});

// =====================================================================
// Skenario 31-35: penyebut, ketiadaan penandaan, ketiadaan downtime,
//                 ekspor .xlsx
// =====================================================================

it('skenario 31 — setiap metrik punya penyebutnya SENDIRI, dan metrik jarang tidak mengempis', function () {
    $record = laporanEngineRoomRecord($this->stationA, $this->day2, 'ER-1');

    // Kolom turbin uap pada 12 slot; kolom genset hanya pada 2 — dan
    // nilainya besar, supaya penyebut bersama akan terlihat mengempiskannya.
    foreach (range(0, 11) as $index) {
        $values = ['steam_turbine_rpm' => 3000.0];

        if ($index < 2) {
            $values['diesel_gen_1_load_kw'] = 900.0;
        }

        laporanEngineRoomSlot($record, $this->slots[$index], $values);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEngineRoomUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    expect($summary->json('metrics.steam_turbine_rpm.reading_count'))->toBe(12);
    expect($summary->json('metrics.diesel_gen_1_load_kw.reading_count'))->toBe(2);

    // Rata-rata genset TETAP 900, bukan 900 × 2 / 12 = 150 yang akan
    // diterbitkan penyebut bersama — angka yang tetap terlihat sah.
    expect($summary->json('metrics.diesel_gen_1_load_kw.avg'))->toEqual(900.0);
    expect($summary->json('metrics.diesel_gen_1_load_kw.avg'))->not->toEqual(150.0);

    // Dan TIDAK ADA satu penyebut bersama di mana pun pada payload.
    expect($summary->json())->not->toHaveKey('reading_count');
    expect($summary->json('coverage'))->not->toHaveKey('reading_count');
});

it('skenario 32 — jumlah pembacaan menyertai angkanya DI DALAM entri metrik yang sama', function () {
    $record = laporanEngineRoomRecord($this->stationA, $this->day2, 'ER-1');

    // Satu metrik bersandar pada 3 pembacaan, satu lagi pada 20.
    foreach (range(0, 19) as $index) {
        $values = ['steam_turbine_rpm' => 3000.0];

        if ($index < 3) {
            $values['fuel_tank_level'] = 50.0;
        }

        laporanEngineRoomSlot($record, $this->slots[$index], $values);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEngineRoomUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    // SETIAP dari 22 entri membawa reading_count DI DALAM entrinya sendiri,
    // berdampingan dengan min/avg/max — tidak dikumpulkan di satu tempat
    // terpisah.
    foreach ($summary->json('metrics') as $column => $metric) {
        expect(array_keys($metric))->toBe(['min', 'avg', 'max', 'reading_count', 'group'], $column);
    }

    expect($summary->json('metrics.fuel_tank_level.reading_count'))->toBe(3);
    expect($summary->json('metrics.steam_turbine_rpm.reading_count'))->toBe(20);

    // Rekap per unit dan rekap harian pun membawa penyebut per selnya.
    expect(array_keys($summary->json('by_unit.0.metrics.fuel_tank_level')))
        ->toBe(['min', 'avg', 'max', 'reading_count', 'group']);
    expect(array_keys($summary->json('daily.0.metrics_avg.fuel_tank_level')))
        ->toBe(['avg', 'reading_count']);
});

it('skenario 33 — tidak ada penandaan nilai di luar batas: diasersi atas KETIADAAN KUNCI', function () {
    $record = laporanEngineRoomRecord($this->stationA, $this->day2, 'ER-1');

    // Jauh dari nominal yang dikenal luas DI LUAR aplikasi.
    laporanEngineRoomSlot($record, '07:00', [
        'electrical_sync_system_frequency_hz' => 43.0,
        'electrical_sync_power_factor' => 0.35,
        'electrical_sync_busbar_voltage_v' => 310.0,
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEngineRoomUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    $keys = laporanEngineRoomAllKeys($summary->json());

    foreach (laporanEngineRoomForbiddenKeys() as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
    }

    // Nilainya diterbitkan APA ADANYA.
    expect($summary->json('metrics.electrical_sync_system_frequency_hz.avg'))->toEqual(43.0);
    expect($summary->json('metrics.electrical_sync_power_factor.avg'))->toEqual(0.35);
    expect($summary->json('metrics.electrical_sync_busbar_voltage_v.avg'))->toEqual(310.0);

    // Dan berkas ekspornya pun tidak punya satu kolom kesimpulan.
    foreach (EngineRoomReportService::EXPORT_HEADER as $header) {
        expect(strtolower($header))->not->toContain('target');
        expect(strtolower($header))->not->toContain('ambang');
        expect(strtolower($header))->not->toContain('di luar batas');
    }
});

it('skenario 34 — laporan tidak memuat blok downtime, dan berkas ekspornya tidak punya kolomnya', function () {
    $record = laporanEngineRoomRecord($this->stationA, $this->day2, 'ER-1');

    laporanEngineRoomSlot($record, '07:00', array_fill_keys(
        array_merge(EngineRoomReportService::NUMERIC_METRICS, ['daily_energy_export_kwh']),
        7.5,
    ));

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEngineRoomUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    $keys = laporanEngineRoomAllKeys($summary->json());

    foreach (['downtime', 'downtime_minutes', 'downtime_events', 'total_minutes', 'stop_events'] as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
    }

    // Kunci yang MEMANG ada, dan hanya itu.
    expect(array_keys($summary->json()))->toBe([
        'period', 'business_unit', 'production_line', 'has_data', 'coverage',
        'metrics', 'daily_energy_export', 'status_distribution',
        'maintenance_remarks', 'findings', 'daily_trend', 'by_unit', 'daily',
        'daily_total', 'total', '_note_absences',
    ]);

    // Ketiadaannya DINYATAKAN di payload, bukan dibiarkan sebagai lubang.
    expect($summary->json('_note_absences'))->toContain('TIDAK ADA kunci downtime');

    // Dan header CSV-nya tidak memuat satu kolom downtime pun.
    $export = $this->actingAs($this->supervisor, 'web')->get(laporanEngineRoomUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));
    $export->assertOk();

    $lines = laporanEngineRoomCsvLinesOf(laporanEngineRoomStreamed($export->baseResponse));

    expect(strtolower($lines[0]))->not->toContain('downtime');
    expect(strtolower($lines[0]))->not->toContain('lama berhenti');
});

it('skenario 35 — ekspor Excel adalah .xlsx SUNGGUHAN, csv sama isinya, format pdf 422', function () {
    $record = laporanEngineRoomRecord($this->stationA, $this->day2, 'ER-9', [
        'status' => RecordStatus::Saved,
    ]);

    foreach (range(0, 2) as $index) {
        laporanEngineRoomSlot($record, $this->slots[$index], [
            'steam_turbine_rpm' => 3000.0,
            'diesel_gen_1_status' => 'standby',
            'diesel_gen_2_status' => null,
            'action_taken_maintenance_remark' => 'Ganti seal',
            'findings' => 'Suara kasar',
        ]);
    }

    $excel = $this->actingAs($this->supervisor, 'web')->get(laporanEngineRoomUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'excel',
    ]));
    $excel->assertOk();
    expect($excel->headers->get('content-type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect($excel->headers->get('content-disposition'))->toContain('.xlsx');

    // XLSX SUNGGUHAN, bukan CSV bernama .xlsx (temuan audit 2026-10-04 #1):
    // berkas xlsx adalah arsip ZIP, jadi dua byte pertamanya 'PK' (0x504b).
    expect(bin2hex(substr(laporanEngineRoomStreamed($excel->baseResponse), 0, 2)))->toBe('504b');

    $csv = $this->actingAs($this->supervisor, 'web')->get(laporanEngineRoomUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));
    $csv->assertOk();

    $lines = laporanEngineRoomCsvLinesOf(laporanEngineRoomStreamed($csv->baseResponse));

    // 1 header + 3 baris slot: SATU BARIS PER SLOT WAKTU.
    expect($lines)->toHaveCount(4);

    // TIGA PULUH LIMA kolom: 8 konteks + 27 READING_FIELDS.
    expect(EngineRoomReportService::EXPORT_HEADER)->toHaveCount(35);
    expect(count(EngineRoomRecordService::READING_FIELDS))->toBe(27);
    expect(str_getcsv($lines[0]))->toHaveCount(35);
    expect(str_getcsv($lines[0]))->toBe(EngineRoomReportService::EXPORT_HEADER);

    foreach (array_slice($lines, 1) as $line) {
        $cells = str_getcsv($line);

        expect($cells)->toHaveCount(35);
        // Kolom konteks DIULANG verbatim pada ketiga baris.
        expect($cells[0])->toBe('Periode Engine Room Alpha');
        expect($cells[1])->toBe('Mill Alpha');
        expect($cells[3])->toBe($this->day2);
        expect($cells[4])->toBe('ER-9');
    }

    // SLOT WAKTU berformat HH:MM.
    expect(str_getcsv($lines[1])[7])->toBe('07:00');
    expect(str_getcsv($lines[2])[7])->toBe('08:00');
    expect(str_getcsv($lines[3])[7])->toBe('09:00');

    // KEDUA PEMETA YANG TIDAK BOLEH DIPERTUKARKAN, diasersi pada SEL-nya
    // masing-masing — bukan dengan memindai barisnya: label dua atau tiga
    // huruf cocok di dalam kata lain dan asersi substring bisa hijau tanpa
    // membuktikan apa pun.
    $readingFields = EngineRoomRecordService::READING_FIELDS;
    $gen1 = 8 + array_search('diesel_gen_1_status', $readingFields, true);
    $gen2 = 8 + array_search('diesel_gen_2_status', $readingFields, true);

    $cells = str_getcsv($lines[1]);

    // Kolom status GENSET: ExportValue::option() → Run / Standby / Off,
    // identik dengan layar Detail.
    expect($cells[$gen1])->toBe('Standby');
    // Kolom Genset 2 kosong karena nilainya null — bukan 0, bukan '-'.
    expect($cells[$gen2])->toBe('');
    // Kolom Status KONTEKS: ExportValue::status() → Tersimpan / Tersinkron.
    expect($cells[5])->toBe('Tersimpan');
    // Dan tidak ada label Bahasa Indonesia untuk nilai status genset.
    expect($cells[$gen1])->not->toBe('Siaga');
    expect($cells[$gen1])->not->toBe('Tersimpan');

    // Kedua kolom teks bebas ikut, apa adanya, di ujung blok 27 kolom.
    expect($cells[33])->toBe('Ganti seal');
    expect($cells[34])->toBe('Suara kasar');

    // format di luar csv|excel: 422 VALIDATION_ERROR, dan pengaliran tidak
    // pernah dimulai.
    $pdf = $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanEngineRoomUrl('export', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
            'format' => 'pdf',
        ]));

    $pdf->assertStatus(422)->assertJsonValidationErrors('format');
    expect($pdf->headers->get('content-type'))->not->toContain('text/csv');

    // Tanpa parameter format: jatuh ke csv (bawaan controller).
    $default = $this->actingAs($this->supervisor, 'web')->get(laporanEngineRoomUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $default->assertOk();
    expect($default->headers->get('content-type'))->toContain('text/csv');
});
