<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * period_stations — satu jenis stasiun di dalam satu Periode Pelaporan,
 * beserta status tutup/bukanya. entity-catalog v18: entitas `period-station`.
 *
 * MENGAPA TABEL INI ADA (keputusan user 2026-09-25)
 * Sebelumnya status tutup/buka ada di `periods` bersama nama dan rentang
 * tanggal, sehingga satu periode yang mencakup 17 jenis stasiun menuntut 17
 * baris `periods` dengan nama dan rentang tanggal yang diulang-ulang — dan 17
 * kali menutup. Cakupan periode kini mill saja (seluruh Production Line,
 * seluruh jenis stasiun), sementara PENUTUPAN tetap per jenis stasiun karena
 * stasiun tidak selesai serentak: Admin dapat menutup Sterilizer sementara
 * Clarification masih terbuka. Bentuk data itu memang yang ditemukan di DB dev
 * saat keputusan diambil.
 *
 * Migrasi ini HANYA ADITIF — ia tidak menyentuh `periods` sama sekali. Kolom
 * `station_type`, `status`, `closed_by`, `closed_at` dibuang dari `periods`
 * oleh migrasi terpisah (2026_09_26_000040), yang sengaja diberi timestamp
 * jauh di belakang supaya migrasi KONVERSI DATA masih punya ruang di
 * antaranya. Urutannya wajib: buat tabel ini -> pindahkan datanya -> baru
 * buang kolom lamanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('period_stations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Baris ini tidak punya arti tanpa periodenya: menghapus periode
            // menghapus seluruh barisnya. Yang mencegah penghapusan diam-diam
            // atas stasiun yang sudah dikunci adalah aturan service (periode
            // dengan satu saja stasiun closed tidak boleh dihapus), bukan FK
            // ini.
            $table->foreignUuid('period_id')->constrained('periods')->cascadeOnDelete();

            // FK ke station_types.code (lihat migration 000029), BUKAN
            // $table->enum() — alasannya sama seperti stations.type dan seperti
            // periods.station_type sebelumnya.
            //
            // NOT NULL, dan ini perubahan semantik yang paling penting di
            // migrasi ini: konsep `station_type = NULL` = "berlaku untuk semua
            // jenis stasiun" HILANG sepenuhnya. Cakupan semua-stasiun kini
            // dinyatakan lewat ADANYA satu baris per jenis stasiun, bukan lewat
            // satu baris tanpa jenis. Label "Semua Stasiun" ikut lenyap.
            $table->string('station_type');

            $table->string('status')->default('draft');
            $table->foreignUuid('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            // Tanpa ini satu periode dapat memiliki dua baris untuk jenis
            // stasiun yang sama dengan status berbeda, dan tidak ada jawaban
            // benar atas pertanyaan "stasiun ini tertutup atau tidak".
            // Merangkap indeks untuk sisi probe kueri kunci periode:
            // ps.period_id = p.id AND ps.station_type = ?.
            $table->unique(['period_id', 'station_type']);

            // Sisi sebaliknya dari kueri kunci periode. Bentuk kuerinya:
            //   periods p JOIN period_stations ps ON ps.period_id = p.id
            //   WHERE p.business_unit_id = ? AND ps.station_type = ?
            //     AND ps.status = 'closed' AND <record.date> BETWEEN p.start_date AND p.end_date
            // Perencana boleh mulai dari periods (indeks business_unit_id +
            // tanggal, lalu probe UNIQUE di atas) ATAU mulai dari sini bila
            // stasiun yang ditanya jarang punya baris closed — indeks ini yang
            // melayani jalur kedua, dan sekaligus melayani pertanyaan daftar
            // "stasiun apa saja yang sedang tertutup" tanpa memindai tabel.
            // status ditaruh di belakang station_type karena station_type
            // selalu dibanding kesetaraan, sedangkan status kadang IN (...).
            $table->index(['station_type', 'status']);

            // Integritas jenis stasiun ditegakkan DB, bukan hanya validasi
            // aplikasi. restrictOnDelete — jenis stasiun yang masih dipakai
            // periode tidak boleh dihapus dari master.
            $table->foreign('station_type')
                ->references('code')
                ->on('station_types')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_stations');
    }
};
