<?php

namespace App\Services;

use App\Enums\StationType as StationTypeEnum;
use App\Models\StationType;
use App\Support\Concerns\ScopesToActorMill;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Papan status Production Process Activity (screen-035).
 *
 * SEBELUM 2026-10-07 halaman itu Blade statis murni: 18 tile di-hardcode,
 * tanpa satu angka pun, dan tanpa membaca basis data sama sekali. Layar ini
 * mengubahnya menjadi PAPAN STATUS — tiap tile membawa jumlah record hari ini
 * dan kapan stasiun itu terakhir menerima input — dan memindahkan sumber nama
 * serta keaktifan stasiun ke master `station_types`.
 *
 * ────────────────────────────────────────────────────────────────────────
 * DUA SUMBER KEBENARAN YANG DIHAPUS, DAN SATU YANG SENGAJA DIPERTAHANKAN
 * ────────────────────────────────────────────────────────────────────────
 * Yang DIHAPUS: nama dan keaktifan stasiun. Keduanya dulu literal di Blade
 * sementara `station_types` adalah master yang dibaca screen-140 (/reports).
 * Stasiun yang di-rename atau dinonaktifkan di master mengubah /reports dan
 * TIDAK mengubah halaman ini — dua grid yang menggambarkan stasiun yang sama
 * bisa berselisih tanpa satu uji pun gagal. Sekarang keduanya dibaca dari
 * master, dan `StationReportService::stationList()` sudah memakai jalur yang
 * sama lebih dulu.
 *
 * Yang DIPERTAHANKAN dengan sengaja: URUTAN TILE. `station_types.sort_order`
 * bukan urutan halaman ini — ia urutan /reports. Urutan di sini adalah urutan
 * kustom permintaan produk (2026-09-01) yang DICERMINKAN `ORDER BY` berbasis
 * CASE pada mobile `stationRepo.ts`, dan diverifikasi masih identik pada
 * 2026-10-07. Mengurutkan ulang menurut `sort_order` akan membuat web dan
 * mobile menyimpang tanpa ada yang memintanya, jadi urutannya tetap literal
 * di TILE_ORDER di bawah — satu-satunya hal yang masih literal, dan sekarang
 * ia satu-satunya yang memang HARUS literal karena pasangannya ada di repo
 * lain.
 *
 * ────────────────────────────────────────────────────────────────────────
 * DUA JENDELA WAKTU, DAN KEDUANYA HARUS DILABELI
 * ────────────────────────────────────────────────────────────────────────
 * `today_count` menghitung record yang TANGGALNYA hari ini.
 * `last_input_at` adalah saat record stasiun itu terakhir DIBUAT, tanpa
 * dibatasi tanggal mana pun.
 *
 * Keduanya memang beda cakupan, dan itu disengaja: jendela yang disamakan
 * membuat "input terakhir" kosong setiap pagi sebelum orang pertama mengisi,
 * padahal justru itu saat pertanyaan "kapan terakhir ada yang mengisi ini?"
 * paling berguna. Karena beda, LAYAR WAJIB MELABELI keduanya — dibaca
 * sebagai satu cakupan, "0 hari ini" di samping "input terakhir 3 menit lalu"
 * terbaca seperti bug.
 *
 * ────────────────────────────────────────────────────────────────────────
 * CAKUPAN MILL, DAN MENGAPA TIDAK ADA PEMILIH PRODUCTION LINE
 * ────────────────────────────────────────────────────────────────────────
 * Peran terikat mill melihat mill-nya; Admin melihat SELURUH mill, dan layar
 * menyatakannya. Jalurnya `actorReadMillId()` — satu tempat yang memutuskan
 * "mill mana yang boleh dibaca aktor ini", sama seperti layar baca lainnya.
 *
 * TIDAK ADA pemilih Production Line di sini, dan itu BERBEDA dari laporan
 * periode yang mewajibkannya. Alasannya: laporan menerbitkan ANGKA MUTU yang
 * tak punya arti tanpa line-nya, sementara halaman ini menghitung RECORD, dan
 * hitungan se-mill adalah hal yang sah untuk ditampilkan sebuah peluncur —
 * persis seperti Data Browser yang tujuannya diluncurkan halaman ini, yang
 * memang punya opsi "semua line". Menyalin aturan wajib-pilih-line ke sini
 * akan membuat peluncur menolak meluncurkan apa pun sebelum pengguna memilih
 * sesuatu yang tidak mempengaruhi tujuannya.
 *
 * ────────────────────────────────────────────────────────────────────────
 * DELAPAN BELAS QUERY, BUKAN SATU UNION — DAN ITU PILIHAN
 * ────────────────────────────────────────────────────────────────────────
 * Tidak ada agregat yang bisa dipakai ulang: `DashboardService` hanya
 * menghitung TIGA stasiun (Weighbridge, Grading, Cages Track), dan 18 tabel
 * `*_records` terpisah tidak punya tabel induk bersama.
 *
 * Satu `UNION ALL` 18 cabang akan jadi satu perjalanan ke basis data, tetapi
 * ia menuntut SQL mentah — dan dengan itu hilang `whereDate()`, yang
 * diterjemahkan BERBEDA oleh SQLite (suite uji) dan PostgreSQL (produksi).
 * Proyek ini sudah membayar kelas defek itu dua kali. Jadi: 18 query query-
 * builder, masing-masing memakai `whereDate()` apa adanya, dibungkus cache 60
 * detik. 18 COUNT atas himpunan yang sudah ter-scope mill bukan beban; satu
 * kelas defek yang hanya muncul di produksi adalah beban.
 *
 * DAN SATU TABEL BERBEDA KOLOM TANGGALNYA. Weighbridge memakai
 * `record_datetime`, ketujuh belas lainnya memakai `date` — diverifikasi dari
 * information_schema, bukan diasumsikan. Loop yang menganggap semuanya `date`
 * akan melempar galat kolom-tidak-ada untuk satu stasiun saja, yaitu stasiun
 * dengan record TERBANYAK di basis data.
 */
