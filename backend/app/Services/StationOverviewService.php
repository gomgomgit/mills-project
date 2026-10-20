<?php

namespace App\Services;

use App\Enums\UserRole;
use Illuminate\Support\Facades\DB;

/**
 * Ringkasan per JENIS STASIUN untuk halaman Alur Proses (/alur-proses).
 *
 * BUKAN laporan periode, dan sengaja TIDAK meminta periode maupun Production
 * Line. Itu keputusan yang menentukan bentuk seluruh kelas ini: keenam belas
 * report service stasiun MEWAJIBKAN (periode, production_line) — diverifikasi
 * pada tanda tangan metodenya — sehingga memakai salah satunya di sini akan
 * memaksa halaman tur menumbuhkan dua pemilih sebelum satu angka pun muncul,
 * dan tur yang dimulai dengan dua formulir berhenti menjadi tur.
 *
 * Yang diterbitkan di sini karena itu hanya angka yang BERMAKNA TANPA kedua
 * konteks itu:
 *
 *   - alat terdaftar  : hitungan `machinery` per jenis stasiun. Ia properti
 *                       pabrik, bukan properti periode.
 *   - stasiun & line  : berapa unit stasiun jenis ini aktif, tersebar di
 *                       berapa production line.
 *   - pencatatan      : total record, record 30 hari terakhir, dan tanggal
 *                       record terbaru.
 *   - target operasi  : baris `*_operational_targets` apa adanya, untuk EMPAT
 *                       jenis stasiun yang punya masternya.
 *
 * "BELUM ADA PENCATATAN" ADALAH JAWABAN YANG SAH, BUKAN KEKOSONGAN YANG PERLU
 * DISEMBUNYIKAN. Diukur di basis data pengembangan 2026-10-20: 10 dari 18
 * jenis stasiun nol record. Justru itulah yang berguna dilihat seorang Admin
 * di halaman ikhtisar — stasiun mana yang benar-benar dipakai mencatat dan
 * mana yang belum pernah. Menyembunyikannya di balik em dash akan membuang
 * satu-satunya informasi yang tidak ada di tempat lain mana pun.
 *
 * CAKUPAN MILL mengikuti aturan rumah (lihat
 * KernelDispatchReportService::resolveBusinessUnit): peran yang terikat mill
 * mendapat mill akunnya dan nilai kiriman klien dibuang. Admin TIDAK dipaksa
 * memilih di sini — ia mendapat agregat SELURUH mill, mengikuti preseden Data
 * Browser yang punya opsi "semua", bukan preseden layar laporan yang mewajibkan
 * satu pilihan. Alasannya: ini konteks MENJELAJAH, bukan konteks melaporkan
 * angka yang akan ditandatangani seseorang.
 */
class StationOverviewService
{
    /**
     * Jenis stasiun -> tabel record-nya.
     *
     * DIPETAKAN EKSPLISIT, TIDAK DITURUNKAN DARI NAMA. Dua di antaranya tidak
     * mengikuti pola apa pun yang dapat ditebak dari kode stasiunnya
     * (`cages-track` -> `cages_track_records`, `kernel-plant` ->
     * `kernel_plant_records`), dan sebuah peta yang "pintar" akan diam-diam
     * meleset pada jenis stasiun berikutnya yang ditambahkan.
     *
     * @var array<string, string>
     */
    protected const RECORD_TABLES = [
        'weighbridge' => 'weighbridge_records',
        'grading' => 'grading_records',
        'cages-track' => 'cages_track_records',
        'sterilizer' => 'sterilizer_records',
        'threshing' => 'threshing_records',
        'pressing' => 'pressing_records',
        'clarification' => 'clarification_records',
        'kernel-plant' => 'kernel_plant_records',
        'boiler-room' => 'boiler_room_records',
        'effluent-plant' => 'effluent_plant_records',
        'depricarping' => 'depricarping_records',
        'engine-room' => 'engine_room_records',
        'process-water' => 'process_water_records',
        'storage-tank' => 'storage_tank_records',
        'solid-waste-disposal' => 'solid_waste_disposal_records',
        'kernel-dispatch' => 'kernel_dispatch_records',
        'cpo-dispatch' => 'cpo_dispatch_records',
        'process-quality-control' => 'process_quality_control_records',
    ];

    /**
     * SATU-SATUNYA tabel record yang kolom tanggalnya BUKAN `date`.
     *
     * `weighbridge_records` memakai `record_datetime` (timestamp), sementara
     * ketujuh belas lainnya memakai `date` (date). Diverifikasi di skema
     * 2026-10-20. Pengecualian ini DINAMAI di sini alih-alih ditangani dengan
     * percabangan di tengah kueri, supaya ketika tabel ke-19 menyusul,
     * penulisnya menemukan daftar yang menyatakan aturannya dan bukan sebuah
     * `if` yang harus ditebak maksudnya.
     */
    protected const DATETIME_COLUMN_TABLES = [
        'weighbridge_records' => 'record_datetime',
    ];

