<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menghapus record stasiun bertanggal JAUH DI MASA DEPAN — data sisa
 * browser test, bukan data pabrik.
 *
 * KENAPA PERINTAH INI ADA. Aplikasi ini sengaja tidak punya jalur hapus
 * untuk record stasiun: sebuah log sheet yang sudah masuk tidak boleh
 * lenyap. Konsekuensinya, setiap browser test yang menanam record
 * meninggalkannya di database dev SELAMANYA. Penulis spec e2e sudah tahu
 * ini dan menyiasatinya dengan memberi tiap run jalur tanggalnya sendiri
 * (lihat RUN_OFFSET di e2e-web/tests/laporan-*.spec.ts: detik jam dinding
 * dikali 120 hari), sehingga run lama tidak ikut terhitung oleh run baru.
 * Yang tersisa adalah tumpukan yang tidak pernah menyusut — 841 baris saat
 * perintah ini ditulis, tersebar dari tahun 2699 sampai 9258.
 *
 * KENAPA AMBANG TAHUN, BUKAN PREFIX. Record stasiun tidak punya kolom nama
 * yang bisa diberi awalan seperti periode pelaporan (deletePeriodsByPrefix
 * di e2e-web/tests/support/periods.ts). Yang membedakan data test dari data
 * pabrik hanyalah tanggalnya, dan bedanya menganga: data sungguhan ada di
 * tahun berjalan, data test di tahun 2600 ke atas. Tidak ada pabrik yang
 * mencatat log sheet untuk abad ke-27.
 *
 * TIGA PENJAGA, karena perintah ini menghapus dan tidak dapat dibatalkan:
 *   1. Menolak berjalan di environment production, tanpa kecuali — --force
 *      sekalipun tidak membukanya.
 *   2. Menolak ambang tahun di bawah MINIMUM_YEAR. Tanpa ini sebuah salah
 *      ketik seperti --year=2026 akan menghapus seluruh data pabrik.
 *   3. Tanpa --force perintah ini hanya MENGHITUNG dan tidak menghapus
 *      apa pun. Menghapus harus disengaja, bukan efek samping.
 *
 * Baris detail ikut terhapus dengan sendirinya: setiap FK detail ke tabel
 * record memakai ON DELETE CASCADE (diperiksa di information_schema, bukan
 * diasumsikan).
 */
class PruneE2eRecords extends Command
{
    /**
     * Ambang paling rendah yang boleh diminta. Data pabrik sungguhan ada
     * di tahun berjalan; jarak ratusan tahun ini yang membuat perintah
     * ini mustahil salah sasaran.
     */
    private const MINIMUM_YEAR = 2400;

    protected $signature = 'e2e:prune-records
        {--year=2600 : Hapus record bertanggal 1 Januari tahun ini ke atas}
        {--force : Benar-benar menghapus; tanpa ini hanya menghitung}';

    protected $description = 'Hapus record stasiun sisa browser test (bertanggal jauh di masa depan)';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Ditolak: perintah ini tidak pernah berjalan di production.');

            return self::FAILURE;
        }

        $year = (int) $this->option('year');

        if ($year < self::MINIMUM_YEAR) {
            $this->error(sprintf(
                'Ditolak: --year=%d di bawah ambang aman %d. Data pabrik sungguhan ada di tahun berjalan.',
                $year,
                self::MINIMUM_YEAR,
            ));

            return self::FAILURE;
        }

        $cutoff = sprintf('%04d-01-01', $year);
        $force = (bool) $this->option('force');

        $total = 0;
        $affected = [];

        foreach ($this->datedRecordTables() as $table) {
            $count = DB::table($table)->whereDate('date', '>=', $cutoff)->count();

            if ($count === 0) {
                continue;
            }

            $total += $count;
            $affected[$table] = $count;
        }

        if ($total === 0) {
            $this->info(sprintf('Tidak ada record bertanggal %s ke atas. Tidak ada yang dihapus.', $cutoff));

            return self::SUCCESS;
        }

        foreach ($affected as $table => $count) {
            $this->line(sprintf('  %-34s %d', $table, $count));
        }

        if (! $force) {
            $this->warn(sprintf(
                '%d record bertanggal %s ke atas. Jalankan ulang dengan --force untuk menghapus.',
                $total,
                $cutoff,
            ));

            return self::SUCCESS;
        }

        // Satu transaksi: kalau satu tabel gagal, tidak ada tabel yang
        // setengah terhapus.
        DB::transaction(function () use ($affected, $cutoff): void {
            foreach (array_keys($affected) as $table) {
                DB::table($table)->whereDate('date', '>=', $cutoff)->delete();
            }
        });

        $this->info(sprintf('%d record dihapus (baris detail ikut lewat ON DELETE CASCADE).', $total));

        return self::SUCCESS;
    }

    /**
     * Tabel *_records yang benar-benar PUNYA kolom `date`, dibaca dari
     * skema. weighbridge_records tidak punya, dan menebak nama kolom
     * adalah cara termudah menghapus tabel yang salah. Membaca skema juga
     * membuat perintah ini otomatis mencakup stasiun yang ditambahkan
     * kelak, tanpa daftar yang harus diingat untuk diperbarui.
     *
     * Dibaca lewat Schema builder, BUKAN information_schema: dev dan
     * production memakai PostgreSQL sementara test berjalan di SQLite
     * in-memory (phpunit.xml), dan kueri information_schema akan meledak
     * di sana — sehingga penjaga-penjaga di atas justru tidak dapat diuji.
     *
     * @return list<string>
     */
    private function datedRecordTables(): array
    {
        $tables = array_filter(
            Schema::getTableListing(),
            static fn (string $table): bool => str_ends_with($table, '_records'),
        );

        // Sebagian driver mengembalikan nama ber-prefix skema ("public.x").
        $tables = array_map(
            static fn (string $table): string => str_contains($table, '.')
                ? substr($table, strrpos($table, '.') + 1)
                : $table,
            $tables,
        );

        $tables = array_values(array_filter(
            $tables,
            static fn (string $table): bool => Schema::hasColumn($table, 'date'),
        ));

        sort($tables);

        return $tables;
    }
}
