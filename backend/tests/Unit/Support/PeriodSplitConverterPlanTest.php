<?php

use App\Support\PeriodSplit\PeriodSplitConverter;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| PeriodSplitConverter::plan() — aturan konversi periods -> period_stations
|--------------------------------------------------------------------------
| Test MURNI, tanpa aplikasi dan tanpa DB: plan() hanya menerima array baris
| `periods` berbentuk LAMA dan mengembalikan rencananya. Itu sebabnya logikanya
| diekstrak dari migrasinya — menguji cabang-cabang ini lewat
| `php artisan migrate` mustahil, karena di DB test `periods` kosong saat
| migrasi berjalan, dan setelah migrasi 000040 kolom station_type/status/
| closed_by/closed_at sudah tidak ada sehingga baris berbentuk lama tidak dapat
| dibuat lagi untuk diuji. Sisi TULISnya (apply()) diuji terhadap SQLite di
| tests/Feature/Support/PeriodSplitConverterApplyTest.php.
*/

// Tanpa RefreshDatabase: test ini tidak menyentuh DB sama sekali. Tests\TestCase
// dipasang hanya supaya berkas ini tidak berjalan sebagai test Pest "telanjang",
// yang di PHP 8.5 memicu notis deprecated dari refleksi internal Pest dan
// membuat seluruh berkas dilaporkan DEPR alih-alih passed.
uses(TestCase::class);

const PSC_MILL_A = 'bu-aaaa';
const PSC_MILL_B = 'bu-bbbb';

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function oldPeriod(array $overrides = []): array
{
    return array_merge([
        'id' => 'p-'.bin2hex(random_bytes(4)),
        'business_unit_id' => PSC_MILL_A,
        'station_type' => 'weighbridge',
        'name' => 'sep 2026',
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
        'status' => 'draft',
        'closed_by' => null,
        'closed_at' => null,
        'created_by' => 'u-1',
        'updated_by' => 'u-1',
        'created_at' => '2026-09-23 06:48:23',
        'updated_at' => '2026-09-23 06:48:33',
    ], $overrides);
}

it('tidak melakukan apa pun untuk tabel periods yang kosong', function () {
    $plan = (new PeriodSplitConverter)->plan([]);

    expect($plan->isEmpty())->toBeTrue()
        ->and($plan->stationRows)->toBe([])
        ->and($plan->deletedPeriodIds)->toBe([])
        ->and($plan->notes)->toBe([]);
});

it('membiarkan satu baris berdiri sendiri sebagai satu induk + satu anak', function () {
    $plan = (new PeriodSplitConverter)->plan([
        oldPeriod(['id' => 'p-1', 'station_type' => 'sterilizer']),
    ]);

    expect($plan->survivorIds)->toBe(['p-1'])
        ->and($plan->deletedPeriodIds)->toBe([])
        ->and($plan->renames)->toBe([])
        ->and($plan->stationRows)->toHaveCount(1);

    expect($plan->stationRows[0])->toMatchArray([
        'period_id' => 'p-1',
        'station_type' => 'sterilizer',
        'status' => 'draft',
    ]);
});

it('menggabungkan baris dengan (mill, start_date, end_date) sama menjadi satu induk dan N anak', function () {
    // Bentuk persis data dev pra-migrasi: dua baris, mill sama, nama sama,
    // rentang sama, jenis stasiun berbeda, salah satunya sudah ditutup.
    $plan = (new PeriodSplitConverter)->plan([
        oldPeriod([
            'id' => 'p-muda',
            'station_type' => 'weighbridge',
            'status' => 'closed',
            'closed_by' => 'u-admin',
            'closed_at' => '2026-09-25 07:12:08',
            'created_at' => '2026-09-25 07:11:33',
        ]),
        oldPeriod([
            'id' => 'p-tua',
            'station_type' => 'sterilizer',
            'created_at' => '2026-09-23 06:48:23',
        ]),
    ]);

    // Induknya baris TERTUA, sehingga name/created_by/created_at terwarisi apa
    // adanya tanpa penyalinan.
    expect($plan->survivorIds)->toBe(['p-tua'])
        ->and($plan->deletedPeriodIds)->toBe(['p-muda'])
        ->and($plan->stationRows)->toHaveCount(2);

    $byType = collect($plan->stationRows)->keyBy('station_type');

    expect($byType->keys()->sort()->values()->all())->toBe(['sterilizer', 'weighbridge'])
        ->and($byType['weighbridge']['period_id'])->toBe('p-tua')
        ->and($byType['sterilizer']['period_id'])->toBe('p-tua');

    expect(collect($plan->notes)->contains(fn ($n) => str_contains($n, 'GABUNG:')))->toBeTrue();
});

