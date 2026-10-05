<?php

namespace Database\Seeders;

use App\Enums\PeriodStatus;
use App\Enums\RecordStatus;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use App\Services\PeriodService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * DemoReportDataSeeder — data demo untuk layar Laporan Stasiun (screen-140)
 * dan enam laporan per stasiun: Weighbridge, Sterilizer, Cages & Tracks,
 * Boiler Room, Clarification, Storage Tank.
 *
 * CAKUPAN: hanya mill demo "Business Unit A", Line 1 (utama) dan Line 2
 * (sekitar 1/3 volume Line 1, supaya filter line terlihat mengubah angka).
 * Rentang tanggal 2026-09-01 .. 2026-10-03, tidak pernah melewati hari ini.
 *
 * PERIODE DEMO (2026-10-05): laporan hanya tampil bila ada Periode Pelaporan
 * yang mencakup tanggal datanya. Seeder ini memastikan dua periode demo ada
 * untuk Business Unit A — "September 2026" (1–30 Sep, seluruh stasiun
 * CLOSED) dan "Oktober 2026" (1–31 Okt, seluruh stasiun DRAFT) — sehingga
 * database baru (migrate:fresh --seed) langsung bisa dipakai melihat laporan.
 * Periode dengan rentang yang SAMA PERSIS dipakai ulang (tidak dibuat ganda);
 * bila rentangnya bertabrakan dengan periode lain milik mill itu (mis. periode
 * buatan pengguna), periode demo itu DILEWATI dengan peringatan — aturan
 * "periode tidak boleh tumpang-tindih per mill" tidak pernah dilanggar.
 *
 * IDEMPOTEN: setiap header yang dibuat seeder ini memakai awalan "DEMO-RPT-"
 * pada kolom ID/nomornya (sterilizer_id, cages_track_number, boiler_room_id,
 * clarification_id, storage_tank_id, wb_card_number). Saat dijalankan ulang,
 * HANYA baris berawalan itu yang dihapus (detail ikut terhapus lewat
 * ON DELETE CASCADE) lalu dibuat ulang. Data lain tidak disentuh.
 *
 * DETERMINISTIK: semua angka berasal dari mt_rand() dengan seed tetap,
 * sehingga dua kali jalan menghasilkan angka yang sama persis (UUID tetap
 * acak — itu tidak memengaruhi angka laporan).
 *
 * KELENGKAPAN SENGAJA TIDAK SEMPURNA: beberapa hari kosong total, satu hari
 * hanya setengah shift, dan sebagian kecil slot jam acak terlewat — supaya
 * kartu "kelengkapan pencatatan" di laporan menampilkan sesuatu yang nyata.
 *
 * Insert langsung lewat query builder (konteks seeder) — kunci periode dan
 * validasi HTTP sengaja dilewati. Untuk periode September yang sudah ada,
 * baris period_stations yang belum ada ditambahkan (status closed); baris
 * yang sudah ada tidak pernah diubah.
 */
class DemoReportDataSeeder extends Seeder
{
    protected const PREFIX = 'DEMO-RPT-';

    protected const BUSINESS_UNIT_NAME = 'Business Unit A';

    protected const START_DATE = '2026-09-01';

    protected const END_DATE = '2026-10-03';

    /** Seed acak tetap — ganti hanya bila memang ingin angka demo berbeda. */
    protected const RANDOM_SEED = 20260930;

    /** Jenis stasiun yang dilaporkan dan wajib punya baris di periode September. */
    protected const REPORT_STATION_TYPES = [
        'weighbridge', 'sterilizer', 'cages-track', 'boiler-room', 'clarification', 'storage-tank',
    ];

    /**
     * Profil per line. `scale` ≈ volume relatif terhadap Line 1; `skip_days`
     * = hari tanpa catatan sama sekali (stasiun per jam); `half_days` = hari
     * yang hanya tercatat setengah shift pertama.
     */
    protected const LINES = [
        'Line 1' => [
            'code' => 'L1',
            'scale' => 1.0,
            'sterilizers' => ['1', '2', '3'],
            'boilers' => ['01', '02'],
            'tanks' => ['01' => ['area_m2' => 201.06, 'opening_mt' => 1250.0, 'max_mt' => 2300.0], '02' => ['area_m2' => 201.06, 'opening_mt' => 780.0, 'max_mt' => 2300.0]],
            'cage_fleet' => 120,
            'skip_days' => ['2026-09-13'],
            'half_days' => ['2026-09-22'],
        ],
        'Line 2' => [
            'code' => 'L2',
            'scale' => 0.33,
            'sterilizers' => ['1'],
            'boilers' => ['01'],
            'tanks' => ['01' => ['area_m2' => 95.03, 'opening_mt' => 420.0, 'max_mt' => 950.0]],
            'cage_fleet' => 45,
            'skip_days' => ['2026-09-06', '2026-09-20'],
            'half_days' => ['2026-09-15'],
        ],
    ];

