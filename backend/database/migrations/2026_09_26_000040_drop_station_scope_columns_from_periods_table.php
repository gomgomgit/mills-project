<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * periods — buang cakupan dan status per jenis stasiun; keduanya pindah ke
 * period_stations (2026_09_26_000031). entity-catalog v18: entitas `period`.
 *
 * Yang dibuang: station_type, status, closed_by, closed_at.
 * Yang berubah:
 *   index  (business_unit_id, station_type, start_date, end_date)
 *          -> (business_unit_id, start_date, end_date)
 *   unique (business_unit_id, station_type, name)
 *          -> (business_unit_id, name)
 *
 * Unique yang baru LEBIH KETAT dan untuk pertama kalinya benar-benar bekerja:
 * di PostgreSQL setiap NULL dianggap nilai berbeda, sehingga dua periode
 * ber-station_type NULL dengan nama sama lolos dari constraint lama dan hanya
 * tertahan di PeriodService.
 *
 * MENGAPA TIMESTAMPNYA 000040, BUKAN 000032
 * Migrasi ini MENGHAPUS data (isi kolom status/station_type baris periods yang
 * sudah ada). Ia harus berjalan SETELAH migrasi konversi data yang memindahkan
 * isi kolom itu menjadi baris period_stations. Migrasi konversi tersebut belum
 * ditulis (ia menunggu aturan tumpang tindih final), jadi slot 000032..000039
 * sengaja dibiarkan kosong untuknya — beri ia timestamp di rentang itu, jangan
 * setelah migrasi ini.
 *
 * PENJAGA DI BAWAH menegakkan urutan itu: bila masih ada baris `periods` yang
 * belum punya satu pun baris `period_stations`, migrasi ini MENOLAK berjalan
 * dan menyebut nama barisnya, alih-alih diam-diam membuang status periode yang
 * mungkin 'closed'. Di DB test (dibangun dari nol) tabelnya kosong, jadi
 * penjaga ini tidak pernah aktif di sana.
 */
return new class extends Migration
{
    public function up(): void
    {
        $unconverted = DB::table('periods')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('period_stations')
                    ->whereColumn('period_stations.period_id', 'periods.id');
            })
            ->pluck('name', 'id')
            ->all();

        if ($unconverted !== []) {
            throw new RuntimeException(
                'Menolak membuang periods.station_type/status/closed_by/closed_at: '
                .count($unconverted).' baris periods belum punya baris period_stations, '
                .'sehingga status tutup/bukanya akan hilang tanpa jejak ['
                .implode(', ', array_map(
                    fn ($id, $name) => $id.' "'.$name.'"',
                    array_keys($unconverted),
                    array_values($unconverted)
                ))
                .']. Jalankan migrasi konversi data periods -> period_stations lebih dulu '
                .'(timestamp 2026_09_26_000032..000039), lalu ulangi migrasi ini.'
            );
        }

        // Urutan wajib: FK dan indeks yang memuat station_type harus lepas
        // sebelum kolomnya dibuang. Di SQLite, DROP COLUMN atas kolom yang
        // masih terpakai indeks gagal di level engine; di PostgreSQL indeksnya
        // akan terbawa hilang tapi namanya lalu tidak bisa diandalkan.
        Schema::table('periods', function (Blueprint $table) {
            $table->dropForeign(['station_type']);
            $table->dropForeign(['closed_by']);
            $table->dropUnique(['business_unit_id', 'station_type', 'name']);
            $table->dropIndex(['business_unit_id', 'station_type', 'start_date', 'end_date']);
        });

        Schema::table('periods', function (Blueprint $table) {
            $table->dropColumn(['station_type', 'status', 'closed_by', 'closed_at']);
        });

        Schema::table('periods', function (Blueprint $table) {
            // Dua kueri terpanas, keduanya kini tanpa jenis stasiun: pengecekan
            // tumpang tindih rentang saat create/update periode, dan pencarian
            // periode yang memuat sebuah tanggal record.
            $table->index(['business_unit_id', 'start_date', 'end_date']);

            // Satu nama periode per mill, titik.
            $table->unique(['business_unit_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('periods', function (Blueprint $table) {
            $table->dropUnique(['business_unit_id', 'name']);
            $table->dropIndex(['business_unit_id', 'start_date', 'end_date']);
        });

        Schema::table('periods', function (Blueprint $table) {
            $table->string('station_type')->nullable();
            $table->string('status')->default('draft');
            $table->foreignUuid('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
        });

        Schema::table('periods', function (Blueprint $table) {
            $table->index(['business_unit_id', 'station_type', 'start_date', 'end_date']);
            $table->unique(['business_unit_id', 'station_type', 'name']);
            $table->foreign('station_type')
                ->references('code')
                ->on('station_types')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });

        // Isi kolomnya TIDAK dipulihkan — rollback hanya memulihkan bentuk.
        // period_stations masih memegang datanya sampai migrasi 000031
        // di-rollback juga.
    }
};
