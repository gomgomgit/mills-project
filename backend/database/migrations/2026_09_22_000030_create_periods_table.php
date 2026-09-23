<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * periods — Periode Pelaporan per mill (Business Unit) + jenis stasiun.
 * entity-catalog: entitas `period` (screen-128--kelola-periode-pelaporan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_unit_id')->constrained('business_units')->cascadeOnDelete();

            // FK ke station_types.code (lihat migration 000029), BUKAN
            // $table->enum(). Alasannya sama seperti stations.type: enum
            // DB-level sudah tiga kali memaksa migration yang isinya hanya
            // melebarkan CHECK constraint. Dengan tabel master, menambah
            // jenis stasiun cukup INSERT satu baris.
            //
            // NULL = periode berlaku untuk SEMUA jenis stasiun di mill itu.
            // FK mengizinkan NULL, jadi cakupan 'semua stasiun' tetap valid
            // tanpa baris khusus di station_types.
            $table->string('station_type')->nullable();

            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('draft');
            $table->foreignUuid('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Dipakai dua query terpanas: pengecekan tumpang tindih rentang
            // saat create/update periode, dan pencarian periode yang memuat
            // sebuah tanggal record (kunci periode tertutup).
            $table->index(['business_unit_id', 'station_type', 'start_date', 'end_date']);

            // Keunikan nama per cakupan (business_unit_id, station_type).
            // CATATAN: di PostgreSQL, UNIQUE memperlakukan setiap NULL sebagai
            // nilai yang berbeda, sehingga nama duplikat pada periode ber-
            // station_type NULL TIDAK tertangkap constraint ini. Penegakan
            // sesungguhnya ada di layer service (PeriodService memvalidasi
            // keunikan nama sebelum insert/update) — jangan mengandalkan DB
            // untuk kasus station_type NULL tersebut.
            $table->unique(['business_unit_id', 'station_type', 'name']);

            // Integritas jenis stasiun ditegakkan DB, bukan hanya validasi
            // aplikasi: station_type harus ada di master station_types.
            // restrictOnDelete — sebuah jenis stasiun yang masih dipakai
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
        Schema::dropIfExists('periods');
    }
};