    protected const ESTATES = [
        'Kebun Inti Sei Rokan', 'Kebun Plasma KUD Makmur', 'Kebun Inti Tanjung Medan',
        'Koperasi Tani Sawit Jaya', 'Pemasok Luar CV Mitra Tandan', 'Kebun Inti Bukit Batu',
    ];

    protected const DIVISIONS = ['Divisi I', 'Divisi II', 'Divisi III', 'Divisi IV'];

    protected const DISPATCH_DESTINATIONS = ['KCP Dumai', 'Refinery Belawan', 'Tangki Timbun Pelabuhan Dumai'];

    protected const TRANSPORTERS = ['PT Sinar Angkutan Sawit', 'CV Lintas Tangki Riau'];

    protected const DRIVERS = [
        'Budi Santoso', 'Agus Salim', 'Rahmat Hidayat', 'Joko Susilo', 'Hendra Gunawan',
        'Slamet Riyadi', 'Dedi Kurniawan', 'Andi Saputra', 'Wahyu Pratama', 'Supriyadi',
    ];

    protected array $users = [];

    protected string $now;

    public function run(): void
    {
        mt_srand(self::RANDOM_SEED);
        $this->now = now()->toDateTimeString();

        $businessUnit = BusinessUnit::where('name', self::BUSINESS_UNIT_NAME)->first();

        if ($businessUnit === null) {
            $this->command?->warn('DemoReportDataSeeder: "'.self::BUSINESS_UNIT_NAME.'" tidak ditemukan — dilewati.');

            return;
        }

        $this->users = [
            'operator' => User::where('username', 'operator01')->value('id'),
            'supervisor' => User::where('username', 'supervisor01')->value('id'),
            'mill_management' => User::where('username', 'millmanagement-a')->value('id'),
            'admin' => User::where('username', 'admin')->value('id'),
            'operator_name' => User::where('username', 'operator01')->value('name') ?? 'Operator 01',
        ];

        if ($this->users['operator'] === null) {
            $this->command?->warn('DemoReportDataSeeder: akun operator01 tidak ditemukan — dilewati.');

            return;
        }

        DB::transaction(function () use ($businessUnit): void {
            $this->purgePreviousDemoRows();
            $this->ensureDemoPeriods($businessUnit);
            $this->ensureSeptemberPeriodStations($businessUnit);

            foreach (self::LINES as $lineName => $profile) {
                $line = ProductionLine::where('business_unit_id', $businessUnit->id)->where('name', $lineName)->first();

                if ($line === null) {
                    continue;
                }

                $stations = Station::where('production_line_id', $line->id)
                    ->where('is_active', true)
                    ->get()
                    ->keyBy(fn (Station $station) => $station->type instanceof \BackedEnum ? $station->type->value : (string) $station->type);

                foreach ($this->dates() as $date) {
                    $this->seedWeighbridge($stations->get('weighbridge'), $line->id, $profile, $date);
                    $this->seedSterilizer($stations->get('sterilizer'), $line->id, $profile, $date);
                    $this->seedCagesTrack($stations->get('cages-track'), $line->id, $profile, $date);
                    $this->seedBoilerRoom($stations->get('boiler-room'), $line->id, $profile, $date);
                    $this->seedClarification($stations->get('clarification'), $line->id, $profile, $date);
                }

                // Storage Tank disimulasikan sepanjang rentang sekaligus: stok
                // adalah STATE yang berlanjut dari hari ke hari.
                $this->seedStorageTank($stations->get('storage-tank'), $line->id, $profile);
            }
        });
    }

    // ------------------------------------------------------------------
    // Persiapan
    // ------------------------------------------------------------------

    /** Hapus HANYA header berawalan DEMO-RPT- (detail ikut via cascade). */
    protected function purgePreviousDemoRows(): void
    {
        $like = self::PREFIX.'%';

        DB::table('weighbridge_records')->where('wb_card_number', 'like', $like)->delete();
        DB::table('sterilizer_records')->where('sterilizer_id', 'like', $like)->delete();
        DB::table('cages_track_records')->where('cages_track_number', 'like', $like)->delete();
        DB::table('boiler_room_records')->where('boiler_room_id', 'like', $like)->delete();
        DB::table('clarification_records')->where('clarification_id', 'like', $like)->delete();
        DB::table('storage_tank_records')->where('storage_tank_id', 'like', $like)->delete();
    }