    /**
     * Jenis stasiun yang punya master target operasi.
     *
     * HANYA EMPAT — diverifikasi: tidak ada `*_operational_targets` untuk
     * empat belas jenis lainnya. Nilai targetnya adalah TEKS BEBAS
     * ("20 - 25 Amps (Nut Breakage >95%)", "≤ 7.0%"), bukan angka, jadi ia
     * dicetak apa adanya dan tidak pernah dibandingkan dengan nilai terukur.
     * Membandingkannya berarti mengurai prosa menjadi ambang, dan ambang yang
     * diurai dari prosa adalah ambang yang dikarang.
     *
     * NAMA KOLOMNYA BERBEDA DI KEEMPAT TABEL, dan itu bukan dugaan — versi
     * pertama kelas ini menyimpulkan nama kolom dari SATU tabel
     * (`kernel_plant`) dan langsung gagal dengan "column equipment_parameter
     * does not exist" pada tabel kedua. Karena itu tiap tabel membawa nama
     * kolomnya sendiri di sini: [tabel, kolom parameter, kolom target].
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    protected const TARGET_TABLES = [
        'kernel-plant' => ['kernel_plant_operational_targets', 'equipment_parameter', 'target_benchmark'],
        'depricarping' => ['depricarping_operational_targets', 'parameter_metric', 'target_range'],
        'pressing' => ['pressing_operational_targets', 'parameter_metric', 'target_operating_range'],
        'threshing' => ['threshing_operational_targets', 'parameter', 'standard_operational_target'],
    ];

    /** Jendela "pencatatan terkini", dalam hari. */
    public const RECENT_DAYS = 30;

    /**
     * Ikhtisar seluruh jenis stasiun, berkunci kode stasiun.
     *
     * @return array{scope_label: string, business_unit_id: string|null, stations: array<string, array<string, mixed>>}
     */
    public function overview(): array
    {
        $businessUnitId = $this->resolveBusinessUnitOrNull();

        $facilities = $this->facilitiesBy($businessUnitId);
        $activity = $this->activityBy($businessUnitId);
        $targets = $this->targets();

        $stations = [];

        foreach (array_keys(self::RECORD_TABLES) as $code) {
            $stations[$code] = [
                'machinery_count' => (int) ($facilities[$code]['machinery_count'] ?? 0),
                'active_station_count' => (int) ($facilities[$code]['active_station_count'] ?? 0),
                'production_line_count' => (int) ($facilities[$code]['production_line_count'] ?? 0),
                'record_count' => (int) ($activity[$code]['record_count'] ?? 0),
                'recent_record_count' => (int) ($activity[$code]['recent_record_count'] ?? 0),
                // null, BUKAN string kosong: "belum pernah ada record" dan
                // "ada record bertanggal kosong" adalah dua hal berbeda, dan
                // hanya yang pertama yang mungkin di sini.
                'last_record_date' => $activity[$code]['last_record_date'] ?? null,
                'targets' => $targets[$code] ?? [],
            ];
        }

        return [
            'scope_label' => $businessUnitId === null
                ? 'Seluruh mill'
                : $this->businessUnitName($businessUnitId),
            'business_unit_id' => $businessUnitId,
            'stations' => $stations,
        ];
    }

    /**
     * Mill yang menjadi cakupan, atau null untuk "seluruh mill" (Admin).
     *
     * Nilai kiriman klien TIDAK diterima sama sekali di kelas ini — tidak ada
     * parameternya. Halaman ini tidak punya pemilih mill, jadi satu-satunya
     * sumber cakupan adalah akun yang sedang masuk; sebuah parameter yang
     * tidak dapat diisi siapa pun hanya akan menjadi lubang yang menunggu
     * dipakai.
     */
    protected function resolveBusinessUnitOrNull(): ?string
    {
        $user = auth()->user();

        if ($user === null) {
            return null;
        }

        $role = $user->role->value ?? (string) $user->role;

        if ($role === UserRole::Admin->value) {
            return null;
        }

        $businessUnitId = (string) ($user->business_unit_id ?? '');

        // Akun terikat mill yang business_unit_id-nya NULL tidak dijatuhkan ke
        // cabang Admin — itu akan membocorkan seluruh mill kepada akun yang
        // justru paling sempit haknya. Ia mendapat cakupan yang tidak cocok
        // dengan satu stasiun pun, sehingga halamannya jujur kosong.
        return $businessUnitId === '' ? '00000000-0000-0000-0000-000000000000' : $businessUnitId;
    }

