<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan `shift_id` (nullable, selamanya) ke ke-18 tabel `*_records`.
 *
 * MENGAPA KOLOMNYA ADA
 * --------------------
 * Jadwal Shift per Production Line sudah dapat didefinisikan sejak
 * screen-170, tetapi sampai migrasi ini ia belum punya satu pun pembaca:
 * jadwalnya ada, dan tidak ada data yang terhubung kepadanya. Kolom inilah
 * penghubungnya — ia menjawab "record ini dihasilkan pada shift mana", dan
 * dari situ laporan per-shift serta gerbang sinkronisasi memperoleh
 * dasarnya.
 *
 * NULLABLE SELAMANYA, DAN TIDAK ADA BACKFILL
 * ------------------------------------------
 * Berbeda dari seri `production_line_id` (2026_09_28_000041/42/43) yang
 * bertahap nullable -> backfill -> NOT NULL, kolom ini BERHENTI di langkah
 * pertama, dan itu bukan pekerjaan yang belum selesai. Dua alasan, keduanya
 * permanen:
 *
 *   1. TIDAK ADA KEBENARAN UNTUK DI-BACKFILL. `production_line_id` dapat
 *      diturunkan dari `station.production_line_id` karena stasiunnya memang
 *      selalu berada di suatu line. Shift tidak punya padanan itu: menebak
 *      shift sebuah record lama dari `created_at` berarti menebak dari WAKTU
 *      SINKRONISASI, bukan waktu kerja — dan sinkronisasi memang terjadi
 *      setelah shift berakhir. Tebakan itu akan terlihat masuk akal dan salah
 *      secara sistematis.
 *   2. CELAH JADWAL ITU SAH. Kelola Shift sengaja MENGIZINKAN celah dan hanya
 *      menandainya; sebuah line juga boleh belum punya shift sama sekali.
 *      Record yang disimpan pada saat seperti itu akan selalu lahir tanpa
 *      shift, dan menolak menyimpannya berarti menukar data lapangan dengan
 *      kelengkapan master — menghukum orang yang tidak dapat memperbaikinya.
 *      (Keputusan pemilik produk 2026-10-16.)
 *
 * Konsekuensinya mengikat pembacanya: setiap laporan yang mengelompokkan
 * menurut shift WAJIB punya kelompok 'tanpa shift', dan kelompok itu bukan
 * kasus tepi melainkan keadaan setiap baris yang sudah ada hari ini.
 *
 * SIAPA YANG MENGISINYA — DAN INI INVERSI YANG DISENGAJA
 * ------------------------------------------------------
 * `production_line_id` TIDAK PERNAH dibaca dari request: server menulisnya
 * sendiri dari stasiun yang sudah ia resolve. `shift_id` justru sebaliknya
 * untuk jalur mobile — PERANGKAT yang menyetempelnya, dan server menerimanya.
 * Inversi itu perlu dan disengaja: server hanya tahu kapan sebuah record
 * DISINKRONKAN, sementara yang ditanyakan adalah kapan operator menekan
 * Simpan, dan jarak antara keduanya memang berjam-jam secara rancangan.
 *
 * Karena nilainya datang dari klien, server WAJIB memvalidasinya: shift
 * kiriman harus benar-benar milik `production_line_id` record itu. Tanpa
 * pemeriksaan itu, klien yang keliru dapat menandai record dengan shift line
 * lain, dan laporan per-line akan memuat shift yang bukan miliknya.
 *
 * Jalur web berbeda dan lebih sederhana: form web menyimpan langsung di
 * server, jadi `shift_id`-nya diturunkan dari jam simpan di server.
 * BATASNYA DINYATAKAN DI SINI supaya tidak ditemukan ulang sebagai kejutan:
 * form web punya field Tanggal yang BOLEH DIMUNDURKAN, sehingga input susulan
 * akan tertandai shift saat input — bukan shift saat kejadian. Itu pilihan
 * sadar pemilik produk 2026-10-16, bukan cacat yang terlewat.
 *
 * ON DELETE RESTRICT, BUKAN CASCADE MAUPUN SET NULL
 * -------------------------------------------------
 * Sama dengan `station_id` dan `production_line_id` pada ke-18 tabel yang
 * sama. Record operasional adalah BUKTI pekerjaan yang sudah terjadi;
 * menghapus definisi shift tidak boleh menghapus maupun melupakan buktinya.
 * Dalam praktik batasan ini hampir tidak pernah tersentuh — penghapusan shift
 * adalah SOFT DELETE (`shifts.deleted_at`), barisnya ditutup bukan dibuang,
 * justru supaya rujukan ini tetap dapat diselesaikan. RESTRICT adalah jaring
 * di bawahnya, untuk penghapusan paksa yang melewati jalur aplikasi.
 *
 * INDEX
 * -----
 * Satu index kolom tunggal per tabel. PostgreSQL TIDAK membuat index otomatis
 * untuk kolom foreign key, sehingga tanpa ini setiap pemeriksaan FK pada
 * penghapusan shift memindai 18 tabel secara berurutan. Ia juga bentuk yang
 * dipakai pembaca pertamanya: "seluruh record milik shift ini". Nama index
 * terpanjang (process_quality_control_records_shift_id_index, 46 karakter)
 * masih jauh di bawah batas 63 karakter PostgreSQL.
 */
return new class extends Migration
{
    /**
     * Ke-18 tabel record. Daftar yang sama persis dengan seri
     * production_line_id (2026_09_28_000041), disalin utuh dan bukan
     * diturunkan dari enum, karena yang dibicarakan di sini adalah NAMA
     * TABEL — dan nama tabel adalah milik migrasi, bukan milik kode aplikasi
     * yang boleh berubah di bawahnya.
     *
     * @var list<string>
     */
    private const TABLES = [
        'boiler_room_records',
        'cages_track_records',
        'clarification_records',
        'cpo_dispatch_records',
        'depricarping_records',
        'effluent_plant_records',
        'engine_room_records',
        'grading_records',
        'kernel_dispatch_records',
        'kernel_plant_records',
        'pressing_records',
        'process_quality_control_records',
        'process_water_records',
        'solid_waste_disposal_records',
        'sterilizer_records',
        'storage_tank_records',
        'threshing_records',
        'weighbridge_records',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignUuid('shift_id')
                    ->nullable()
                    ->after('production_line_id')
                    ->constrained('shifts')
                    ->restrictOnDelete();

                $blueprint->index('shift_id');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                // STRING, BUKAN ARRAY — dan perbedaannya tidak kelihatan
                // sampai rollback dijalankan. dropForeign(['nama']) membaca
                // isinya sebagai DAFTAR KOLOM lalu menyusun nama constraint
                // darinya, menghasilkan
                // "boiler_room_records_boiler_room_records_shift_id_foreign_foreign"
                // dan gagal dengan "constraint does not exist". Bentuk string
                // memakai nilainya sebagai NAMA, yang memang yang dimaksud.
                // Terbukti dengan menjalankan rollback-nya, bukan dengan
                // membacanya; sebuah down() yang tidak pernah dijalankan
                // adalah down() yang rusak dan belum ketahuan.
                //
                // Urutannya mengikat pada PostgreSQL: constraint foreign key
                // dilepas sebelum kolomnya.
                $blueprint->dropForeign($table.'_shift_id_foreign');
                $blueprint->dropIndex($table.'_shift_id_index');
                $blueprint->dropColumn('shift_id');
            });
        }
    }
};