    /**
     * Periode demo yang dipastikan ada. Status berlaku untuk SEMUA baris
     * period_stations yang dibuat (satu per jenis stasiun aktif di mill).
     */
    protected const DEMO_PERIODS = [
        ['name' => 'September 2026', 'start' => '2026-09-01', 'end' => '2026-09-30', 'status' => PeriodStatus::Closed],
        ['name' => 'Oktober 2026', 'start' => '2026-10-01', 'end' => '2026-10-31', 'status' => PeriodStatus::Draft],
    ];

    /**
     * Buat periode demo yang belum ada. Tiga kemungkinan per periode:
     *   - sudah ada periode dengan rentang sama persis → dipakai ulang, tidak disentuh;
     *   - ada periode lain yang rentangnya bertabrakan, atau namanya sudah dipakai
     *     → dilewati dengan peringatan (aturan overlap/nama unik per mill);
     *   - selain itu → dibuat, beserta satu baris period_stations per jenis
     *     stasiun aktif di mill (sumber yang sama dengan PeriodService::create()).
     */
    protected function ensureDemoPeriods(BusinessUnit $businessUnit): void
    {
        $stationTypes = app(PeriodService::class)->activeStationTypesForMill($businessUnit->id);

        foreach (self::DEMO_PERIODS as $demo) {
            $sameRange = Period::where('business_unit_id', $businessUnit->id)
                ->whereDate('start_date', $demo['start'])
                ->whereDate('end_date', $demo['end'])
                ->exists();

            if ($sameRange) {
                continue;
            }

            $conflict = Period::where('business_unit_id', $businessUnit->id)
                ->where(fn ($q) => $q
                    ->where(fn ($r) => $r->whereDate('start_date', '<=', $demo['end'])->whereDate('end_date', '>=', $demo['start']))
                    ->orWhere('name', $demo['name']))
                ->first();

            if ($conflict !== null) {
                $this->command?->warn('DemoReportDataSeeder: periode demo "'.$demo['name'].'" dilewati — bertabrakan dengan periode "'.$conflict->name.'".');

                continue;
            }

            $period = Period::create([
                'business_unit_id' => $businessUnit->id,
                'name' => $demo['name'],
                'start_date' => $demo['start'],
                'end_date' => $demo['end'],
                'created_by' => $this->users['admin'],
            ]);

            $closed = $demo['status'] === PeriodStatus::Closed;

            foreach ($stationTypes as $code) {
                PeriodStation::create([
                    'period_id' => $period->id,
                    'station_type' => $code,
                    'status' => $demo['status'],
                    'closed_by' => $closed ? $this->users['admin'] : null,
                    'closed_at' => $closed ? '2026-10-01 08:00:00' : null,
                ]);
            }
        }
    }

    /**
     * Periode "sep 2026" milik pengguna hanya punya baris Sterilizer dan
     * Weighbridge. Baris jenis stasiun laporan yang belum ada ditambahkan
     * dengan status closed (September sudah selesai). Baris yang sudah ada
     * tidak diubah, nama/tanggal periode tidak disentuh. Tidak ada aturan
     * overlap yang dilanggar: aturan itu berlaku per tanggal per mill pada
     * tingkat periode, dan rentang periode tidak berubah.
     */
    protected function ensureSeptemberPeriodStations(BusinessUnit $businessUnit): void
    {
        $period = Period::where('business_unit_id', $businessUnit->id)
            ->whereDate('start_date', '2026-09-01')
            ->whereDate('end_date', '2026-09-30')
            ->first();

        if ($period === null) {
            return;
        }

        foreach (self::REPORT_STATION_TYPES as $type) {
            $exists = PeriodStation::where('period_id', $period->id)->where('station_type', $type)->exists();

            if (! $exists) {
                PeriodStation::create([
                    'period_id' => $period->id,
                    'station_type' => $type,
                    'status' => PeriodStatus::Closed,
                    'closed_by' => $this->users['admin'],
                    'closed_at' => '2026-10-01 08:00:00',
                ]);
            }
        }
    }

    /** @return list<string> */
    protected function dates(): array
    {
        $dates = [];
        $cursor = CarbonImmutable::parse(self::START_DATE);
        $end = CarbonImmutable::parse(self::END_DATE);

        while ($cursor->lte($end)) {
            $dates[] = $cursor->toDateString();
            $cursor = $cursor->addDay();
        }

        return $dates;
    }

    /** 24 slot kanonik 07:00 .. 06:00, sama dengan *RecordService::canonicalTimeSlots(). */
    protected function timeSlots(): array
    {
        return array_map(fn (int $i) => sprintf('%02d:00', (7 + $i) % 24), range(0, 23));
    }

