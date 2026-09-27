<?php

use App\Services\PeriodService;
use App\Support\PeriodSplit\PeriodSplitConverter;
use App\Support\PeriodSplit\PeriodSplitPlan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * KONVERSI DATA periods (model lama) -> periods + period_stations (model baru).
 *
 * Inilah migrasi yang slot 000032..000039 disisakan untuknya oleh
 * 2026_09_26_000031 (yang membuat `period_stations`) dan 2026_09_26_000040
 * (yang membuang `periods.station_type/status/closed_by/closed_at`). Urutan
 * wajibnya: 000031 buat tabel -> 000035 pindahkan datanya -> 000040 buang
 * kolom lamanya. Migrasi ini adalah satu-satunya tempat di mana kedua bentuk
 * data hidup berdampingan.
 *
 * SELURUH ATURAN KONVERSINYA ADA DI {@see PeriodSplitConverter}, bukan di
 * sini — lihat docblock kelas itu untuk keenam aturannya dan alasan
 * pemisahannya (singkatnya: cabang-cabangnya tidak bisa diuji lewat migrasi,
 * karena di DB test `periods` kosong dan sesudah 000040 baris berbentuk lama
 * tidak bisa dibuat lagi). Berkas ini hanya: baca, minta rencana, terapkan,
 * laporkan.
 *
 * DB::table() MENTAH, BUKAN ELOQUENT. Model App\Models\Period memasang
 * penjaga yang MELEMPAR LogicException begitu `station_type`, `status`,
 * `closed_by`, atau `closed_at` dibaca/ditulis (lihat
 * Period::MOVED_TO_PERIOD_STATIONS) — justru empat kolom yang migrasi ini
 * harus baca. Satu-satunya kelas aplikasi yang dipakai di sini adalah
 * PeriodService::activeStationTypesForMill(), dan itu sengaja: aturan "jenis
 * stasiun apa yang aktif di mill ini" hanya boleh punya satu definisi, dan
 * definisinya ada di sana. Menyalinnya ke migrasi berarti pemekaran cakupan
 * NULL bisa berbeda dari apa yang create() hasilkan untuk periode baru di mill
 * yang sama.
 *
 * DI DB YANG DIBANGUN DARI NOL (termasuk seluruh suite test) `periods` kosong
 * saat migrasi ini berjalan: ia no-op dengan tenang — tanpa error, tanpa
 * keluaran, tanpa memanggil PeriodService sama sekali.
 *
 * TIDAK REVERSIBEL — DAN down() MENGATAKANNYA, BUKAN BERPURA-PURA.
 * Penggabungan menghapus informasi yang tidak ada lagi di mana pun: N baris
 * `periods` menjadi 1, dan mana dari N baris itu yang dulu memegang id, nama,
 * created_by/updated_by, serta created_at/updated_at yang mana tidak terekam —
 * baris anak menyimpan status dan penutupannya, tetapi bukan identitas induk
 * yang hilang. Pemekaran cakupan NULL juga tidak dapat dibedakan lagi dari
 * sekumpulan baris eksplisit: 18 baris period_stations bisa berasal dari satu
 * baris "semua stasiun" atau dari 18 baris per jenis, dan kedua asal itu
 * bermuara pada bentuk yang identik. De-duplikasi nama menimpa nama aslinya.
 * down() yang "berhasil" berarti mengarang ketiganya. Jadi down() melempar dan
 * menyuruh restore dari dump — satu-satunya pemulihan yang jujur.
 */
return new class extends Migration
{
    public function up(): void
    {
        $periods = DB::table('periods')->get()->map(fn ($row) => (array) $row)->all();

        if ($periods === []) {
            // DB baru / DB test: tidak ada apa pun untuk dikonversi.
            return;
        }

        // Migrasi ini mengasumsikan `period_stations` masih kosong — 000031
        // baru saja membuatnya beberapa langkah sebelumnya dalam rangkaian yang
        // sama. Bila ternyata sudah berisi, ada konversi lain yang sudah jalan
        // (sebagian?), dan menambahkan baris di atasnya dapat menabrak unique
        // (period_id, station_type) atau menggandakan catatan penutupan.
        // Berhenti dan serahkan ke operator.
        $existing = DB::table('period_stations')->count();

        if ($existing > 0) {
            throw new RuntimeException(
                'Menolak mengonversi periods -> period_stations: tabel period_stations sudah '
                ."berisi {$existing} baris padahal masih ada ".count($periods).' baris periods '
                .'berbentuk lama. Konversi lain tampaknya sudah berjalan sebagian. Periksa isi '
                .'kedua tabel, atau restore dari dump, lalu ulangi.'
            );
        }

        $converter = new PeriodSplitConverter;
        $plan = $converter->plan($periods, $this->activeStationTypesByMill($periods));
        $converter->apply($plan);

        $this->report($plan, count($periods));
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Migrasi 2026_09_26_000035 (konversi periods -> period_stations) TIDAK DAPAT '
            .'di-rollback. Penggabungan N baris periods menjadi 1 menghapus identitas induk yang '
            .'lebur (id, nama, created_by/updated_by, created_at/updated_at), pemekaran cakupan '
            .'station_type NULL tidak dapat dibedakan lagi dari sekumpulan baris eksplisit, dan '
            .'de-duplikasi nama menimpa nama aslinya — tidak satu pun dari ketiganya terekam di '
            .'period_stations. Satu-satunya pemulihan yang jujur adalah restore dari dump '
            .'pra-migrasi (pg_restore atas berkas mill_smart_log_*_pre-period-split.dump), bukan '
            .'`migrate:rollback`.'
        );
    }

    /**
     * Daftar jenis stasiun aktif per mill — HANYA untuk mill yang benar-benar
     * punya baris bercakupan NULL, supaya DB yang tidak memuat kasus itu (dev
     * ini, misalnya) tidak menyentuh PeriodService sama sekali.
     *
     * @param  list<array<string, mixed>>  $periods
     * @return array<string, list<string>>
     */
    private function activeStationTypesByMill(array $periods): array
    {
        $mills = [];

        foreach ($periods as $row) {
            if (($row['station_type'] ?? null) === null) {
                $mills[(string) $row['business_unit_id']] = true;
            }
        }

        if ($mills === []) {
            return [];
        }

        $service = new PeriodService;
        $map = [];

        foreach (array_keys($mills) as $millId) {
            $map[$millId] = $service->activeStationTypesForMill($millId);
        }

        return $map;
    }

    /**
     * Setiap keputusan yang membuang atau mengubah sesuatu muncul di keluaran
     * `php artisan migrate` DAN di log aplikasi — aturan 4 dan 5 menuntut nama
     * yang dibuang/diubah tercatat, dan keluaran konsol saja bisa tergulung
     * hilang di rangkaian migrasi yang panjang.
     */
    private function report(PeriodSplitPlan $plan, int $oldRowCount): void
    {
        $lines = array_merge([
            sprintf(
                'periods -> period_stations: %d baris lama menjadi %d periode induk + %d baris period_stations; %d baris periods dihapus setelah lebur; %d nama diubah.',
                $oldRowCount,
                count($plan->survivorIds),
                count($plan->stationRows),
                count($plan->deletedPeriodIds),
                count($plan->renames),
            ),
        ], $plan->notes);

        foreach ($lines as $line) {
            Log::info('[migrasi 000035] '.$line);

            if (PHP_SAPI === 'cli') {
                fwrite(STDOUT, '  '.$line.PHP_EOL);
            }
        }
    }
};