class ProductionProcessActivityService
{
    use ScopesToActorMill;

    /**
     * Berapa lama snapshot hitungan ditahan. 60 detik: cukup pendek supaya
     * papan ini tetap terasa hidup bagi orang yang baru menekan Simpan di
     * layar input, cukup panjang supaya memuat ulang berkali-kali tidak
     * berarti 18 query berkali-kali.
     */
    public const SNAPSHOT_TTL_SECONDS = 60;

    /**
     * URUTAN TILE — literal, dan HARUS tetap literal.
     *
     * Ini urutan kustom permintaan produk (2026-09-01), dan pasangannya ada
     * di repo LAIN: `ORDER BY` berbasis CASE pada mobile
     * `src/services/stationRepo.ts`. Diverifikasi identik pada 2026-10-07.
     * Mengubah salah satunya tanpa yang lain membuat kedua aplikasi
     * menggambarkan pabrik yang sama dengan urutan berbeda.
     *
     * BUKAN `station_types.sort_order` — itu urutan /reports (screen-140),
     * yang memang berbeda dan memang benar untuk layar itu.
     *
     * @var list<string>
     */
    public const TILE_ORDER = [
        'weighbridge',
        'pressing',
        'storage-tank',
        'grading',
        'clarification',
        'effluent-plant',
        'cages-track',
        'engine-room',
        'cpo-dispatch',
        'sterilizer',
        'boiler-room',
        'kernel-dispatch',
        'kernel-plant',
        'process-water',
        'threshing',
        'depricarping',
        'solid-waste-disposal',
        'process-quality-control',
    ];

