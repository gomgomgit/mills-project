<?php

/**
 * ReportAuditFix20261004Test — regresi temuan audit 2026-10-04 di area
 * LAPORAN (selain #1 ekspor xlsx → ExportXlsxTest, #2 Laporan Manajemen →
 * tests/Feature/Livewire/ManagementReportTest.php dan
 * tests/Unit/Services/ManagementReportServiceTest.php).
 *
 * Setiap tes menyebut nomor temuannya. Asersi tampilan memeriksa HTML/CSS
 * HASIL RENDER, bukan sumber Blade.
 */

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Livewire\Dashboard\LaporanBoilerRoom;
use App\Livewire\Dashboard\LaporanCagesTrack;
use App\Livewire\Dashboard\LaporanClarification;
use App\Livewire\Dashboard\LaporanSterilizer;
use App\Livewire\Dashboard\LaporanStorageTank;
use App\Livewire\Dashboard\LaporanWeighbridge;
use App\Models\BoilerRoomDetail;
use App\Models\BoilerRoomRecord;
use App\Models\BusinessUnit;
use App\Models\CagesTippedTime;
use App\Models\CagesTrackRecord;
use App\Models\ClarificationDetail;
use App\Models\ClarificationRecord;
use App\Models\Period;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\SterilizerDetail;
use App\Models\SterilizerRecord;
use App\Models\StorageTankDetail;
use App\Models\StorageTankRecord;
use App\Models\User;
use App\Models\WeighbridgeRecord;
use App\Services\BoilerRoomRecordService;
use App\Services\GradingRecordService;
use App\Services\SterilizerReportService;
use App\Support\ChartAxis;
use App\Support\ReportPeriodDays;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    // "Hari ini" = 04 Okt 2026 siang WIB — tanggal audit.
    Carbon::setTestNow(Carbon::parse('2026-10-04 12:00:00', 'Asia/Jakarta'));

    $this->mill = BusinessUnit::factory()->create(['name' => 'Mill Audit']);
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->mill)->create();
});

afterEach(function () {
    Carbon::setTestNow();
});

function auditBoilerFullDay(Station $station, string $date): void
{
    $record = BoilerRoomRecord::factory()->forStation($station)->onDate($date)->create(['boiler_room_id' => 'BLR-1']);

    foreach (BoilerRoomRecordService::canonicalTimeSlots() as $slot) {
        BoilerRoomDetail::factory()->forRecord($record)->create(['time_slot' => $slot, 'steam_pressure_bar' => 20.0]);
    }
}

// =====================================================================
// #3 — kelengkapan periode yang masih berjalan tidak menghitung hari depan
// =====================================================================
it('#3 ReportPeriodDays: periode berjalan dihitung sampai hari ini, periode selesai utuh, periode belum mulai 0', function () {
    $running = Period::factory()->forBusinessUnit($this->mill)->stationType('boiler-room')->range('2026-10-01', '2026-10-09')->open()->create();
    $done = Period::factory()->forBusinessUnit($this->mill)->stationType('boiler-room')->range('2026-09-01', '2026-09-30')->open()->create();
    $future = Period::factory()->forBusinessUnit($this->mill)->stationType('boiler-room')->range('2026-11-01', '2026-11-30')->open()->create();

    expect(ReportPeriodDays::inPeriod($running))->toBe(9);
    expect(ReportPeriodDays::counted($running))->toBe(4);
    expect(ReportPeriodDays::isRunning($running))->toBeTrue();

    expect(ReportPeriodDays::counted($done))->toBe(30);
    expect(ReportPeriodDays::isRunning($done))->toBeFalse();

    expect(ReportPeriodDays::counted($future))->toBe(0);
});