it('membawa status, closed_by dan closed_at setiap baris lama ke baris anaknya', function () {
    $plan = (new PeriodSplitConverter)->plan([
        oldPeriod([
            'id' => 'p-1',
            'station_type' => 'weighbridge',
            'status' => 'closed',
            'closed_by' => 'u-admin',
            'closed_at' => '2026-09-25 07:12:08',
            'created_at' => '2026-09-20 00:00:00',
            'updated_at' => '2026-09-25 07:12:08',
        ]),
        oldPeriod(['id' => 'p-2', 'station_type' => 'sterilizer', 'status' => 'open', 'created_at' => '2026-09-21 00:00:00']),
        oldPeriod(['id' => 'p-3', 'station_type' => 'grading', 'status' => 'draft', 'created_at' => '2026-09-22 00:00:00']),
    ]);

    $byType = collect($plan->stationRows)->keyBy('station_type');

    expect($byType['weighbridge'])->toMatchArray([
        'status' => 'closed',
        'closed_by' => 'u-admin',
        'closed_at' => '2026-09-25 07:12:08',
        // Timestamp baris lamanya ikut, bukan waktu migrasi.
        'created_at' => '2026-09-20 00:00:00',
        'updated_at' => '2026-09-25 07:12:08',
    ]);

    expect($byType['sterilizer']['status'])->toBe('open')
        ->and($byType['sterilizer']['closed_by'])->toBeNull()
        ->and($byType['grading']['status'])->toBe('draft');
});

it('memekarkan baris bercakupan NULL menjadi satu anak per jenis stasiun aktif di mill itu', function () {
    $plan = (new PeriodSplitConverter)->plan(
        [oldPeriod(['id' => 'p-semua', 'station_type' => null, 'status' => 'open'])],
        [PSC_MILL_A => ['weighbridge', 'grading', 'sterilizer']],
    );

    expect($plan->stationRows)->toHaveCount(3)
        ->and(collect($plan->stationRows)->pluck('station_type')->all())
        ->toBe(['weighbridge', 'grading', 'sterilizer']);

    // Status baris lamanya diwarisi SETIAP anak hasil pemekaran.
    expect(collect($plan->stationRows)->pluck('status')->unique()->all())->toBe(['open']);

    expect(collect($plan->notes)->contains(fn ($n) => str_contains($n, 'MEKAR:')))->toBeTrue();
});

it('memekarkan cakupan NULL memakai daftar jenis stasiun mill-nya sendiri, bukan mill lain', function () {
    $plan = (new PeriodSplitConverter)->plan(
        [
            oldPeriod(['id' => 'p-a', 'business_unit_id' => PSC_MILL_A, 'station_type' => null]),
            oldPeriod(['id' => 'p-b', 'business_unit_id' => PSC_MILL_B, 'station_type' => null]),
        ],
        [
            PSC_MILL_A => ['weighbridge', 'grading'],
            PSC_MILL_B => ['sterilizer'],
        ],
    );

    $byMill = collect($plan->stationRows)->groupBy('period_id');

    expect($byMill['p-a']->pluck('station_type')->all())->toBe(['weighbridge', 'grading'])
        ->and($byMill['p-b']->pluck('station_type')->all())->toBe(['sterilizer']);
});

it('mempertahankan penutupan ketika baris eksplisit dan hasil pemekaran NULL bertabrakan pada jenis yang sama', function () {
    $plan = (new PeriodSplitConverter)->plan(
        [
            oldPeriod([
                'id' => 'p-ditutup',
                'station_type' => 'weighbridge',
                'status' => 'closed',
                'closed_by' => 'u-admin',
                'closed_at' => '2026-09-25 07:12:08',
                'created_at' => '2026-09-24 00:00:00',
            ]),
            oldPeriod(['id' => 'p-semua', 'station_type' => null, 'status' => 'draft', 'created_at' => '2026-09-23 00:00:00']),
        ],
        [PSC_MILL_A => ['weighbridge', 'grading']],
    );

    $byType = collect($plan->stationRows)->keyBy('station_type');

    expect($plan->stationRows)->toHaveCount(2)
        ->and($byType['weighbridge']['status'])->toBe('closed')
        ->and($byType['weighbridge']['closed_by'])->toBe('u-admin')
        ->and($byType['grading']['status'])->toBe('draft');

    expect(collect($plan->notes)->contains(fn ($n) => str_contains($n, 'TABRAKAN:')))->toBeTrue();
});

