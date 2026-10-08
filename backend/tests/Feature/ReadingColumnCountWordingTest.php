<?php

/**
 * ReadingColumnCountWordingTest — angka kolom bacaan yang DITULIS di layar
 * laporan harus sama dengan jumlah kolom bacaan yang sebenarnya (2026-10-08).
 *
 * ────────────────────────────────────────────────────────────────────────
 * DEFEK YANG DIJAGANYA
 * ────────────────────────────────────────────────────────────────────────
 * Laporan Depricarping — web DAN mobile — menulis "minimal satu dari ENAM
 * kolom bacaan diisi". Kolom bacaan Depricarping ada SEMBILAN.
 *
 * Asal-usulnya salin-tempel: kalimat itu benar untuk Threshing dan Pressing,
 * yang memang punya enam kolom, dan ikut terbawa ke Depricarping tanpa
 * angkanya disesuaikan. Layar yang sama bahkan menyebut "sembilan" di
 * tempat lain, jadi ia bertentangan dengan dirinya sendiri — dan pembaca
 * yang memakai angka itu untuk memeriksa cakupan akan menghitung salah.
 *
 * Tercatat sebagai catatan diketahui "K-3" di docs/qa, dengan dua hal yang
 * ternyata kurang tepat: ia menyebut "kosmetik", padahal angka ini dipakai
 * pembaca untuk menafsirkan penyebut cakupan; dan ia menyebut mobile saja,
 * padahal web juga salah.
 *
 * ────────────────────────────────────────────────────────────────────────
 * MENGAPA PEMINDAI STATIK, DAN MENGAPA ANGKANYA DITURUNKAN
 * ────────────────────────────────────────────────────────────────────────
 * Satu uji ini menjangkau WEB DAN MOBILE sekaligus dengan membaca kedua
 * berkasnya sebagai teks — tidak ada uji Livewire yang dapat melihat berkas
 * Vue, dan tidak ada uji Vitest yang dapat melihat blade.
 *
 * Angkanya DITURUNKAN dari READING_FIELDS, bukan dipaku di sini. Kalau
 * sebuah kolom bacaan ditambahkan kelak, uji ini langsung menunjuk kalimat
 * mana yang harus ikut berubah — sementara angka yang dipaku justru akan
 * ikut usang bersama kalimatnya.
 */

use App\Services\DepricarpingRecordService;
use App\Services\PressingRecordService;
use App\Services\ThreshingRecordService;

/**
 * Kata bilangan Indonesia yang dipakai kalimat itu. Sengaja hanya sampai
 * belasan: stasiun dengan lebih dari itu akan gagal di sini dengan pesan
 * yang jelas, alih-alih lolos karena kata yang tak terbaca.
 *
 * @return array<int, string>
 */
function readingCountWords(): array
{
    return [
        1 => 'satu', 2 => 'dua', 3 => 'tiga', 4 => 'empat', 5 => 'lima',
        6 => 'enam', 7 => 'tujuh', 8 => 'delapan', 9 => 'sembilan',
        10 => 'sepuluh', 11 => 'sebelas', 12 => 'dua belas',
        13 => 'tiga belas', 14 => 'empat belas',
    ];
}

/**
 * Stasiun yang kalimatnya memuat jumlah kolom bacaan, beserta kedua berkas
 * yang memuatnya.
 *
 * @return list<array{0: string, 1: list<string>, 2: string}>
 */
function readingCountSurfaces(): array
{
    return [
        ['Depricarping', DepricarpingRecordService::READING_FIELDS, 'depricarping'],
        ['Threshing', ThreshingRecordService::READING_FIELDS, 'threshing'],
        ['Pressing', PressingRecordService::READING_FIELDS, 'pressing'],
    ];
}

it('angka kolom bacaan pada laporan WEB sama dengan READING_FIELDS', function (string $label, array $fields, string $slug) {
    $path = resource_path("views/livewire/dashboard/laporan-{$slug}.blade.php");
    expect(file_exists($path))->toBeTrue("blade laporan {$label} tidak ditemukan: {$path}");

    $html = file_get_contents($path);
    $expected = readingCountWords()[count($fields)] ?? null;
    expect($expected)->not->toBeNull("tidak ada kata bilangan untuk ".count($fields)." kolom");

    // Mencari kalimatnya, bukan sekadar kata bilangannya: kata "enam" bisa
    // muncul sah di tempat lain pada halaman yang sama.
    preg_match_all('/satu dari ([a-z ]+?) kolom bacaan/u', $html, $m);

    expect($m[1])->not->toBeEmpty("laporan {$label} (web) tidak memuat kalimat jumlah kolom bacaan");

    foreach ($m[1] as $found) {
        expect(trim($found))->toBe($expected,
            "laporan {$label} (web) menulis \"{$found}\" kolom bacaan, padahal READING_FIELDS memuat ".count($fields));
    }
})->with(readingCountSurfaces());

it('angka kolom bacaan pada laporan MOBILE sama dengan READING_FIELDS', function (string $label, array $fields, string $slug) {
    // Satu-satunya uji di suite ini yang membaca berkas mobile. Tanpanya,
    // kedua sisi dapat berselisih dan tidak satu suite pun menyadarinya —
    // persis cara defek ini bertahan.
    $path = base_path("../mobile/src/views/Laporan{$label}View.vue");
    expect(file_exists($path))->toBeTrue("view laporan {$label} tidak ditemukan: {$path}");

    $vue = file_get_contents($path);
    $expected = readingCountWords()[count($fields)] ?? null;

    preg_match_all('/satu dari ([a-z ]+?) kolom bacaan/u', $vue, $m);

    expect($m[1])->not->toBeEmpty("laporan {$label} (mobile) tidak memuat kalimat jumlah kolom bacaan");

    foreach ($m[1] as $found) {
        expect(trim($found))->toBe($expected,
            "laporan {$label} (mobile) menulis \"{$found}\" kolom bacaan, padahal READING_FIELDS memuat ".count($fields));
    }
})->with(readingCountSurfaces());

it('web dan mobile menulis angka yang SAMA untuk stasiun yang sama', function (string $label, array $fields, string $slug) {
    // Penjaga kesetaraan. Kedua uji di atas dapat lulus sendiri-sendiri bila
    // keduanya salah dengan cara yang sama; uji ini menangkap keadaan
    // sebaliknya — satu sisi diperbaiki, satu sisi tertinggal, yang justru
    // bentuk defek ini saat ditemukan.
    $html = file_get_contents(resource_path("views/livewire/dashboard/laporan-{$slug}.blade.php"));
    $vue = file_get_contents(base_path("../mobile/src/views/Laporan{$label}View.vue"));

    preg_match('/satu dari ([a-z ]+?) kolom bacaan/u', $html, $w);
    preg_match('/satu dari ([a-z ]+?) kolom bacaan/u', $vue, $m);

    expect(trim($m[1]))->toBe(trim($w[1]),
        "laporan {$label}: web menulis \"{$w[1]}\", mobile menulis \"{$m[1]}\"");
})->with(readingCountSurfaces());
