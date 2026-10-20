<?php

namespace App\Services;

use App\Enums\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Angka PRODUKSI per jenis stasiun untuk halaman Alur Proses (/alur-proses),
 * dicakup oleh mill + Production Line + jendela hari.
 *
 * MENGGANTIKAN StationOverviewService, yang menerbitkan angka FASILITAS
 * (jumlah alat, stasiun, line). Keputusan pemilik produk 2026-10-20: yang
 * ingin dilihat di kartu adalah apa yang DIHASILKAN stasiun, bukan apa yang
 * TERPASANG di sana.
 *
 * ──────────────────────────────────────────────────────────────────────
 * KENAPA TIDAK MEMAKAI KEENAM BELAS REPORT SERVICE YANG SUDAH ADA
 * ──────────────────────────────────────────────────────────────────────
 * Semuanya mewajibkan sebuah PERIODE PELAPORAN (baris `periods` yang
 * tertaut ke jenis stasiun lewat `period_stations`), dan periode itu
 * berbeda-beda per stasiun. Memakainya di sini berarti halaman ini
 * menumbuhkan pemilih ketiga, dan — lebih buruk — kartu stasiun yang
 * periodenya belum dibuat akan kosong walaupun datanya ada.
 *
 * Jadi kelas ini membaca tabelnya LANGSUNG atas JENDELA TANGGAL, persis
 * seperti ManagementReportService melakukannya untuk tiga stasiun. Angkanya
 * karena itu TIDAK dijamin sama dengan laporan periode mana pun, dan tidak
 * seharusnya: keduanya menjawab pertanyaan yang berbeda. Halaman ini
 * menyatakan jendelanya di layar supaya tidak ada yang mengira sebaliknya.
 *
 * ──────────────────────────────────────────────────────────────────────
 * TIGA BENTUK SUMBER, DAN KETIGANYA ADA DI SKEMA INI
 * ──────────────────────────────────────────────────────────────────────
 *   - `record`  — metriknya ada di tabel record itu sendiri. Tiga stasiun:
 *                 weighbridge (yang bahkan TIDAK punya tabel detail),
 *                 grading (netto/quantity di record), dan cages-track
 *                 (cages_out/cages_tipped di record).
 *   - `detail`  — metriknya di tabel detail, di-join ke record-nya lewat
 *                 `<prefix>_record_id`. Lima belas stasiun.
 *   - Kolom tanggalnya `date` di tujuh belas tabel record dan
 *     `record_datetime` (timestamp) HANYA di weighbridge_records.
 *
 * Ketiganya DIPETAKAN EKSPLISIT di METRICS di bawah, bukan diturunkan dari
 * nama. Sebuah peta yang "pintar" akan meleset pada jenis stasiun berikutnya,
 * dan melesetnya berupa angka yang salah — bukan galat yang terlihat.
 */
class StationProductionService
{
    /** Jendela bawaan, dalam hari. */
    public const DEFAULT_DAYS = 30;

    /** Jendela yang boleh dipilih; apa pun di luar ini jatuh ke bawaan. */
    public const ALLOWED_DAYS = [7, 30, 90];

