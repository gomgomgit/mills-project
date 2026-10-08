<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * depricarping_records.presser_id -> depricarping_id
 *
 * ────────────────────────────────────────────────────────────────────────
 * MENGAPA: SATU-SATUNYA STASIUN YANG MENAMAI MESIN STASIUN LAIN
 * ────────────────────────────────────────────────────────────────────────
 * Keenam belas stasiun yang punya kolom identitas unit menamai mesinnya
 * SENDIRI — thresher_id, sterilizer_id, clarification_id, kernel_plant_id,
 * engine_room_id, storage_tank_id, dan seterusnya. Depricarping sendirian
 * memakai presser_id, yaitu kolom identitas stasiun PRESSING.
 *
 * Dan tidak satu pun pengukurannya milik presser. Keenam standar di
 * DepricarpingOperationalTargetSeeder adalah alat depericarper: Fan Static
 * Pressure, Polishing Drum Speed, Air Velocity (Aspirator), Fibre Moisture
 * Content, Kernel Loss in Fibre, Nut Silo Temperature. Jadi identitas
 * recordnya menyebut mesin press sementara seluruh angkanya milik
 * depericarper.
 *
 * Asal-usulnya tercatat di spec-nya sendiri: screen-043--form-depricarping
 * dibangun "mengikuti pola Form Pressing, disesuaikan dengan kolom bacaan
 * dan tabel referensi Depricarping sendiri". Kolom bacaannya disesuaikan;
 * kolom identitasnya ikut terbawa.
 *
 * ────────────────────────────────────────────────────────────────────────
 * KLAIM YANG DULU MEMBELA NAMA LAMA, DAN MENGAPA IA TIDAK BERTAHAN
 * ────────────────────────────────────────────────────────────────────────
 * Komentar di mobile/src/services/localSchema.ts menyatakan "NOT a typo,
 * both Pressing's and Depricarping's header ID field share this name/label
 * in the source log sheet". Klaim itu tidak didukung apa pun di repo:
 *
 *   - entity-catalog justru menulis "Depricarping ID (DEPERICARPER ID), input
 *     manual" — penulisnya sendiri sudah menandai yang dimaksud.
 *   - PRD tidak menyebut nama kolom identitas ini sama sekali. Klausa
 *     "sesuai log sheet kertas asli" di PRD berlaku untuk TABEL REFERENSI
 *     TARGET, bukan untuk kolom header.
 *
 * Log sheet kertasnya tidak ada di repo, jadi tidak ada yang dapat
 * memutuskannya dari sini; tanda kurung di entity-catalog adalah bukti
 * terbaik yang tersedia, dan ia menunjuk depericarper. Komentar itu
 * diperbaiki pada commit yang sama.
 *
 * ────────────────────────────────────────────────────────────────────────
 * MENGAPA depricarping_id, BUKAN depericarper_id
 * ────────────────────────────────────────────────────────────────────────
 * Keputusan user (2026-10-08). Sejajar dengan kernel_plant_id /
 * engine_room_id / boiler_room_id / process_water_id — kelompok yang
 * menamai STASIUNNYA, bukan mesinnya. Dari 16 kolom identitas, bentuk
 * nama-stasiun itu yang mayoritas; thresher_id/presser_id/sterilizer_id
 * yang menamai mesin justru minoritas.
 *
 * ────────────────────────────────────────────────────────────────────────
 * AMAN TERHADAP DATA
 * ────────────────────────────────────────────────────────────────────────
 * rename mempertahankan isi kolom, dan maknanya tidak berubah: nilainya
 * tetap ID unit yang diketik operator. Pada saat migrasi ini ditulis
 * depricarping_records masih kosong di basis data dev (0 baris).
 *
 * Tabel server yang kosong TIDAK membuktikan tidak ada perangkat yang
 * memegang draft dengan kunci lama — draft hidup di SQLite perangkat.
 * Karena itu DepricarpingRecordService::normalizeForm() menerima kunci lama
 * sebagai alias untuk satu rilis; lihat komentarnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('depricarping_records', function (Blueprint $table) {
            $table->renameColumn('presser_id', 'depricarping_id');
        });
    }

    public function down(): void
    {
        Schema::table('depricarping_records', function (Blueprint $table) {
            $table->renameColumn('depricarping_id', 'presser_id');
        });
    }
};