    /**
     * Rute Data Browser per kode stasiun. Tile yang kodenya tidak ada di
     * sini dirender tanpa tautan alih-alih menjatuhkan halaman — itulah
     * yang terjadi bila seseorang menambah baris ke master tanpa membangun
     * layarnya.
     *
     * @var array<string, string>
     */
    public const ROUTE_NAMES = [
        'weighbridge' => 'data.weighbridge',
        'pressing' => 'data.pressing',
        'storage-tank' => 'data.storage-tank',
        'grading' => 'data.grading',
        'clarification' => 'data.clarification',
        'effluent-plant' => 'data.effluent-plant',
        'cages-track' => 'data.cages-track',
        'engine-room' => 'data.engine-room',
        'cpo-dispatch' => 'data.cpo-dispatch',
        'sterilizer' => 'data.sterilizer',
        'boiler-room' => 'data.boiler-room',
        'kernel-dispatch' => 'data.kernel-dispatch',
        'kernel-plant' => 'data.kernel-plant',
        'process-water' => 'data.process-water',
        'threshing' => 'data.threshing',
        'depricarping' => 'data.depricarping',
        'solid-waste-disposal' => 'data.solid-waste-disposal',
        'process-quality-control' => 'data.process-quality-control',
    ];

    /**
     * Kolom tanggal yang BUKAN `date`. Hanya satu, dan ia stasiun dengan
     * record terbanyak — jadi loop yang mengasumsikan `date` gagal tepat di
     * tempat yang paling terlihat.
     *
     * @var array<string, string>
     */
    protected const DATE_COLUMN_OVERRIDES = [
        'weighbridge' => 'record_datetime',
    ];

    /** Jenis stasiun historis yang bukan stasiun sungguhan — tidak pernah jadi tile. */
    protected const EXCLUDED_STATION_TYPE = StationTypeEnum::Other->value;

    /**
     * Seluruh tile papan ini, dalam urutan TILE_ORDER.
     *
     * @return list<array{
     *     code: string, name: string, is_active: bool, route: ?string,
     *     today_count: int, last_input_at: ?string, has_input_today: bool
     * }>
     */
    public function tiles(): array
    {
        $millId = $this->actorReadMillId();
        $snapshot = $this->snapshot($millId);

        // Nama diambil dari MASTER, bukan dari literal Blade. Baris master
        // yang hilang tidak boleh menjatuhkan halaman — tile-nya tetap
        // digambar dengan nama turunan kodenya, karena tile yang menghilang
        // tak dapat dibedakan dari stasiun yang tidak ada.
        $names = StationType::query()
            ->where('code', '<>', self::EXCLUDED_STATION_TYPE)
            ->pluck('name', 'code')
            ->all();

        $active = StationType::query()
            ->where('code', '<>', self::EXCLUDED_STATION_TYPE)
            ->pluck('is_active', 'code')
            ->all();

        $tiles = [];

        foreach (self::TILE_ORDER as $code) {
            $row = $snapshot[$code] ?? ['today_count' => 0, 'last_input_at' => null];
            $isActive = (bool) ($active[$code] ?? true);

            $tiles[] = [
                'code' => $code,
                'name' => (string) ($names[$code] ?? $this->nameFromCode($code)),
                'is_active' => $isActive,
                // Stasiun nonaktif TIDAK ditautkan. Data Browser-nya masih
                // ada dan masih berfungsi; yang dinyatakan master adalah
                // bahwa stasiun ini tidak dioperasikan, dan meluncurkannya
                // dari papan status akan membantahnya.
                'route' => $isActive ? (self::ROUTE_NAMES[$code] ?? null) : null,
                'today_count' => (int) $row['today_count'],
                'last_input_at' => $row['last_input_at'],
                'has_input_today' => ((int) $row['today_count']) > 0,
            ];
        }

        return $tiles;
    }

