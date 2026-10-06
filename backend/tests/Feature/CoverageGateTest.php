<?php

/**
 * CoverageGateTest — menjaga penjaga (2026-10-06).
 *
 * `php artisan test --coverage --min=80` keluar dengan kode 0 dan DIAM ketika
 * tidak ada driver coverage terpasang: tanpa tabel, tanpa peringatan, tanpa
 * galat. Gerbang mutu apa pun yang bersandar padanya melaporkan sukses tanpa
 * mengukur apa pun — dan paling mungkin melakukannya di mesin yang paling
 * kurang terkonfigurasi. Enam artefak implement mencatat hal ini secara
 * terpisah sebelum ada yang memperbaikinya.
 *
 * `php artisan coverage:gate` menolak keadaan itu, dan dipakai lewat
 * `composer test:coverage` yang berhenti pada skrip pertama yang gagal.
 *
 * TEST INI SENGAJA TIDAK MENGASERSI "driver harus ada". Itu konfigurasi
 * mesin, bukan isi repositori — memaksanya akan memerahkan suite di setiap
 * mesin pengembang tanpa ada yang meminta cakupan. Yang diasersi adalah
 * KONSISTENSI: apa pun keadaan mesinnya, perintah itu melaporkannya dengan
 * benar dan dengan exit code yang benar. Karena itu test ini deterministik
 * baik di mesin yang punya driver maupun yang tidak.
 */

use Illuminate\Support\Facades\Artisan;

function coverageDriverLoaded(): bool
{
    return extension_loaded('xdebug') || extension_loaded('pcov');
}

it('melaporkan keadaan driver coverage dengan exit code yang benar', function () {
    $exitCode = Artisan::call('coverage:gate');
    $output = Artisan::output();

    if (coverageDriverLoaded()) {
        // Ada driver → gerbang terbuka, rantai composer lanjut ke --min=80.
        expect($exitCode)->toBe(0);
        expect($output)->toContain('Driver coverage tersedia');

        return;
    }

    // Tidak ada driver → gerbang WAJIB gagal. Inilah seluruh gunanya: tanpa
    // ini, perintah cakupan di bawahnya akan keluar 0 tanpa mengukur apa pun.
    expect($exitCode)->toBe(1);
    expect($output)->toContain('Tidak ada driver coverage terpasang');
});

it('menjelaskan cara memperbaikinya, bukan sekadar menolak', function () {
    if (coverageDriverLoaded()) {
        expect(true)->toBeTrue();

        return;
    }

    Artisan::call('coverage:gate');
    $output = Artisan::output();

    // Sebuah gerbang yang gagal tanpa memberi tahu jalan keluarnya akan
    // dilewati orang, bukan diperbaiki.
    expect($output)->toContain('pcov');
    expect($output)->toContain('xdebug');

    // Dan ia harus menyatakan bahwa menjalankan suite biasa TIDAK terpengaruh,
    // supaya tidak ada yang mengira seluruh pengujian sedang diblokir.
    expect($output)->toContain('php artisan test');
});

it('composer menyediakan test:coverage yang menjalankan gerbang SEBELUM perintah cakupan', function () {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);

    expect($composer['scripts'])->toHaveKey('test:coverage');

    $steps = $composer['scripts']['test:coverage'];

    // Urutannya yang menentukan. Composer berhenti pada skrip pertama yang
    // keluar tidak nol, jadi gerbang HARUS yang pertama — ditaruh sesudahnya,
    // perintah cakupan sudah terlanjur "lolos" lebih dulu.
    expect($steps[0])->toContain('coverage:gate');
    expect($steps[1])->toContain('--min=80');

    // Ambangnya harus sama dengan test_strategy.unit_test.coverage_threshold.
    expect($steps[1])->toContain('--coverage');
});
