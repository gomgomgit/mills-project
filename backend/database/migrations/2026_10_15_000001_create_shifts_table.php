<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * shifts — Jadwal shift kerja PER PRODUCTION LINE.
 * entity-catalog: entitas `shift` (screen-170--kelola-shift /
 * usecase-176--kelola-shift).
 *
 * INDUKNYA PRODUCTION LINE, BUKAN BUSINESS UNIT. Satu mill dapat punya
 * belasan line dengan jadwal kerja yang berbeda-beda, jadi jadwal tingkat
 * mill akan salah untuk sebagian besar line-nya. FK-nya karena itu menunjuk
 * `production_lines` dengan cascade — pola yang sama dengan tabel anak
 * Production Line lainnya (`stations`).
 *
 * TIDAK ADA KOLOM HARI, TANGGAL BERLAKU, MAUPUN POLA BERGILIR, dan
 * ketiadaan itu DISENGAJA (keputusan pemilik produk 2026-10-15): jam mulai
 * dan jam selesai tiap shift sama persis setiap hari, sepanjang tahun. Yang
 * bergilir di lapangan adalah REGU yang mengisi shift, bukan batas jamnya,
 * dan penugasan regu kelak adalah entitasnya sendiri — bukan perluasan
 * tabel ini. Jangan "melengkapi" skema ini dengan dimensi hari.
 *
 * `start_time` / `end_time` bertipe TIME. end_time BOLEH lebih awal daripada
 * start_time: itu berarti shift melewati tengah malam (23:00 → 07:00), bentuk
 * paling umum untuk shift malam.
 *
 * TUMPANG TINDIH TIDAK DAPAT DITEGAKKAN INDEX. Dua rentang jam yang
 * beririsan bukan duplikasi nilai, jadi tidak ada UNIQUE yang dapat
 * menangkapnya — dan exclusion constraint PostgreSQL tidak dipakai karena
 * suite test berjalan di SQLite. Penegakannya ada di lapisan aplikasi
 * (ShiftService::guardAgainstOverlap()), sama seperti aturan tumpang tindih
 * rentang tanggal pada `periods` (lihat 2026_09_22_000030).
 *
 * UNIQUE(production_line_id, name) — nama shift unik DI DALAM satu line;
 * 'Pagi' di Line 1 dan 'Pagi' di Line 2 adalah dua shift berbeda dan itu
 * normal. Keduanya NOT NULL, jadi tidak ada jebakan NULL-tidak-pernah-sama
 * seperti pada `periods.station_type`.
 *
 * `created_by`/`updated_by` nullable FK-to-users, mengikuti konvensi
 * `production_lines` (induknya) dan `mill_settings`.
 *
 * DDL-nya sengaja portabel: dijalankan sama di PostgreSQL (produksi) dan
 * SQLite (suite test) — tidak ada tipe maupun constraint khas PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('production_line_id')->constrained('production_lines')->cascadeOnDelete();
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');

            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Keunikan nama PER LINE (constraint entity-catalog #1).
            $table->unique(['production_line_id', 'name']);

            // Kueri terpanas: seluruh shift satu line, untuk pemeriksaan
            // tumpang tindih dan untuk menghitung cakupan 24 jam.
            $table->index(['production_line_id', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
