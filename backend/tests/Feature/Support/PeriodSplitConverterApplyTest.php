<?php

use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\User;
use App\Support\PeriodSplit\PeriodSplitConverter;
use App\Support\PeriodSplit\PeriodSplitPlan;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| PeriodSplitConverter::apply() — sisi TULIS konversi periods -> period_stations
|--------------------------------------------------------------------------
| Test ini berjalan terhadap SKEMA BARU (SQLite in-memory, seluruh migrasi
| termasuk 000040 sudah jalan), karena itulah bentuk tabel yang apply() tulisi:
| ia hanya meng-INSERT ke `period_stations`, meng-UPDATE `periods`.`name`, dan
| meng-DELETE baris `periods` yang lebur — tidak satu pun menyentuh kolom lama.
| Yang diuji di sini adalah bahwa muatannya sah bagi skema itu (kolom, FK,
| unique) dan bahwa seluruhnya satu transaksi. Aturan KEPUTUSANnya (gabung,
| mekar, de-duplikasi, tolak irisan) diuji tanpa DB di
| tests/Unit/Support/PeriodSplitConverterPlanTest.php.
*/

it('menulis baris period_stations lengkap dengan status dan catatan penutupannya', function () {
    $mill = BusinessUnit::factory()->create();
    $closer = User::factory()->create();
    $period = Period::factory()->forBusinessUnit($mill)->noStations()->create();

    (new PeriodSplitConverter)->apply(new PeriodSplitPlan(
        stationRows: [
            [
                'period_id' => $period->id,
                'station_type' => 'weighbridge',
                'status' => 'closed',
                'closed_by' => $closer->id,
                'closed_at' => '2026-09-25 07:12:08',
                'created_at' => '2026-09-25 07:11:33',
                'updated_at' => '2026-09-25 07:12:08',
            ],
            [
                'period_id' => $period->id,
                'station_type' => 'sterilizer',
                'status' => 'draft',
                'closed_by' => null,
                'closed_at' => null,
                'created_at' => '2026-09-23 06:48:23',
                'updated_at' => '2026-09-23 06:48:33',
            ],
        ],
        survivorIds: [$period->id],
    ));

    $rows = DB::table('period_stations')->where('period_id', $period->id)->get()->keyBy('station_type');

    expect($rows)->toHaveCount(2);

    expect((array) $rows['weighbridge'])->toMatchArray([
        'period_id' => $period->id,
        'station_type' => 'weighbridge',
        'status' => 'closed',
        'closed_by' => $closer->id,
        'closed_at' => '2026-09-25 07:12:08',
        // Timestamp baris lamanya, bukan waktu migrasi berjalan.
        'created_at' => '2026-09-25 07:11:33',
        'updated_at' => '2026-09-25 07:12:08',
    ]);

    expect($rows['weighbridge']->id)->not->toBeEmpty()
        ->and($rows['sterilizer']->id)->not->toBe($rows['weighbridge']->id);

    expect((array) $rows['sterilizer'])->toMatchArray([
        'status' => 'draft',
        'closed_by' => null,
        'closed_at' => null,
    ]);
});

it('menghapus baris periods yang lebur tanpa menyentuh anak-anak induknya', function () {
    $mill = BusinessUnit::factory()->create();
    $survivor = Period::factory()->forBusinessUnit($mill)->noStations()->named('induk')->create();
    $merged = Period::factory()->forBusinessUnit($mill)->noStations()->named('lebur')->create();

    (new PeriodSplitConverter)->apply(new PeriodSplitPlan(
        stationRows: [[
            'period_id' => $survivor->id,
            'station_type' => 'weighbridge',
            'status' => 'open',
            'closed_by' => null,
            'closed_at' => null,
            'created_at' => '2026-09-23 06:48:23',
            'updated_at' => '2026-09-23 06:48:23',
        ]],
        deletedPeriodIds: [$merged->id],
        survivorIds: [$survivor->id],
    ));

    expect(DB::table('periods')->where('id', $merged->id)->exists())->toBeFalse()
        ->and(DB::table('periods')->where('id', $survivor->id)->exists())->toBeTrue()
        // Anak menempel pada induk yang BERTAHAN, sehingga cascade penghapusan
        // baris yang lebur tidak ikut membawanya.
        ->and(DB::table('period_stations')->where('period_id', $survivor->id)->count())->toBe(1);
});

