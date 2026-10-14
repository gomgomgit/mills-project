<?php

/**
 * LaporanEffluentPlantTest (API) — screen-156--laporan-effluent-plant-web,
 * keempat endpoint /api/effluent-plant-reports/*.
 *
 * Satu uji per entri `test_scenarios[].api_test` pada tech spec screen-156.
 * Aturan agregasinya sendiri dibuktikan di
 * tests/Unit/Services/EffluentPlantReportServiceTest.php terhadap service-nya;
 * yang dibuktikan berkas ini adalah KONTRAK HTTP-nya — kode status, siapa yang
 * diterima, apa yang benar-benar dibawa payload-nya, dan bagaimana bentuk
 * berkas ekspornya.
 *
 * ────────────────────────────────────────────────────────────────────────
 * YANG MEMBEDAKAN LAPORAN INI DARI SEBELAS SAUDARANYA
 * ────────────────────────────────────────────────────────────────────────
 * OPERATOR DITOLAK 403 PADA KEEMPAT RUTE SAMPAI 2026-10-13, bukan diterima
 * pada tiga rute data seperti Grading / Kernel Plant. Alasannya bukan
 * kelalaian: kembaran mobile screen-157 belum dibangun, jadi tidak ada
 * pemanggil Operator yang sah — dan menerima sebuah peran sebelum
 * pemanggilnya ada adalah akses yang tidak dapat ditinjau siapa pun.
 * Mengikuti presedens Weighbridge (screen-143 lebih dulu, Operator
 * ditambahkan saat screen-144 dibangun).
 *
 * PENUNDAAN ITU BERAKHIR BERSAMA screen-157--laporan-effluent-plant-mobile.
 * Operator sekarang DITERIMA 200 pada KETIGA rute data dan TETAP DITOLAK 403
 * pada /business-units/options. Skenario 21 di bawah yang menjaga keduanya,
 * dan skenario 21c menjaga bagian yang paling mudah terlewat: pelebaran itu
 * harus menyentuh TIGA tempat (daftar peran middleware, guardAccess(), DAN
 * cabang terikat-mill resolveBusinessUnit()); dua dari tiga menjatuhkan
 * Operator ke cabang Admin tempat business_unit_id kiriman klien DIHORMATI,
 * yaitu kebocoran lintas mill, bukan cacat tampilan.
 *
 * FIXTURE-NYA SENGAJA TIDAK RATA: kolom diisi pada HIMPUNAN SLOT YANG
 * BERBEDA-BEDA, dan ketiga kolom laboratorium diisi jauh lebih jarang
 * daripada sisanya, sehingga satu penyebut bersama tidak dapat lolos tanpa
 * terlihat. Lihat docblock berkas unit untuk mengapa merapikannya mengubah
 * asersi-asersi ini menjadi selalu hijau.
 *
 * TANGGALNYA SELALU RELATIF (now()->subDays(...)): periode uji di bawah harus
 * SELALU sudah selesai agar days_counted-nya deterministik, dan tanggal yang
 * dipaku berhenti memenuhi syarat itu begitu kalender melewatinya.
 *
 * TIGA TEMPAT KONTRAKNYA BERBEDA DARI TULISAN TECH SPEC, dan di ketiganya
 * implementasinya yang diikuti — masing-masing diberi komentar di tempatnya:
 * akun terikat mill tanpa business_unit_id adalah 422 VALIDATION_ERROR (bukan
 * 403), Admin lolos periode mill mana pun (jadi 200, bukan 403), dan label
 * ketiga kolom status pada berkas ekspor mengikuti layar Detail
 * (ExportValue::option -> On/Off/Fault, Run/Stop) alih-alih pemeta status
 * RECORD yang disebut tech spec.
 */

use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\EffluentPlantDetail;
use App\Models\EffluentPlantRecord;
use App\Models\Period;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use App\Services\EffluentPlantRecordService;
use App\Services\EffluentPlantReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function laporanEffluentPlantUrl(string $path, array $query = []): string
{
    return '/api/effluent-plant-reports/'.$path.($query === [] ? '' : '?'.http_build_query($query));
}

function laporanEffluentPlantRecord(
    Station $station,
    string $date,
    string $effluentPlantId = 'EP-1',
    array $attributes = [],
): EffluentPlantRecord {
    return EffluentPlantRecord::factory()
        ->forStation($station)
        ->onDate($date)
        ->create(array_merge(['effluent_plant_id' => $effluentPlantId, 'note' => 'Catatan harian'], $attributes));
}

function laporanEffluentPlantSlot(EffluentPlantRecord $record, string $timeSlot, array $values = []): EffluentPlantDetail
{
    return EffluentPlantDetail::factory()
        ->forRecord($record)
        ->timeSlot($timeSlot)
        ->create($values);
}

/** Isi StreamedResponse, ditangkap. ALIRKAN SEKALI SAJA. */
function laporanEffluentPlantStreamed($response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

function laporanEffluentPlantCsvLinesOf(string $body): array
{
    return array_values(array_filter(explode("\n", trim($body))));
}

/** @return list<string> seluruh nama KUNCI di mana pun dalam struktur */
function laporanEffluentPlantAllKeys(mixed $value): array
{
    if (! is_array($value)) {
        return [];
    }

    $keys = [];

    foreach ($value as $key => $child) {
        if (is_string($key)) {
            $keys[] = $key;
        }

        $keys = array_merge($keys, laporanEffluentPlantAllKeys($child));
    }

    return array_values(array_unique($keys));
}

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->effluentPlant()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->effluentPlant()->create();

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
        ->stationType(StationTypeEnum::EffluentPlant->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Effluent Plant Alpha')
        ->create();

    $this->slots = EffluentPlantRecordService::canonicalTimeSlots();
});

// =====================================================================
// Skenario 1-2: jalur sukses
// =====================================================================

it('skenario 1 — Supervisor dan Mill Management: periods + summary + export mill sendiri', function () {
    $record = laporanEffluentPlantRecord($this->stationA, $this->day2, 'EP-1');

    // pH kolam pada 4 slot; nilai laboratorium hanya pada 2 — penyebut yang
    // berbeda itulah yang membuat asersi di bawah bermakna.
    foreach (range(0, 3) as $index) {
        $values = ['anaerobic_pond_1_ph' => 7.0 + $index / 10];

        if ($index < 2) {
            $values['final_discharge_bod_mgl_lab'] = 100.0 + $index;
        }

        laporanEffluentPlantSlot($record, $this->slots[$index], $values);
    }

    foreach ([$this->supervisor, $this->millManagement] as $user) {
        $periods = $this->actingAs($user, 'web')->getJson(laporanEffluentPlantUrl('periods'));
        $periods->assertOk();
        expect(collect($periods->json('data'))->pluck('id'))->toContain((string) $this->periodA->id);
        // BERTANDA HUBUNG: kunci snake_case akan ter-compile, tampak benar,
        // dan tidak pernah cocok dengan satu baris master pun.
        expect(collect($periods->json('data'))->pluck('station_type'))->toContain('effluent-plant');

        $summary = $this->actingAs($user, 'web')->getJson(laporanEffluentPlantUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]));
        $summary->assertOk();

        expect($summary->json('business_unit.name'))->toBe('Mill Alpha');
        expect($summary->json('production_line.id'))->toBe($this->lineA);
        expect($summary->json('has_data'))->toBeTrue();
        expect($summary->json('coverage.filled_slots'))->toBe(4);
        expect($summary->json('metrics'))->toHaveCount(14);
        expect($summary->json('status_distribution'))->toHaveCount(3);

        // PENYEBUT PER KOLOM, bukan satu penyebut bersama.
        expect($summary->json('metrics.anaerobic_pond_1_ph.reading_count'))->toBe(4);
        expect($summary->json('metrics.final_discharge_bod_mgl_lab.reading_count'))->toBe(2);
        expect($summary->json('metrics.final_discharge_bod_mgl_lab.is_lab'))->toBeTrue();
        expect($summary->json('metrics.anaerobic_pond_1_ph.is_lab'))->toBeFalse();

        // DUA daftar teks bebas yang TERPISAH, selalu ada sebagai kunci.
        expect($summary->json())->toHaveKey('maintenance_remarks');
        expect($summary->json())->toHaveKey('findings');
    }

    $export = $this->actingAs($this->supervisor, 'web')->get(laporanEffluentPlantUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));
    $export->assertOk();
    expect($export->headers->get('content-type'))->toContain('text/csv');
});