    /**
     * Metrik produksi per jenis stasiun.
     *
     * Tiap metrik: [label, agregat, kolom, satuan, desimal]. Agregat `count`
     * menghitung BARIS (muatan/trip/siklus), `sum` menjumlahkan, `avg`
     * merata-ratakan. Kolom null hanya sah untuk `count`.
     *
     * PEMILIHAN METRIKNYA TIDAK NETRAL dan layak dinyatakan: untuk stasiun
     * per-transaksi dipilih VOLUME (berapa dan berapa ton), untuk stasiun
     * berbasis pembacaan 24-slot dipilih satu ukuran PROSES dan satu ukuran
     * MUTU/KERUGIAN. Merata-ratakan pembacaan slot memang meratakan puncak,
     * dan itu benar untuk kartu ikhtisar — yang ingin dijawab di sini adalah
     * "stasiun ini berjalan di angka berapa", bukan "kapan ia menyimpang".
     * Pertanyaan kedua dijawab laporan periode, dan di sanalah tempatnya.
     *
     * @var array<string, array<string, mixed>>
     */
    protected const METRICS = [
        // CATATAN SATUAN, BERLAKU UNTUK SELURUH PETA INI: kolom ber-sufiks
        // `_mt` (kernel_dispatch, cpo_dispatch, solid_waste_disposal,
        // storage_tank) memang METRIK TON — diverifikasi pada storage_tank
        // yang rata-ratanya 1.048 untuk tangki 2.000 ton. Kolom TANPA sufiks
        // itu (weighbridge.net_weight, grading.netto) adalah KILOGRAM.
        // Satuan di sini DIAMBIL DARI KOLOMNYA, tidak pernah diseragamkan:
        // menyeragamkannya berarti mengonversi, dan konversi diam-diam di
        // satu layar adalah persis yang dilarang laporan Weighbridge web.
        'weighbridge' => [
            'source' => 'record', 'table' => 'weighbridge_records', 'date' => 'record_datetime',
            'metrics' => [
                ['Trip', 'count', null, 'trip', 0],
                // SATUANNYA kg, BUKAN MT. Diukur di kolomnya: net_weight
                // rata-rata 20.405 dengan maks 30.492 — itu kilogram satu
                // truk, bukan tonase. Versi pertama melabelinya MT dan
                // menghasilkan "15.899.356 MT dari 777 trip", sekitar dua
                // puluh ribu ton per truk. Laporan Weighbridge web memakai kg
                // dan punya komentar eksplisit bahwa kolomnya DILABELI (kg)
                // dan TIDAK dikonversi di satu layar; aturan itu diikuti di
                // sini, bukan ditafsir ulang.
                ['Tonase netto', 'sum', 'net_weight', 'kg', 1],
            ],
        ],
        'grading' => [
            'source' => 'record', 'table' => 'grading_records', 'date' => 'date',
            'metrics' => [
                ['Muatan disortir', 'count', null, 'muatan', 0],
                // kg, sama seperti weighbridge: grading_records.netto
                // rata-rata 8.046. Laporan Grading web juga memakai kg.
                ['Netto', 'sum', 'netto', 'kg', 1],
            ],
        ],
        'cages-track' => [
            'source' => 'record', 'table' => 'cages_track_records', 'date' => 'date',
            'metrics' => [
                ['Lori keluar', 'sum', 'cages_out', 'lori', 0],
                ['Lori ditippler', 'sum', 'cages_tipped', 'lori', 0],
            ],
        ],
        'sterilizer' => [
            'source' => 'detail', 'table' => 'sterilizer_details', 'fk' => 'sterilizer_record_id',
            'record_table' => 'sterilizer_records', 'date' => 'date',
            'metrics' => [
                ['Siklus rebus', 'count', null, 'siklus', 0],
                ['Rata-rata durasi', 'avg', 'duration_minutes', 'menit', 0],
                ['Lori direbus', 'sum', 'number_of_cages', 'lori', 0],
            ],
        ],
        'threshing' => [
            'source' => 'detail', 'table' => 'threshing_details', 'fk' => 'threshing_record_id',
            'record_table' => 'threshing_records', 'date' => 'date',
            'metrics' => [
                ['Throughput TBS', 'avg', 'ffb_throughput_mt_hour', 'MT/jam', 1],
                ['Oil loss EFB', 'avg', 'empty_bunch_oil_loss_percent', '%', 2],
            ],
        ],
        'pressing' => [
            'source' => 'detail', 'table' => 'pressing_details', 'fk' => 'pressing_record_id',
            'record_table' => 'pressing_records', 'date' => 'date',
            'metrics' => [
                ['Suhu digester', 'avg', 'digester_temp_c', '°C', 1],
                ['Arus motor press', 'avg', 'press_motor_current_amps', 'A', 1],
            ],
        ],
        'clarification' => [
            'source' => 'detail', 'table' => 'clarification_details', 'fk' => 'clarification_record_id',
            'record_table' => 'clarification_records', 'date' => 'date',
            'metrics' => [
                ['Laju pure oil', 'avg', 'pure_oil_production_rate_ton_hour', 'ton/jam', 2],
                ['Downtime', 'sum', 'downtime_mins', 'menit', 0],
            ],
        ],
        'kernel-plant' => [
            'source' => 'detail', 'table' => 'kernel_plant_details', 'fk' => 'kernel_plant_record_id',
            'record_table' => 'kernel_plant_records', 'date' => 'date',
            'metrics' => [
                ['Kadar air kernel', 'avg', 'kernel_moisture_percent', '%', 2],
                ['Kernel loss di cangkang', 'avg', 'shell_bin_kernel_loss_percent', '%', 2],
            ],
        ],
        'boiler-room' => [
            'source' => 'detail', 'table' => 'boiler_room_details', 'fk' => 'boiler_room_record_id',
            'record_table' => 'boiler_room_records', 'date' => 'date',
            'metrics' => [
                ['Tekanan uap', 'avg', 'steam_pressure_bar', 'bar', 1],
                ['TDS air', 'avg', 'water_tds_ppm', 'ppm', 0],
            ],
        ],
        'effluent-plant' => [
            'source' => 'detail', 'table' => 'effluent_plant_details', 'fk' => 'effluent_plant_record_id',
            'record_table' => 'effluent_plant_records', 'date' => 'date',
            'metrics' => [
                ['Laju biogas', 'avg', 'biogas_flow_rate_m3h', 'm³/jam', 1],
                ['BOD akhir', 'avg', 'final_discharge_bod_mgl_lab', 'mg/L', 1],
            ],
        ],
        'depricarping' => [
            'source' => 'detail', 'table' => 'depricarping_details', 'fk' => 'depricarping_record_id',
            'record_table' => 'depricarping_records', 'date' => 'date',
            'metrics' => [
                ['Kernel loss di serat', 'avg', 'kernel_loss_in_fibre_percent', '%', 2],
                ['Kadar air serat', 'avg', 'fibre_moisture_percent', '%', 2],
            ],
        ],
        'engine-room' => [
            'source' => 'detail', 'table' => 'engine_room_details', 'fk' => 'engine_room_record_id',
            'record_table' => 'engine_room_records', 'date' => 'date',
            'metrics' => [
                ['Beban pabrik', 'avg', 'electrical_sync_total_factory_load_kw', 'kW', 0],
                ['Ekspor energi', 'sum', 'daily_energy_export_kwh', 'kWh', 0],
            ],
        ],
        'process-water' => [
            'source' => 'detail', 'table' => 'process_water_details', 'fk' => 'process_water_record_id',
            'record_table' => 'process_water_records', 'date' => 'date',
            'metrics' => [
                ['Laju air baku', 'avg', 'raw_water_flow_m3h', 'm³/jam', 1],
                ['TDS umpan boiler', 'avg', 'boiler_feed_tds_ppm', 'ppm', 0],
            ],
        ],
        'storage-tank' => [
            'source' => 'detail', 'table' => 'storage_tank_details', 'fk' => 'storage_tank_record_id',
            'record_table' => 'storage_tank_records', 'date' => 'date',
            'metrics' => [
                ['Stok CPO terukur', 'avg', 'calculated_weight_mt', 'MT', 1],
                ['ALB', 'avg', 'ffa_percent', '%', 2],
            ],
        ],
        'solid-waste-disposal' => [
            'source' => 'detail', 'table' => 'solid_waste_disposal_details', 'fk' => 'solid_waste_disposal_record_id',
            'record_table' => 'solid_waste_disposal_records', 'date' => 'date',
            'metrics' => [
                ['Muatan', 'count', null, 'muatan', 0],
                ['Tonase netto', 'sum', 'net_weight_mt', 'MT', 1],
            ],
        ],
        'kernel-dispatch' => [
            'source' => 'detail', 'table' => 'kernel_dispatch_details', 'fk' => 'kernel_dispatch_record_id',
            'record_table' => 'kernel_dispatch_records', 'date' => 'date',
            'metrics' => [
                ['Pengiriman', 'count', null, 'kirim', 0],
                ['Tonase netto', 'sum', 'net_weight_mt', 'MT', 1],
                ['Kadar air kernel', 'avg', 'kernel_moisture_percent', '%', 2],
            ],
        ],
        'cpo-dispatch' => [
            'source' => 'detail', 'table' => 'cpo_dispatch_details', 'fk' => 'cpo_dispatch_record_id',
            'record_table' => 'cpo_dispatch_records', 'date' => 'date',
            'metrics' => [
                ['Pengiriman', 'count', null, 'kirim', 0],
                ['Tonase netto', 'sum', 'net_weight_mt', 'MT', 1],
                ['ALB', 'avg', 'ffa_percent', '%', 2],
            ],
        ],
        'process-quality-control' => [
            'source' => 'detail', 'table' => 'process_quality_control_details', 'fk' => 'process_quality_control_record_id',
            'record_table' => 'process_quality_control_records', 'date' => 'date',
            'metrics' => [
                ['Oil loss di sludge', 'avg', 'fruit_press_oil_loss_in_sludge_percent', '%', 2],
                ['ALB storage akhir', 'avg', 'final_storage_ffa_percent', '%', 2],
            ],
        ],
    ];

