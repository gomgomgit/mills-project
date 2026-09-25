<?php

use App\Models\SterilizerDetail;
use App\Models\SterilizerRecord;
use App\Models\StorageTankRecord;
use Illuminate\Support\Facades\DB;

/**
 * `e2e:prune-records` — perintah paling destruktif di repo ini, dan
 * satu-satunya yang menghapus baris tanpa bisa dibatalkan.
 *
 * Yang diuji di sini BUKAN "perintahnya menghapus sesuatu" — itu bagian
 * yang mudah. Yang diuji adalah bahwa ia TIDAK menghapus apa pun di luar
 * sasarannya, dan bahwa ketiga penjaganya benar-benar menutup. Sebuah
 * perintah hapus yang penjaganya tidak teruji sama saja dengan tidak
 * punya penjaga: kesalahannya baru ketahuan setelah datanya hilang.
 *
 * Catatan lingkungan: test berjalan di SQLite in-memory (phpunit.xml)
 * sementara dev/production memakai PostgreSQL. Justru itulah alasan
 * perintahnya membaca daftar tabel lewat Schema builder, bukan
 * information_schema — kalau tidak, berkas ini tidak akan pernah bisa
 * menjalankannya.
 */
it('tanpa --force hanya menghitung, dan tidak menghapus satu baris pun', function () {
    $future = SterilizerRecord::factory()->create(['date' => '2700-03-05']);

    $this->artisan('e2e:prune-records')
        ->expectsOutputToContain('sterilizer_records')
        ->expectsOutputToContain('Jalankan ulang dengan --force')
        ->assertSuccessful();

    // Inti test ini: barisnya MASIH ADA. Tanpa asersi ini, perintah yang
    // diam-diam menghapus saat hanya diminta menghitung tetap hijau.
    expect(SterilizerRecord::whereKey($future->id)->exists())->toBeTrue();
});

it('dengan --force menghapus record jauh-masa-depan dan MENYISAKAN data tahun berjalan', function () {
    $future = SterilizerRecord::factory()->create(['date' => '2700-03-05']);
    $real = SterilizerRecord::factory()->create(['date' => '2026-03-05']);

    // Batas tepat: 31 Desember tahun sebelum ambang harus SELAMAT, dan
    // 1 Januari tahun ambang harus terhapus. Salah satu arah dari
    // off-by-one di perbandingan tanggal akan memerahkan salah satunya.
    $dayBefore = SterilizerRecord::factory()->create(['date' => '2599-12-31']);
    $onCutoff = SterilizerRecord::factory()->create(['date' => '2600-01-01']);

    $this->artisan('e2e:prune-records', ['--force' => true])->assertSuccessful();

    expect(SterilizerRecord::whereKey($future->id)->exists())->toBeFalse();
    expect(SterilizerRecord::whereKey($onCutoff->id)->exists())->toBeFalse();
    expect(SterilizerRecord::whereKey($real->id)->exists())->toBeTrue();
    expect(SterilizerRecord::whereKey($dayBefore->id)->exists())->toBeTrue();
});

it('menolak ambang tahun di bawah batas aman, bahkan dengan --force, dan tidak menghapus apa pun', function () {
    $real = SterilizerRecord::factory()->create(['date' => '2026-03-05']);

    $this->artisan('e2e:prune-records', ['--year' => 2026, '--force' => true])
        ->expectsOutputToContain('di bawah ambang aman')
        ->assertFailed();

    // Penjaga yang mengeluh lalu tetap menghapus adalah penjaga palsu.
    expect(SterilizerRecord::whereKey($real->id)->exists())->toBeTrue();
});

it('menolak berjalan di environment production, bahkan dengan --force', function () {
    app()->detectEnvironment(fn () => 'production');

    $future = SterilizerRecord::factory()->create(['date' => '2700-03-05']);

    $this->artisan('e2e:prune-records', ['--force' => true])
        ->expectsOutputToContain('tidak pernah berjalan di production')
        ->assertFailed();

    expect(SterilizerRecord::whereKey($future->id)->exists())->toBeTrue();
});

it('menyapu SELURUH tabel record bertanggal, bukan hanya satu stasiun', function () {
    $sterilizer = SterilizerRecord::factory()->create(['date' => '2700-03-05']);
    $storageTank = StorageTankRecord::factory()->create(['date' => '2800-06-11']);

    $this->artisan('e2e:prune-records', ['--force' => true])->assertSuccessful();

    expect(SterilizerRecord::whereKey($sterilizer->id)->exists())->toBeFalse();
    expect(StorageTankRecord::whereKey($storageTank->id)->exists())->toBeFalse();
});

it('membawa serta baris detail lewat cascade, tanpa meninggalkan detail yatim', function () {
    $future = SterilizerRecord::factory()->create(['date' => '2700-03-05']);
    SterilizerDetail::factory()->count(3)->create(['sterilizer_record_id' => $future->id]);

    $real = SterilizerRecord::factory()->create(['date' => '2026-03-05']);
    SterilizerDetail::factory()->count(2)->create(['sterilizer_record_id' => $real->id]);

    $this->artisan('e2e:prune-records', ['--force' => true])->assertSuccessful();

    expect(SterilizerDetail::where('sterilizer_record_id', $future->id)->count())->toBe(0);
    // Detail milik record yang selamat tidak boleh ikut hilang.
    expect(SterilizerDetail::where('sterilizer_record_id', $real->id)->count())->toBe(2);
});

it('tidak menyentuh tabel record yang tidak punya kolom tanggal', function () {
    // weighbridge_records tidak punya kolom `date`. Sebuah implementasi
    // yang menebak nama kolom alih-alih membaca skema akan meledak di
    // sini — atau, lebih buruk, menghapus dengan predikat yang salah.
    $before = DB::table('weighbridge_records')->count();

    SterilizerRecord::factory()->create(['date' => '2700-03-05']);

    $this->artisan('e2e:prune-records', ['--force' => true])->assertSuccessful();

    expect(DB::table('weighbridge_records')->count())->toBe($before);
});

it('menjawab bersih dan tidak gagal ketika tidak ada residu sama sekali', function () {
    SterilizerRecord::factory()->create(['date' => '2026-03-05']);

    $this->artisan('e2e:prune-records', ['--force' => true])
        ->expectsOutputToContain('Tidak ada record')
        ->assertSuccessful();
});
