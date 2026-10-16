<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * shifts — PENGHAPUSAN MENJADI SOFT DELETE (keputusan pemilik produk
 * 2026-10-16; screen-170--kelola-shift v2 business_logic 26–28,
 * implementation_notes 19–21, entity-catalog `shift.deleted_at`).
 *
 * MENGAPA BARISNYA DITUTUP ALIH-ALIH DIBUANG: `shift_id` akan mendarat di
 * kedelapan belas tabel record di putaran penandaan berikutnya, bermakna
 * "shift yang berlaku saat operator menyimpan record". Begitu rujukan itu
 * ada, membuang baris shift membuat record lama kehilangan jawaban atas
 * "record ini sebenarnya shift apa" dan laporan historisnya berhenti
 * terbaca. SKEMA PALING MURAH DIUBAH SEBELUM ADA DATA, jadi kolomnya
 * mendarat sekarang — sementara perilaku penggantian-saat-ubah
 * (business_logic 29–30) menunggu sampai pemicunya benar-benar dapat
 * bernilai benar.
 *
 * DUA PERUBAHAN DALAM SATU MIGRASI, dan keduanya memang satu keputusan:
 *
 *  1. `deleted_at` nullable — penanda baris tertutup.
 *  2. UNIQUE(production_line_id, name) DIGANTI PARTIAL UNIQUE INDEX yang
 *     hanya berlaku `WHERE deleted_at IS NULL`.
 *
 * INDEX LAMA DI-DROP LEBIH DULU, DAN ITU BUKAN KERAPIAN. Membiarkan unique
 * index penuh berdampingan dengan yang partial tetap memblokir nama yang
 * dipakai ulang — sebuah 'Pagi' yang ditutup akan memblokir 'Pagi'
 * penggantinya selamanya — dan gejalanya terbaca seperti bug di lapisan
 * aplikasi padahal berasal dari index yang lupa dibuang. Penggantian adalah
 * justru yang paling sering terjadi pada master ini.
 *
 * SATU DDL UNTUK DUA DIALEK — DIVERIFIKASI, BUKAN DIASUMSIKAN. Suite
 * backend berjalan di SQLite in-memory sementara produksi dan basis data
 * e2e memakai PostgreSQL; itu jebakan yang sudah berulang di proyek ini.
 * Partial unique index TIDAK dapat diungkapkan lewat $table->unique(),
 * jadi ia ditulis sebagai SQL mentah — dan `CREATE UNIQUE INDEX ... WHERE
 * deleted_at IS NULL` kebetulan sah di KEDUA dialek (SQLite mendukung
 * partial index sejak 3.8.0, PostgreSQL sejak lama), sehingga TIDAK ADA
 * percabangan driver di sini. Keduanya sudah dijalankan: SQLite lewat
 * RefreshDatabase seluruh suite, PostgreSQL lewat basis data probe sekali
 * pakai.
 *
 * KEUNIKAN TETAP DITEGAKKAN DI LAPISAN APLIKASI JUGA
 * (ShiftService::validate(), Rule::unique()->where(... whereNull
 * deleted_at)), karena index memulangkan galat driver sementara Admin
 * membutuhkan pesan 422 yang menyebut nama yang bentrok — dan karena dua
 * penegak yang sepakat lebih murah daripada satu yang bergantung dialek.
 */
return new class extends Migration
{
    /**
     * Nama index yang diturunkan Laravel dari $table->unique(['production_line_id',
     * 'name']) di 2026_10_15_000001 — tidak diberi nama eksplisit di sana,
     * jadi namanya adalah hasil konvensi: {tabel}_{kolom...}_unique.
     */
    private const UNIQUE_INDEX = 'shifts_production_line_id_name_unique';

    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            // softDeletes() = timestamp nullable 'deleted_at'. KOLOM INI
            // SATU-SATUNYA yang di-cast otomatis oleh model Shift: kolom
            // start_time/end_time sengaja dibiarkan string (lihat docblock
            // model), sementara deleted_at memang sebuah timestamp sungguhan
            // sehingga penanganan trait SoftDeletes atasnya benar.
            $table->softDeletes();
        });

        // Urutannya menentukan: DROP lebih dulu, baru CREATE yang partial.
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropUnique(self::UNIQUE_INDEX);
        });

        DB::statement(
            'CREATE UNIQUE INDEX '.self::UNIQUE_INDEX.' ON shifts (production_line_id, name) '
            .'WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS '.self::UNIQUE_INDEX);

        // Mengembalikan keunikan PENUH. Ini dapat gagal bila sudah ada baris
        // tertutup yang namanya dipakai ulang oleh penggantinya — justru
        // keadaan yang membuat migrasi ini ada. Kegagalan itu benar: ia
        // menyatakan bahwa datanya sudah bergantung pada aturan yang baru,
        // alih-alih membuang diam-diam salah satu barisnya.
        Schema::table('shifts', function (Blueprint $table) {
            $table->unique(['production_line_id', 'name'], self::UNIQUE_INDEX);
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