    /**
     * Header status & verifikasi. September: sebagian besar sudah dicek
     * Supervisor, sebagian juga diketahui Mill Management. Oktober: masih
     * berjalan — kebanyakan belum diverifikasi.
     */
    protected function headerMeta(string $date): array
    {
        $isSeptember = str_starts_with($date, '2026-09');
        $checked = $this->chance($isSeptember ? 80 : 25);
        $acknowledged = $checked && $this->chance($isSeptember ? 55 : 10);
        $status = $date === self::END_DATE && $this->chance(50)
            ? RecordStatus::Saved->value
            : ($this->chance(96) ? RecordStatus::Synced->value : RecordStatus::Saved->value);

        return [
            'checked_by' => $checked ? $this->users['supervisor'] : null,
            'acknowledged_by' => $acknowledged ? $this->users['mill_management'] : null,
            'status' => $status,
            'created_by' => $this->users['operator'],
            'created_at' => $date.' 07:30:00',
            'updated_at' => $this->now,
        ];
    }

    protected function isSkipDay(array $profile, string $date): bool
    {
        return in_array($date, $profile['skip_days'], true);
    }

    /** Berapa slot (dari 24) yang dicatat hari itu; setengah hari = shift pertama saja. */
    protected function slotLimit(array $profile, string $date): int
    {
        return in_array($date, $profile['half_days'], true) ? 12 : 24;
    }

    // ------------------------------------------------------------------
    // Weighbridge — trip TBS masuk dan CPO keluar
    // ------------------------------------------------------------------

    protected function seedWeighbridge(?Station $station, string $lineId, array $profile, string $date): void
    {
        if ($station === null) {
            return;
        }

        $rows = [];
        // Line 1 mengolah ± 45 t TBS/jam ≈ 900 t TBS/hari ≈ 40–55 truk TBS
        // (± 18 t netto per truk) — sejalan dengan ± 200 t CPO/hari di laporan
        // Clarification dan Storage Tank.
        $receiveTrips = (int) round($this->between(40, 55) * $profile['scale']) + ($profile['scale'] < 1 ? 1 : 0);
        // Truk tangki CPO ± 27 t: Line 1 memproduksi ± 200 t CPO/hari.
        $dispatchTrips = $profile['scale'] < 1 ? $this->between(1, 3) : $this->between(5, 9);
        $sequence = 1;

        for ($i = 0; $i < $receiveTrips; $i++) {
            $gross = $this->float(24000, 32000, 0);
            $tare = $this->float(7200, 9800, 0);
            $net = $gross - $tare;
            $isDraft = $date === self::END_DATE && $i === 0;

            $rows[] = $this->weighbridgeRow($station, $lineId, $profile, $date, $sequence++, 'receive', $this->tripTime($date, 7, 21), [
                'estate_supplier' => $this->pick(self::ESTATES),
                'division' => $this->pick(self::DIVISIONS),
                'block' => 'Blok '.sprintf('%02d', $this->between(1, 48)),
                'destination' => null,
                'gross_weight' => $gross,
                'tare_weight' => $isDraft ? null : $tare,
                'net_weight' => $isDraft ? null : $net,
                // quantity = jumlah janjang pada truk TBS.
                'quantity' => (float) round($net / $this->float(17, 23, 1)),
                'status_override' => $isDraft ? RecordStatus::DraftOngoing->value : null,
            ]);
        }

        for ($i = 0; $i < $dispatchTrips; $i++) {
            $tare = $this->float(11500, 13500, 0);
            $net = $this->float(24000, 30500, 0);

            $rows[] = $this->weighbridgeRow($station, $lineId, $profile, $date, $sequence++, 'dispatch', $this->tripTime($date, 8, 17), [
                'estate_supplier' => $this->pick(self::TRANSPORTERS),
                'division' => null,
                'block' => null,
                'destination' => $this->pick(self::DISPATCH_DESTINATIONS),
                'gross_weight' => $tare + $net,
                'tare_weight' => $tare,
                'net_weight' => $net,
                'quantity' => 1.0,
                'status_override' => null,
            ]);
        }

        if ($rows !== []) {
            DB::table('weighbridge_records')->insert($rows);
        }
    }