it('memakai nama baris tertua sebagai nama induk dan mencatat nama yang dibuang', function () {
    $plan = (new PeriodSplitConverter)->plan([
        oldPeriod(['id' => 'p-tua', 'name' => 'September 2026', 'station_type' => 'weighbridge', 'created_at' => '2026-09-01 00:00:00']),
        oldPeriod(['id' => 'p-muda', 'name' => 'sep 2026 (sterilizer)', 'station_type' => 'sterilizer', 'created_at' => '2026-09-05 00:00:00']),
    ]);

    expect($plan->survivorIds)->toBe(['p-tua']);

    $note = collect($plan->notes)->first(fn ($n) => str_contains($n, 'NAMA DIBUANG:'));

    expect($note)->not->toBeNull()
        ->and($note)->toContain('September 2026')
        ->and($note)->toContain('sep 2026 (sterilizer)')
        ->and($note)->toContain('p-muda');
});

it('menyelesaikan bentrok nama antar induk dengan mengimbuhi rentang tanggal dan melaporkannya', function () {
    // Dua rentang yang TIDAK beririsan di mill yang sama, tetapi bernama sama —
    // sah di skema lama (unique-nya memuat station_type), mustahil di skema
    // baru yang unique (business_unit_id, name).
    $plan = (new PeriodSplitConverter)->plan([
        oldPeriod(['id' => 'p-sep', 'name' => 'periode bulanan', 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']),
        oldPeriod(['id' => 'p-okt', 'name' => 'periode bulanan', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'station_type' => 'sterilizer']),
    ]);

    // Yang paling awal mempertahankan namanya; yang belakangan diubah.
    expect($plan->renames)->toHaveCount(1)
        ->and($plan->renames[0])->toBe([
            'id' => 'p-okt',
            'from' => 'periode bulanan',
            'to' => 'periode bulanan (2026-10-01 s.d. 2026-10-31)',
        ]);

    $note = collect($plan->notes)->first(fn ($n) => str_contains($n, 'NAMA DIUBAH:'));

    expect($note)->not->toBeNull()
        ->and($note)->toContain('periode bulanan (2026-10-01 s.d. 2026-10-31)');
});

it('tidak menganggap nama yang sama di mill berbeda sebagai bentrok', function () {
    $plan = (new PeriodSplitConverter)->plan([
        oldPeriod(['id' => 'p-a', 'business_unit_id' => PSC_MILL_A, 'name' => 'sep 2026']),
        oldPeriod(['id' => 'p-b', 'business_unit_id' => PSC_MILL_B, 'name' => 'sep 2026']),
    ]);

    expect($plan->renames)->toBe([])
        ->and($plan->survivorIds)->toHaveCount(2);
});

it('memotong nama panjang agar hasil de-duplikasi tetap muat di varchar(255)', function () {
    $long = str_repeat('a', 250);

    $plan = (new PeriodSplitConverter)->plan([
        oldPeriod(['id' => 'p-1', 'name' => $long, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']),
        oldPeriod(['id' => 'p-2', 'name' => $long, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31']),
    ]);

    expect($plan->renames)->toHaveCount(1)
        ->and(mb_strlen($plan->renames[0]['to']))->toBe(PeriodSplitConverter::NAME_MAX_LENGTH)
        ->and($plan->renames[0]['to'])->toEndWith('(2026-10-01 s.d. 2026-10-31)');
});

it('menghentikan migrasi ketika dua rentang di mill yang sama beririsan sebagian', function () {
    $run = fn () => (new PeriodSplitConverter)->plan([
        oldPeriod(['id' => 'p-awal', 'name' => 'a', 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']),
        oldPeriod(['id' => 'p-tumpang', 'name' => 'b', 'station_type' => 'sterilizer', 'start_date' => '2026-09-15', 'end_date' => '2026-10-15']),
    ]);

    expect($run)->toThrow(RuntimeException::class);

    try {
        $run();
    } catch (RuntimeException $e) {
        expect($e->getMessage())
            ->toContain('beririsan sebagian')
            ->toContain('p-awal')
            ->toContain('p-tumpang')
            ->toContain('2026-09-01..2026-09-30')
            ->toContain('2026-09-15..2026-10-15');
    }
});

it('menghentikan migrasi juga ketika satu rentang sepenuhnya memuat rentang lain', function () {
    $run = fn () => (new PeriodSplitConverter)->plan([
        oldPeriod(['id' => 'p-luar', 'name' => 'a', 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']),
        oldPeriod(['id' => 'p-dalam', 'name' => 'b', 'station_type' => 'sterilizer', 'start_date' => '2026-09-10', 'end_date' => '2026-09-20']),
    ]);

    expect($run)->toThrow(RuntimeException::class);
});

it('tidak menganggap rentang beririsan di mill berbeda sebagai masalah', function () {
    $plan = (new PeriodSplitConverter)->plan([
        oldPeriod(['id' => 'p-a', 'business_unit_id' => PSC_MILL_A, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']),
        oldPeriod(['id' => 'p-b', 'business_unit_id' => PSC_MILL_B, 'start_date' => '2026-09-15', 'end_date' => '2026-10-15']),
    ]);

    expect($plan->survivorIds)->toHaveCount(2);
});

it('menghentikan migrasi ketika periode semua-stasiun berada di mill tanpa stasiun aktif', function () {
    // Grup tanpa satu pun baris anak akan menjadi induk tanpa anak, dan
    // migrasi 000040 menolak seluruh rangkaian di tengah jalan karenanya.
    // Lebih baik berhenti di sini, dengan nama barisnya.
    $run = fn () => (new PeriodSplitConverter)->plan(
        [oldPeriod(['id' => 'p-kosong', 'name' => 'mill kosong', 'station_type' => null])],
        [PSC_MILL_A => []],
    );

    expect($run)->toThrow(RuntimeException::class);

    try {
        $run();
    } catch (RuntimeException $e) {
        expect($e->getMessage())
            ->toContain('p-kosong')
            ->toContain('mill kosong')
            ->toContain('000040');
    }
});

it('menerima objek baris (stdClass) apa adanya dari DB::table()->get()', function () {
    $plan = (new PeriodSplitConverter)->plan([
        (object) oldPeriod(['id' => 'p-1', 'station_type' => 'weighbridge']),
    ]);

    expect($plan->stationRows)->toHaveCount(1)
        ->and($plan->stationRows[0]['period_id'])->toBe('p-1');
});

it('menormalkan nilai tanggal dan timestamp berbentuk objek DateTime', function () {
    $plan = (new PeriodSplitConverter)->plan([
        oldPeriod([
            'id' => 'p-1',
            'start_date' => new DateTimeImmutable('2026-09-01'),
            'end_date' => new DateTimeImmutable('2026-09-30'),
            'closed_at' => new DateTimeImmutable('2026-09-25 07:12:08'),
            'status' => 'closed',
            'closed_by' => 'u-admin',
        ]),
        oldPeriod([
            'id' => 'p-2',
            'station_type' => 'sterilizer',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]),
    ]);

    // Kedua baris harus jatuh di grup yang SAMA walau satu bertipe objek dan
    // satu bertipe string — kalau normalisasinya bocor, penggabungannya gagal.
    expect($plan->survivorIds)->toHaveCount(1)
        ->and($plan->stationRows)->toHaveCount(2)
        ->and(collect($plan->stationRows)->firstWhere('station_type', 'weighbridge')['closed_at'])
        ->toBe('2026-09-25 07:12:08');
});

it('memberi hasil yang sama apa pun urutan baris yang dikembalikan SELECT', function () {
    $rows = [
        oldPeriod(['id' => 'p-1', 'name' => 'x', 'station_type' => 'weighbridge', 'created_at' => '2026-09-01 00:00:00']),
        oldPeriod(['id' => 'p-2', 'name' => 'y', 'station_type' => 'sterilizer', 'created_at' => '2026-09-02 00:00:00']),
        oldPeriod(['id' => 'p-3', 'name' => 'x', 'station_type' => 'grading', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'created_at' => '2026-09-03 00:00:00']),
    ];

    $forward = (new PeriodSplitConverter)->plan($rows);
    $reversed = (new PeriodSplitConverter)->plan(array_reverse($rows));

    expect($reversed->survivorIds)->toBe($forward->survivorIds)
        ->and($reversed->deletedPeriodIds)->toBe($forward->deletedPeriodIds)
        ->and($reversed->renames)->toBe($forward->renames)
        ->and(collect($reversed->stationRows)->sortBy('station_type')->values()->all())
        ->toBe(collect($forward->stationRows)->sortBy('station_type')->values()->all());
});
