<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mencabut `app_name` dan `logo` dari `mill_settings` — keduanya DICABUT dari
 * layar Mills Setting (keputusan user 2026-10-12).
 *
 * Sebabnya: tidak ada satu layar pun yang membacanya. Keduanya tersimpan,
 * terkirim lewat sync, dan tersimpan di basis data perangkat, lalu tidak
 * berpengaruh ke mana-mana — Admin mengubahnya, melihat pesan berhasil, dan
 * tidak ada yang berubah bagi Operator. Kepala halaman Home mobile memakai
 * NAMA PRODUK yang tetap, terpisah dari Nama Aplikasi per mill
 * (business_rules screen-005 v5); pemakaian app_name/logo di sana sempat
 * ditambahkan pada 2026-10-12 mengikuti QA AD-10 lalu dibatalkan. Sesuai
 * aturan screen-034 — "setiap pengaturan di layar ini harus punya layar yang
 * membacanya" — pengaturan tanpa pembaca dicabut, bukan dibiarkan.
 *
 * NILAI YANG SUDAH TERSIMPAN HILANG dan tidak dapat dipulihkan: down() hanya
 * mengembalikan KOLOMNYA, kosong. `app_name` dibuat nullable pada down()
 * meskipun aslinya NOT NULL — mengembalikan NOT NULL pada tabel yang sudah
 * berisi baris akan gagal, karena tidak ada nilai untuk diisikan.
 *
 * Pola dan sebab teknisnya sama dengan
 * 2026_08_20_000007_remove_jumlah_cages_from_mill_settings_table.php:
 * DROP COLUMN menuntut doctrine/dbal (sudah ada di composer.json).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mill_settings', function (Blueprint $table) {
            $table->dropColumn(['app_name', 'logo']);
        });
    }

    public function down(): void
    {
        Schema::table('mill_settings', function (Blueprint $table) {
            // Nullable, bukan NOT NULL seperti aslinya — lihat doc di atas.
            $table->string('app_name')->nullable();
            $table->string('logo')->nullable();
        });
    }
};