it('#3 Boiler Room: 4 hari pertama periode 01–09 Okt terisi penuh pada 04 Okt = 100%, bukan ~44%, dan layar menyebut "sampai hari ini"', function () {
    $station = Station::factory()->forBusinessUnit($this->mill)->boilerRoom()->create();
    $period = Period::factory()->forBusinessUnit($this->mill)->stationType('boiler-room')
        ->range('2026-10-01', '2026-10-09')->named('fdsf')->open()->create();

    foreach (['2026-10-01', '2026-10-02', '2026-10-03', '2026-10-04'] as $date) {
        auditBoilerFullDay($station, $date);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson('/api/boiler-room-reports/summary?'.http_build_query([
        'period_id' => $period->id,
        'production_line_id' => $station->production_line_id,
    ]))->assertOk()->json();

    $slots = count(BoilerRoomRecordService::canonicalTimeSlots());
    expect($summary['coverage']['filled_slots'])->toBe(4 * $slots);
    expect($summary['coverage']['expected_slots'])->toBe(4 * $slots);
    expect((float) $summary['coverage']['coverage_percent'])->toBe(100.0);
    expect($summary['coverage']['days_in_period'])->toBe(9);
    expect($summary['coverage']['days_counted'])->toBe(4);
    expect($summary['coverage']['period_running'])->toBeTrue();

    $html = Livewire::actingAs($this->supervisor)->test(LaporanBoilerRoom::class)
        ->set('productionLineId', (string) $station->production_line_id)
        ->set('periodId', (string) $period->id)
        ->html();

    expect($html)->toContain('data-testid="period-running-note"')
        ->toContain('Dihitung sampai hari ini, periode masih berjalan')
        ->toContain('100,0%');
});

it('#3 Weighbridge: persen hari bertrip memakai hari yang sudah lewat sebagai penyebut', function () {
    $station = Station::factory()->forBusinessUnit($this->mill)->weighbridge()->create();
    $period = Period::factory()->forBusinessUnit($this->mill)->stationType('weighbridge')
        ->range('2026-10-01', '2026-10-09')->open()->create();

    WeighbridgeRecord::factory()->forStation($station)->arrivedAt('2026-10-01 08:00:00')->create();
    WeighbridgeRecord::factory()->forStation($station)->arrivedAt('2026-10-03 08:00:00')->create();

    $html = Livewire::actingAs($this->supervisor)->test(LaporanWeighbridge::class)
        ->set('productionLineId', (string) $station->production_line_id)
        ->set('periodId', (string) $period->id)
        ->html();

    // 2 dari 4 hari yang sudah lewat = 50,0% (dulu 2 dari 9 = 22,2%).
    expect($html)->toMatch('/data-testid="days-with-trip-percent">\s*50,0%/')
        ->toContain('data-testid="period-running-note"');
});

// =====================================================================
// #8 — CSV laporan stasiun: konteks Periode/Mill/Line, judul Indonesia,
//      tanpa enum mentah, slot HH:MM
// =====================================================================
function auditCsvRows(string $body): array
{
    $lines = array_values(array_filter(explode("\n", trim($body)), fn ($line) => $line !== ''));

    return array_map(fn ($line) => str_getcsv($line, ',', '"', '\\'), $lines);
}

it('#8 keenam CSV laporan stasiun membawa Periode/Mill/Production Line di tiap baris, judul Indonesia, tanpa enum mentah, slot HH:MM', function (string $stationFactory, string $stationType, string $apiPrefix, Closure $seed) {
    $station = Station::factory()->forBusinessUnit($this->mill)->{$stationFactory}()->create();
    $period = Period::factory()->forBusinessUnit($this->mill)->stationType($stationType)
        ->range('2026-09-01', '2026-09-30')->named('Periode Audit')->open()->create();
    $lineName = (string) ProductionLine::query()->whereKey($station->production_line_id)->value('name');

    $seed($station);

    $response = $this->actingAs($this->supervisor, 'web')->get("/api/{$apiPrefix}/export?".http_build_query([
        'period_id' => $period->id,
        'production_line_id' => $station->production_line_id,
        'format' => 'csv',
    ]));
    $response->assertOk();

    $body = $response->streamedContent();
    $rows = auditCsvRows($body);

    expect(count($rows))->toBeGreaterThan(1);
    expect(array_slice($rows[0], 0, 3))->toBe(['Periode', 'Mill', 'Production Line']);

    foreach (array_slice($rows, 1) as $row) {
        expect(array_slice($row, 0, 3))->toBe(['Periode Audit', 'Mill Audit', $lineName]);
    }

    // Tidak ada judul Inggris peninggalan ekspor Sterilizer lama.
    foreach (['Sterilizer ID', 'Date', 'Note', 'Duration (Minutes)', 'Checked By'] as $english) {
        expect($rows[0])->not->toContain($english);
    }

    // Tidak ada nilai enum mentah di sel mana pun.
    foreach (array_slice($rows, 1) as $row) {
        foreach (['synced', 'saved', 'draft_paused', 'draft_ongoing', 'open_1_2', 'open_1_4', 'y', 'n'] as $raw) {
            expect($row)->not->toContain($raw);
        }
        // Jam/slot tidak pernah berdetik ("07:00:00").
        foreach ($row as $cell) {
            expect(preg_match('/(^|\s)\d{2}:\d{2}:\d{2}$/', (string) $cell))->toBe(0);
        }
    }
})->with([
    'weighbridge' => ['weighbridge', 'weighbridge', 'weighbridge-reports', function (Station $station) {
        WeighbridgeRecord::factory()->forStation($station)->arrivedAt('2026-09-02 07:10:00')->status(RecordStatus::Synced)->create();
    }],
    'sterilizer' => ['sterilizer', 'sterilizer', 'sterilizer-reports', function (Station $station) {
        $record = SterilizerRecord::factory()->forStation($station)->onDate('2026-09-02')->status(RecordStatus::Synced)->create();
        SterilizerDetail::factory()->forRecord($record)->create(['close_door_time' => '07:00:00', 'open_door_time' => '08:30:00', 'checked_by_spv' => true]);
    }],
    'cages-track' => ['cagesTrack', 'cages-track', 'cages-track-reports', function (Station $station) {
        $record = CagesTrackRecord::factory()->forStation($station)->onDate('2026-09-02')->create();
        CagesTippedTime::factory()->forRecord($record)->create(['tipped_hour' => 7]);
    }],
    'boiler-room' => ['boilerRoom', 'boiler-room', 'boiler-room-reports', function (Station $station) {
        $record = BoilerRoomRecord::factory()->forStation($station)->onDate('2026-09-02')->status(RecordStatus::Synced)->create(['boiler_room_id' => 'BLR-1']);
        BoilerRoomDetail::factory()->forRecord($record)->create(['time_slot' => '07:00:00', 'steam_pressure_bar' => 20.0, 'blowdown_executed' => 'y', 'sootblowing_executed' => 'n']);
    }],
    'clarification' => ['clarification', 'clarification', 'clarification-reports', function (Station $station) {
        $record = ClarificationRecord::factory()->forStation($station)->onDate('2026-09-02')->create();
        ClarificationDetail::factory()->forRecord($record)->timeSlot('07:00:00')->filled()->create();
    }],
    'storage-tank' => ['storageTank', 'storage-tank', 'storage-tank-reports', function (Station $station) {
        $record = StorageTankRecord::factory()->forStation($station)->onDate('2026-09-02')->create();
        StorageTankDetail::factory()->forRecord($record)->timeSlot('07:00:00')->filled()->create(['steam_heating_valve_status' => 'open_1_2']);
    }],
]);

it('#8 CSV Sterilizer: judul Bahasa Indonesia, status & "Diperiksa SPV" berlabel', function () {
    $station = Station::factory()->forBusinessUnit($this->mill)->sterilizer()->create();
    $period = Period::factory()->forBusinessUnit($this->mill)->stationType('sterilizer')->range('2026-09-01', '2026-09-30')->open()->create();
    $record = SterilizerRecord::factory()->forStation($station)->onDate('2026-09-02')->status(RecordStatus::Synced)->create();
    SterilizerDetail::factory()->forRecord($record)->create(['close_door_time' => '07:00:00', 'checked_by_spv' => true]);

    $rows = auditCsvRows($this->actingAs($this->supervisor, 'web')->get('/api/sterilizer-reports/export?'.http_build_query([
        'period_id' => $period->id, 'production_line_id' => $station->production_line_id, 'format' => 'csv',
    ]))->streamedContent());

    expect($rows[0])->toBe(SterilizerReportService::EXPORT_HEADER);
    $col = array_flip($rows[0]);
    expect($rows[1][$col['Status']])->toBe('Tersinkron');
    expect($rows[1][$col['Jam Tutup Pintu']])->toBe('07:00');
    expect($rows[1][$col['Diperiksa SPV']])->toBe('Ya');
});

it('#8 CSV Data Browser Weighbridge: label layar Detail, Checked/Acknowledged By, status Indonesia, jam tanpa detik', function () {
    $station = Station::factory()->forBusinessUnit($this->mill)->weighbridge()->create();
    WeighbridgeRecord::factory()->forStation($station)->arrivedAt('2026-09-02 07:10:45')->ofType('receive')->status(RecordStatus::Synced)
        ->create(['checked_by' => $this->supervisor->id]);

    $rows = auditCsvRows($this->actingAs($this->supervisor, 'web')->get('/api/weighbridge-records/export?'.http_build_query([
        'date_from' => '2026-09-01', 'date_to' => '2026-09-30', 'format' => 'csv',
    ]))->streamedContent());

    expect($rows[0])->toContain('No. WB Card')->toContain('Tanggal & Waktu')->toContain('Checked By')->toContain('Acknowledged By')
        ->not->toContain('WB Card Number')->not->toContain('Record Datetime');
    $col = array_flip($rows[0]);
    expect($rows[1][$col['Status']])->toBe('Tersinkron');
    expect($rows[1][$col['Tanggal & Waktu']])->toBe('2026-09-02 07:10');
    expect($rows[1][$col['Tipe Weighbridge']])->toBe('Receive');
    expect($rows[1][$col['Checked By']])->toBe($this->supervisor->name);
});

it('#8 CSV Data Browser Grading tidak lagi punya kolom Checked By yang selalu kosong', function () {
    $response = app(GradingRecordService::class);
    $this->actingAs($this->supervisor);

    ob_start();
    $response->export([], 'csv')->sendContent();
    $rows = auditCsvRows((string) ob_get_clean());

    expect($rows[0])->not->toContain('Checked By')->toContain('Acknowledged By');
});

// =====================================================================
// #9 — keadaan kosong: penyebut 0 → "—", bukan 0
// =====================================================================
it('#9 Cages & Tracks tanpa data: rata-rata per hari "–" dan bukan "Sama banyak" untuk 0 vs 0', function () {
    $station = Station::factory()->forBusinessUnit($this->mill)->cagesTrack()->create();
    $period = Period::factory()->forBusinessUnit($this->mill)->stationType('cages-track')->range('2026-09-01', '2026-09-30')->open()->create();

    $html = Livewire::actingAs($this->supervisor)->test(LaporanCagesTrack::class)
        ->set('productionLineId', (string) $station->production_line_id)
        ->set('periodId', (string) $period->id)
        ->html();

    expect($html)->toContain('data-testid="kpi-avg-per-day-empty"')
        ->not->toContain('Sama banyak dengan lori yang ditumpahkan')
        ->not->toContain('0 <span>lori/hari</span>');
});

it('#9 Boiler Room tanpa unit tercatat: kelengkapan "—", bukan "0 dari 0 slot · 0,0%"', function () {
    $station = Station::factory()->forBusinessUnit($this->mill)->boilerRoom()->create();
    $period = Period::factory()->forBusinessUnit($this->mill)->stationType('boiler-room')->range('2026-09-01', '2026-09-30')->open()->create();

    $html = Livewire::actingAs($this->supervisor)->test(LaporanBoilerRoom::class)
        ->set('productionLineId', (string) $station->production_line_id)
        ->set('periodId', (string) $period->id)
        ->html();

    expect($html)->toContain('data-testid="coverage-no-expected"')
        ->toMatch('/data-testid="coverage-percent">\s*—/')
        ->not->toContain('0,0%');
});

// =====================================================================
// #10 — salinan laporan
// =====================================================================
it('#10 klausa "bukan …" penyebut rata-rata Weighbridge hanya muncul bila angkanya berbeda; hero menyebut line', function () {
    $station = Station::factory()->forBusinessUnit($this->mill)->weighbridge()->create();
    $period = Period::factory()->forBusinessUnit($this->mill)->stationType('weighbridge')->range('2026-09-01', '2026-09-30')->open()->create();
    WeighbridgeRecord::factory()->forStation($station)->arrivedAt('2026-09-02 07:10:00')->ofType('receive')->count(2)->create();

    $html = Livewire::actingAs($this->supervisor)->test(LaporanWeighbridge::class)
        ->set('productionLineId', (string) $station->production_line_id)
        ->set('periodId', (string) $period->id)
        ->html();

    expect($html)->toMatch('/data-testid="receive-avg-denominator">2<\/b> trip<\/p>/')
        ->not->toContain('trip, bukan 2');
});

it('#10 hero kelima laporan lain menyebut nama production line', function (string $component, string $stationFactory, string $stationType) {
    $station = Station::factory()->forBusinessUnit($this->mill)->{$stationFactory}()->create();
    $period = Period::factory()->forBusinessUnit($this->mill)->stationType($stationType)->range('2026-09-01', '2026-09-30')->open()->create();
    $lineName = (string) ProductionLine::query()->whereKey($station->production_line_id)->value('name');

    $html = Livewire::actingAs($this->supervisor)->test($component)
        ->set('productionLineId', (string) $station->production_line_id)
        ->set('periodId', (string) $period->id)
        ->html();

    preg_match('/<p class="md-hero__subtitle">(.*?)<\/p>/s', $html, $m);
    expect($m[1] ?? '')->toContain($lineName);
})->with([
    'sterilizer' => [LaporanSterilizer::class, 'sterilizer', 'sterilizer'],
    'cages-track' => [LaporanCagesTrack::class, 'cagesTrack', 'cages-track'],
    'boiler-room' => [LaporanBoilerRoom::class, 'boilerRoom', 'boiler-room'],
    'clarification' => [LaporanClarification::class, 'clarification', 'clarification'],
    'storage-tank' => [LaporanStorageTank::class, 'storageTank', 'storage-tank'],
]);

// =====================================================================
// #4 #5 #7 #10 — CSS hasil render (bukan sumber): setiap kelas yang dipakai
// punya aturan, dan aturan perbaikannya ada.
// =====================================================================
it('#4/#5/#7/#10 report-styles hasil render memuat aturan ukuran ikon, angka KPI penuh, petunjuk gulir, dan grid stasiun auto-fill', function () {
    $css = view('dashboard.partials.report-styles')->render();

    expect($css)->toContain('.md-threshold svg { width: 18px; height: 18px;')
        ->toContain('.md-kpi__value > span[data-testid] { font-size: inherit;')
        ->toContain('.md-scrollhint {')
        ->toContain('.md-trendchart--days .md-trendchart__col {')
        ->toContain('.md-budget__note {')
        ->toContain('repeat(auto-fill, minmax(150px, 1fr))')
        ->not->toContain('.station-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; max-width: 640px; }');
});

// =====================================================================
// #6 — sumbu tegak angka bulat
// =====================================================================
it('#6 ChartAxis menghasilkan langkah bulat berjarak sama', function () {
    $wb = ChartAxis::nice(0, 1181176 * 1.0);
    expect($wb['ticks'])->toBe([0.0, 500000.0, 1000000.0, 1500000.0]);
    expect($wb['decimals'])->toBe(0);

    $temp = ChartAxis::nice(89.6, 96.4);
    expect($temp['ticks'])->toBe([88.0, 90.0, 92.0, 94.0, 96.0, 98.0]);

    $small = ChartAxis::nice(1.02, 1.37);
    expect($small['decimals'])->toBe(1);
    $steps = array_map(fn ($a, $b) => round($b - $a, 6), array_slice($small['ticks'], 0, -1), array_slice($small['ticks'], 1));
    expect(array_unique($steps))->toHaveCount(1);
});

it('#6 grafik tren Weighbridge memakai label sumbu bulat, bukan 295.294', function () {
    $station = Station::factory()->forBusinessUnit($this->mill)->weighbridge()->create();
    $period = Period::factory()->forBusinessUnit($this->mill)->stationType('weighbridge')->range('2026-09-01', '2026-09-30')->open()->create();
    WeighbridgeRecord::factory()->forStation($station)->arrivedAt('2026-09-02 07:10:00')->ofType('receive')->create(['gross_weight' => 1056000, 'tare_weight' => 1000, 'net_weight' => 1055000]);
    WeighbridgeRecord::factory()->forStation($station)->arrivedAt('2026-09-03 07:10:00')->ofType('receive')->create(['gross_weight' => 901000, 'tare_weight' => 1000, 'net_weight' => 900000]);

    $html = Livewire::actingAs($this->supervisor)->test(LaporanWeighbridge::class)
        ->set('productionLineId', (string) $station->production_line_id)
        ->set('periodId', (string) $period->id)
        ->html();

    preg_match_all('/class="md-lc__ytick"[^>]*>([^<]+)</', $html, $m);
    expect($m[1])->toBe(['0', '500.000', '1.000.000', '1.500.000']);
});