    /**
     * Master target operasi — HANYA EMPAT jenis stasiun yang punya.
     *
     * Nama kolomnya BERBEDA di keempat tabel; itu bukan dugaan, versi pertama
     * kelas pendahulunya menyimpulkan dari satu tabel dan gagal di tabel
     * kedua. Nilainya TEKS BEBAS ("≤ 7.0%"), dicetak apa adanya dan tidak
     * pernah dibandingkan dengan angka terukur di atas: membandingkannya
     * berarti mengurai prosa menjadi ambang, dan ambang yang diurai dari
     * prosa adalah ambang yang dikarang.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    protected const TARGET_TABLES = [
        'kernel-plant' => ['kernel_plant_operational_targets', 'equipment_parameter', 'target_benchmark'],
        'depricarping' => ['depricarping_operational_targets', 'parameter_metric', 'target_range'],
        'pressing' => ['pressing_operational_targets', 'parameter_metric', 'target_operating_range'],
        'threshing' => ['threshing_operational_targets', 'parameter', 'standard_operational_target'],
    ];

    /**
     * @return array{
     *   scope: array<string, mixed>,
     *   mills: list<array{id: string, name: string}>,
     *   lines: list<array{id: string, name: string}>,
     *   stations: array<string, array<string, mixed>>
     * }
     */
    public function overview(?string $requestedBusinessUnitId, ?string $requestedLineId, ?int $requestedDays): array
    {
        $isAdmin = $this->isAdmin();
        $businessUnitId = $this->resolveBusinessUnit($requestedBusinessUnitId);
        $days = in_array($requestedDays, self::ALLOWED_DAYS, true) ? $requestedDays : self::DEFAULT_DAYS;

        $mills = $isAdmin ? $this->millOptions() : [];
        $lines = $businessUnitId === null ? [] : $this->lineOptions($businessUnitId);
        $lineId = $businessUnitId === null ? null : $this->resolveLine($businessUnitId, $requestedLineId);

        // Line WAJIB dipilih sebelum satu angka pun dihitung — aturan yang
        // sama dengan seluruh layar laporan di proyek ini (isolasi Production
        // Line, keputusan pemilik produk). Selama belum dipilih, kelas ini
        // TIDAK menjalankan satu kueri agregat pun: menampilkan total semua
        // line lalu membiarkan pengguna menyempitkannya akan sempat
        // memperlihatkan angka yang tidak dapat ditindaklanjuti.
        $stations = $lineId === null
            ? $this->emptyStations()
            : $this->productionFor($businessUnitId, $lineId, $days);

        return [
            'scope' => [
                'is_admin' => $isAdmin,
                'business_unit_id' => $businessUnitId,
                'business_unit_name' => $businessUnitId === null ? null : $this->millName($businessUnitId),
                'production_line_id' => $lineId,
                'production_line_name' => $lineId === null ? null : $this->lineName($lineId),
                'days' => $days,
                'allowed_days' => self::ALLOWED_DAYS,
                'needs_mill' => $businessUnitId === null,
                'needs_line' => $businessUnitId !== null && $lineId === null,
            ],
            'mills' => $mills,
            'lines' => $lines,
            'stations' => $stations,
        ];
    }

