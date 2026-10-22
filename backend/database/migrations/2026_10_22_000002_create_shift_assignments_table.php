<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * shift_assignments — penugasan orang ke SEL (shift × tanggal)
 * (screen-171--jadwal-shift / usecase-177--jadwal-shift, entity-catalog
 * entitas `shift-assignment`).
 *
 * SATU BARIS = SATU ORANG × SATU SHIFT × SATU TANGGAL. Production Line tidak
 * disimpan di sini: ia selalu dapat diturunkan dari `shifts.production_line_id`,
 * dan menyimpannya dua kali hanya membuka peluang keduanya berselisih. Satu
 * sel boleh berisi banyak orang (banyak baris); sel kosong = nol baris.
 *
 * `work_date` ADALAH TANGGAL MULAI SHIFT. Shift lintas tengah malam
 * (23:00 — 07:00 +1) ditaruh pada tanggal ia MULAI, tanpa logika memecah ke
 * dua tanggal. Kolomnya `date` biasa, bukan timestamp, sehingga tidak ada
 * zona waktu yang ikut membelokkan tanggalnya.
 *
 * FK `shift_id` SENGAJA TANPA CASCADE. Shift dihapus dengan soft delete
 * (2026_10_16_000001): baris `shifts`-nya tetap ada, dan histori penugasan
 * sebelum tanggal penutupan harus tetap menunjuk ke baris itu supaya grid
 * dapat menampilkannya read-only. Menambah cascadeOnDelete di sini akan
 * membuang histori itu bila baris shift suatu hari benar-benar dibuang.
 *
 * UNIQUE (shift_id, user_id, work_date) membuat penaruhan ulang orang yang
 * sama pada sel yang sama idempoten — ditulis lewat insertOrIgnore.
 * INDEX (shift_id, work_date) melayani kueri grid satu bulan; INDEX
 * (user_id, work_date) melayani pertanyaan "orang ini bertugas di mana
 * hari itu" (penanda "juga di shift lain hari ini").
 *
 * down() cukup dropIfExists(): menjatuhkan tabel ikut membuang seluruh FK
 * dan index miliknya, sehingga TIDAK ADA dropForeign(['kolom']) yang menyusun
 * nama constraint dari nama kolom (jebakan yang sudah pernah terjadi di
 * migrasi lain). DDL-nya portabel untuk PostgreSQL dan SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shift_id')->constrained('shifts');
            $table->foreignUuid('user_id')->constrained('users');
            $table->date('work_date');
            $table->foreignUuid('created_by')->constrained('users');

            $table->timestamps();

            $table->unique(['shift_id', 'user_id', 'work_date'], 'shift_assignments_cell_user_unique');
            $table->index(['shift_id', 'work_date'], 'shift_assignments_shift_date_index');
            $table->index(['user_id', 'work_date'], 'shift_assignments_user_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_assignments');
    }
};