    protected function weighbridgeRow(Station $station, string $lineId, array $profile, string $date, int $sequence, string $type, string $datetime, array $fields): array
    {
        $meta = $this->headerMeta($date);
        $statusOverride = $fields['status_override'];
        unset($fields['status_override']);

        return array_merge([
            'id' => (string) Str::orderedUuid(),
            'station_id' => $station->id,
            'production_line_id' => $lineId,
            'wb_card_number' => self::PREFIX.'WB-'.$profile['code'].'-'.str_replace('-', '', $date).'-'.sprintf('%03d', $sequence),
            'weighbridge_type' => $type,
            'record_datetime' => $datetime,
            'vehicle_number' => 'BM '.$this->between(8000, 9899).' '.$this->pick(['AB', 'RC', 'TU', 'FA', 'KD']),
            'driver_name' => $this->pick(self::DRIVERS),
        ], $fields, $meta, $statusOverride !== null ? ['status' => $statusOverride, 'checked_by' => null, 'acknowledged_by' => null] : []);
    }

    protected function tripTime(string $date, int $fromHour, int $toHour): string
    {
        return sprintf('%s %02d:%02d:00', $date, $this->between($fromHour, $toHour), $this->between(0, 59));
    }

    // ------------------------------------------------------------------
    // Sterilizer — satu record per hari per line, satu detail per siklus
    // ------------------------------------------------------------------

    protected function seedSterilizer(?Station $station, string $lineId, array $profile, string $date): void
    {
        if ($station === null || $this->isSkipDay($profile, $date)) {
            return;
        }

        $recordId = (string) Str::orderedUuid();
        $meta = $this->headerMeta($date);

        DB::table('sterilizer_records')->insert(array_merge([
            'id' => $recordId,
            'station_id' => $station->id,
            'production_line_id' => $lineId,
            'sterilizer_id' => self::PREFIX.'STR-'.$profile['code'].'-'.str_replace('-', '', $date),
            'date' => $date,
            'note' => $this->chance(10) ? 'Perebusan triple peak normal, tekanan uap stabil.' : null,
        ], $meta));

        $halfDay = in_array($date, $profile['half_days'], true);
        $details = [];

        foreach ($profile['sterilizers'] as $index => $sterilizerNo) {
            // Tiap rebusan mulai bergiliran 30 menit agar uap tidak berebut.
            $cursor = 7 * 60 + $index * 30 + $this->between(0, 10);
            $endOfDay = $halfDay ? 19 * 60 : 7 * 60 + 18 * 60;

            while ($cursor < $endOfDay) {
                $duration = $this->between(84, 98);

                if ($this->chance(3)) {
                    $duration = $this->between(118, 140); // siklus macet — muncul sebagai outlier
                }

                $incompletePeak = $this->chance(7);
                $close = $cursor;
                $open = $close + $duration;

                $details[] = [
                    'id' => (string) Str::orderedUuid(),
                    'sterilizer_record_id' => $recordId,
                    'sterilizer_no' => $sterilizerNo,
                    'close_door_time' => $this->clock($close),
                    'peak_1_time' => $this->clock($close + 12),
                    'exhaust_1_time' => $this->clock($close + 16),
                    'peak_2_time' => $this->clock($close + 30),
                    'exhaust_2_time' => $this->clock($close + 35),
                    'peak_3_time' => $incompletePeak ? null : $this->clock($close + 52),
                    'exhaust_3_time' => $incompletePeak ? null : $this->clock($open - 6),
                    'open_door_time' => $this->clock($open),
                    'duration_minutes' => $this->chance(2) ? null : $duration,
                    'number_of_cages' => $this->between(8, 10),
                    'cages_status' => $this->chance(90) ? 'Penuh' : 'Kurang',
                    'checked_by_spv' => $meta['checked_by'] !== null,
                    'remarks' => $incompletePeak ? 'Peak ke-3 tidak tercapai, tekanan boiler turun' : null,
                    'created_at' => $meta['created_at'],
                    'updated_at' => $this->now,
                ];

                // Bongkar-muat lori sebelum siklus berikutnya.
                $cursor = $open + $this->between(12, 20);
            }
        }

        $this->insertChunked('sterilizer_details', $details);
    }

    // ------------------------------------------------------------------
    // Cages & Tracks — satu record per hari, detail per jam tipping
    // ------------------------------------------------------------------

