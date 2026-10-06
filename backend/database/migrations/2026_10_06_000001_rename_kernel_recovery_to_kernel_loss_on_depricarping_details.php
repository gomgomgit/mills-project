<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * depricarping_details.kernel_recovery_in_fibre_percent
 *   -> kernel_loss_in_fibre_percent
 *
 * MENYELESAIKAN PERTENTANGAN ARAH yang ditemukan saat membangun laporan
 * periode Depricarping (screen-152 / screen-153) pada 2026-10-06.
 *
 * APA YANG BERTENTANGAN. depricarping_operational_targets memuat satu baris
 * bernama 'Kernel Loss in Fibre' dengan target '< 0.50%', batas kritis
 * '> 1.00%', dan justifikasi 'Direct operational revenue loss. Signifies an
 * unstable pneumatic lifting balance or unstripped cake clumps.' — itu
 * menggambarkan KEHILANGAN: kernel yang lolos terbawa aliran fibre, makin
 * kecil makin baik. Kolomnya justru bernama `recovery`, dan keempat layar
 * input/detail Depricarping melabelinya 'Kernel Recovery in Fibre' — sebuah
 * PEROLEHAN, yang lazimnya makin besar makin baik.
 *
 * MENGAPA ITU BUKAN SEKADAR SOAL PENAMAAN. Kedua pembacaan menuntut skala
 * angka yang berbeda sekitar dua ratus kali: sebagai kehilangan nilai wajarnya
 * 0-2%, sebagai perolehan 90-100%. Memasangkan kolom itu dengan standarnya
 * tanpa menyelesaikan arahnya akan membuat laporan menyatakan "98%, jauh di
 * atas batas kritis > 1.00%" pada kondisi yang sebenarnya sangat baik —
 * penilaian dengan arah TERBALIK, pada angka yang tetap terlihat masuk akal.
 * Karena itu laporan sengaja TIDAK memetakannya sampai keputusan ini diambil.
 *
 * MENGAPA MASTER YANG DIMENANGKAN. Tiga hal: justifikasi master sendiri
 * berbicara tentang kernel yang terbuang; 'kernel loss in fibre' adalah KPI
 * baku pabrik CPO sementara 'kernel recovery' lazimnya diukur di Kernel Plant
 * dan bukan 'di fibre'; dan target '< 0.50%' beserta batas '> 1.00%' hanya
 * masuk akal untuk sebuah kehilangan. Diputuskan user pada 2026-10-06.
 *
 * JANGAN DIKEMBALIKAN. Bila suatu saat tampak bahwa yang diinput Operator
 * memang perolehan, yang perlu diubah adalah MASTER-nya (nama parameter,
 * rentang, batas, dan justifikasinya sekaligus) — bukan kolom ini sendiri,
 * karena kolom yang berdiri sendiri tanpa standar justru keadaan yang baru
 * saja kita tinggalkan.
 *
 * AMAN TERHADAP DATA. rename mempertahankan isi kolom, dan pada saat migrasi
 * ini ditulis depricarping_details masih kosong di basis data dev (0 baris) —
 * formulir Depricarping belum pernah dipakai. Jadi tidak ada nilai yang
 * maknanya berubah akibat rename ini; yang berubah hanya namanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('depricarping_details', function (Blueprint $table) {
            $table->renameColumn('kernel_recovery_in_fibre_percent', 'kernel_loss_in_fibre_percent');
        });
    }

    public function down(): void
    {
        Schema::table('depricarping_details', function (Blueprint $table) {
            $table->renameColumn('kernel_loss_in_fibre_percent', 'kernel_recovery_in_fibre_percent');
        });
    }
};
