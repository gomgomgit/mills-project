<?php

use App\Models\GradingRecord;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\SterilizerDetail;
use App\Models\SterilizerRecord;
use App\Models\StorageTankRecord;
use App\Models\WeighbridgeRecord;

/**
 * `e2e:prune-records` — perintah paling destruktif di repo ini, dan
 * satu-satunya yang menghapus baris tanpa bisa dibatalkan.
 *
 * Yang diuji di sini BUKAN "perintahnya menghapus sesuatu" — itu bagian
 * yang mudah. Yang diuji adalah bahwa ia TIDAK menghapus apa pun di luar
 * sasarannya (rentang lajur 1970-01-01 s.d. sebelum 2020-01-01), dan bahwa
 * penjaga environment/database-nya benar-benar menutup. Sebuah perintah
 * hapus yang penjaganya tidak teruji sama saja dengan tidak punya penjaga.
 *
 * Catatan lingkungan: test berjalan di SQLite in-memory (phpunit.xml,
 * APP_ENV=testing — satu-satunya environment selain e2e yang diizinkan)
 * sementara e2e memakai PostgreSQL mill_smart_log_e2e.
 */
it('tanpa --force hanya menghitung, dan tidak menghapus satu baris pun', function () {
    $lane = SterilizerRecord::factory()->create(['date' => '1985-03-05']);

    $this->artisan('e2e:prune-records')
        ->expectsOutputToContain('sterilizer_records')
        ->expectsOutputToContain('Jalankan ulang dengan --force')
        ->assertSuccessful();

    // Inti test ini: barisnya MASIH ADA.
    expect(SterilizerRecord::whereKey($lane->id)->exists())->toBeTrue();
});

it('dengan --force menghapus record di rentang lajur dan MENYISAKAN data di luarnya, tepat di batasnya', function () {
    $lane = SterilizerRecord::factory()->create(['date' => '1985-03-05']);
    $real = SterilizerRecord::factory()->create(['date' => '2026-03-05']);

    // Batas tepat di kedua ujung: 1970-01-01 dan 2019-12-31 terhapus,
    // 1969-12-31 dan 2020-01-01 (FIXTURE_START spec form-*) selamat.
    $firstDay = SterilizerRecord::factory()->create(['date' => '1970-01-01']);
    $lastDay = SterilizerRecord::factory()->create(['date' => '2019-12-31']);
    $dayBefore = SterilizerRecord::factory()->create(['date' => '1969-12-31']);
    $dayAfter = SterilizerRecord::factory()->create(['date' => '2020-01-01']);

    $this->artisan('e2e:prune-records', ['--force' => true])->assertSuccessful();

    expect(SterilizerRecord::whereKey($lane->id)->exists())->toBeFalse();
    expect(SterilizerRecord::whereKey($firstDay->id)->exists())->toBeFalse();
    expect(SterilizerRecord::whereKey($lastDay->id)->exists())->toBeFalse();
    expect(SterilizerRecord::whereKey($real->id)->exists())->toBeTrue();
    expect(SterilizerRecord::whereKey($dayBefore->id)->exists())->toBeTrue();
    expect(SterilizerRecord::whereKey($dayAfter->id)->exists())->toBeTrue();
});

it('menolak environment selain e2e/testing, bahkan dengan --force, dan tidak menghapus apa pun', function (string $environment) {
    app()->detectEnvironment(fn () => $environment);

    $lane = SterilizerRecord::factory()->create(['date' => '1985-03-05']);

    $this->artisan('e2e:prune-records', ['--force' => true])
        ->expectsOutputToContain('hanya berjalan di environment e2e')
        ->assertFailed();

    // Penjaga yang mengeluh lalu tetap menghapus adalah penjaga palsu.
    expect(SterilizerRecord::whereKey($lane->id)->exists())->toBeTrue();
})->with(['production', 'local']);

it('menolak environment e2e yang databasenya bukan *_e2e (mis. .env.e2e salah salin dari .env)', function () {
    app()->detectEnvironment(fn () => 'e2e');
    // Hanya nilai konfigurasinya yang dibaca penjaga; koneksi SQLite test
    // yang sudah terbuka tidak berubah.
    config(['database.connections.'.config('database.default').'.database' => 'mill_smart_log']);

    $lane = SterilizerRecord::factory()->create(['date' => '1985-03-05']);

    $this->artisan('e2e:prune-records', ['--force' => true])
        ->expectsOutputToContain('bukan database e2e')
        ->assertFailed();

    expect(SterilizerRecord::whereKey($lane->id)->exists())->toBeTrue();
});

it('berjalan di environment e2e dengan database *_e2e', function () {
    app()->detectEnvironment(fn () => 'e2e');
    config(['database.connections.'.config('database.default').'.database' => 'mill_smart_log_e2e']);

    $lane = SterilizerRecord::factory()->create(['date' => '1985-03-05']);

    $this->artisan('e2e:prune-records', ['--force' => true])->assertSuccessful();

    expect(SterilizerRecord::whereKey($lane->id)->exists())->toBeFalse();
});

