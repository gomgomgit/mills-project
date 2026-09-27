<?php

use App\Enums\PeriodStatus;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\StationType;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Menjaga pemecahan `periods` menjadi induk + anak (keputusan user
 * 2026-09-25): cakupan periode adalah mill, sedangkan status tutup/buka ada
 * PER JENIS STASIUN di `period_stations`.
 *
 * Tiga hal yang dijaga di sini, semuanya pernah menjadi sumber bug diam:
 *   1. bentuk skemanya (unique, not-null, cascade, restrict) benar-benar
 *      ditegakkan DB, bukan hanya oleh service;
 *   2. membaca status dari INDUK melempar exception alih-alih mengembalikan
 *      null — tanpa ini delapan penolakan `=== 'closed'` berhenti bekerja
 *      tanpa satu baris pun gagal kompilasi;
 *   3. API PeriodFactory yang dipakai 21 berkas test tetap utuh, tapi kini
 *      mendaratkan datanya di tabel anak.
 */

// ---------------------------------------------------------------- skema

it('menolak dua baris untuk jenis stasiun yang sama di satu periode', function () {
    $period = Period::factory()->noStations()->create();

    PeriodStation::factory()->forPeriod($period)->stationType('sterilizer')->create();

    // Tanpa UNIQUE(period_id, station_type), periode ini akan punya dua baris
    // sterilizer dengan status berbeda dan tidak ada jawaban benar atas
    // "sterilizer di periode ini tertutup atau tidak".
    expect(fn () => PeriodStation::factory()
        ->forPeriod($period)
        ->stationType('sterilizer')
        ->closed()
        ->create()
    )->toThrow(QueryException::class);

    expect(PeriodStation::where('period_id', $period->id)->count())->toBe(1);
});

it('mengizinkan jenis stasiun yang sama di dua periode berbeda', function () {
    $a = Period::factory()->noStations()->create();
    $b = Period::factory()->noStations()->create();

    PeriodStation::factory()->forPeriod($a)->stationType('sterilizer')->create();
    PeriodStation::factory()->forPeriod($b)->stationType('sterilizer')->create();

    expect(PeriodStation::where('station_type', 'sterilizer')->count())->toBe(2);
});