it('mengubah nama periode sesuai rencana de-duplikasi', function () {
    $mill = BusinessUnit::factory()->create();
    $keep = Period::factory()->forBusinessUnit($mill)->noStations()
        ->named('periode bulanan')->range('2026-09-01', '2026-09-30')->create();
    $rename = Period::factory()->forBusinessUnit($mill)->noStations()
        ->named('periode bulanan sementara')->range('2026-10-01', '2026-10-31')->create();

    (new PeriodSplitConverter)->apply(new PeriodSplitPlan(
        renames: [[
            'id' => $rename->id,
            'from' => 'periode bulanan sementara',
            'to' => 'periode bulanan (2026-10-01 s.d. 2026-10-31)',
        ]],
    ));

    expect(DB::table('periods')->where('id', $rename->id)->value('name'))
        ->toBe('periode bulanan (2026-10-01 s.d. 2026-10-31)')
        ->and(DB::table('periods')->where('id', $keep->id)->value('name'))
        ->toBe('periode bulanan');
});

it('tidak menyentuh DB sama sekali untuk rencana kosong', function () {
    $period = Period::factory()->noStations()->create();

    (new PeriodSplitConverter)->apply(new PeriodSplitPlan);

    expect(DB::table('period_stations')->count())->toBe(0)
        ->and(DB::table('periods')->where('id', $period->id)->exists())->toBeTrue();
});

it('membatalkan seluruh rencana bila satu langkah gagal', function () {
    // Satu transaksi: INSERT anak yang sah lalu UPDATE nama yang menabrak
    // unique (business_unit_id, name). Kalau apply() tidak transaksional,
    // baris period_stations-nya akan tertinggal — separuh konversi, bentuk
    // kerusakan yang paling sulit ditelusuri.
    $mill = BusinessUnit::factory()->create();
    $taken = Period::factory()->forBusinessUnit($mill)->noStations()->named('sudah dipakai')->create();
    $subject = Period::factory()->forBusinessUnit($mill)->noStations()->named('milik sendiri')->create();

    $apply = fn () => (new PeriodSplitConverter)->apply(new PeriodSplitPlan(
        stationRows: [[
            'period_id' => $subject->id,
            'station_type' => 'weighbridge',
            'status' => 'closed',
            'closed_by' => null,
            'closed_at' => '2026-09-25 07:12:08',
            'created_at' => '2026-09-25 07:11:33',
            'updated_at' => '2026-09-25 07:12:08',
        ]],
        renames: [[
            'id' => $subject->id,
            'from' => 'milik sendiri',
            'to' => 'sudah dipakai',
        ]],
        deletedPeriodIds: [$taken->id],
    ));

    expect($apply)->toThrow(QueryException::class);

    expect(DB::table('period_stations')->count())->toBe(0)
        ->and(DB::table('periods')->where('id', $subject->id)->value('name'))->toBe('milik sendiri')
        ->and(DB::table('periods')->where('id', $taken->id)->exists())->toBeTrue();
});

it('menolak dua baris untuk jenis stasiun yang sama di satu induk', function () {
    // Penjaga skema yang membuat aturan TABRAKAN di plan() bukan sekadar
    // kerapian: tanpa resolusi itu, INSERT-nya gagal di level DB.
    $period = Period::factory()->noStations()->create();

    $row = [
        'period_id' => $period->id,
        'station_type' => 'weighbridge',
        'status' => 'draft',
        'closed_by' => null,
        'closed_at' => null,
        'created_at' => '2026-09-23 06:48:23',
        'updated_at' => '2026-09-23 06:48:23',
    ];

    expect(fn () => (new PeriodSplitConverter)->apply(new PeriodSplitPlan(stationRows: [$row, $row])))
        ->toThrow(QueryException::class);

    expect(DB::table('period_stations')->count())->toBe(0);
});
