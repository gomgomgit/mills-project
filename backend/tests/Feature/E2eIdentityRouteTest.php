<?php

/**
 * E2eIdentityRouteTest — GET /api/e2e/identity hanya ada di environment e2e.
 *
 * Rute itu ditambahkan 2026-10-06 sebagai penjaga suite browser: ia melaporkan
 * `env` dan nama `database` milik server yang MENJAWAB, sehingga
 * e2e-web/tests/support/global-setup.ts dapat membuktikan ia tidak sedang
 * berbicara dengan server dev. Lihat catatan panjangnya di routes/api.php.
 *
 * MENGAPA UJI INI ADA, DAN MENGAPA BENTUKNYA "HARUS 404". Nilai keamanan rute
 * itu seluruhnya bergantung pada ia TIDAK terdaftar di luar environment e2e:
 * /api/health di sebelahnya terbuka tanpa autentikasi, dan nama database serta
 * environment adalah hal yang tidak perlu diketahui siapa pun dari luar.
 * Justru karena itu penjaga suite memakai KEBERADAAN rutenya sebagai bukti —
 * membuatnya tak bersyarat akan sekaligus membocorkan informasi DAN
 * melumpuhkan penjaganya, karena server dev pun akan menjawab 200.
 *
 * Uji ini berjalan di environment `testing`, jadi ia menguji sisi negatifnya.
 * Sisi positifnya (200 + env=e2e + database berakhiran _e2e) dibuktikan setiap
 * kali suite e2e-web jalan: global-setup berhenti dengan pesan yang menyebut
 * sebabnya kalau rutenya tidak menjawab seperti itu.
 */

it('tidak terdaftar di luar environment e2e', function () {
    expect(app()->environment())->toBe('testing');

    $this->getJson('/api/e2e/identity')->assertNotFound();
});

it('tidak menambahkan satu pun field ke /api/health, yang tetap polos', function () {
    $response = $this->getJson('/api/health');

    $response->assertOk();
    $response->assertExactJson(['status' => 'ok']);
});
