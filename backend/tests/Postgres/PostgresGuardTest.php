<?php

/**
 * PostgresGuardTest — memastikan irisan ini benar-benar berjalan di
 * PostgreSQL, dan di database yang benar (2026-10-07).
 *
 * Dua kegagalan yang dicegahnya, dan keduanya diam:
 *
 *   1. Dijalankan dengan phpunit.xml biasa, seluruh berkas di tests/Postgres/
 *      akan berjalan di SQLite dan LOLOS SEMUA — tanpa menguji apa pun yang
 *      dimaksudkannya. Suite hijau yang tidak menguji apa-apa lebih buruk
 *      daripada suite yang tidak ada, karena ia dipercaya.
 *   2. Menunjuk database dev atau e2e. Test di sini memakai RefreshDatabase,
 *      yang MENJALANKAN MIGRASI — diarahkan ke mill_smart_log ia akan
 *      membuang data kerja, diarahkan ke mill_smart_log_e2e ia akan merusak
 *      fixture 87 spec browser.
 *
 * Berkas ini dinamai agar berjalan lebih dulu secara alfabetis di dalam
 * tests/Postgres/, tetapi tidak bergantung pada urutan itu: setiap berkas
 * lain di irisan ini juga memakai trait yang sama.
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('benar-benar berjalan di PostgreSQL, bukan SQLite', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql');

    // Bukti dari mesinnya sendiri, bukan dari konfigurasi: hanya PostgreSQL
    // yang menjawab version() dengan string ini.
    expect(DB::selectOne('select version() as v')->v)->toContain('PostgreSQL');
});

it('memakai database uji tersendiri, bukan dev dan bukan e2e', function () {
    $database = DB::connection()->getDatabaseName();

    expect($database)->toEndWith('_pgtest');

    // Dinyatakan eksplisit, bukan hanya tersirat dari akhiran di atas —
    // RefreshDatabase menjalankan migrasi, dan dua nama ini yang paling
    // mahal bila tertukar.
    expect($database)->not->toBe('mill_smart_log');
    expect($database)->not->toBe('mill_smart_log_e2e');
});

it('tipe kolom uuid benar-benar uuid di sini, bukan varchar seperti di SQLite', function () {
    // Inilah akar seluruh irisan ini. Di SQLite `$table->uuid()` menjadi
    // varchar dan '' diterima diam-diam; di sini ia uuid sungguhan dan ''
    // ditolak. Bila asersi ini gagal, setiap test lain di irisan ini
    // kehilangan maknanya.
    $type = DB::selectOne(
        "select data_type from information_schema.columns
         where table_name = 'companies' and column_name = 'corporate_id'"
    )->data_type;

    expect($type)->toBe('uuid');
});