    protected function seedCagesTrack(?Station $station, string $lineId, array $profile, string $date): void
    {
        if ($station === null || $this->isSkipDay($profile, $date)) {
            return;
        }

        $halfDay = in_array($date, $profile['half_days'], true);
        $startHour = 7;
        $startMinute = $this->between(0, 20);
        $operatingHours = $halfDay ? 12 : $this->between(16, 19);
        $start = CarbonImmutable::parse($date)->setTime($startHour, $startMinute);
        $stop = $start->addMinutes($operatingHours * 60 - $this->between(0, 25));

        // Satu atau dua jam jeda (pembersihan tippler / tunggu lori) di dalam jendela.
        $idleHours = [];
        if ($this->chance(45)) {
            $idleHours[] = ($startHour + $this->between(4, $operatingHours - 3)) % 24;
        }

        $recordId = (string) Str::orderedUuid();
        $meta = $this->headerMeta($date);
        $fleet = $profile['cage_fleet'];
        $perHourMin = max(2, (int) round(14 * $profile['scale']));
        $perHourMax = max(4, (int) round(19 * $profile['scale']));

        $details = [];
        $cumulative = 0;
        $minutes = $start->diffInMinutes($stop);
        $hourCount = (int) ceil($minutes / 60);

        for ($step = 0; $step < $hourCount; $step++) {
            $hour = ($startHour + $step) % 24;

            if (in_array($hour, $idleHours, true)) {
                continue;
            }

            $tipped = $this->between($perHourMin, $perHourMax);
            $cumulative += $tipped;
            $cageNumbers = $this->distinctNumbers(1, $fleet, $tipped);

            $details[] = [
                'id' => (string) Str::orderedUuid(),
                'cages_track_record_id' => $recordId,
                'tipped_hour' => $hour,
                'checked_cage_numbers' => implode(',', $cageNumbers),
                'total_cages' => $tipped,
                'cages_remain' => max(0, $fleet - $tipped - $this->between(0, (int) round($fleet / 3))),
                'created_at' => $meta['created_at'],
                'updated_at' => $this->now,
            ];
        }

        DB::table('cages_track_records')->insert(array_merge([
            'id' => $recordId,
            'station_id' => $station->id,
            'production_line_id' => $lineId,
            'cages_track_number' => self::PREFIX.'CT-'.$profile['code'].'-'.str_replace('-', '', $date),
            'date' => $date,
            'tippler_start_time' => $start->toDateTimeString(),
            'tippler_stop_time' => $stop->toDateTimeString(),
            'cages_out' => $cumulative + $this->between(-3, 4),
            'cages_tipped' => $cumulative,
            'note' => $idleHours !== [] ? 'Tippler berhenti 1 jam untuk pembersihan rel.' : null,
        ], $meta));

        $this->insertChunked('cages_tipped_times', $details);
    }

    // ------------------------------------------------------------------
    // Boiler Room — satu record per boiler per hari, 24 slot per jam
    // ------------------------------------------------------------------

    protected function seedBoilerRoom(?Station $station, string $lineId, array $profile, string $date): void
    {
        if ($station === null || $this->isSkipDay($profile, $date)) {
            return;
        }

        foreach ($profile['boilers'] as $unit) {
            $recordId = (string) Str::orderedUuid();
            $meta = $this->headerMeta($date);

            DB::table('boiler_room_records')->insert(array_merge([
                'id' => $recordId,
                'station_id' => $station->id,
                'production_line_id' => $lineId,
                'boiler_room_id' => self::PREFIX.'BLR-'.$profile['code'].'-'.$unit,
                'date' => $date,
                'note' => null,
            ], $meta));

            $details = [];

            foreach ($this->slotsFor($profile, $date) as $slotIndex => $slot) {
                $pressure = $this->float(18.5, 21.5, 1);
                if ($this->chance(2)) {
                    $pressure = $this->float(13.0, 16.0, 1); // tekanan drop sesaat
                }

                $hour = (int) substr($slot, 0, 2);

                $details[] = [
                    'id' => (string) Str::orderedUuid(),
                    'boiler_room_record_id' => $recordId,
                    'time_slot' => $slot,
                    'steam_pressure_bar' => $pressure,
                    'steam_temp_c' => round(198 + $pressure * 1.05 + $this->float(-2, 3, 1), 1),
                    'feed_water_temp_c' => $this->float(88, 102, 1),
                    'feed_water_tank_level_percent' => $this->float(60, 88, 0),
                    'boiler_water_level_percent' => $this->float(45, 62, 0),
                    // pH & TDS diuji lab tiap 2 jam — sisanya kosong (denominator per metrik).
                    'water_tds_ppm' => $slotIndex % 2 === 0 ? $this->float(1400, 2900, 0) : null,
                    'water_ph' => $slotIndex % 2 === 0 ? $this->float(10.5, 11.6, 1) : null,
                    'fuel_feed_rate' => $this->between(36, 48).' Hz',
                    'id_fan_load' => $this->between(70, 92).' %',
                    'sa_fan_load' => $this->between(55, 80).' %',
                    'exhaust_gas_temp_c' => $this->float(185, 245, 0),
                    'dust_collector_differential_pressure_mmh2o' => $this->float(60, 120, 0),
                    // Blowdown tiap 4 jam, sootblowing awal tiap shift; sebagian tidak tercatat.
                    'blowdown_executed' => $this->chance(6) ? null : ($hour % 4 === 3 ? 'y' : 'n'),
                    'sootblowing_executed' => $this->chance(6) ? null : (in_array($hour, [7, 15, 23], true) ? 'y' : 'n'),
                    'findings' => $this->chance(3) ? $this->pick(['Kebocoran kecil pada gland pompa feed water', 'Grate perlu dibersihkan', 'Fiber basah, nyala api kurang stabil']) : null,
                    'created_at' => $meta['created_at'],
                    'updated_at' => $this->now,
                ];
            }

            $this->insertChunked('boiler_room_details', $details);
        }
    }