    protected function isAdmin(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        $role = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;

        return $role === UserRole::Admin->value;
    }

    /**
     * Mill yang dipakai. Peran terikat mill SELALU mendapat mill akunnya dan
     * nilai kiriman klien DIBUANG — tidak divalidasi, tidak dibandingkan,
     * dibuang. Hanya Admin yang boleh memilih, dan sebelum ia memilih,
     * nilainya null (bukan "semua mill": lihat catatan di overview()).
     */
    protected function resolveBusinessUnit(?string $requested): ?string
    {
        $user = auth()->user();

        if ($user === null) {
            return null;
        }

        if (! $this->isAdmin()) {
            $own = (string) ($user->business_unit_id ?? '');

            return $own === '' ? null : $own;
        }

        if ($requested === null || $requested === '' || ! Str::isUuid($requested)) {
            return null;
        }

        return DB::table('business_units')->where('id', $requested)->exists() ? $requested : null;
    }

    /**
     * Line yang sah untuk mill ini, atau null.
     *
     * Str::isUuid() DULU: nilai bukan-UUID tidak boleh sampai ke SQL —
     * PostgreSQL menjawabnya dengan SQLSTATE 22P02 dan itu menjadi 500. Pola
     * ini disalin dari ManagementReportService::resolveProductionLineOrNull,
     * bukan ditulis ulang.
     */
    protected function resolveLine(string $businessUnitId, ?string $requested): ?string
    {
        if ($requested === null || $requested === '' || ! Str::isUuid($requested)) {
            return null;
        }

        return DB::table('production_lines')
            ->where('id', $requested)
            ->where('business_unit_id', $businessUnitId)
            ->exists() ? $requested : null;
    }