it('skenario 2 — Admin: pemilih mill lalu angka mill yang dipilih saja, ekspor excel .xlsx sungguhan', function () {
    $recordA = laporanEffluentPlantRecord($this->stationA, $this->day2, 'EP-A');
    laporanEffluentPlantSlot($recordA, '07:00', ['anaerobic_pond_1_ph' => 7.0]);

    $recordB = laporanEffluentPlantRecord($this->stationB, $this->day2, 'EP-B');
    laporanEffluentPlantSlot($recordB, '07:00', ['anaerobic_pond_1_ph' => 99.0]);

    $options = $this->actingAs($this->admin, 'web')->getJson(laporanEffluentPlantUrl('business-units/options'));
    $options->assertOk();
    expect(collect($options->json('data'))->pluck('name'))->toContain('Mill Alpha', 'Mill Beta');

    $periods = $this->actingAs($this->admin, 'web')->getJson(laporanEffluentPlantUrl('periods', [
        'business_unit_id' => (string) $this->businessUnitA->id,
    ]));
    $periods->assertOk();
    expect(collect($periods->json('data'))->pluck('id'))->toContain((string) $this->periodA->id);

    $summary = $this->actingAs($this->admin, 'web')->getJson(laporanEffluentPlantUrl('summary', [
        'business_unit_id' => (string) $this->businessUnitA->id,
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    // Hanya mill yang dipilih — 99.0 milik mill lain tidak boleh terbaca.
    expect($summary->json('metrics.anaerobic_pond_1_ph.max'))->toEqual(7.0);

    $excel = $this->actingAs($this->admin, 'web')->get(laporanEffluentPlantUrl('export', [
        'business_unit_id' => (string) $this->businessUnitA->id,
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'excel',
    ]));
    $excel->assertOk();
    expect($excel->headers->get('content-type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

// =====================================================================
// Skenario 3-6: parameter wajib dan daftar kosong
// =====================================================================

it('skenario 3 — Admin tanpa business_unit_id: 422 pada periods dan pada summary', function () {
    $this->actingAs($this->admin, 'web')
        ->getJson(laporanEffluentPlantUrl('periods'))
        ->assertStatus(422)
        ->assertJsonValidationErrors('business_unit_id');

    // Opsi mill tetap 200 — itulah yang harus ia pakai lebih dulu.
    $this->actingAs($this->admin, 'web')
        ->getJson(laporanEffluentPlantUrl('business-units/options'))
        ->assertOk();

    $this->actingAs($this->admin, 'web')
        ->getJson(laporanEffluentPlantUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('business_unit_id');
});

it('skenario 4 — production_line_id TIDAK dikirim: 422, dan NOL kueri effluent_plant_records', function () {
    $record = laporanEffluentPlantRecord($this->stationA, $this->day2, 'EP-1');
    laporanEffluentPlantSlot($record, '07:00', ['anaerobic_pond_1_ph' => 7.0]);

    $queries = [];

    DB::listen(function ($query) use (&$queries) {
        $queries[] = strtolower($query->sql);
    });

    // TIDAK PERNAH di-default ke "semua line mill": diam-diam melebarkan
    // cakupan laporan karena satu parameter terlupa adalah yang terburuk
    // dari dua dunia — ia menjawab 200 dengan angka yang tidak diminta
    // siapa pun.
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanEffluentPlantUrl('summary', ['period_id' => (string) $this->periodA->id]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('production_line_id');

    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "effluent_plant_records"');
    }

    // Ekspor pun sama.
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanEffluentPlantUrl('export', ['period_id' => (string) $this->periodA->id]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('production_line_id');

    // Dan period_id absen: 422 dengan errors.period_id, bukan 404.
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanEffluentPlantUrl('summary', ['production_line_id' => $this->lineA]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('period_id');
});

it('skenario 5 — Admin berganti mill: daftar periode kedua mill masing-masing 200 dan tidak bercampur', function () {
    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::EffluentPlant->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Effluent Plant Beta')
        ->create();

    $forA = $this->actingAs($this->admin, 'web')->getJson(laporanEffluentPlantUrl('periods', [
        'business_unit_id' => (string) $this->businessUnitA->id,
    ]));
    $forA->assertOk();

    $forB = $this->actingAs($this->admin, 'web')->getJson(laporanEffluentPlantUrl('periods', [
        'business_unit_id' => (string) $this->businessUnitB->id,
    ]));
    $forB->assertOk();

    expect(collect($forA->json('data'))->pluck('id')->all())->toBe([(string) $this->periodA->id]);
    expect(collect($forB->json('data'))->pluck('id')->all())->toBe([(string) $periodB->id]);
});

it('skenario 6 — mill belum punya satu pun periode Effluent Plant: 200 dengan data []', function () {
    // Mill ini hanya punya periode berjenis stasiun LAIN — bukan 404, dan
    // bukan daftar yang diisi periode jenis lain.
    Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::KernelPlant->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->create();

    $supervisorB = User::factory()
        ->role(UserRole::Supervisor)
        ->forBusinessUnit($this->businessUnitB)
        ->create();

    $response = $this->actingAs($supervisorB, 'web')
        ->getJson(laporanEffluentPlantUrl('periods'));

    $response->assertOk();
    expect($response->json('data'))->toBe([]);
});

// =====================================================================
// Skenario 7-9: keadaan kosong per metrik
// =====================================================================

it('skenario 7 — periode tanpa satu pun slot terisi: 200, has_data false, keempat belas metrik null', function () {
    $record = laporanEffluentPlantRecord($this->stationA, $this->day2, 'EP-1');
    laporanEffluentPlantSlot($record, '07:00');

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEffluentPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    expect($summary->json('has_data'))->toBeFalse();
    expect($summary->json('coverage.filled_slots'))->toBe(0);

    // KARTUNYA TETAP ADA keempat belas, dengan null — BUKAN 0, yang akan
    // terbaca sebagai hasil ukur.
    expect($summary->json('metrics'))->toHaveCount(14);

    foreach (EffluentPlantReportService::NUMERIC_METRICS as $metric) {
        expect($summary->json("metrics.{$metric}.avg"))->toBeNull($metric);
        expect($summary->json("metrics.{$metric}.min"))->toBeNull($metric);
        expect($summary->json("metrics.{$metric}.max"))->toBeNull($metric);
        expect($summary->json("metrics.{$metric}.reading_count"))->toBe(0, $metric);
    }

    // Grafik tren kosong tidak digambar: serinya kosong, bukan berisi titik
    // nol.
    expect($summary->json('daily_trend.raw_pome_feed_rate_m3h'))->toBe([]);
    expect($summary->json('daily_trend.final_discharge_ph'))->toBe([]);
    expect($summary->json('daily'))->toBe([]);
});

it('skenario 8 — satu metrik tidak pernah diisi sepanjang periode, metrik lain tetap berangka', function () {
    $record = laporanEffluentPlantRecord($this->stationA, $this->day2, 'EP-1');

    foreach (range(0, 2) as $index) {
        laporanEffluentPlantSlot($record, $this->slots[$index], [
            'anaerobic_pond_1_ph' => 7.0,
            'cooling_pond_temp_c' => 40.0 + $index,
        ]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEffluentPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    // chemical_consumed_kgl kosong sepanjang periode: null dengan penyebut
    // 0, bukan 0.
    expect($summary->json('metrics.chemical_consumed_kgl.avg'))->toBeNull();
    expect($summary->json('metrics.chemical_consumed_kgl.reading_count'))->toBe(0);
    // Sementara dua metrik lain membawa penyebutnya sendiri.
    expect($summary->json('metrics.anaerobic_pond_1_ph.reading_count'))->toBe(3);
    expect($summary->json('metrics.cooling_pond_temp_c.reading_count'))->toBe(3);
    expect($summary->json('metrics.cooling_pond_temp_c.avg'))->toEqual(41.0);
});

it('skenario 9 — nilai laboratorium tidak pernah diisi: ketiganya null dan is_lab, sebelas lain utuh', function () {
    $nonLab = array_values(array_diff(
        EffluentPlantReportService::NUMERIC_METRICS,
        EffluentPlantReportService::LAB_METRICS,
    ));

    $record = laporanEffluentPlantRecord($this->stationA, $this->day2, 'EP-1');

    foreach ($this->slots as $slot) {
        laporanEffluentPlantSlot($record, $slot, array_fill_keys($nonLab, 7.0));
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEffluentPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    expect($summary->json('coverage.filled_slots'))->toBe(24);

    // PENYEBUT KECIL PADA KETIGANYA ADALAH KEADAAN NORMAL, bukan data
    // hilang: is_lab ada supaya layar dapat mengatakannya di tempat
    // angkanya, dan supaya tidak ada pembaca kode yang "memperbaiki"
    // penyebutnya menjadi penyebut bersama.
    foreach (EffluentPlantReportService::LAB_METRICS as $metric) {
        expect($summary->json("metrics.{$metric}.is_lab"))->toBeTrue($metric);
        expect($summary->json("metrics.{$metric}.reading_count"))->toBe(0, $metric);
        expect($summary->json("metrics.{$metric}.avg"))->toBeNull($metric);
    }

    foreach ($nonLab as $metric) {
        expect($summary->json("metrics.{$metric}.is_lab"))->toBeFalse($metric);
        expect($summary->json("metrics.{$metric}.reading_count"))->toBe(24, $metric);
    }
});

// =====================================================================
// Skenario 10-11: sebaran status
// =====================================================================

it('skenario 10 — status tidak tercatat masuk not_recorded dan TIDAK PERNAH menjadi Mati/Berhenti', function () {
    $record = laporanEffluentPlantRecord($this->stationA, $this->day2, 'EP-1');

    // 3 slot bernilai, 5 slot terisi lewat kolom lain tanpa status.
    laporanEffluentPlantSlot($record, $this->slots[0], ['biogas_flare_status' => 'on']);
    laporanEffluentPlantSlot($record, $this->slots[1], ['biogas_flare_status' => 'on']);
    laporanEffluentPlantSlot($record, $this->slots[2], ['biogas_flare_status' => 'fault']);

    foreach (range(3, 7) as $index) {
        laporanEffluentPlantSlot($record, $this->slots[$index], ['anaerobic_pond_1_ph' => 7.0]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEffluentPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    $flare = $summary->json('status_distribution.biogas_flare_status');

    expect($flare['counts'])->toBe(['on' => 2, 'off' => 0, 'fault' => 1]);
    // KUNCI TERPISAH, dan jumlahnya BERBEDA dari 'off': melipatnya akan
    // melaporkan flare mati yang tidak pernah terjadi.
    expect($flare['not_recorded'])->toBe(5);
    expect($flare['counts']['off'])->not->toBe($flare['not_recorded']);
    expect($flare['recorded_slots'])->toBe(8);
    expect(array_sum($flare['counts']) + $flare['not_recorded'])->toBe($flare['recorded_slots']);

    // Ketiga sebaran menjawab dengan bentuk yang sama.
    foreach (['dosing_pump_1_status', 'sludge_dewatering_status'] as $column) {
        $dist = $summary->json("status_distribution.{$column}");

        expect($dist['not_recorded'])->toBe(8, $column);
        expect(array_sum($dist['counts']))->toBe(0, $column);
    }
});

it('skenario 11 — nilai status dikenal yang tidak pernah muncul tetap hadir dengan jumlah 0', function () {
    $record = laporanEffluentPlantRecord($this->stationA, $this->day2, 'EP-1');

    foreach (range(0, 3) as $index) {
        laporanEffluentPlantSlot($record, $this->slots[$index], [
            'biogas_flare_status' => 'on',
            'dosing_pump_1_status' => 'run',
            'sludge_dewatering_status' => 'run',
        ]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEffluentPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    // 'Gangguan' tidak pernah terjadi, dan baris itu TETAP ada dengan 0 —
    // baris yang hilang tidak dapat dibedakan dari nilai yang tidak pernah
    // ada di basis data, dan "tidak pernah terjadi" harus terbaca lain dari
    // "tidak dilaporkan".
    expect($summary->json('status_distribution.biogas_flare_status.counts'))
        ->toBe(['on' => 4, 'off' => 0, 'fault' => 0]);
    expect($summary->json('status_distribution.biogas_flare_status.known_values'))
        ->toBe(['on', 'off', 'fault']);
    expect($summary->json('status_distribution.biogas_flare_status.not_recorded'))->toBe(0);
    expect($summary->json('status_distribution.dosing_pump_1_status.counts'))
        ->toBe(['run' => 4, 'stop' => 0]);
    expect($summary->json('status_distribution.sludge_dewatering_status.counts'))
        ->toBe(['run' => 4, 'stop' => 0]);
});

// =====================================================================
// Skenario 12-13: dua daftar teks bebas
// =====================================================================

it('skenario 12 — satu slot mengisi catatan perawatan DAN temuan: muncul di KEDUA daftar', function () {
    $record = laporanEffluentPlantRecord($this->stationA, $this->day3, 'EP-1');

    laporanEffluentPlantSlot($record, '08:00', [
        'remarks_maintenance_actions' => 'Kalibrasi pompa dosing',
        'findings' => 'pH turun mendadak',
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEffluentPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    // SATU KALI di tiap daftar — bukan dua kali di satu daftar gabungan.
    expect($summary->json('maintenance_remarks'))->toHaveCount(1);
    expect($summary->json('findings'))->toHaveCount(1);
    expect($summary->json('maintenance_remarks.0'))->toBe([
        'date' => $this->day3,
        'time_slot' => '08:00',
        'text' => 'Kalibrasi pompa dosing',
    ]);
    expect($summary->json('findings.0'))->toBe([
        'date' => $this->day3,
        'time_slot' => '08:00',
        'text' => 'pH turun mendadak',
    ]);
    expect($summary->json('coverage.filled_slots'))->toBe(1);
});

it('skenario 13 — kedua kolom teks bebas kosong: dua array kosong yang TERPISAH', function () {
    $record = laporanEffluentPlantRecord($this->stationA, $this->day2, 'EP-1');
    laporanEffluentPlantSlot($record, '07:00', ['anaerobic_pond_1_ph' => 7.0]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEffluentPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    expect($summary->json('maintenance_remarks'))->toBe([]);
    expect($summary->json('findings'))->toBe([]);
    // DUA kunci, tidak digabung menjadi satu daftar ketiga.
    expect(laporanEffluentPlantAllKeys($summary->json()))->not->toContain('notes');
    expect(laporanEffluentPlantAllKeys($summary->json()))->not->toContain('text_entries');
});

// =====================================================================
// Skenario 14-17: kelengkapan dan rekap per unit
// =====================================================================

it('skenario 14 — pencatatan sangat tidak lengkap: angka tetap terbit, cakupannya kecil dan nyata', function () {
    $record = laporanEffluentPlantRecord($this->stationA, $this->day2, 'EP-1');

    foreach (range(0, 2) as $index) {
        laporanEffluentPlantSlot($record, $this->slots[$index], ['anaerobic_pond_1_ph' => 7.0]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEffluentPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    // 1 unit x 10 hari x 24 slot = 240 yang diharapkan, 3 terisi = 1,3%.
    expect($summary->json('coverage.expected_slots'))->toBe(240);
    expect($summary->json('coverage.filled_slots'))->toBe(3);
    expect($summary->json('coverage.coverage_percent'))->toEqual(1.3);
    expect($summary->json('coverage.days_in_period'))->toBe(10);
    expect($summary->json('coverage.days_counted'))->toBe(10);
    expect($summary->json('coverage.slots_per_unit_per_day'))->toBe(24);
    expect($summary->json('coverage.period_running'))->toBeFalse();
    // Angkanya TETAP diterbitkan — berdampingan dengan penyebut kecilnya.
    expect($summary->json('metrics.anaerobic_pond_1_ph.avg'))->toEqual(7.0);
    expect($summary->json('metrics.anaerobic_pond_1_ph.reading_count'))->toBe(3);
});

it('skenario 15 — periode masih berjalan: penyebutnya berhenti di hari ini', function () {
    $running = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::EffluentPlant->value)
        ->range(now()->subDays(3)->toDateString(), now()->addDays(5)->toDateString())
        ->open()
        ->named('Periode Berjalan')
        ->create();

    foreach (range(0, 3) as $offset) {
        $date = now()->subDays(3 - $offset)->toDateString();
        $record = laporanEffluentPlantRecord($this->stationA, $date, 'EP-1');

        foreach ($this->slots as $slot) {
            laporanEffluentPlantSlot($record, $slot, ['anaerobic_pond_1_ph' => 7.0]);
        }
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEffluentPlantUrl('summary', [
        'period_id' => (string) $running->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    expect($summary->json('coverage.days_in_period'))->toBe(9);
    expect($summary->json('coverage.days_counted'))->toBe(4);
    expect($summary->json('coverage.period_running'))->toBeTrue();
    // Hari yang belum terjadi tidak mungkin tercatat, jadi tidak menurunkan
    // persentase.
    expect($summary->json('coverage.expected_slots'))->toBe(96);
    expect($summary->json('coverage.coverage_percent'))->toEqual(100.0);
});

it('skenario 16 — tidak ada slot yang diharapkan: coverage_percent 0.0, bukan null', function () {
    $future = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::EffluentPlant->value)
        ->range(now()->addDays(5)->toDateString(), now()->addDays(14)->toDateString())
        ->draft()
        ->named('Periode Belum Mulai')
        ->create();

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEffluentPlantUrl('summary', [
        'period_id' => (string) $future->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    expect($summary->json('coverage.days_counted'))->toBe(0);
    expect($summary->json('coverage.expected_slots'))->toBe(0);
    // 0.0 SENGAJA, bukan null — dan layar yang menerjemahkannya menjadi '—'
    // dengan 'belum ada slot yang diharapkan'.
    expect($summary->json('coverage.coverage_percent'))->toEqual(0.0);
    expect($summary->json('coverage.coverage_percent'))->not->toBeNull();
    expect($summary->json('has_data'))->toBeFalse();
});

it('skenario 17 — line punya beberapa unit Effluent Plant, salah satunya tanpa pembacaan', function () {
    $unit1 = laporanEffluentPlantRecord($this->stationA, $this->day2, 'EP-1');
    laporanEffluentPlantSlot($unit1, '07:00', ['anaerobic_pond_1_ph' => 6.0, 'raw_pome_feed_rate_m3h' => 30.0]);
    laporanEffluentPlantSlot($unit1, '08:00', ['anaerobic_pond_1_ph' => 8.0, 'raw_pome_feed_rate_m3h' => 50.0]);

    $unit2 = laporanEffluentPlantRecord($this->stationA, $this->day2, 'EP-2');
    laporanEffluentPlantSlot($unit2, '07:00');

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEffluentPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    expect($summary->json('by_unit'))->toHaveCount(2);
    expect(collect($summary->json('by_unit'))->pluck('effluent_plant_id')->all())->toBe(['EP-1', 'EP-2']);
    // Unit tanpa pembacaan TETAP dirender — membuangnya akan menyembunyikan
    // justru unit yang tidak pernah ditulis.
    expect($summary->json('by_unit.1.reading_rows'))->toBe(0);
    expect($summary->json('by_unit.1.metrics.anaerobic_pond_1_ph.avg'))->toBeNull();
    // Angka periode MENGGABUNGKAN seluruh unit.
    expect($summary->json('metrics.anaerobic_pond_1_ph.avg'))->toEqual(7.0);
    expect($summary->json('coverage.effluent_plant_unit_count'))->toBe(2);
    expect($summary->json('coverage.expected_slots'))->toBe(480);
    // Satu definisi, satu jawaban.
    expect(collect($summary->json('by_unit'))->sum('reading_rows'))
        ->toBe($summary->json('coverage.filled_slots'));
    expect($summary->json('daily_total.reading_rows'))->toBe($summary->json('coverage.filled_slots'));
});

// =====================================================================
// Skenario 18-21: akses
// =====================================================================

it('skenario 18 — akun terikat mill tanpa business_unit_id: 422 VALIDATION_ERROR pada periods dan summary', function () {
    // BEDA DARI TULISAN TECH SPEC yang menyebut 403 pada satu tempat: yang
    // diterbitkan adalah 422 VALIDATION_ERROR dengan errors.business_unit_id,
    // sama seperti kesebelas laporan sebelumnya. Yang load-bearing adalah
    // GAGAL TERTUTUP tanpa pernah menawarkan daftar seluruh mill.
    $tanpaMill = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $this->actingAs($tanpaMill, 'web')
        ->getJson(laporanEffluentPlantUrl('periods'))
        ->assertStatus(422)
        ->assertJsonValidationErrors('business_unit_id');

    $this->actingAs($tanpaMill, 'web')
        ->getJson(laporanEffluentPlantUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('business_unit_id');

    // Dan daftar seluruh mill tetap tertutup untuknya.
    $this->actingAs($tanpaMill, 'web')
        ->getJson(laporanEffluentPlantUrl('business-units/options'))
        ->assertStatus(403);
});

it('skenario 19 — Supervisor mencoba melihat mill lain: 200 berisi data mill SENDIRI, periode mill lain 403', function () {
    $recordA = laporanEffluentPlantRecord($this->stationA, $this->day2, 'EP-A');
    laporanEffluentPlantSlot($recordA, '07:00', ['anaerobic_pond_1_ph' => 7.0]);

    $recordB = laporanEffluentPlantRecord($this->stationB, $this->day2, 'EP-B');
    laporanEffluentPlantSlot($recordB, '07:00', ['anaerobic_pond_1_ph' => 99.0]);

    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::EffluentPlant->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->create();

    // business_unit_id kiriman klien DIABAIKAN untuk peran terikat mill:
    // 200 dengan data mill sendiri, dan SENGAJA bukan 403 — 403 justru akan
    // menegaskan bahwa mill lain itu ada.
    $periods = $this->actingAs($this->supervisor, 'web')->getJson(laporanEffluentPlantUrl('periods', [
        'business_unit_id' => (string) $this->businessUnitB->id,
    ]));
    $periods->assertOk();
    expect(collect($periods->json('data'))->pluck('id')->all())->toBe([(string) $this->periodA->id]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEffluentPlantUrl('summary', [
        'business_unit_id' => (string) $this->businessUnitB->id,
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();
    expect($summary->json('business_unit.name'))->toBe('Mill Alpha');
    expect($summary->json('metrics.anaerobic_pond_1_ph.max'))->toEqual(7.0);

    // period_id milik mill LAIN: 403, sesudah guard line — pemanggil tak
    // berhak tidak boleh belajar id periode mana yang ada.
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanEffluentPlantUrl('summary', [
            'period_id' => (string) $periodB->id,
            'production_line_id' => $this->lineA,
        ]))
        ->assertStatus(403);

    // Dan period_id yang tidak ada: 404.
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanEffluentPlantUrl('summary', [
            'period_id' => (string) Str::uuid(),
            'production_line_id' => $this->lineA,
        ]))
        ->assertStatus(404);
});

it('skenario 20 — production_line_id milik mill lain: 403 pada summary DAN pada export', function () {
    foreach (['summary', 'export'] as $path) {
        $this->actingAs($this->supervisor, 'web')
            ->getJson(laporanEffluentPlantUrl($path, [
                'period_id' => (string) $this->periodA->id,
                'production_line_id' => $this->lineB,
                'format' => 'csv',
            ]))
            ->assertStatus(403);
    }

    // Line yang tidak ada sama sekali juga 403 — bukan 404, karena yang
    // dijawab adalah "bukan milik mill Anda".
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanEffluentPlantUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => (string) Str::uuid(),
        ]))
        ->assertStatus(403);
});

it('skenario 21 — OPERATOR 200 PADA KETIGA RUTE DATA, 403 HANYA PADA PEMILIH MILL', function () {
    $record = laporanEffluentPlantRecord($this->stationA, $this->day2, 'EP-1');
    laporanEffluentPlantSlot($record, '07:00', ['anaerobic_pond_1_ph' => 7.0]);

    // ────────────────────────────────────────────────────────────────────
    // DULU 403 PADA KEEMPATNYA, DAN PERUBAHANNYA DISENGAJA
    // ────────────────────────────────────────────────────────────────────
    // Ketiga 403 pada rute DATA mendokumentasikan sebuah PENUNDAAN, bukan
    // sebuah invarian: sampai kembaran mobile screen-157 dibangun tidak ada
    // pemanggil Operator yang sah, dan menerima sebuah peran sebelum
    // pemanggilnya ada adalah akses yang tidak dapat ditinjau siapa pun.
    // screen-157--laporan-effluent-plant-mobile ADALAH pemanggil itu, jadi
    // pelebarannya mendarat BERSAMA layar itu — persis seperti Grading dan
    // Kernel Plant, yang kembaran mobile-nya memang dibangun dalam perubahan
    // yang sama sejak commit pertama.
    //
    // PEMILIH MILL TIDAK IKUT DILEBARKAN, dan itu bagian terpenting dari
    // perubahan ini: Operator terikat SATU mill, jadi meminta daftar seluruh
    // mill bukan "daftar berisi satu" melainkan 403. Layar mobile itu memang
    // tidak punya satu pun jalur kode yang memanggilnya.
    $this->actingAs($this->operator, 'web')
        ->getJson(laporanEffluentPlantUrl('business-units/options'))
        ->assertStatus(403);

    $periods = $this->actingAs($this->operator, 'web')
        ->getJson(laporanEffluentPlantUrl('periods'));

    $periods->assertOk();
    expect(collect($periods->json('data'))->pluck('id'))->toContain((string) $this->periodA->id);
    // DENGAN TANDA HUBUNG — garis bawah di situ menjawab [] untuk setiap mill
    // tanpa satu galat.
    expect(collect($periods->json('data'))->pluck('station_type')->unique()->values()->all())
        ->toBe(['effluent-plant']);

    $summary = $this->actingAs($this->operator, 'web')
        ->getJson(laporanEffluentPlantUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]));

    $summary->assertOk();
    expect($summary->json('business_unit.name'))->toBe('Mill Alpha');
    expect($summary->json('period.id'))->toBe((string) $this->periodA->id);
    expect($summary->json('has_data'))->toBeTrue();

    $export = $this->actingAs($this->operator, 'web')
        ->get(laporanEffluentPlantUrl('export', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
            'format' => 'csv',
        ]));

    $export->assertOk();
    expect($export->headers->get('content-type'))->toContain('text/csv');

    // Dan rute KEEMPAT tetap 403 untuk setiap peran terikat mill —
    // penolakan yang datang dari DALAM service, bukan dari middleware
    // prefix, sehingga pelebaran daftar peran rute untuk screen-157 justru
    // TIDAK melonggarkannya. Uji ini gagal bila guard-nya dipindah ke rute.
    foreach ([$this->supervisor, $this->millManagement] as $user) {
        $this->actingAs($user, 'web')
            ->getJson(laporanEffluentPlantUrl('business-units/options'))
            ->assertStatus(403);
    }
});

/**
 * PENJAGA REGRESI KEBOCORAN LINTAS MILL — tempat ketiga dari tiga tempat
 * pelebaran screen-157.
 *
 * Melebarkan daftar peran middleware dan guardAccess() TANPA menambahkan
 * Operator ke cabang terikat-mill resolveBusinessUnit() menjatuhkannya ke
 * cabang ADMIN, tempat business_unit_id kiriman klien DIHORMATI. Akibatnya
 * bukan 403 melainkan 200 BERISI DATA MILL LAIN — kegagalan yang tidak
 * terlihat pada satu pun uji lain di berkas ini, karena setiap uji lainnya
 * memakai mill akunnya sendiri.
 *
 * Yang benar: nilai kiriman DIBUANG tanpa dibandingkan maupun divalidasi, dan
 * jawabannya 200 berisi data mill AKUN. Sengaja BUKAN 403 — 403 justru akan
 * menegaskan bahwa mill lain itu ada.
 */
it('skenario 21c — KRITIS: business_unit_id mill lain dari Operator DIBUANG, 200 berisi data mill akun', function () {
    $recordA = laporanEffluentPlantRecord($this->stationA, $this->day2, 'EP-A');
    laporanEffluentPlantSlot($recordA, '07:00', ['anaerobic_pond_1_ph' => 7.0]);

    $recordB = laporanEffluentPlantRecord($this->stationB, $this->day2, 'EP-B');
    laporanEffluentPlantSlot($recordB, '07:00', ['anaerobic_pond_1_ph' => 99.0]);

    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::EffluentPlant->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Effluent Plant Beta')
        ->create();

    $summary = $this->actingAs($this->operator, 'web')
        ->getJson(laporanEffluentPlantUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
            // Mill LAIN pada query — nilai inilah yang harus dibuang.
            'business_unit_id' => (string) $this->businessUnitB->id,
        ]));

    $summary->assertOk();
    expect($summary->json('business_unit.id'))->toBe((string) $this->businessUnitA->id);
    expect($summary->json('business_unit.name'))->toBe('Mill Alpha');
    // 99.0 dan 'EP-B' milik mill lain tidak boleh terbaca sama sekali.
    // toEqual, bukan toBe: JSON menyerahkan 7.0 sebagai int 7 — konvensi
    // yang sama dengan asersi metrik lain di berkas ini.
    expect($summary->json('metrics.anaerobic_pond_1_ph.max'))->toEqual(7.0);
    expect(collect($summary->json('by_unit'))->pluck('effluent_plant_id')->all())->toBe(['EP-A']);

    // Daftar periode mengikuti cakupan yang sama: periode mill lain tidak
    // pernah terdaftar, bahkan ketika mill-nya diminta secara eksplisit.
    $periods = $this->actingAs($this->operator, 'web')
        ->getJson(laporanEffluentPlantUrl('periods', [
            'business_unit_id' => (string) $this->businessUnitB->id,
        ]));

    $periods->assertOk();
    expect(collect($periods->json('data'))->pluck('id'))->toContain((string) $this->periodA->id);
    expect(collect($periods->json('data'))->pluck('id'))->not->toContain((string) $periodB->id);

    // Dan penjagaan lintas mill yang SUNGGUHAN tetap 403: line mill lain,
    // yang ditolak sebelum satu kueri record pun berjalan.
    $this->actingAs($this->operator, 'web')
        ->getJson(laporanEffluentPlantUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineB,
        ]))
        ->assertStatus(403);
});

it('skenario 21d — Operator tanpa mill: 422 GAGAL TERTUTUP pada ketiga rute data, 403 pada pemilih mill', function () {
    $operatorWithoutMill = User::factory()->role(UserRole::Operator)->create(['business_unit_id' => null]);

    // DULU 403 (penjaga peran menolaknya lebih dulu). Sekarang Operator
    // diterima, jadi ia sampai ke cabang terikat-mill dan berhenti di sana:
    // 422 tentang akun yang belum terhubung ke mill, dan TIDAK ADA pemilih
    // mill yang ditawarkan sebagai gantinya. Bentuknya sama persis dengan
    // akun Supervisor tanpa mill.
    $this->actingAs($operatorWithoutMill, 'web')
        ->getJson(laporanEffluentPlantUrl('periods'))
        ->assertStatus(422)
        ->assertJsonValidationErrors('business_unit_id');

    $this->actingAs($operatorWithoutMill, 'web')
        ->getJson(laporanEffluentPlantUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]))
        ->assertStatus(422);

    $this->actingAs($operatorWithoutMill, 'web')
        ->getJson(laporanEffluentPlantUrl('business-units/options'))
        ->assertStatus(403);
});

it('skenario 21b — tamu: 401 pada keempat rute', function () {
    foreach (['business-units/options', 'periods', 'summary', 'export'] as $path) {
        $this->getJson(laporanEffluentPlantUrl($path))->assertStatus(401);
    }
});

// =====================================================================
// Skenario 22, 24, 25: periode tertutup, baca-saja, cakupan daftar periode
// =====================================================================

it('skenario 22 — periode tertutup tetap dapat dibaca dan diekspor', function () {
    $closed = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::EffluentPlant->value)
        ->range(now()->subDays(40)->toDateString(), now()->subDays(31)->toDateString())
        ->closed()
        ->named('Periode Tertutup')
        ->create();

    $record = laporanEffluentPlantRecord($this->stationA, now()->subDays(35)->toDateString(), 'EP-9');
    laporanEffluentPlantSlot($record, '07:00', ['anaerobic_pond_1_ph' => 7.0]);

    $periods = $this->actingAs($this->supervisor, 'web')->getJson(laporanEffluentPlantUrl('periods'));
    $periods->assertOk();
    // STATUS TIDAK PERNAH MENYARING daftar ini.
    expect(collect($periods->json('data'))->pluck('status'))->toContain('closed');

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEffluentPlantUrl('summary', [
        'period_id' => (string) $closed->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();
    expect($summary->json('period.status'))->toBe('closed');
    expect($summary->json('has_data'))->toBeTrue();

    // Kunci periode mengatur PENULISAN data, bukan pembacaan laporan.
    $this->actingAs($this->supervisor, 'web')->get(laporanEffluentPlantUrl('export', [
        'period_id' => (string) $closed->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]))->assertOk();
});

it('skenario 24 — prefix /api/effluent-plant-reports TIDAK punya satu pun rute yang mengubah data', function () {
    // Laporan ini BACA SAJA secara rancangan. Rute yang dapat mengubah data
    // Effluent Plant tidak boleh pernah muncul di prefix ini — dan asersinya
    // harus atas DAFTAR RUTE, bukan atas satu respons, karena rute yang baru
    // ditambahkan tidak akan membuat satu pun uji lainnya gagal.
    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/effluent-plant-reports'));

    expect($routes)->toHaveCount(4);

    expect($routes->map(fn ($route) => $route->uri())->sort()->values()->all())->toBe([
        'api/effluent-plant-reports/business-units/options',
        'api/effluent-plant-reports/export',
        'api/effluent-plant-reports/periods',
        'api/effluent-plant-reports/summary',
    ]);

    foreach ($routes as $route) {
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $verb) {
            $this->assertNotContains($verb, $route->methods(), "rute {$route->uri()} tidak boleh menerima {$verb}");
        }
    }

    // Dan membaca laporannya tidak menggerakkan satu baris pun.
    $record = laporanEffluentPlantRecord($this->stationA, $this->day2, 'EP-1');
    laporanEffluentPlantSlot($record, '07:00', ['anaerobic_pond_1_ph' => 7.0]);

    $recordsBefore = EffluentPlantRecord::query()->count();
    $detailsBefore = EffluentPlantDetail::query()->count();

    $this->actingAs($this->supervisor, 'web')->getJson(laporanEffluentPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]))->assertOk();

    expect(EffluentPlantRecord::query()->count())->toBe($recordsBefore);
    expect(EffluentPlantDetail::query()->count())->toBe($detailsBefore);
});

it('skenario 25 — daftar periode hanya memuat periode yang mencakup Effluent Plant', function () {
    $semuaJenis = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(null)
        ->range(now()->subDays(60)->toDateString(), now()->subDays(51)->toDateString())
        ->open()
        ->named('Periode Semua Jenis')
        ->create();

    $kernelSaja = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelPlant->value)
        ->range(now()->subDays(80)->toDateString(), now()->subDays(71)->toDateString())
        ->open()
        ->named('Periode Kernel Plant Saja')
        ->create();

    $response = $this->actingAs($this->supervisor, 'web')->getJson(laporanEffluentPlantUrl('periods'));
    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain((string) $this->periodA->id);
    // Cakupan semua-stasiun dinyatakan lewat ADANYA satu baris
    // period_stations per jenis, jadi periode ini PUNYA baris effluent-plant.
    expect($ids)->toContain((string) $semuaJenis->id);
    expect($ids)->not->toContain((string) $kernelSaja->id);

    foreach ($response->json('data') as $option) {
        expect($option['station_type'])->toBe('effluent-plant');
        expect(array_keys($option))->toBe([
            'id', 'name', 'start_date', 'end_date', 'status', 'station_type', 'station_type_label',
        ]);
    }
});

// =====================================================================
// Skenario 26-28: keanggotaan periode dan penyebut per metrik
// =====================================================================

it('skenario 26 — rentang periode inklusif di kedua ujung, dan kedua ujung ada di berkas ekspor', function () {
    $first = laporanEffluentPlantRecord($this->stationA, $this->periodStart, 'EP-AWAL');
    $last = laporanEffluentPlantRecord($this->stationA, $this->periodEnd, 'EP-AKHIR');
    $outside = laporanEffluentPlantRecord($this->stationA, now()->subDays(10)->toDateString(), 'EP-LUAR');

    laporanEffluentPlantSlot($first, '07:00', ['anaerobic_pond_1_ph' => 6.0]);
    laporanEffluentPlantSlot($last, '07:00', ['anaerobic_pond_1_ph' => 8.0]);
    laporanEffluentPlantSlot($outside, '07:00', ['anaerobic_pond_1_ph' => 999.0]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEffluentPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    expect($summary->json('coverage.filled_slots'))->toBe(2);
    expect($summary->json('metrics.anaerobic_pond_1_ph.max'))->toEqual(8.0);
    expect(collect($summary->json('daily'))->pluck('date')->all())->toBe([$this->periodStart, $this->periodEnd]);

    $export = $this->actingAs($this->supervisor, 'web')->get(laporanEffluentPlantUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));
    $export->assertOk();

    $body = laporanEffluentPlantStreamed($export->baseResponse);

    expect($body)->toContain('EP-AWAL');
    expect($body)->toContain('EP-AKHIR');
    expect($body)->not->toContain('EP-LUAR');
});

it('skenario 27 — setiap metrik punya penyebutnya SENDIRI, dan metrik jarang tidak mengempis', function () {
    $record = laporanEffluentPlantRecord($this->stationA, $this->day2, 'EP-1');

    // 24 slot mengisi pH kolam; HANYA DUA mengisi COD laboratorium.
    foreach ($this->slots as $index => $slot) {
        $values = ['anaerobic_pond_1_ph' => 7.0];

        if ($index === 0) {
            $values['final_discharge_cod_mgl_lab'] = 300.0;
        }

        if ($index === 1) {
            $values['final_discharge_cod_mgl_lab'] = 400.0;
        }

        laporanEffluentPlantSlot($record, $slot, $values);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEffluentPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    // 350 (dibagi 2), BUKAN 29,17 (dibagi 24). Satu penyebut bersama akan
    // mengempiskan metrik yang jarang diisi dan tetap menghasilkan angka
    // yang terlihat sah.
    expect($summary->json('metrics.final_discharge_cod_mgl_lab.reading_count'))->toBe(2);
    expect($summary->json('metrics.final_discharge_cod_mgl_lab.avg'))->toEqual(350.0);
    expect($summary->json('metrics.anaerobic_pond_1_ph.reading_count'))->toBe(24);

    // DAN TIDAK ADA SATU PUN PENYEBUT BERSAMA di payload: penyebut hidup di
    // dalam tiap entri metrik, bukan sebagai satu angka di atasnya.
    expect($summary->json())->not->toHaveKey('reading_count');
    expect($summary->json('coverage'))->not->toHaveKey('reading_count');
});

it('skenario 28 — jumlah pembacaan menyertai angkanya di dalam entri metrik yang sama', function () {
    $record = laporanEffluentPlantRecord($this->stationA, $this->day2, 'EP-1');

    laporanEffluentPlantSlot($record, $this->slots[0], [
        'anaerobic_pond_1_ph' => 7.0,
        'cooling_pond_ph' => 7.5,
        'final_discharge_tss_mgl_lab' => 80.0,
    ]);
    laporanEffluentPlantSlot($record, $this->slots[1], ['anaerobic_pond_1_ph' => 7.4]);
    laporanEffluentPlantSlot($record, $this->slots[2], ['anaerobic_pond_1_ph' => 7.2]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEffluentPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    // TIGA penyebut yang BERBEDA pada payload yang sama — itulah yang
    // membuat asersi ini bermakna alih-alih selalu hijau.
    expect($summary->json('metrics.anaerobic_pond_1_ph.reading_count'))->toBe(3);
    expect($summary->json('metrics.cooling_pond_ph.reading_count'))->toBe(1);
    expect($summary->json('metrics.final_discharge_tss_mgl_lab.reading_count'))->toBe(1);
    expect($summary->json('metrics.chemical_consumed_kgl.reading_count'))->toBe(0);

    foreach ($summary->json('metrics') as $column => $metric) {
        expect(array_keys($metric))->toBe(['min', 'avg', 'max', 'reading_count', 'is_lab'], $column);
    }
});

// =====================================================================
// Skenario 29-33: yang sengaja TIDAK ada
// =====================================================================

it('skenario 29 — laporan TIDAK menyatakan kepatuhan lingkungan, di payload maupun di berkas ekspor', function () {
    $record = laporanEffluentPlantRecord($this->stationA, $this->day2, 'EP-1');

    // Keempat parameter buangan akhir terisi, dan ketiganya jauh di luar
    // baku mutu apa pun — laporan ini TETAP tidak menilainya.
    laporanEffluentPlantSlot($record, '07:00', [
        'final_discharge_ph' => 1.0,
        'final_discharge_bod_mgl_lab' => 25000.0,
        'final_discharge_cod_mgl_lab' => 60000.0,
        'final_discharge_tss_mgl_lab' => 9000.0,
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEffluentPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    $allKeys = laporanEffluentPlantAllKeys($summary->json());

    foreach (['compliance', 'is_compliant', 'compliant', 'patuh', 'quality_standard', 'baku_mutu', 'verdict'] as $key) {
        $this->assertNotContains($key, $allKeys, "payload tidak boleh punya kunci {$key}");
    }

    // Angkanya tetap diterbitkan apa adanya.
    expect($summary->json('metrics.final_discharge_ph.avg'))->toEqual(1.0);
    expect($summary->json('metrics.final_discharge_bod_mgl_lab.avg'))->toEqual(25000.0);

    $export = $this->actingAs($this->supervisor, 'web')->get(laporanEffluentPlantUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));
    $export->assertOk();

    $header = laporanEffluentPlantCsvLinesOf(laporanEffluentPlantStreamed($export->baseResponse))[0];

    // TIDAK ADA kolom kesimpulan di berkasnya — diasersi atas JUDUL KOLOM,
    // bukan atas isi berkas, karena teks bebas pengguna bisa memuat kata
    // apa pun.
    foreach (EffluentPlantReportService::EXPORT_HEADER as $column) {
        expect(strtolower($column))->not->toContain('patuh');
        expect(strtolower($column))->not->toContain('complian');
        expect(strtolower($column))->not->toContain('baku mutu');
    }

    expect(strtolower($header))->not->toContain('patuh');
});

it('skenario 30 — tidak ada penandaan nilai di luar batas: diasersi atas KETIADAAN KUNCI', function () {
    $record = laporanEffluentPlantRecord($this->stationA, $this->day2, 'EP-1');

    laporanEffluentPlantSlot($record, '07:00', ['anaerobic_pond_1_ph' => 0.1, 'cooling_pond_temp_c' => 200.0]);
    laporanEffluentPlantSlot($record, '08:00', ['anaerobic_pond_1_ph' => 13.9, 'cooling_pond_temp_c' => -40.0]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEffluentPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    // DIASERSI ATAS KUNCI, bukan atas frasa: `_note` pada payload ini justru
    // MEMUAT frasa "di luar batas", karena ia menyatakan ketiadaannya.
    $allKeys = laporanEffluentPlantAllKeys($summary->json());

    foreach ([
        'severity', 'flag', 'flags', 'is_out_of_range', 'out_of_range', 'warning',
        'color', 'threshold', 'breach', 'exceeds', 'target', 'targets', 'critical_limit',
    ] as $key) {
        $this->assertNotContains($key, $allKeys, "payload tidak boleh punya kunci {$key}");
    }

    // Effluent Plant tidak punya tabel master target operasional sama
    // sekali — tidak ada satu angka pun yang bisa dibandingkan terhadap
    // standar yang ditetapkan siapa pun.
    expect($summary->json())->not->toHaveKey('targets_without_metric');
    expect($summary->json())->not->toHaveKey('all_targets_measured');
    expect($summary->json('metrics.anaerobic_pond_1_ph.max'))->toEqual(13.9);
    expect($summary->json('metrics.cooling_pond_temp_c.min'))->toEqual(-40.0);
});

it('skenario 31 — status kategoris diterbitkan sebagai SEBARAN, bukan rata-rata, dan tidak masuk tren', function () {
    $record = laporanEffluentPlantRecord($this->stationA, $this->day2, 'EP-1');

    laporanEffluentPlantSlot($record, '07:00', [
        'biogas_flare_status' => 'on',
        'dosing_pump_1_status' => 'run',
        'sludge_dewatering_status' => 'stop',
        'raw_pome_feed_rate_m3h' => 40.0,
        'final_discharge_ph' => 7.0,
    ]);
    laporanEffluentPlantSlot($record, '08:00', [
        'biogas_flare_status' => 'fault',
        'dosing_pump_1_status' => 'stop',
        'sludge_dewatering_status' => 'run',
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEffluentPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    // Terendah, rata-rata dan tertinggi tidak punya arti untuk kolom
    // kategoris.
    foreach (array_keys(EffluentPlantReportService::STATUS_COLUMNS) as $column) {
        $this->assertArrayNotHasKey($column, $summary->json('metrics'), "metrics memuat {$column}");
        $this->assertArrayNotHasKey($column, $summary->json('daily_trend'), "daily_trend memuat {$column}");

        $dist = $summary->json("status_distribution.{$column}");

        expect(array_keys($dist))->toBe(['known_values', 'counts', 'not_recorded', 'recorded_slots'], $column);
    }

    // Dan tren harian memuat TEPAT dua seri.
    expect(array_keys($summary->json('daily_trend')))->toBe(['raw_pome_feed_rate_m3h', 'final_discharge_ph']);
});

it('skenario 32 — catatan perawatan dan temuan tidak pernah dirata-rata maupun digrafikkan', function () {
    $day2 = laporanEffluentPlantRecord($this->stationA, $this->day2, 'EP-1');
    $day3 = laporanEffluentPlantRecord($this->stationA, $this->day3, 'EP-1');

    laporanEffluentPlantSlot($day2, '07:00', ['remarks_maintenance_actions' => 'Ganti seal']);
    laporanEffluentPlantSlot($day2, '09:00', ['findings' => 'Busa tebal']);
    laporanEffluentPlantSlot($day3, '13:00', ['findings' => 'Busa tebal']);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEffluentPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    // Teks diterbitkan UTUH beserta tanggal dan slot waktu HH:MM — tidak
    // dikelompokkan, tidak dihitung, tidak dirata-rata.
    expect($summary->json('maintenance_remarks'))->toHaveCount(1);
    expect($summary->json('findings'))->toHaveCount(2);
    expect($summary->json('findings.0.time_slot'))->toBe('09:00');
    expect($summary->json('findings.1.time_slot'))->toBe('13:00');

    foreach (array_merge($summary->json('maintenance_remarks'), $summary->json('findings')) as $entry) {
        expect(array_keys($entry))->toBe(['date', 'time_slot', 'text']);
        expect($entry)->not->toHaveKey('slot_count');
        expect($entry)->not->toHaveKey('avg');
    }

    foreach (EffluentPlantReportService::FREE_TEXT_COLUMNS as $column) {
        expect($summary->json('metrics'))->not->toHaveKey($column);
        expect($summary->json('daily_trend'))->not->toHaveKey($column);
    }
});

it('skenario 33 — laporan tidak memuat blok downtime, dan berkas ekspornya tidak punya kolomnya', function () {
    $record = laporanEffluentPlantRecord($this->stationA, $this->day2, 'EP-1');
    laporanEffluentPlantSlot($record, '07:00', ['anaerobic_pond_1_ph' => 7.0]);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEffluentPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    // effluent_plant_details tidak punya satu pun kolom downtime, dan
    // ketiadaan itu DISENGAJA. Diasersi atas KUNCI: `_note` pada payload
    // justru memuat kata "downtime" karena ia menyatakan ketiadaannya.
    $allKeys = laporanEffluentPlantAllKeys($summary->json());

    foreach (['downtime', 'downtime_minutes', 'total_minutes', 'recorded_slot_count'] as $key) {
        $this->assertNotContains($key, $allKeys, "payload tidak boleh punya kunci {$key}");
    }

    expect($summary->json('_note'))->toContain('TIDAK ADA kunci downtime');

    $export = $this->actingAs($this->supervisor, 'web')->get(laporanEffluentPlantUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));
    $export->assertOk();

    $header = laporanEffluentPlantCsvLinesOf(laporanEffluentPlantStreamed($export->baseResponse))[0];

    expect(strtolower($header))->not->toContain('downtime');
    expect(strtolower($header))->not->toContain('berhenti');
});

// =====================================================================
// Skenario 34: ekspor
// =====================================================================

it('skenario 34 — ekspor Excel adalah .xlsx SUNGGUHAN, csv sama isinya, format pdf 422', function () {
    $record = laporanEffluentPlantRecord($this->stationA, $this->day2, 'EP-9');

    foreach (range(0, 2) as $index) {
        laporanEffluentPlantSlot($record, $this->slots[$index], [
            'anaerobic_pond_1_ph' => 7.0,
            'biogas_flare_status' => 'fault',
            'dosing_pump_1_status' => 'run',
            'sludge_dewatering_status' => 'stop',
            'remarks_maintenance_actions' => 'Ganti seal',
            'findings' => 'Busa tebal',
        ]);
    }

    $excel = $this->actingAs($this->supervisor, 'web')->get(laporanEffluentPlantUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'excel',
    ]));
    $excel->assertOk();
    expect($excel->headers->get('content-type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect($excel->headers->get('content-disposition'))->toContain('.xlsx');

    $xlsxBody = laporanEffluentPlantStreamed($excel->baseResponse);

    // XLSX SUNGGUHAN, bukan CSV bernama .xlsx (temuan audit 2026-10-04 #1):
    // berkas xlsx adalah arsip ZIP, jadi dua byte pertamanya 'PK' (0x504b).
    expect(bin2hex(substr($xlsxBody, 0, 2)))->toBe('504b');

    $csv = $this->actingAs($this->supervisor, 'web')->get(laporanEffluentPlantUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));
    $csv->assertOk();

    $lines = laporanEffluentPlantCsvLinesOf(laporanEffluentPlantStreamed($csv->baseResponse));

    // 1 header + 3 baris slot: SATU BARIS PER SLOT WAKTU.
    expect($lines)->toHaveCount(4);
    expect($lines[0])->toContain('Slot Waktu');
    expect($lines[0])->toContain('Unit Effluent Plant');
    expect($lines[0])->toContain('Catatan Perawatan');
    expect($lines[0])->toContain('Temuan');
    expect($lines[0])->toContain('BOD Buangan Akhir (mg/L)');
    // DUA PULUH TUJUH kolom: 7 konteks + Slot Waktu + 19 READING_FIELDS.
    expect(EffluentPlantReportService::EXPORT_HEADER)->toHaveCount(27);
    expect(count(EffluentPlantRecordService::READING_FIELDS))->toBe(19);

    foreach (array_slice($lines, 1) as $line) {
        // Kolom konteks DIULANG verbatim pada ketiga baris.
        expect($line)->toContain('Periode Effluent Plant Alpha');
        expect($line)->toContain('Mill Alpha');
        expect($line)->toContain($this->day2);
        expect($line)->toContain('EP-9');
        expect($line)->toContain('Busa tebal');
        // LABEL STATUS MENGIKUTI LAYAR DETAIL (ExportValue::option ->
        // Display::OPTION_LABELS): On/Off/Fault dan Run/Stop. Tech spec
        // menulis ExportValue::status(), tetapi pemeta itu memetakan status
        // RECORD (Tersimpan/Tersinkron) dan BUKAN ketiga kolom ini —
        // implementasinya identik dengan ekspor Data Browser stasiun yang
        // sama dan dengan layar Detail, sesuai konvensi "label ekspor ikut
        // layar Detail".
        expect($line)->toContain('Fault');
        expect($line)->toContain('Run');
        expect($line)->toContain('Stop');
    }

    expect($lines[1])->toContain('07:00');
    expect($lines[2])->toContain('08:00');
    expect($lines[3])->toContain('09:00');

    // format di luar csv|excel: 422 VALIDATION_ERROR, dan pengaliran tidak
    // pernah dimulai.
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanEffluentPlantUrl('export', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
            'format' => 'pdf',
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('format');

    // Tanpa parameter format: jatuh ke csv (default-nya di controller).
    $default = $this->actingAs($this->supervisor, 'web')->get(laporanEffluentPlantUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $default->assertOk();
    expect($default->headers->get('content-type'))->toContain('text/csv');
});

it('skenario 34b — slot yang seluruh kolomnya kosong TETAP menjadi baris ekspor dengan sel kosong', function () {
    $record = laporanEffluentPlantRecord($this->stationA, $this->day2, 'EP-KOSONG');
    laporanEffluentPlantSlot($record, '07:00');

    $export = $this->actingAs($this->supervisor, 'web')->get(laporanEffluentPlantUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $export->assertOk();

    $lines = laporanEffluentPlantCsvLinesOf(laporanEffluentPlantStreamed($export->baseResponse));

    // Bukan dibuang, dan BUKAN ditulis 0 — membuangnya akan membuat
    // berkasnya berselisih dengan angka cakupan yang laporan yang sama
    // terbitkan.
    expect($lines)->toHaveCount(2);
    expect($lines[1])->toContain('EP-KOSONG');
    expect($lines[1])->not->toContain(',0,');
});

it('skenario 34c — penyaringan line pada berkas ekspor memakai kolom record, bukan join ke stations', function () {
    $otherLine = ProductionLine::factory()->create(['business_unit_id' => $this->businessUnitA->id]);

    $onLineA = laporanEffluentPlantRecord($this->stationA, $this->day2, 'EP-LINE-A', [
        'production_line_id' => $this->lineA,
    ]);
    laporanEffluentPlantSlot($onLineA, '07:00', ['anaerobic_pond_1_ph' => 7.0]);

    $onOtherLine = laporanEffluentPlantRecord($this->stationA, $this->day2, 'EP-LINE-LAIN', [
        'production_line_id' => $otherLine->id,
    ]);
    laporanEffluentPlantSlot($onOtherLine, '07:00', ['anaerobic_pond_1_ph' => 99.0]);

    // Stasiunnya KINI terdaftar di line lain: join ke stations akan membaca
    // keadaan SEKARANG dan menulis ulang sejarah pembacaan yang sudah
    // dicatat.
    $this->stationA->forceFill(['production_line_id' => $otherLine->id])->save();

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanEffluentPlantUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();
    expect($summary->json('coverage.filled_slots'))->toBe(1);
    expect($summary->json('metrics.anaerobic_pond_1_ph.max'))->toEqual(7.0);

    $export = $this->actingAs($this->supervisor, 'web')->get(laporanEffluentPlantUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $export->assertOk();

    $body = laporanEffluentPlantStreamed($export->baseResponse);

    // BERKAS YANG MENCAMPUR LINE adalah kesalahan yang paling sulit
    // dibantah setelah terkirim.
    expect($body)->toContain('EP-LINE-A');
    expect($body)->not->toContain('EP-LINE-LAIN');
});