    // ------------------------------------------------------------------
    // Clarification — satu record per hari per line, 24 slot per jam
    // ------------------------------------------------------------------

    protected function seedClarification(?Station $station, string $lineId, array $profile, string $date): void
    {
        if ($station === null || $this->isSkipDay($profile, $date)) {
            return;
        }

        $recordId = (string) Str::orderedUuid();
        $meta = $this->headerMeta($date);

        DB::table('clarification_records')->insert(array_merge([
            'id' => $recordId,
            'station_id' => $station->id,
            'production_line_id' => $lineId,
            'clarification_id' => self::PREFIX.'CLR-'.$profile['code'].'-01',
            'date' => $date,
            'note' => null,
        ], $meta));

        // Laju CPO murni: Line 1 ≈ 9–10.5 t/jam (± 45 t TBS/jam, OER ≈ 21%).
        $baseRate = 9.6 * $profile['scale'];
        $details = [];

        foreach ($this->slotsFor($profile, $date) as $slot) {
            $downtime = $this->chance(8) ? (float) $this->pick([10, 15, 20, 30, 45]) : 0.0;
            $rate = round(max(0, $baseRate * $this->float(0.9, 1.1, 3) * (1 - $downtime / 60)), 2);

            $details[] = [
                'id' => (string) Str::orderedUuid(),
                'clarification_record_id' => $recordId,
                'time_slot' => $slot,
                'clarification_tank_temp_c' => $this->float(90, 96, 1),
                'oil_tank_temperature_c' => $this->float(92, 98, 1),
                'sludge_tank_temp_c' => $this->float(88, 95, 1),
                'buffer_tank_level_percent' => $this->float(40, 80, 0),
                'pure_oil_production_rate_ton_hour' => $rate,
                'downtime_mins' => $downtime,
                'findings' => $downtime >= 30 ? 'Sludge separator dibersihkan (desludging manual)' : null,
                'created_at' => $meta['created_at'],
                'updated_at' => $this->now,
            ];
        }

        $this->insertChunked('clarification_details', $details);
    }

    // ------------------------------------------------------------------
    // Storage Tank — satu record per tangki per hari, stok berkesinambungan
    // ------------------------------------------------------------------