    protected function businessUnitName(string $businessUnitId): string
    {
        $name = DB::table('business_units')->where('id', $businessUnitId)->value('name');

        return $name === null ? 'Mill tidak dikenal' : (string) $name;
    }

    /**
     * Alat, stasiun aktif, dan sebaran production line per jenis stasiun.
     *
     * SATU KUERI untuk kedelapan belas jenis sekaligus, bukan satu kueri per
     * jenis: halaman ini merender semuanya setiap kali dibuka, dan delapan
     * belas perjalanan ke basis data untuk angka sekecil ini adalah N+1 yang
     * ditulis dengan tangan.
     *
     * @return array<string, array<string, int>>
     */
    protected function facilitiesBy(?string $businessUnitId): array
    {
        $rows = DB::table('stations as s')
            ->leftJoin('machinery as m', 'm.station_id', '=', 's.id')
            ->when($businessUnitId !== null, fn ($q) => $q->where('s.business_unit_id', $businessUnitId))
            ->groupBy('s.type')
            ->selectRaw('s.type as code')
            ->selectRaw('count(distinct case when s.is_active then s.id end) as active_station_count')
            ->selectRaw('count(distinct s.production_line_id) as production_line_count')
            ->selectRaw('count(distinct m.id) as machinery_count')
            ->get();

        $out = [];

        foreach ($rows as $row) {
            $out[(string) $row->code] = [
                'active_station_count' => (int) $row->active_station_count,
                'production_line_count' => (int) $row->production_line_count,
                'machinery_count' => (int) $row->machinery_count,
            ];
        }

        return $out;
    }

    /**
     * Aktivitas pencatatan per jenis stasiun.
     *
     * Delapan belas tabel yang berbeda disatukan dengan UNION ALL lalu
     * diagregasi sekali. Alternatifnya — delapan belas kueri terpisah — bukan
     * hanya lebih lambat, ia juga membuat ambang "30 hari terakhir" dihitung
     * delapan belas kali terhadap delapan belas pembacaan jam yang berbeda,
     * sehingga dua stasiun dapat memakai batas tanggal yang berbeda pada
     * halaman yang sama.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function activityBy(?string $businessUnitId): array
    {
        $parts = [];
        $bindings = [];

        foreach (self::RECORD_TABLES as $code => $table) {
            $dateColumn = self::DATETIME_COLUMN_TABLES[$table] ?? 'date';
            // Kolom timestamp DIPOTONG ke tanggal supaya kedelapan belas
            // cabang menghasilkan tipe yang sama; tanpa ini UNION-nya gagal di
            // PostgreSQL dan diam-diam menyamakan tipe di SQLite.
            $dateExpr = $dateColumn === 'date' ? 'r.date' : "cast(r.{$dateColumn} as date)";

            $where = $businessUnitId === null ? '' : ' where s.business_unit_id = ?';

            $parts[] = "select ? as code, {$dateExpr} as d"
                ." from {$table} r join stations s on s.id = r.station_id{$where}";

            // Binding ditambahkan PERSIS dalam urutan kemunculan placeholder
            // di potongan SQL di atas: kode stasiun dulu, lalu business unit
            // bila cabang ini memakainya.
            $bindings[] = $code;

            if ($businessUnitId !== null) {
                $bindings[] = $businessUnitId;
            }
        }

        $union = implode(' union all ', $parts);

        $rows = DB::select(
            "select code, count(d) as record_count,"
            ." count(case when d >= ? then 1 end) as recent_record_count,"
            ." max(d) as last_record_date"
            ." from ({$union}) as rec group by code",
            array_merge([now()->subDays(self::RECENT_DAYS)->toDateString()], $bindings)
        );

        $out = [];

        foreach ($rows as $row) {
            $last = $row->last_record_date;

            $out[(string) $row->code] = [
                'record_count' => (int) $row->record_count,
                'recent_record_count' => (int) $row->recent_record_count,
                'last_record_date' => $last === null ? null : substr((string) $last, 0, 10),
            ];
        }

        return $out;
    }

    /**
     * Target operasi untuk keempat jenis stasiun yang punya masternya.
     *
     * @return array<string, list<array{parameter: string, benchmark: string}>>
     */
    protected function targets(): array
    {
        $out = [];

        foreach (self::TARGET_TABLES as $code => [$table, $parameterColumn, $benchmarkColumn]) {
            $out[$code] = DB::table($table)
                ->orderBy('sort_order')
                ->get([$parameterColumn, $benchmarkColumn])
                ->map(fn ($row) => [
                    'parameter' => (string) $row->{$parameterColumn},
                    'benchmark' => (string) $row->{$benchmarkColumn},
                ])
                ->all();
        }

        return $out;
    }
}