it('menyapu SELURUH tabel record bertanggal, bukan hanya satu stasiun', function () {
    $sterilizer = SterilizerRecord::factory()->create(['date' => '1985-03-05']);
    $storageTank = StorageTankRecord::factory()->create(['date' => '2001-06-11']);

    $this->artisan('e2e:prune-records', ['--force' => true])->assertSuccessful();

    expect(SterilizerRecord::whereKey($sterilizer->id)->exists())->toBeFalse();
    expect(StorageTankRecord::whereKey($storageTank->id)->exists())->toBeFalse();
});

it('membawa serta baris detail lewat cascade, tanpa meninggalkan detail yatim', function () {
    $lane = SterilizerRecord::factory()->create(['date' => '1985-03-05']);
    SterilizerDetail::factory()->count(3)->create(['sterilizer_record_id' => $lane->id]);

    $real = SterilizerRecord::factory()->create(['date' => '2026-03-05']);
    SterilizerDetail::factory()->count(2)->create(['sterilizer_record_id' => $real->id]);

    $this->artisan('e2e:prune-records', ['--force' => true])->assertSuccessful();

    expect(SterilizerDetail::where('sterilizer_record_id', $lane->id)->count())->toBe(0);
    // Detail milik record yang selamat tidak boleh ikut hilang.
    expect(SterilizerDetail::where('sterilizer_record_id', $real->id)->count())->toBe(2);
});

it('ikut menyapu weighbridge_records lewat record_datetime (tidak punya kolom date)', function () {
    // Versi lama melewati Weighbridge sepenuhnya, dan laporan-weighbridge
    // meninggalkan 282 baris bertahun 7278 di database dev.
    $lane = WeighbridgeRecord::factory()->create(['record_datetime' => '1985-03-05 08:00:00']);
    $lastMinute = WeighbridgeRecord::factory()->create(['record_datetime' => '2019-12-31 23:59:00']);
    $real = WeighbridgeRecord::factory()->create(['record_datetime' => '2026-03-05 08:00:00']);
    $newYear = WeighbridgeRecord::factory()->create(['record_datetime' => '2020-01-01 00:00:00']);

    $this->artisan('e2e:prune-records', ['--force' => true])->assertSuccessful();

    expect(WeighbridgeRecord::whereKey($lane->id)->exists())->toBeFalse();
    expect(WeighbridgeRecord::whereKey($lastMinute->id)->exists())->toBeFalse();
    expect(WeighbridgeRecord::whereKey($real->id)->exists())->toBeTrue();
    expect(WeighbridgeRecord::whereKey($newYear->id)->exists())->toBeTrue();
});

it('menghapus grading lajur sebelum weighbridge-nya, dan melewati weighbridge yang dirujuk grading di luar rentang', function () {
    $laneWb = WeighbridgeRecord::factory()->create(['record_datetime' => '1985-03-05 08:00:00']);
    $laneGrading = GradingRecord::factory()->create(['date' => '1985-03-05 09:00:00', 'weighbridge_record_id' => $laneWb->id]);

    $pinnedWb = WeighbridgeRecord::factory()->create(['record_datetime' => '1985-03-06 08:00:00']);
    $realGrading = GradingRecord::factory()->create(['date' => '2026-03-06 09:00:00', 'weighbridge_record_id' => $pinnedWb->id]);

    $this->artisan('e2e:prune-records', ['--force' => true])
        ->expectsOutputToContain('dilewati')
        ->assertSuccessful();

    expect(GradingRecord::whereKey($laneGrading->id)->exists())->toBeFalse();
    expect(WeighbridgeRecord::whereKey($laneWb->id)->exists())->toBeFalse();
    // FK RESTRICT: menghapusnya akan menggagalkan seluruh transaksi.
    expect(WeighbridgeRecord::whereKey($pinnedWb->id)->exists())->toBeTrue();
    expect(GradingRecord::whereKey($realGrading->id)->exists())->toBeTrue();
});

it('menghapus periode yang seluruh rentangnya di dalam lajur, beserta period_stations-nya, dan tidak menyentuh periode lain', function () {
    $lane = Period::factory()->create(['start_date' => '1985-03-01', 'end_date' => '1985-03-31']);
    $laneStation = PeriodStation::where('period_id', $lane->id)->firstOrFail();

    // Prasyarat Form e2e: mulai 2020-01-01 — di luar rentang.
    $fixture = Period::factory()->create(['start_date' => '2020-01-01', 'end_date' => '2026-10-11']);
    // Melintasi batas atas: tidak SELURUHNYA di lajur, jadi selamat.
    $straddling = Period::factory()->create(['start_date' => '2019-12-01', 'end_date' => '2020-01-15']);

    $this->artisan('e2e:prune-records', ['--force' => true])
        ->expectsOutputToContain('periods')
        ->assertSuccessful();

    expect(Period::whereKey($lane->id)->exists())->toBeFalse();
    expect(PeriodStation::whereKey($laneStation->id)->exists())->toBeFalse();
    expect(Period::whereKey($fixture->id)->exists())->toBeTrue();
    expect(Period::whereKey($straddling->id)->exists())->toBeTrue();
});

it('menjawab bersih dan tidak gagal ketika tidak ada residu sama sekali', function () {
    SterilizerRecord::factory()->create(['date' => '2026-03-05']);

    $this->artisan('e2e:prune-records', ['--force' => true])
        ->expectsOutputToContain('Tidak ada record')
        ->assertSuccessful();
});