it('menolak baris stasiun tanpa jenis stasiun', function () {
    $period = Period::factory()->noStations()->create();

    // Konsep station_type = NULL ("berlaku semua jenis stasiun") hilang pada
    // 2026-09-25 — cakupan semua-stasiun dinyatakan lewat adanya satu baris
    // per jenis, jadi NULL tidak boleh lolos lagi.
    expect(fn () => DB::table('period_stations')->insert([
        'id' => (string) Str::uuid(),
        'period_id' => $period->id,
        'station_type' => null,
        'status' => PeriodStatus::Draft->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('menolak jenis stasiun yang tidak ada di master station_types', function () {
    $period = Period::factory()->noStations()->create();

    expect(fn () => DB::table('period_stations')->insert([
        'id' => (string) Str::uuid(),
        'period_id' => $period->id,
        'station_type' => 'not-a-real-station-type',
        'status' => PeriodStatus::Draft->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('menghapus seluruh baris stasiun saat periodenya dihapus', function () {
    $period = Period::factory()->stationTypes(['sterilizer', 'boiler-room', 'clarification'])->create();
    $lain = Period::factory()->stationTypes(['sterilizer'])->create();

    expect(PeriodStation::count())->toBe(4);

    $period->delete();

    expect(PeriodStation::count())->toBe(1)
        ->and(PeriodStation::where('period_id', $period->id)->count())->toBe(0)
        ->and(PeriodStation::where('period_id', $lain->id)->count())->toBe(1);
});

it('menolak menghapus jenis stasiun yang masih dipakai sebuah periode', function () {
    $period = Period::factory()->stationTypes(['sterilizer'])->create();

    expect(fn () => StationType::where('code', 'sterilizer')->delete())
        ->toThrow(QueryException::class);

    expect(PeriodStation::where('period_id', $period->id)->count())->toBe(1)
        ->and(StationType::where('code', 'sterilizer')->exists())->toBeTrue();
});

it('menolak nama periode yang sama di satu mill dan mengizinkannya di mill lain', function () {
    $millA = BusinessUnit::factory()->create();
    $millB = BusinessUnit::factory()->create();

    Period::factory()->forBusinessUnit($millA)->named('Oktober 2026')->noStations()->create();

    // Sebelum 2026-09-25 scope-nya (business_unit_id, station_type, name), dan
    // karena PostgreSQL menganggap tiap NULL nilai berbeda, duplikat pada
    // periode ber-station_type NULL lolos dari DB dan hanya tertahan di
    // PeriodService. Sekarang DB yang menegakkan.
    expect(fn () => Period::factory()
        ->forBusinessUnit($millA)
        ->named('Oktober 2026')
        ->noStations()
        ->create()
    )->toThrow(QueryException::class);

    $diMillLain = Period::factory()
        ->forBusinessUnit($millB)
        ->named('Oktober 2026')
        ->noStations()
        ->create();

    expect($diMillLain->exists)->toBeTrue()
        ->and(Period::where('name', 'Oktober 2026')->count())->toBe(2);
});

it('tidak lagi punya kolom cakupan dan status di tabel induk', function () {
    foreach (Period::MOVED_TO_PERIOD_STATIONS as $column) {
        expect(Schema::hasColumn('periods', $column))->toBeFalse("periods.$column seharusnya sudah pindah");
        expect(Schema::hasColumn('period_stations', $column))->toBeTrue("period_stations.$column seharusnya ada");
    }
});

// -------------------------------------------------------------- penjaga

it('melempar exception saat atribut yang sudah pindah dibaca dari induk', function (string $column) {
    $period = Period::factory()->create();

    // Ini inti langkah ini: Eloquent mengembalikan null untuk atribut yang
    // tidak ada dan TIDAK melempar apa pun, sehingga
    // `$period->status === 'closed'` diam-diam menjadi false dan penolakan
    // aksinya berhenti bekerja. Penjaga mengubahnya menjadi kegagalan berisik.
    expect(fn () => $period->{$column})
        ->toThrow(LogicException::class, 'period_stations');
})->with(['station_type', 'status', 'closed_by', 'closed_at']);

it('melempar exception saat atribut yang sudah pindah ditulis ke induk', function (string $column) {
    $period = Period::factory()->create();

    expect(fn () => $period->{$column} = 'apa pun')->toThrow(LogicException::class);
    expect(fn () => $period->forceFill([$column => 'apa pun']))->toThrow(LogicException::class);
    expect(fn () => $period->update([$column => 'apa pun']))->toThrow(LogicException::class);
})->with(['station_type', 'status', 'closed_by', 'closed_at']);

it('menyebut period_stations di pesan exception-nya', function () {
    $period = Period::factory()->create();

    expect(fn () => $period->status)->toThrow(
        LogicException::class,
        'Period::$status tidak ada lagi',
    );
});

it('tidak mengganggu atribut dan relasi induk yang masih ada', function () {
    $mill = BusinessUnit::factory()->create();
    $period = Period::factory()->forBusinessUnit($mill)->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')->create();

    expect($period->name)->toBe('Oktober 2026')
        ->and($period->start_date->toDateString())->toBe('2026-10-01')
        ->and($period->end_date->toDateString())->toBe('2026-10-31')
        ->and($period->businessUnit->id)->toBe($mill->id)
        ->and($period->createdBy)->not->toBeNull();
});

// -------------------------------------------------------------- factory

it('membuat satu baris per jenis stasiun aktif secara default', function () {
    $aktif = StationType::where('is_active', true)->pluck('code')->all();

    $period = Period::factory()->create();

    expect($period->stations)->toHaveCount(count($aktif))
        ->and($period->stations->pluck('station_type')->sort()->values()->all())
        ->toBe(collect($aktif)->sort()->values()->all())
        ->and($period->stations->pluck('status')->unique()->all())->toBe([PeriodStatus::Draft]);
});

it('menerjemahkan stationType(null) menjadi seluruh jenis stasiun aktif', function () {
    $period = Period::factory()->stationType(null)->create();

    expect($period->stations)->toHaveCount(StationType::where('is_active', true)->count())
        ->and($period->stations->pluck('station_type'))->toContain('sterilizer');
});

it('membuat tepat satu baris untuk stationType(kode)', function () {
    $period = Period::factory()->stationType('sterilizer')->create();

    expect($period->stations)->toHaveCount(1)
        ->and($period->stations->first()->station_type)->toBe('sterilizer');
});

it('membuat periode tanpa baris stasiun untuk noStations()', function () {
    expect(Period::factory()->noStations()->create()->stations)->toHaveCount(0);
});

it('menaruh status closed di baris stasiun, bukan di induknya', function () {
    $admin = User::factory()->create();

    $period = Period::factory()->stationType('sterilizer')->closed($admin, '2026-11-01 09:14:00')->create();

    $station = $period->stations->sole();

    expect($station->status)->toBe(PeriodStatus::Closed)
        ->and($station->closed_by)->toBe($admin->id)
        ->and($station->closed_at->toDateTimeString())->toBe('2026-11-01 09:14:00')
        ->and($station->closedBy->id)->toBe($admin->id)
        ->and(DB::table('periods')->where('id', $period->id)->count())->toBe(1);

    // Yang tersimpan di DB adalah baris anak — bukan kolom induk, yang sudah
    // tidak ada sama sekali.
    expect(DB::table('period_stations')->where('period_id', $period->id)->value('status'))
        ->toBe(PeriodStatus::Closed->value);
});

it('menyetel closed_by sendiri bila closed() dipanggil tanpa argumen', function () {
    $station = Period::factory()->stationType('sterilizer')->closed()->create()->stations->sole();

    expect($station->status)->toBe(PeriodStatus::Closed)
        ->and($station->closed_by)->not->toBeNull()
        ->and($station->closed_at)->not->toBeNull();
});

it('mengosongkan closed_by/closed_at untuk draft() dan open()', function (string $state, PeriodStatus $expected) {
    $station = Period::factory()->stationType('sterilizer')->{$state}()->create()->stations->sole();

    expect($station->status)->toBe($expected)
        ->and($station->closed_by)->toBeNull()
        ->and($station->closed_at)->toBeNull();
})->with([
    ['draft', PeriodStatus::Draft],
    ['open', PeriodStatus::Open],
]);

it('tidak peduli urutan pemanggilan state factory', function () {
    $a = Period::factory()->stationType('sterilizer')->closed()->create();
    $b = Period::factory()->closed()->stationType('sterilizer')->create();

    foreach ([$a, $b] as $period) {
        expect($period->stations)->toHaveCount(1)
            ->and($period->stations->first()->station_type)->toBe('sterilizer')
            ->and($period->stations->first()->status)->toBe(PeriodStatus::Closed);
    }
});

it('tetap menerapkan konfigurasi stasiun saat atribut diberikan ke create()', function () {
    $period = Period::factory()->stationType('boiler-room')->open()->create(['name' => 'November 2026']);

    expect($period->name)->toBe('November 2026')
        ->and($period->stations)->toHaveCount(1)
        ->and($period->stations->first()->status)->toBe(PeriodStatus::Open);
});

it('menerapkan konfigurasi stasiun ke setiap periode saat count() dipakai', function () {
    $periods = Period::factory()->count(3)->stationType('sterilizer')->open()->create();

    expect($periods)->toHaveCount(3)
        ->and(PeriodStation::count())->toBe(3)
        ->and(PeriodStation::where('status', PeriodStatus::Open->value)->count())->toBe(3);
});

it('mendukung periode yang separuh tertutup', function () {
    // Bentuk yang menjadi alasan tabel ini dipisah: satu periode, Sterilizer
    // sudah dikunci sementara Clarification masih berjalan.
    $period = Period::factory()->noStations()->create();

    PeriodStation::factory()->forPeriod($period)->stationType('sterilizer')->closed()->create();
    PeriodStation::factory()->forPeriod($period)->stationType('clarification')->open()->create();

    $byType = $period->stations()->pluck('status', 'station_type');

    // pluck() lewat relasi Eloquent ikut menerapkan cast, jadi yang keluar
    // adalah enum PeriodStatus, bukan string.
    expect($byType['sterilizer'])->toBe(PeriodStatus::Closed)
        ->and($byType['clarification'])->toBe(PeriodStatus::Open);
});

it('membuat induk tanpa baris lain saat PeriodStation::factory() dipakai sendiri', function () {
    $station = PeriodStation::factory()->create();

    expect($station->period)->not->toBeNull()
        ->and($station->period->stations)->toHaveCount(1)
        ->and($station->station_type)->toBe('sterilizer');
});

// ------------------------------------------------- penjaga query builder

it('melempar exception saat kueri Period menyebut kolom yang sudah pindah', function () {
    Period::factory()->stationType('sterilizer')->closed()->create();

    // Di SQLite kueri seperti ini TIDAK gagal: identifier berkutip ganda atas
    // kolom yang tidak ada diperlakukan sebagai string literal, sehingga
    // kondisinya sekadar false dan seluruh baris hilang tanpa suara — hijau di
    // test, meledak di PostgreSQL. Penjaga builder yang membuatnya berisik.
    $kueri = [
        'where' => fn () => Period::query()->where('status', 'closed')->get(),
        'where berkualifikasi' => fn () => Period::query()->where('periods.status', 'closed')->get(),
        'where bentuk array' => fn () => Period::query()->where(['status' => 'closed'])->get(),
        'where di dalam closure' => fn () => Period::query()
            ->where(fn ($q) => $q->where('station_type', 'sterilizer')->orWhereNull('station_type'))
            ->get(),
        'orWhereNull' => fn () => Period::query()->orWhereNull('station_type')->get(),
        'whereIn' => fn () => Period::query()->whereIn('status', ['open', 'closed'])->get(),
        'orderBy' => fn () => Period::query()->orderBy('closed_at')->get(),
        'update' => fn () => Period::query()->update(['status' => 'closed']),
    ];

    foreach ($kueri as $bentuk => $jalankan) {
        expect($jalankan)->toThrow(LogicException::class, 'period_stations', "bentuk kueri: $bentuk");
    }
});

it('tetap mengizinkan kueri yang benar lewat tabel anak', function () {
    $tertutup = Period::factory()->stationType('sterilizer')->closed()->create();
    Period::factory()->stationType('sterilizer')->open()->create();

    $ids = Period::query()
        ->whereHas('stations', fn ($q) => $q->where('station_type', 'sterilizer')
            ->where('status', PeriodStatus::Closed->value))
        ->orderBy('start_date')
        ->pluck('id')
        ->all();

    expect($ids)->toBe([$tertutup->id]);

    // Kolom milik period_stations pada sebuah JOIN tidak boleh ikut dijaga —
    // justru inilah bentuk kueri yang benar sekarang.
    $joined = Period::query()
        ->join('period_stations', 'period_stations.period_id', '=', 'periods.id')
        ->where('period_stations.status', PeriodStatus::Closed->value)
        ->pluck('periods.id')
        ->all();

    expect($joined)->toBe([$tertutup->id]);
});