    protected function seedStorageTank(?Station $station, string $lineId, array $profile): void
    {
        if ($station === null) {
            return;
        }

        $tankCount = count($profile['tanks']);
        // Produksi CPO masuk tangki, dibagi rata ke semua tangki line ini.
        $inflowPerHour = 9.6 * $profile['scale'] / $tankCount;

        foreach ($profile['tanks'] as $unit => $tank) {
            $stock = $tank['opening_mt'];

            foreach ($this->dates() as $date) {
                if ($this->isSkipDay($profile, $date)) {
                    // Tidak tercatat, tetapi pabrik tetap berjalan: stok tetap bergerak.
                    $stock = min($tank['max_mt'], $stock + $inflowPerHour * 20);

                    continue;
                }

                $recordId = (string) Str::orderedUuid();
                $meta = $this->headerMeta($date);

                DB::table('storage_tank_records')->insert(array_merge([
                    'id' => $recordId,
                    'station_id' => $station->id,
                    'production_line_id' => $lineId,
                    'storage_tank_id' => self::PREFIX.'TK-'.$profile['code'].'-'.$unit,
                    'date' => $date,
                    'note' => null,
                ], $meta));

                // Pengiriman CPO: saat tangki mendekati penuh, dipompa keluar pada jam kerja.
                $dispatchSlot = $stock > $tank['max_mt'] * 0.7 ? sprintf('%02d:00', $this->between(9, 15)) : null;
                $details = [];

                // Laporan Storage Tank mengurutkan pembacaan per (tanggal, time_slot)
                // apa adanya — 00:00..06:00 terbaca SEBELUM 07:00 pada tanggal yang
                // sama. Stok disimulasikan dalam urutan yang sama supaya stok
                // awal/akhir dan pergerakannya koheren di laporan.
                $slots = $this->slotsFor($profile, $date);
                asort($slots);

                foreach ($slots as $slotIndex => $slot) {
                    $stock += $inflowPerHour * $this->float(0.85, 1.1, 3);

                    if ($slot === $dispatchSlot) {
                        $stock -= $tank['max_mt'] * $this->float(0.3, 0.45, 3);
                    }

                    $stock = max($tank['max_mt'] * 0.08, min($tank['max_mt'], $stock));

                    $top = $this->float(50, 56, 1);
                    $middle = round($top - $this->float(1, 3, 1), 1);
                    $bottom = round($middle - $this->float(1, 4, 1), 1);
                    $average = round(($top + $middle + $bottom) / 3, 1);
                    $density = 0.8930 - 0.00067 * ($average - 15);
                    $weight = round($stock, 2);
                    $volume = round($weight / $density, 2);
                    $netOilMm = round($volume / $tank['area_m2'] * 1000, 0);
                    $waterMm = $this->float(5, 30, 0);
                    $qualitySlot = $slotIndex % 4 === 0;

                    $details[] = [
                        'id' => (string) Str::orderedUuid(),
                        'storage_tank_record_id' => $recordId,
                        'time_slot' => $slot,
                        'cpo_sounding_depth_mm' => $netOilMm + $waterMm,
                        'water_dip_bottom_depth_mm' => $waterMm,
                        'net_oil_depth_mm' => $netOilMm,
                        'oil_temperature_top_c' => $top,
                        'oil_temperature_middle_c' => $middle,
                        'oil_temperature_bottom_c' => $bottom,
                        'average_temperature_c' => $average,
                        'calculated_volume_m3' => $volume,
                        'calculated_weight_mt' => $weight,
                        // Sampel mutu diambil tiap 4 jam — slot lain kosong.
                        'ffa_percent' => $qualitySlot ? $this->float(3.0, 4.6, 2) : null,
                        'moisture_content_percent' => $qualitySlot ? $this->float(0.10, 0.22, 2) : null,
                        'impurities_dirt_percent' => $qualitySlot ? $this->float(0.010, 0.028, 3) : null,
                        'dobi_index' => $qualitySlot ? $this->float(2.3, 3.1, 2) : null,
                        'steam_heating_valve_status' => $average < 49 ? 'open_1_2' : ($average < 52 ? 'open_1_4' : 'closed'),
                        'tank_structural_condition' => $this->chance(97) ? 'Baik' : 'Rembesan kecil pada manhole',
                        'inspector_name' => $this->users['operator_name'],
                        'findings' => $slot === $dispatchSlot ? 'Pengiriman CPO ke truk tangki' : null,
                        'created_at' => $meta['created_at'],
                        'updated_at' => $this->now,
                    ];
                }

                // Jam yang tidak tercatat (setengah hari) tetap menambah stok.
                $stock = min($tank['max_mt'], $stock + $inflowPerHour * (24 - $this->slotLimit($profile, $date)));

                $this->insertChunked('storage_tank_details', $details);
            }
        }
    }

    // ------------------------------------------------------------------
    // Utilitas
    // ------------------------------------------------------------------

    /**
     * Slot jam yang dicatat hari itu: 24 slot kanonik (atau 12 pada hari
     * setengah shift), dengan ±3% slot acak terlewat.
     *
     * @return array<int, string>
     */
    protected function slotsFor(array $profile, string $date): array
    {
        $slots = array_slice($this->timeSlots(), 0, $this->slotLimit($profile, $date));

        return array_filter($slots, fn () => ! $this->chance(3));
    }

    protected function insertChunked(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    protected function clock(int $minutesFromMidnight): string
    {
        $minutes = (($minutesFromMidnight % 1440) + 1440) % 1440;

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /** @return list<int> */
    protected function distinctNumbers(int $min, int $max, int $count): array
    {
        $pool = range($min, $max);
        $picked = [];

        for ($i = 0; $i < $count && $pool !== []; $i++) {
            $index = mt_rand(0, count($pool) - 1);
            $picked[] = $pool[$index];
            array_splice($pool, $index, 1);
        }

        sort($picked);

        return $picked;
    }

    protected function between(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }

    protected function float(float $min, float $max, int $decimals): float
    {
        return round($min + mt_rand() / mt_getrandmax() * ($max - $min), $decimals);
    }

    protected function chance(int $percent): bool
    {
        return mt_rand(1, 100) <= $percent;
    }

    protected function pick(array $items): mixed
    {
        return $items[mt_rand(0, count($items) - 1)];
    }
}