    /**
     * Jumlah stasiun AKTIF yang sudah menerima input hari ini, beserta
     * jumlah stasiun aktif seluruhnya — sejajar `$availableCount` pada
     * screen-140, dan alasan keberadaannya sama: satu angka di kepala
     * halaman menjawab pertanyaan yang kalau tidak dijawab menuntut
     * pembaca memindai 18 tile.
     *
     * @param  list<array{is_active: bool, has_input_today: bool}>  $tiles
     * @return array{with_input: int, active_total: int}
     */
    public function todayHeadline(array $tiles): array
    {
        $activeTiles = array_values(array_filter($tiles, fn (array $tile) => $tile['is_active']));

        return [
            'with_input' => count(array_filter($activeTiles, fn (array $tile) => $tile['has_input_today'])),
            'active_total' => count($activeTiles),
        ];
    }

    /** Apakah aktor melihat SELURUH mill (Admin) alih-alih satu mill. */
    public function isAllMills(): bool
    {
        return $this->actorReadMillId() === null;
    }

    /**
     * Hitungan hari ini + input terakhir per kode stasiun, di-cache.
     *
     * Kunci cache memuat tanggalnya, bukan hanya mill-nya — tanpa itu
     * snapshot tengah malam akan bertahan sampai TTL-nya habis dan
     * melaporkan hitungan hari kemarin sebagai hitungan hari ini.
     *
     * @return array<string, array{today_count: int, last_input_at: ?string}>
     */
    protected function snapshot(?string $millId): array
    {
        $today = now()->toDateString();
        $key = 'ppa:snapshot:'.($millId ?? 'all').':'.$today;

        return Cache::remember($key, self::SNAPSHOT_TTL_SECONDS, function () use ($millId, $today) {
            $snapshot = [];

            foreach (self::TILE_ORDER as $code) {
                $table = str_replace('-', '_', $code).'_records';
                $dateColumn = self::DATE_COLUMN_OVERRIDES[$code] ?? 'date';

                $snapshot[$code] = [
                    'today_count' => $this->countForDate($table, $dateColumn, $millId, $today),
                    'last_input_at' => $this->lastInputAt($table, $millId),
                ];
            }

            return $snapshot;
        });
    }

    /**
     * Jumlah record satu stasiun yang TANGGALNYA $date, ter-scope mill.
     *
     * `whereDate()` dipakai apa adanya dan bukan `where()` atas kolom
     * timestamp: untuk Weighbridge kolomnya `record_datetime`, dan
     * perbandingan `where('record_datetime', $date)` hanya akan cocok pada
     * tengah malam tepat. Dan `whereDate()` inilah yang diterjemahkan benar
     * oleh SQLite maupun PostgreSQL.
     */
    protected function countForDate(string $table, string $dateColumn, ?string $millId, string $date): int
    {
        return (int) $this->scoped($table, $millId)
            ->whereDate($table.'.'.$dateColumn, $date)
            ->count();
    }

    /**
     * Kapan stasiun ini terakhir menerima input — TANPA batas tanggal.
     * Lihat catatan "dua jendela waktu" di docblock kelas.
     */
    protected function lastInputAt(string $table, ?string $millId): ?string
    {
        $value = $this->scoped($table, $millId)->max($table.'.created_at');

        return $value === null ? null : (string) $value;
    }

    /**
     * Query dasar satu tabel record, ter-scope mill aktor.
     *
     * Scope-nya lewat `stations.business_unit_id` — jalur yang sama dengan
     * `DashboardService`, bukan jalur kedua yang dapat menyimpang darinya.
     * millId null hanya mungkin untuk Admin, dan berarti seluruh mill.
     */
    protected function scoped(string $table, ?string $millId): Builder
    {
        $query = DB::table($table);

        if ($millId !== null) {
            $query->whereIn(
                $table.'.station_id',
                DB::table('stations')->select('id')->where('business_unit_id', $millId)
            );
        }

        return $query;
    }

    /**
     * Nama cadangan ketika baris master hilang: 'cages-track' -> 'Cages
     * Track'. Bukan untuk dipakai normal — ia ada supaya baris master yang
     * terhapus menghasilkan tile bernama apa adanya alih-alih tile kosong.
     */
    protected function nameFromCode(string $code): string
    {
        return ucwords(str_replace('-', ' ', $code));
    }
}
