<?php

/**
 * ExportXlsxTest — temuan audit 2026-10-04 #1 (KRITIS): setiap "Ekspor
 * Excel" (6 laporan stasiun, Laporan Manajemen, 18 ekspor Data Browser)
 * mengirim byte CSV dengan nama file .xlsx dan content type spreadsheetml,
 * sehingga Excel menolak membukanya.
 *
 * Tes ini memanggil endpoint ekspor SUNGGUHAN lewat HTTP (route ->
 * middleware -> controller -> service -> App\Support\SheetWriter) dengan
 * format=excel dan memeriksa bahwa body-nya paket xlsx yang sah: diawali
 * "PK", berisi xl/worksheets/sheet1.xml (dan bagian wajib lainnya — lihat
 * xlsxRows() di tests/Pest.php), header di baris 1, angka sebagai sel
 * ANGKA. Format csv tetap CSV biasa.
 *
 * Ke-18 ekspor Data Browser juga diperiksa di masing-masing
 * tests/Unit/Services/*RecordServiceTest.php ("returns a StreamedResponse
 * ... for csv and excel formats"); di sini satu wakil lewat HTTP.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\Station;
use App\Models\User;
use App\Models\WeighbridgeRecord;
use App\Support\SheetWriter;

function exportXlsxBody($response): string
{
    $base = $response->baseResponse;
    ob_start();
    $base->sendContent();

    return (string) ob_get_clean();
}

beforeEach(function () {
    $this->mill = BusinessUnit::factory()->create(['name' => 'Mill Ekspor']);
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->mill)->create();
});

it('SheetWriter menulis paket xlsx sah: header tebal, angka sebagai sel angka, teks berawalan nol tetap teks', function () {
    $path = tempnam(sys_get_temp_dir(), 'sheetwriter-test-');
    $sheet = SheetWriter::open('excel', $path);
    $sheet->row(['Nama', 'Jumlah', 'Kode', 'Catatan']);
    $sheet->row(['Truk <A> & "B"', 12, '007', null]);
    $sheet->row(['Desimal', '1500.25', '2026-10-04', 'ok']);
    $sheet->close();

    $bytes = file_get_contents($path);
    @unlink($path);

    expect($bytes)->toStartWith('PK');
    expect(xlsxRows($bytes))->toBe([
        ['Nama', 'Jumlah', 'Kode', 'Catatan'],
        ['Truk <A> & "B"', 12, '007'],
        ['Desimal', 1500.25, '2026-10-04', 'ok'],
    ]);

    $zip = new ZipArchive;
    $zipPath = tempnam(sys_get_temp_dir(), 'sheetwriter-zip-');
    file_put_contents($zipPath, $bytes);
    $zip->open($zipPath);
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    @unlink($zipPath);

    // Baris 1 bergaya tebal (s="1"); angka ditulis tanpa t="inlineStr".
    expect($sheetXml)->toContain('<c r="A1" s="1" t="inlineStr">');
    expect($sheetXml)->toContain('<c r="B2"><v>12</v></c>');
    expect($sheetXml)->toContain('<c r="C2" t="inlineStr">');
});

it('ekspor laporan stasiun format=excel adalah xlsx sungguhan untuk keenam laporan', function (string $stationFactory, string $stationType, string $apiPrefix) {
    $station = Station::factory()->forBusinessUnit($this->mill)->{$stationFactory}()->create();
    $period = Period::factory()->forBusinessUnit($this->mill)->stationType($stationType)
        ->range('2026-09-01', '2026-09-30')->named('Periode Ekspor')->open()->create();

    if ($stationType === 'weighbridge') {
        WeighbridgeRecord::factory()->forStation($station)->arrivedAt('2026-09-02 07:10:00')->create();
    }

    $query = http_build_query([
        'period_id' => $period->id,
        'production_line_id' => $station->production_line_id,
        'format' => 'excel',
    ]);

    $response = $this->actingAs($this->supervisor, 'web')->get("/api/{$apiPrefix}/export?{$query}");
    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe(SheetWriter::XLSX_CONTENT_TYPE);
    expect($response->headers->get('Content-Disposition'))->toContain('.xlsx');

    $body = exportXlsxBody($response);
    expect($body)->toStartWith('PK');

    $rows = xlsxRows($body);
    expect($rows)->not->toBeEmpty();
    // Baris 1 = judul kolom (teks).
    expect($rows[0][0])->toBeString()->not->toBe('');

    // csv tetap CSV biasa.
    $csv = $this->actingAs($this->supervisor, 'web')->get('/api/'.$apiPrefix.'/export?'.str_replace('format=excel', 'format=csv', $query));
    $csv->assertOk();
    expect(exportXlsxBody($csv))->not->toStartWith('PK');
})->with([
    'weighbridge' => ['weighbridge', 'weighbridge', 'weighbridge-reports'],
    'sterilizer' => ['sterilizer', 'sterilizer', 'sterilizer-reports'],
    'cages-track' => ['cagesTrack', 'cages-track', 'cages-track-reports'],
    'boiler-room' => ['boilerRoom', 'boiler-room', 'boiler-room-reports'],
    'clarification' => ['clarification', 'clarification', 'clarification-reports'],
    'storage-tank' => ['storageTank', 'storage-tank', 'storage-tank-reports'],
]);

it('ekspor Data Browser format=excel adalah xlsx sungguhan (wakil: weighbridge lewat HTTP)', function () {
    $station = Station::factory()->forBusinessUnit($this->mill)->weighbridge()->create();
    WeighbridgeRecord::factory()->forStation($station)->arrivedAt('2026-09-02 07:10:00')->count(2)->create();

    $response = $this->actingAs($this->supervisor, 'web')->get('/api/weighbridge-records/export?'.http_build_query([
        'date_from' => '2026-09-01',
        'date_to' => '2026-09-30',
        'format' => 'excel',
    ]));

    $response->assertOk();
    $body = exportXlsxBody($response);
    expect($body)->toStartWith('PK');
    expect(xlsxRows($body))->toHaveCount(3);
});

it('ekspor Laporan Manajemen format=excel adalah xlsx sungguhan', function () {
    $millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->mill)->create();
    $station = Station::factory()->forBusinessUnit($this->mill)->weighbridge()->create();
    WeighbridgeRecord::factory()->forStation($station)->arrivedAt('2026-09-02 07:10:00')->create();

    $response = $this->actingAs($millManagement, 'web')->get('/api/reports/management-summary/export?'.http_build_query([
        'date_from' => '2026-09-01',
        'date_to' => '2026-09-05',
        'production_line_id' => $station->production_line_id,
        'format' => 'excel',
    ]));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('.xlsx');
    $body = exportXlsxBody($response);
    expect($body)->toStartWith('PK');
    expect(count(xlsxRows($body)))->toBeGreaterThan(1);
});
