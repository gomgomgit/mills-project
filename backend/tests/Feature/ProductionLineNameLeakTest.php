<?php

/**
 * ProductionLineNameLeakTest — nama Production Line milik mill lain tidak
 * boleh terbit dari satu pun laporan (2026-10-07).
 *
 * ────────────────────────────────────────────────────────────────────────
 * DEFEK YANG DIJAGANYA, DAN MENGAPA IA LOLOS SELAMA INI
 * ────────────────────────────────────────────────────────────────────────
 * `productionLineInfo()` mencari line dengan `find($id)` TANPA klausa
 * `business_unit_id`. Akibatnya `GET /summary?production_line_id=<line mill
 * lain>` menjawab 200 berisi id DAN NAMA MANUSIAWI line yang pemanggil tidak
 * berhak melihatnya.
 *
 * Angkanya aman dengan sendirinya — `scopeToProductionLine()` menyaring ke
 * nol baris dan query tetap terkurung pada mill periode. Yang TIDAK aman
 * adalah blok `production_line` itu sendiri. Dan ia merusak justru alasan
 * line milik mill lain DIABAIKAN alih-alih ditolak 403: penolakan ditahan
 * supaya tidak memastikan line itu ada, lalu blok ini memastikannya juga,
 * lengkap dengan namanya.
 *
 * BENTUK TANPA SCOPE ITU ADA IDENTIK DI SEBELAS REPORT SERVICE, dan seluruh
 * suite 5.263 uji TETAP HIJAU sesudah ia diperbaiki di kesebelasnya — tidak
 * satu uji pun menyadari penghapusannya. Itulah ukuran celahnya, dan sebab
 * berkas ini ada.
 *
 * ────────────────────────────────────────────────────────────────────────
 * DUA LAPIS, DAN KEDUANYA PERLU
 * ────────────────────────────────────────────────────────────────────────
 * 1. PERILAKU — setiap service dipanggil dengan id line mill LAIN dan
 *    diasersikan tidak menerbitkan namanya. Menangkap klausa yang ada tetapi
 *    salah kolom, salah variabel, atau tertimpa.
 * 2. PEMINDAI STATIK — menyisir `app/` untuk bentuk `ProductionLine::query()
 *    ->find(` tanpa `where('business_unit_id'` di dekatnya. Menangkap
 *    SERVICE BARU yang ditambahkan dengan bentuk lama, yang lapis perilaku
 *    tidak dapat lihat karena ia hanya tahu service yang namanya sudah
 *    terdaftar di bawah.
 *
 * Satu uji parametrik atas keduabelas service, bukan dua belas salinan: yang
 * diuji sama persis pada semuanya, dan dua belas salinan berarti sebelas
 * tempat untuk lupa memperbaruinya.
 */

use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Corporate;
use App\Models\ProductionLine;
use App\Services\BoilerRoomReportService;
use App\Services\CagesTrackReportService;
use App\Services\ClarificationReportService;
use App\Services\DepricarpingReportService;
use App\Services\GradingReportService;
use App\Services\KernelPlantReportService;
use App\Services\PressingReportService;
use App\Services\StationReportService;
use App\Services\SterilizerReportService;
use App\Services\StorageTankReportService;
use App\Services\ThreshingReportService;
use App\Services\WeighbridgeReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Setiap service yang menerbitkan blok `production_line`. KernelPlant ada di
 * daftar ini meski ia yang pertama diperbaiki — perbaikan pertama sama
 * mungkinnya untuk dibatalkan seperti yang kesebelas.
 *
 * @return list<class-string>
 */
function leakGuardedServices(): array
{
    return [
        BoilerRoomReportService::class,
        CagesTrackReportService::class,
        ClarificationReportService::class,
        DepricarpingReportService::class,
        GradingReportService::class,
        KernelPlantReportService::class,
        PressingReportService::class,
        SterilizerReportService::class,
        StorageTankReportService::class,
        StationReportService::class,
        ThreshingReportService::class,
        WeighbridgeReportService::class,
    ];
}

/** Satu mill baru beserta induk-induknya. */
function leakMill(string $name): BusinessUnit
{
    return BusinessUnit::factory()->create([
        'name' => $name,
        'company_id' => Company::factory()->create([
            'corporate_id' => Corporate::factory()->create()->id,
        ])->id,
    ]);
}

