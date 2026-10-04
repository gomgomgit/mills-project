<?php

return [
    'name' => env('APP_NAME', 'Mills Smart Log'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    // Zona waktu aplikasi = WIB. Seluruh operasional mill berjalan di WIB,
    // kolom timestamp di database NAIF (tanpa zona), dan PostgreSQL dev
    // memakai sesi Asia/Jakarta. Dengan 'UTC' (sampai 2026-10-04) jam
    // Weighbridge dari mobile tampil 7 jam mundur, default Form Weighbridge,
    // "Dibuat Pada"/"Waktu Ditutup", "Periode Terbuka Hari Ini" (00:00-07:00
    // WIB masih "kemarin") dan nama file ekspor semuanya memakai jam UTC.
    // Input datetime ber-offset dari API dinormalkan ke zona ini sebelum
    // disimpan — lihat App\Support\AppTime::normalizeClientDateTime().
    'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta'),
    // Batas atas tanggal kejadian data stasiun: paling jauh N hari setelah
    // hari ini (zona aplikasi), inklusif. Default 1 (= besok). Kosongkan
    // untuk mematikan batasnya — hanya untuk lingkungan uji yang sengaja
    // menanam data di tahun jauh (lihat App\Support\AppTime::latestEventDate()).
    'event_date_max_days_ahead' => env('EVENT_DATE_MAX_DAYS_AHEAD', 1),
    'locale' => 'id',
    'fallback_locale' => 'en',
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',
];
