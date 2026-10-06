<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Menolak berjalan bila tidak ada driver coverage terpasang, supaya ambang
 * cakupan test-strategy tidak lagi lolos secara HAMPA (2026-10-06).
 *
 * ── MASALAHNYA ───────────────────────────────────────────────────────────
 *
 * `php artisan test --coverage --min=80` TIDAK galat ketika Xdebug maupun
 * PCOV tidak terpasang. Ia diam-diam no-op: tanpa tabel cakupan, tanpa
 * peringatan, tanpa galat — dan KELUAR DENGAN KODE 0. Jadi gerbang apa pun
 * yang bersandar pada perintah itu MELAPORKAN SUKSES tanpa mengukur apa pun,
 * dan justru paling mungkin melakukannya di mesin yang paling kurang
 * terkonfigurasi.
 *
 * Ini bukan temuan baru dan bukan milik satu layar. Enam artefak implement
 * mencatatnya secara terpisah — screen-130, 131, 132, 133, 136, dan 141 —
 * masing-masing menemukannya ulang dan menuliskannya sebagai known issue,
 * dan tidak satu pun berujung pada penjaga. Perintah inilah penjaganya.
 *
 * ── KENAPA BUKAN SEBUAH TEST ─────────────────────────────────────────────
 *
 * Sudah dicoba dan TIDAK BISA: `--coverage` ditangani oleh perintah `test`
 * milik Laravel sendiri dan TIDAK diteruskan ke proses anak — argv yang
 * diterima Pest hanya memuat `--configuration` dan `--no-output`. Jadi tidak
 * ada test yang dapat mengetahui bahwa cakupan sedang diminta, dan test yang
 * gagal hanya karena driver tidak ada akan memerahkan suite di setiap mesin
 * pengembang tanpa ada yang meminta cakupan.
 *
 * Penjaganya karena itu harus berada di JALUR PEMANGGILAN, bukan di dalam
 * suite. Pakai lewat composer, yang berhenti pada skrip pertama yang keluar
 * tidak nol:
 *
 *     composer test:coverage
 *
 * ── KENAPA TIDAK SEKALIAN MEMASANG DRIVERNYA ─────────────────────────────
 *
 * Memasang Xdebug/PCOV adalah konfigurasi mesin (pecl/apt, php.ini), bukan
 * isi repositori — ia tidak dapat di-commit, dan mesin berikutnya akan
 * kembali tanpa driver. Yang dapat di-commit adalah penolakan untuk berpura-
 * pura sudah mengukur.
 */
class CoverageGate extends Command
{
    protected $signature = 'coverage:gate';

    protected $description = 'Gagal bila tidak ada driver coverage (Xdebug/PCOV), supaya --min tidak lolos tanpa mengukur';

    public function handle(): int
    {
        $drivers = array_filter([
            'Xdebug' => extension_loaded('xdebug'),
            'PCOV' => extension_loaded('pcov'),
        ]);

        if ($drivers !== []) {
            $this->info('Driver coverage tersedia: '.implode(', ', array_keys($drivers)).'.');

            return self::SUCCESS;
        }

        $this->error('Tidak ada driver coverage terpasang (Xdebug maupun PCOV).');
        $this->newLine();
        $this->line('Ambang cakupan karena itu TIDAK DAPAT dievaluasi. Perintah ini sengaja');
        $this->line('gagal alih-alih membiarkan `--min` lolos tanpa mengukur apa pun:');
        $this->line('`php artisan test --coverage --min=80` keluar dengan kode 0 dan diam');
        $this->line('ketika drivernya tidak ada, sehingga gerbang mutu melaporkan sukses');
        $this->line('justru di mesin yang paling kurang terkonfigurasi.');
        $this->newLine();
        $this->line('Pasang salah satunya, lalu ulangi:');
        $this->line('  pecl install pcov     && echo "extension=pcov.so" >> php.ini     # ringan, cukup untuk cakupan');
        $this->line('  pecl install xdebug   && echo "zend_extension=xdebug.so" >> php.ini');
        $this->newLine();
        $this->line('Menjalankan suite TANPA gerbang cakupan tetap sah dan tidak terpengaruh:');
        $this->line('  php artisan test');

        return self::FAILURE;
    }
}