    /** @return list<array{id: string, name: string}> */
    protected function millOptions(): array
    {
        return DB::table('business_units')->orderBy('name')->get(['id', 'name'])
            ->map(fn ($r) => ['id' => (string) $r->id, 'name' => (string) $r->name])->all();
    }

    /** @return list<array{id: string, name: string}> */
    protected function lineOptions(string $businessUnitId): array
    {
        return DB::table('production_lines')->where('business_unit_id', $businessUnitId)
            ->orderBy('name')->get(['id', 'name'])
            ->map(fn ($r) => ['id' => (string) $r->id, 'name' => (string) $r->name])->all();
    }

    protected function millName(string $id): string
    {
        return (string) (DB::table('business_units')->where('id', $id)->value('name') ?? 'Mill tidak dikenal');
    }

    protected function lineName(string $id): string
    {
        return (string) (DB::table('production_lines')->where('id', $id)->value('name') ?? 'Line tidak dikenal');
    }

    /**
     * Kerangka kosong — dipakai selama mill/line belum dipilih, supaya layar
     * tetap merender kedelapan belas kartunya dengan bentuk yang sama dan
     * tidak perlu punya dua jalur render.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function emptyStations(): array
    {
        $out = [];

        foreach (self::METRICS as $code => $config) {
            $out[$code] = [
                'row_count' => 0,
                'metrics' => array_map(fn (array $m) => [
                    'label' => $m[0], 'value' => null, 'unit' => $m[3],
                ], $config['metrics']),
                'targets' => [],
            ];
        }

        return $out;
    }

    /**
     * Satu kueri agregat per jenis stasiun. Delapan belas kueri kecil, bukan
     * satu UNION raksasa: metriknya berbeda kolom DAN berbeda agregat per
     * stasiun, sehingga sebuah UNION harus menyamakan delapan belas bentuk
     * yang memang tidak sama — dan setiap kolom yang dipaksa masuk bentuk
     * bersama adalah satu tempat lagi untuk salah diam-diam.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function productionFor(string $businessUnitId, string $lineId, int $days): array
    {
        $since = now()->subDays($days)->toDateString();
        $targets = $this->targets();
        $out = [];

        foreach (self::METRICS as $code => $config) {
            $isDetail = $config['source'] === 'detail';
            $recordTable = $isDetail ? $config['record_table'] : $config['table'];
            $dateColumn = $config['date'];
            // Kolom timestamp dipotong ke tanggal supaya perbandingannya
            // sama bentuk dengan ketujuh belas tabel ber-kolom `date`.
            $dateExpr = $dateColumn === 'date' ? 'r.date' : "cast(r.{$dateColumn} as date)";

            $query = DB::table($recordTable.' as r')
                ->join('stations as s', 's.id', '=', 'r.station_id')
                ->where('s.business_unit_id', $businessUnitId)
                ->where('r.production_line_id', $lineId)
                ->whereRaw("{$dateExpr} >= ?", [$since]);

            if ($isDetail) {
                $query->join($config['table'].' as d', 'd.'.$config['fk'], '=', 'r.id');
            }

            $prefix = $isDetail ? 'd' : 'r';
            $query->selectRaw('count(*) as row_count');

            foreach ($config['metrics'] as $i => [$label, $agg, $column, $unit, $decimals]) {
                $expr = $agg === 'count'
                    ? 'count(*)'
                    : "{$agg}({$prefix}.{$column})";
                $query->selectRaw("{$expr} as m{$i}");
            }

            $row = $query->first();

            $metrics = [];

            foreach ($config['metrics'] as $i => [$label, $agg, $column, $unit, $decimals]) {
                $raw = $row->{'m'.$i} ?? null;

                // null DIPERTAHANKAN, tidak pernah diganti nol: "tidak ada
                // pembacaan" dan "terukur nol" adalah dua hal berbeda, dan
                // menukarnya di kartu ikhtisar adalah cara paling mudah
                // melaporkan pabrik yang berhenti sebagai pabrik yang normal.
                $metrics[] = [
                    'label' => $label,
                    'value' => $raw === null ? null : round((float) $raw, $decimals),
                    'unit' => $unit,
                ];
            }

            $out[$code] = [
                'row_count' => (int) ($row->row_count ?? 0),
                'metrics' => $metrics,
                'targets' => $targets[$code] ?? [],
            ];
        }

        return $out;
    }

    /** @return array<string, list<array{parameter: string, benchmark: string}>> */
    protected function targets(): array
    {
        $out = [];

        foreach (self::TARGET_TABLES as $code => [$table, $parameterColumn, $benchmarkColumn]) {
            $out[$code] = DB::table($table)->orderBy('sort_order')
                ->get([$parameterColumn, $benchmarkColumn])
                ->map(fn ($r) => [
                    'parameter' => (string) $r->{$parameterColumn},
                    'benchmark' => (string) $r->{$benchmarkColumn},
                ])->all();
        }

        return $out;
    }
}
