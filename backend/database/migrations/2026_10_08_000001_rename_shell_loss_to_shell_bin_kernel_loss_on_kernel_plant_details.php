<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * kernel_plant_details.shell_loss_percent
 *   -> shell_bin_kernel_loss_percent
 *
 * ────────────────────────────────────────────────────────────────────────
 * MENGAPA, DAN BAGAIMANA BEDANYA DARI RENAME DEPRICARPING
 * ────────────────────────────────────────────────────────────────────────
 * Standar masternya bernama "Shell Bin Kernel Loss" dengan target
 * "<= 1.5% (Maximized separation recovery)" — yang diukur adalah KERNEL YANG
 * TERBUANG KE SHELL BIN. Kolomnya bernama `shell_loss_percent`, yang menamai
 * ZAT YANG SALAH: ia terbaca sebagai kehilangan SHELL, bukan kehilangan
 * KERNEL ke dalam shell. Keduanya kuantitas yang berbeda di pabrik CPO, dan
 * yang satu bukan pengganti yang lain.
 *
 * INI BUKAN KASUS DEPRICARPING, dan perbedaannya penting supaya tidak
 * ditangani lebih keras daripada seharusnya. Di sana `recovery` vs `loss`
 * berarti ARAH angkanya berlawanan, dengan skala sekitar dua ratus kali
 * berbeda (0-2% untuk kehilangan, 90-100% untuk perolehan), sehingga
 * memasangkan angka dengan standarnya akan mengumumkan pelanggaran atas
 * stasiun yang berkinerja baik. Di sini TIDAK ADA pertentangan arah: nama
 * lama dan standarnya sama-sama sebuah kehilangan dalam persen, makin kecil
 * makin baik, dan "<= 1.5%" masuk akal untuk keduanya. Yang salah hanyalah
 * zat yang disebut namanya.
 *
 * Akibatnya ia tidak pernah menghasilkan angka yang salah — ia hanya
 * menunggu seseorang membacanya sebagai kehilangan shell lalu memasangkannya
 * dengan perolehan shell. Laporan periode SUDAH menerbitkannya sebagai
 * "Shell Bin Kernel Loss" (KernelPlantReportService::METRIC_LABELS dan
 * COLUMN_TARGET_PARAMETER), jadi sesudah migrasi ini nama kolom berhenti
 * menjadi satu-satunya tempat yang masih membawa nama yang menyesatkan.
 *
 * Keputusan penamaannya mengikuti preseden yang sudah diambil user pada
 * 2026-10-06 untuk Depricarping: MEMAKAI PENAMAAN MASTER, dan kolomnya
 * di-rename.
 *
 * ────────────────────────────────────────────────────────────────────────
 * AMAN TERHADAP DATA
 * ────────────────────────────────────────────────────────────────────────
 * rename mempertahankan isi kolom, dan maknanya TIDAK berubah oleh migrasi
 * ini — nilai yang sudah tercatat tetap kehilangan kernel dalam persen,
 * hanya namanya yang kini menyebutnya dengan benar. Pada saat migrasi ini
 * ditulis kernel_plant_records dan kernel_plant_details masih kosong di
 * basis data dev (0 baris).
 *
 * Tetapi tabel server yang kosong TIDAK membuktikan tidak ada perangkat yang
 * memegang draft dengan nama kolom lama — draft hidup di SQLite perangkat,
 * bukan di sini. Karena itu KernelPlantRecordService::normalizeDetails()
 * menerima kunci lama sebagai alias untuk satu rilis; lihat komentarnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kernel_plant_details', function (Blueprint $table) {
            $table->renameColumn('shell_loss_percent', 'shell_bin_kernel_loss_percent');
        });
    }

    public function down(): void
    {
        Schema::table('kernel_plant_details', function (Blueprint $table) {
            $table->renameColumn('shell_bin_kernel_loss_percent', 'shell_loss_percent');
        });
    }
};