it('tidak satu pun report service menerbitkan nama Production Line milik mill lain', function (string $serviceClass) {
    $ownMill = leakMill('Mill Yang Boleh Dilihat');
    $otherMill = leakMill('Mill Orang Lain');

    $foreignLine = ProductionLine::factory()->create([
        'business_unit_id' => $otherMill->id,
        'name' => 'RAHASIA Line Mill Orang Lain',
    ]);

    $service = app($serviceClass);

    $method = new ReflectionMethod($serviceClass, 'productionLineInfo');
    $method->setAccessible(true);

    // Dipanggil seperti pemanggil sungguhan memanggilnya: id line dari
    // pemanggil (yang boleh saja milik mill lain) beserta mill yang sedang
    // dilaporkan.
    $result = $method->invoke($service, (string) $foreignLine->id, (string) $ownMill->id);

    $encoded = json_encode($result, JSON_UNESCAPED_UNICODE);

    expect($encoded)->not->toContain('RAHASIA Line Mill Orang Lain');

    // Dua bentuk kembalian yang sah, dan keduanya tidak membocorkan:
    //   - delapan service nullable  -> null
    //   - Grading & Weighbridge     -> ['id' => <yang dikirim>, 'name' => '']
    // Id yang digaungkan kembali BUKAN kebocoran: pemanggil sendiri yang
    // mengirimnya. Namanya yang tidak boleh ada.
    if ($result !== null) {
        expect($result['name'])->toBe('');
    }
})->with(leakGuardedServices());

it('KONTROL POSITIF: line milik mill sendiri TETAP diterbitkan beserta namanya', function (string $serviceClass) {
    // Tanpa kontrol ini, uji di atas akan tetap hijau bila seseorang membuat
    // productionLineInfo() selalu mengembalikan null — "tidak membocorkan"
    // dengan cara tidak pernah berguna.
    $ownMill = leakMill('Mill Yang Boleh Dilihat');

    $ownLine = ProductionLine::factory()->create([
        'business_unit_id' => $ownMill->id,
        'name' => 'Line Mill Sendiri',
    ]);

    $method = new ReflectionMethod($serviceClass, 'productionLineInfo');
    $method->setAccessible(true);

    $result = $method->invoke(app($serviceClass), (string) $ownLine->id, (string) $ownMill->id);

    expect($result)->not->toBeNull();
    expect($result['name'])->toBe('Line Mill Sendiri');
    expect($result['id'])->toBe((string) $ownLine->id);
})->with(leakGuardedServices());

it('setiap productionLineInfo() menerima mill-nya sebagai parameter, bukan menebaknya', function (string $serviceClass) {
    // Penjaga bentuk: klausa business_unit_id hanya dapat benar bila mill-nya
    // DIBERIKAN. Sebuah service baru yang menyalin bentuk lama akan punya
    // satu parameter saja, dan tertangkap di sini sebelum lapis perilaku
    // sempat memanggilnya dengan dua argumen.
    $method = new ReflectionMethod($serviceClass, 'productionLineInfo');

    expect($method->getNumberOfParameters())->toBe(2);
    expect($method->getParameters()[1]->getName())->toBe('businessUnitId');
})->with(leakGuardedServices());

it('PEMINDAI STATIK: tidak ada ProductionLine::query()->find() tanpa klausa business_unit_id di app/', function () {
    // Lapis kedua, dan ia menangkap apa yang lapis perilaku tidak bisa:
    // SERVICE BARU. Uji di atas hanya tahu kelas yang namanya terdaftar di
    // leakGuardedServices(); sebuah LaporanSolidWasteDisposalService yang
    // menyalin bentuk lama tidak akan ada di daftar itu, dan akan lolos.
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $lines = file($file->getPathname());

        foreach ($lines as $index => $line) {
            if (! str_contains($line, 'ProductionLine::query()')) {
                continue;
            }

            // Jendela pemeriksaan: baris itu sendiri plus 4 baris sesudahnya.
            // Bentuk yang benar memecah rantainya ke beberapa baris
            // (->where(...) lalu ->find(...)), jadi jendela satu baris akan
            // menandai setiap pemakaian yang benar sebagai pelanggaran.
            $window = implode('', array_slice($lines, $index, 5));

            if (! str_contains($window, '->find(')) {
                continue;
            }

            if (str_contains($window, "where('business_unit_id'")) {
                continue;
            }

            $offenders[] = str_replace(base_path().'/', '', $file->getPathname()).':'.($index + 1);
        }
    }

    expect($offenders)->toBe([], 'Lookup ProductionLine tanpa scope mill: '.implode(', ', $offenders));
});

it('PEMINDAI STATIK punya gigi: bentuk tanpa scope BENAR-BENAR tertangkap polanya', function () {
    // Kontrol negatif untuk pemindai di atas. Tanpa ini, sebuah pemindai yang
    // polanya salah tulis akan selalu melaporkan nol pelanggaran dan terlihat
    // seperti perlindungan.
    $unscoped = <<<'PHP'
        $line = ProductionLine::query()->find($productionLineId, ['id', 'name']);
    PHP;

    $scoped = <<<'PHP'
        $line = ProductionLine::query()
            ->where('business_unit_id', $businessUnitId)
            ->find($productionLineId, ['id', 'name']);
    PHP;

    $detects = function (string $code): bool {
        $lines = preg_split('/\R/', $code, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($lines as $index => $line) {
            if (! str_contains($line, 'ProductionLine::query()')) {
                continue;
            }

            $window = implode("\n", array_slice($lines, $index, 5));

            if (str_contains($window, '->find(') && ! str_contains($window, "where('business_unit_id'")) {
                return true;
            }
        }

        return false;
    };

    expect($detects($unscoped))->toBeTrue();
    expect($detects($scoped))->toBeFalse();
});
