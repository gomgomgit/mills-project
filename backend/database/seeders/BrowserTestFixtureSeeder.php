<?php

namespace Database\Seeders;

use App\Enums\RecordStatus;
use App\Enums\StationType;
use App\Enums\UserRole;
use App\Models\BoilerRoomDetail;
use App\Models\BoilerRoomRecord;
use App\Models\BusinessUnit;
use App\Models\CagesTippedTime;
use App\Models\CagesTrackRecord;
use App\Models\ClarificationDetail;
use App\Models\ClarificationRecord;
use App\Models\Company;
use App\Models\Corporate;
use App\Models\CpoDispatchDetail;
use App\Models\CpoDispatchRecord;
use App\Models\DepricarpingDetail;
use App\Models\DepricarpingRecord;
use App\Models\EffluentPlantDetail;
use App\Models\EffluentPlantRecord;
use App\Models\EngineRoomDetail;
use App\Models\EngineRoomRecord;
use App\Models\GradingDetail;
use App\Models\GradingParameter;
use App\Models\GradingRecord;
use App\Models\KernelDispatchDetail;
use App\Models\KernelDispatchRecord;
use App\Models\KernelPlantDetail;
use App\Models\KernelPlantRecord;
use App\Models\Machinery;
use App\Models\MachineryGroup;
use App\Models\PressingDetail;
use App\Models\PressingRecord;
use App\Models\ProcessQualityControlDetail;
use App\Models\ProcessQualityControlRecord;
use App\Models\ProcessWaterDetail;
use App\Models\ProcessWaterRecord;
use App\Models\ProductionLine;
use App\Models\SolidWasteDisposalRecord;
use App\Models\Station;
use App\Models\SterilizerRecord;
use App\Models\StorageTankDetail;
use App\Models\StorageTankRecord;
use App\Models\ThreshingDetail;
use App\Models\ThreshingRecord;
use App\Models\User;
use App\Models\WeighbridgeRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Fixtures for the web browser suite in e2e-web/ (2026-09-16).
 *
 * Those specs came from backend/tests/Browser/*.php, which had never run
 * once — they were Playwright JavaScript behind a `<?php` tag, unparseable
 * as PHP — so the accounts and records they reference have never existed
 * anywhere. This seeder creates them.
 *
 * Deliberately NOT part of DatabaseSeeder: it exists to serve one test
 * suite and creates accounts with a known password, which has no business
 * running as part of a normal `db:seed`. Run it explicitly:
 *
 *     php artisan db:seed --class=BrowserTestFixtureSeeder
 *
 * Idempotent — every row is updateOrCreate'd on a natural key, so re-running
 * after a partial failure (or between test runs) is safe and does not
 * accumulate duplicates.
 *
 * Shape it builds:
 *   - one business unit, "BU Browser Test"
 *   - "PL Mill A" — a production line carrying an ACTIVE station of every
 *     one of the 18 types, because most specs pick it from a dropdown and
 *     expect their station to be there
 *   - "PL Tanpa <Station>" per station family — a production line that
 *     deliberately LACKS that station, which is what the "Production Line
 *     Tanpa Station Aktif" scenarios select to assert the empty/blocked path
 *   - <prefix>-admin01 and <prefix>-supervisor01 per family (some specs also
 *     use a mill-management account; see MILL_MANAGEMENT_PREFIXES)
 */
class BrowserTestFixtureSeeder extends Seeder
{
    public const PASSWORD = 'Passw0rd!';

    /** The Ganti Password specs change the password, so they get their own. */
    public const CHANGE_PASSWORD_PASSWORD = 'OldPass123!';

    public const BUSINESS_UNIT_NAME = 'BU Browser Test';

    public const MAIN_PRODUCTION_LINE = 'PL Mill A';

    /**
     * Station families: username prefix => [station type, display name].
     * The display name is what "PL Tanpa <name>" is built from, and must
     * match what the specs type into their production-line dropdown.
     *
     * @var array<string, array{0: StationType, 1: string}>
     */
    protected const STATION_FAMILIES = [
        'eptest' => [StationType::EffluentPlant, 'Effluent Plant'],
        'pwtest' => [StationType::ProcessWater, 'Process Water'],
        'brtest' => [StationType::BoilerRoom, 'Boiler Room'],
        'cltest' => [StationType::Clarification, 'Clarification'],
        'ertest' => [StationType::EngineRoom, 'Engine Room'],
        'sttest' => [StationType::StorageTank, 'Storage Tank'],
        'pqctest' => [StationType::ProcessQualityControl, 'Process Quality Control'],
        'threshtest' => [StationType::Threshing, 'Threshing'],
        'presstest' => [StationType::Pressing, 'Pressing'],
        'depricarpingtest' => [StationType::Depricarping, 'Depricarping'],
        'kernelplanttest' => [StationType::KernelPlant, 'Kernel Plant'],
    ];

    /**
     * Master-data / settings screens. These specs only ever log in and drive
     * CRUD forms, so they need an account and nothing else.
     *
     * @var list<string>
     */
    protected const MASTER_DATA_PREFIXES = [
        'corptest', 'comptest', 'butest', 'pltest', 'mgtest', 'mtest', 'urtest', 'stest',
    ];

    /** Prefixes whose specs also drive a Mill Management account. */
    protected const MILL_MANAGEMENT_PREFIXES = ['stest'];

    /**
     * Prefixes whose specs assert that a NON-Admin is refused the screen.
     * Seeded as Supervisor — a real role that simply is not Admin, so the
     * block being tested is the role rule rather than a broken account.
     *
     * @var list<string>
     */
    protected const NON_ADMIN_PREFIXES = [
        'corptest', 'comptest', 'butest', 'pltest', 'mgtest', 'mtest', 'urtest', 'stest',
    ];

    /**
     * Per-screen accounts the Data Browser and Form specs log in as. They
     * are declared as a `USERNAME` constant inside each spec rather than
     * passed inline, which is exactly why the first two passes over these
     * files missed them.
     *
     * Supervisor, because these specs both browse/export and submit forms,
     * and Supervisor is the one role permitted to do both on the web.
     *
     * @var list<string>
     */
    protected const SCREEN_USERS = [
        'cdtest-browse01',
        'cdtest-form01',
        'cttest-browse01',
        'depricarpingtest-browse01',
        'grtest-browse01',
        'kdtest-browse01',
        'kdtest-form01',
        'kernelplanttest-browse01',
        'presstest-browse01',
        'stertest-browse01',
        'stertest-form01',
        'swdtest-browse01',
        'swdtest-form01',
        'threshtest-browse01',
        'wbtest-browse01',
    ];

    /**
     * Ganti Password (web) accounts. One per scenario, because each spec
     * CHANGES the password it logs in with — sharing one account would make
     * the specs order-dependent. Seeded with CHANGE_PASSWORD_PASSWORD and
     * reset on every seeder run, so a previous run that successfully changed
     * a password does not break the next one.
     *
     * @var list<string>
     */
    protected const CHANGE_PASSWORD_USERS = [
        'passtest-success01', 'passtest-wrongold01', 'passtest-badformat01', 'passtest-mismatch01',
    ];

    /**
     * Records the specs open by their business id — the "klik Edit dari
     * Detail" and detail-page scenarios navigate to these by name, so they
     * must pre-exist rather than be created by the test.
     *
     * station type => [record model, detail model, id column, business id,
     * one filled reading column]
     *
     * Dated inside 2026-08-01..2026-08-15 because the Data Browser specs
     * filter on exactly that window before asserting the export.
     *
     * ELEVEN STATIONS, NOT ONE, SINCE 2026-10-02. Until then this list held
     * `effluent-plant` alone while FOURTEEN `form-*` specs navigate to a
     * `*-BROWSER-EDIT` row — so thirteen "klik Edit dari Detail" scenarios
     * had been red since the day they were written, waiting on a fixture
     * nothing created. Measured, not assumed: a count of every
     * `*-BROWSER-EDIT` row in the dev database returned 1.
     *
     * THE FIFTH ELEMENT IS NOT DECORATION. Every one of these services counts
     * a detail row as real only when its Time-Slot is set AND at least one
     * READING column is filled (`isRowFilled()`); a row carrying nothing but
     * `time_slot` is filtered away, and re-saving the record then fails with
     * "Minimal satu baris ... harus diisi". That is why all twelve "klik Edit
     * dari Detail" scenarios still timed out after the records themselves
     * existed: the form loaded, the Save was refused, and the page never left
     * /edit. One real reading per station fixes it — the specific column is
     * read from each service's own READING_FIELDS, not guessed.
     *
     * These eleven share one shape: a `date` column on the record and a
     * detail row keyed by `time_slot`. The other three the specs need do
     * not, and each gets its own method below — Cages Track (extra required
     * header fields, detail table `cages_tipped_times`), Grading (a
     * Weighbridge card FK plus four required figures) and Weighbridge
     * (`record_datetime` instead of `date`, and NO detail table at all).
     *
     * The four `form-*` specs with no edit scenario — CPO Dispatch, Kernel
     * Dispatch, Solid Waste Disposal, Sterilizer — are deliberately absent.
     *
     * @var array<string, array{0: class-string, 1: class-string, 2: string, 3: string}>
     */
    protected const EDIT_RECORDS = [
        'boiler-room' => [BoilerRoomRecord::class, BoilerRoomDetail::class, 'boiler_room_id', 'BR-BROWSER-EDIT', ['steam_pressure_bar' => 12.5]],
        'clarification' => [ClarificationRecord::class, ClarificationDetail::class, 'clarification_id', 'CLR-BROWSER-EDIT', ['clarification_tank_temp_c' => 93]],
        'depricarping' => [DepricarpingRecord::class, DepricarpingDetail::class, 'presser_id', 'DP-BROWSER-EDIT', ['polishing_drum_speed_rpm' => 1200]],
        'effluent-plant' => [EffluentPlantRecord::class, EffluentPlantDetail::class, 'effluent_plant_id', 'EP-BROWSER-EDIT', ['anaerobic_pond_1_ph' => 7.0]],
        'engine-room' => [EngineRoomRecord::class, EngineRoomDetail::class, 'engine_room_id', 'ER-BROWSER-EDIT', ['steam_turbine_inlet_pressure_bar' => 20.0]],
        'kernel-plant' => [KernelPlantRecord::class, KernelPlantDetail::class, 'kernel_plant_id', 'KP-BROWSER-EDIT', ['claybath_hydro_sg' => 1.12]],
        'pressing' => [PressingRecord::class, PressingDetail::class, 'presser_id', 'PR-BROWSER-EDIT', ['digester_temp_c' => 95]],
        'process-quality-control' => [ProcessQualityControlRecord::class, ProcessQualityControlDetail::class, 'process_qc_id', 'PQC-BROWSER-EDIT', ['fruit_press_oil_loss_in_sludge_percent' => 1.2]],
        'process-water' => [ProcessWaterRecord::class, ProcessWaterDetail::class, 'process_water_id', 'PW-BROWSER-EDIT', ['raw_water_flow_m3h' => 40]],
        'storage-tank' => [StorageTankRecord::class, StorageTankDetail::class, 'storage_tank_id', 'ST-BROWSER-EDIT', ['cpo_sounding_depth_mm' => 2500]],
        'threshing' => [ThreshingRecord::class, ThreshingDetail::class, 'thresher_id', 'TH-BROWSER-EDIT', ['ffb_throughput_mt_hour' => 30]],
    ];

    protected const RECORD_DATE = '2026-08-05';

    /**
     * A second Production Line whose Cages Track station carries EXACTLY
     * eight machinery rows. form-cages-track.spec.ts's "Jumlah Kolom Grid"
     * scenario selects it by this name and asserts the checklist renders 8
     * columns; N comes from
     * CagesTrackRecordService::machineryCountForStation(), a plain
     * COUNT(machinery WHERE station_id = ?) since 2026-08-20 — the
     * mill-setting `jumlah_cages` that spec's docblock still mentions was
     * removed then.
     */
    public const SMALL_PRODUCTION_LINE = 'PL Mill Kecil';

    /** Machinery rows on the MAIN line's Cages Track station. */
    protected const MAIN_LINE_CAGES = 10;

    /** Machinery rows on SMALL_PRODUCTION_LINE's Cages Track station. */
    protected const SMALL_LINE_CAGES = 8;

    public function run(): void
    {
        $businessUnit = $this->businessUnit(self::BUSINESS_UNIT_NAME);

        $mainLine = $this->productionLine($businessUnit, self::MAIN_PRODUCTION_LINE);

        // Every station type active on the main line — specs from different
        // families all select this same line.
        foreach (StationType::cases() as $type) {
            if ($type === StationType::Other) {
                continue;
            }

            $this->station($mainLine, $type, active: true);
        }

        foreach (self::STATION_FAMILIES as $prefix => [$type, $displayName]) {
            $this->accountsFor($prefix, $businessUnit);

            // A line that deliberately lacks this station. It still carries
            // the OTHER stations, so selecting it is a realistic choice
            // rather than an empty line that could pass for a seeding bug.
            $withoutLine = $this->productionLine($businessUnit, "PL Tanpa {$displayName}");

            foreach (StationType::cases() as $otherType) {
                if ($otherType === StationType::Other || $otherType === $type) {
                    continue;
                }

                $this->station($withoutLine, $otherType, active: true);
            }
        }

        foreach (self::MASTER_DATA_PREFIXES as $prefix) {
            $this->accountsFor($prefix, $businessUnit);
        }

        foreach (self::NON_ADMIN_PREFIXES as $prefix) {
            $this->user("{$prefix}-nonadmin01", UserRole::Supervisor, $businessUnit);
        }

        foreach (self::SCREEN_USERS as $username) {
            $this->user($username, UserRole::Supervisor, $businessUnit);
        }

        // Akun yang DIOPERASIKAN kelola-user-role.spec.ts berdasarkan nama:
        // diedit (existing01), dinonaktifkan (other01), dan dipakai sebagai
        // username yang sudah terpakai (duplicate01). user() memakai
        // updateOrCreate dengan role dan is_active=true, jadi seed berikutnya
        // memulihkan apa yang diubah skenario edit dan nonaktifkan.
        foreach (['urtest-existing01', 'urtest-other01', 'urtest-duplicate01'] as $username) {
            $this->user($username, UserRole::Supervisor, $businessUnit);
        }

        foreach (self::CHANGE_PASSWORD_USERS as $username) {
            $this->user($username, UserRole::Supervisor, $businessUnit, self::CHANGE_PASSWORD_PASSWORD);
        }

        $this->cagesFixtures($businessUnit, $mainLine);
        $this->editRecords($mainLine);
        $this->productionLineFilterFixtures($businessUnit, $mainLine);
        $this->kelolaProductionLineFixtures();
        $this->kelolaHierarchyFixtures();
        $this->machineryFixtures($businessUnit);

        $this->command?->info('Browser fixture: users, "'.self::MAIN_PRODUCTION_LINE.'" and per-station "PL Tanpa ..." lines are ready.');
    }

    /**
     * Fixtures for e2e-web/tests/kelola-machinery*.spec.ts (2026-10-01).
     *
     * Those two specs referenced Stations and Machinery Groups by name that
     * existed in no seeder at all — every one of their 12 data-driven tests
     * had been failing since the day they were written, which is the class
     * e2e-web/README.md calls "Expect failures".
     *
     * Two things the specs MUTATE, which is why this runs on every seed:
     *   - MG-BROWSER-SEBELUM-EDIT is renamed by the edit scenario
     *   - MG-BROWSER-HAPUS-BERSIH and EQ-BROWSER-HAPUS are deleted
     * firstOrCreate restores all three. The leftovers those scenarios leave
     * behind (MG-BROWSER-SESUDAH-EDIT-<ts> and the EQ/MG-BROWSER-<ts> rows
     * the create scenarios add) are swept below, otherwise the database
     * accumulates a fresh set on every run.
     *
     * Their own Production Line is deliberate: these Stations must not
     * collide with the main line's one-station-per-type key, and the create
     * scenario asserts the Production Line name shown in the row.
     */
    protected function machineryFixtures(BusinessUnit $businessUnit): void
    {
        // Sweep what previous runs of the specs left behind, before
        // recreating the canonical rows.
        Machinery::where('equipment_code', 'LIKE', 'EQ-BROWSER-%')
            ->whereNotIn('equipment_code', ['EQ-BROWSER-SEBELUM-EDIT', 'EQ-BROWSER-HAPUS', 'EQ-BROWSER-DIPAKAI'])
            ->delete();
        MachineryGroup::where('group_code', 'LIKE', 'MG-BROWSER-SESUDAH-EDIT-%')->delete();
        MachineryGroup::where('group_code', 'LIKE', 'MG-BROWSER-1%')->delete();

        $line = $this->productionLine($businessUnit, 'Mill Machinery Group PL Baru');
        $lineTujuan = $this->productionLine($businessUnit, 'Mill Machinery Group PL Tujuan');

        $station = $this->namedStation($line, 'Mill Machinery Group Station Baru');
        $this->namedStation($lineTujuan, 'Mill Machinery Group Station Tujuan Edit');

        $base = $this->machineryGroup($station, 'MG-BROWSER-BASE');
        $this->machineryGroup($station, 'MG-BROWSER-SEBELUM-EDIT');
        $this->machineryGroup($station, 'MG-BROWSER-HAPUS-BERSIH');
        $this->machineryGroup($station, 'MG-DUP-01');
        $withMachinery = $this->machineryGroup($station, 'MG-BROWSER-ADA-MACHINERY');

        $this->machinery($base, 'EQ-BROWSER-SEBELUM-EDIT', 'Mesin Sebelum Edit');
        $this->machinery($base, 'EQ-BROWSER-HAPUS', 'Mesin Untuk Dihapus');

        // Makes MG-BROWSER-ADA-MACHINERY refuse deletion — that refusal is
        // the whole point of the scenario that targets it.
        $this->machinery($withMachinery, 'EQ-BROWSER-DIPAKAI', 'Mesin Penahan Hapus');
    }

    /**
     * Cages Track needs MACHINERY, not a mill setting — and it needs two
     * different counts on two different lines.
     *
     * N (the Cages Tipped Time grid's checklist column count) is
     * COUNT(machinery WHERE station_id = ?) since 2026-08-20, when
     * mill-setting `jumlah_cages` was removed; CagesTrackRecordService
     * ::machineryCountForStation() is the whole of it. With zero machinery N
     * is 0, `FormCagesTrack::canAddRow()` returns false, and "+ Tambah Baris"
     * renders DISABLED — which is why seven form-cages-track scenarios timed
     * out clicking it. Measured before writing this: the main line's Cages
     * Track station carried 0 machinery rows.
     *
     * SMALL_PRODUCTION_LINE exists only to carry a DIFFERENT count (8), which
     * is what the "Jumlah Kolom Grid" scenario asserts. It cannot be a second
     * station on the main line: station() keys one row per type per line.
     *
     * The equipment codes deliberately do NOT start with `EQ-BROWSER-` —
     * machineryFixtures() sweeps that prefix on every run, keeping only three
     * named survivors, and it runs after this. A swept cage would put the
     * count back to zero on the next seed.
     */
    protected function cagesFixtures(BusinessUnit $businessUnit, ProductionLine $mainLine): void
    {
        $mainStation = Station::where('production_line_id', $mainLine->id)
            ->where('type', StationType::CagesTrack)
            ->firstOrFail();

        $this->cages($mainStation, 'CAGE-BROWSER-MAIN', self::MAIN_LINE_CAGES);

        $smallLine = $this->productionLine($businessUnit, self::SMALL_PRODUCTION_LINE);
        $smallStation = $this->station($smallLine, StationType::CagesTrack, active: true);

        $this->cages($smallStation, 'CAGE-BROWSER-KECIL', self::SMALL_LINE_CAGES);

        // "PL Tanpa Cages Track" — the ONE "PL Tanpa ..." line the specs ask
        // for that run() does not build, because Cages Track has no entry in
        // STATION_FAMILIES (it has no `cagestest-*` account family). Measured:
        // the specs name twelve such lines and eleven existed.
        //
        // Carries every OTHER station type, same as the eleven built in run():
        // a line that is simply empty would read like a seeding bug rather
        // than the deliberate "no active station of this type" case the
        // scenario tests.
        $withoutLine = $this->productionLine($businessUnit, 'PL Tanpa Cages Track');

        foreach (StationType::cases() as $otherType) {
            if ($otherType === StationType::Other || $otherType === StationType::CagesTrack) {
                continue;
            }

            $this->station($withoutLine, $otherType, active: true);
        }
    }

    /**
     * Exactly $count machinery rows on $station, named deterministically so a
     * re-seed neither duplicates them nor changes the count the grid renders.
     * No MachineryGroup: `machinery.machinery_group_id` is nullable and the
     * count does not look at it.
     */
    protected function cages(Station $station, string $codePrefix, int $count): void
    {
        for ($n = 1; $n <= $count; $n++) {
            $code = sprintf('%s-%02d', $codePrefix, $n);

            Machinery::firstOrCreate(
                ['equipment_code' => $code],
                [
                    'name' => 'Cage '.$n,
                    'station_id' => $station->id,
                    'production_line_id' => $station->production_line_id,
                ],
            );
        }
    }

    /**
     * A Station identified by NAME rather than by (line, type). The
     * type-keyed station() helper allows one row per type per line, which
     * cannot express two differently named stations these specs need.
     */
    protected function namedStation(ProductionLine $line, string $name): Station
    {
        return Station::firstOrCreate(
            ['production_line_id' => $line->id, 'name' => $name],
            [
                'business_unit_id' => $line->business_unit_id,
                'type' => StationType::Weighbridge,
                'is_active' => true,
            ],
        );
    }

    protected function machineryGroup(Station $station, string $groupCode): MachineryGroup
    {
        return MachineryGroup::firstOrCreate(
            ['group_code' => $groupCode],
            [
                'station_id' => $station->id,
                'production_line_id' => $station->production_line_id,
            ],
        );
    }

    protected function machinery(MachineryGroup $group, string $equipmentCode, string $name): Machinery
    {
        return Machinery::firstOrCreate(
            ['equipment_code' => $equipmentCode],
            [
                'name' => $name,
                'machinery_group_id' => $group->id,
                'station_id' => $group->station_id,
                'production_line_id' => $group->production_line_id,
            ],
        );
    }

    /**
     * One pre-existing record per station, on the main line, with a single
     * detail row so the detail grid is not empty.
     *
     * WHY production_line_id IS SET HERE, AND WHY ITS ABSENCE WAS INVISIBLE.
     * Migration 2026_09_28_* (Production Line isolation) made
     * `production_line_id` NOT NULL on every one of the 18 record tables.
     * This method never set it, so it could no longer INSERT — and nobody
     * noticed, because the single row it had to create (EP-BROWSER-EDIT)
     * already existed, which turns updateOrCreate() into an UPDATE. On a
     * fresh database the seeder would have failed outright. It is read from
     * the record's own station rather than from $line so the two can never
     * disagree.
     */
    protected function editRecords(ProductionLine $line): void
    {
        $author = User::where('username', 'eptest-supervisor01')->firstOrFail();

        $this->sweepEditLeftovers();

        foreach (self::EDIT_RECORDS as $stationType => [$recordClass, $detailClass, $idColumn, $businessId, $reading]) {
            $station = Station::where('production_line_id', $line->id)
                ->where('type', $stationType)
                ->firstOrFail();

            $record = $recordClass::updateOrCreate(
                [$idColumn => $businessId],
                [
                    'station_id' => $station->id,
                    'production_line_id' => $station->production_line_id,
                    'date' => self::RECORD_DATE,
                    'status' => RecordStatus::DraftOngoing,
                    'created_by' => $author->id,
                ],
            );

            $detailClass::updateOrCreate(
                [
                    $record->getForeignKey() => $record->id,
                    'time_slot' => '07:00',
                ],
                $reading,
            );

            $this->promoteToSaved($record);
        }

        // `*-BROWSER-DETAIL` — record yang dibuka BERDASARKAN NAMA oleh
        // detail-{depricarping,kernel-plant,pressing,threshing}.spec.ts dari
        // DAFTAR Data Browser tanpa filter. Tidak ada yang pernah menanamnya,
        // jadi keempat spec itu menunggu baris yang tidak ada sampai timeout.
        //
        // Bertanggal HARI INI, bukan RECORD_DATE: daftarnya berhalaman 20 dan
        // diurutkan tanggal menurun, dan spec form-* menambah record setiap run
        // — record bertanggal 2026-08-05 cepat atau lambat terdorong keluar dari
        // halaman pertama yang dilihat spec itu.
        foreach (['depricarping' => 'DP', 'kernel-plant' => 'KP', 'pressing' => 'PR', 'threshing' => 'TH'] as $stationType => $prefix) {
            [$recordClass, $detailClass, $idColumn, , $reading] = self::EDIT_RECORDS[$stationType];

            $station = Station::where('production_line_id', $line->id)
                ->where('type', $stationType)
                ->firstOrFail();

            $record = $recordClass::updateOrCreate(
                [$idColumn => "{$prefix}-BROWSER-DETAIL"],
                [
                    'station_id' => $station->id,
                    'production_line_id' => $station->production_line_id,
                    'date' => now()->toDateString(),
                    'status' => RecordStatus::DraftOngoing,
                    'created_by' => $author->id,
                ],
            );

            // SEMUA 24 slot terisi: grid detail hanya merender slot yang punya
            // baris (detail-*.blade.php melakukan @foreach atas details), dan
            // spec-spec itu mengasersi grid 24 baris — yaitu log sheet sehari
            // penuh, 07:00 sampai 06:00.
            for ($hour = 0; $hour < 24; $hour++) {
                $detailClass::updateOrCreate(
                    [$record->getForeignKey() => $record->id, 'time_slot' => sprintf('%02d:00', (7 + $hour) % 24)],
                    $reading,
                );
            }

            $this->promoteToSaved($record);
        }

        // The Weighbridge record comes FIRST: the Grading record below needs
        // its id for the NOT NULL `weighbridge_record_id`, and Form Grading's
        // WB Card No dropdown is fed by exactly this row
        // (FormGrading::loadWeighbridgeOptions() lists every Weighbridge
        // record whose station belongs to the chosen mill). One row serves
        // both, which is why they are not seeded independently.
        $weighbridge = $this->weighbridgeEditRecord($line, $author);

        // WB-BROWSER-REPORT — management-report.spec.ts membuka Laporan
        // Manajemen TANPA filter (default: bulan berjalan) dan menuntut baris
        // Total, yang hanya dirender bila ada record Weighbridge/Grading/Cages
        // pada rentang itu. Bertanggal hari ini pukul 00:01 supaya record yang
        // ditulis spec form-weighbridge hari ini tetap lebih baru (Form Grading
        // memilih kartu WB TERBARU sebagai opsi pertama).
        WeighbridgeRecord::updateOrCreate(
            ['wb_card_number' => 'WB-BROWSER-REPORT'],
            [
                'station_id' => $weighbridge->station_id,
                'production_line_id' => $weighbridge->production_line_id,
                'weighbridge_type' => 'receive',
                'record_datetime' => now()->toDateString().' 00:01:00',
                'vehicle_number' => 'B 1234 RPT',
                'driver_name' => 'Driver Laporan',
                'estate_supplier' => 'Estate Laporan',
                'division' => 'Divisi 1',
                'gross_weight' => 15000,
                'tare_weight' => 5000,
                'net_weight' => 10000,
                'status' => RecordStatus::Saved,
                'created_by' => $author->id,
            ],
        );
        $this->gradingEditRecord($line, $author, $weighbridge);
        $this->cagesTrackEditRecord($line, $author);
    }

    /**
     * Line KEDUA untuk skenario filter Production Line di ke-18 spec
     * e2e-web/tests/data-browser-*.spec.ts.
     *
     * Filter itu menyaring berdasarkan `production_line_id` MILIK RECORD.
     * Mengujinya butuh record di DUA line pada mill yang sama — dengan satu
     * line saja, "pilih line A, hanya baris line A yang tampil" lulus juga
     * ketika filternya tidak berbuat apa-apa.
     */
    public const FILTER_SECOND_LINE = 'PL Mill B';

    /**
     * Tanggal khusus record filter. Spec-nya menyaring tepat ke tanggal ini,
     * sehingga yang tampil hanya dua record di bawah — tidak bercampur
     * dengan record yang ditulis spec form-* (hari ini / 2020-01-01 /
     * 2026-08-31) maupun *-BROWSER-EDIT (2026-08-05). Lebih tua dari semua
     * fixture lain, jadi tidak pernah menjadi "baris pertama" yang diklik
     * skenario lain (daftar diurutkan tanggal menurun), dan jauh di bawah
     * ambang 2600 e2e:prune-records sehingga tidak ikut tersapu.
     */
    public const FILTER_RECORD_DATE = '2025-03-10';

    /**
     * station type => [record model, kolom id bisnis, awalan id bisnis].
     * Id-nya `<awalan>-BROWSER-PL-A` / `<awalan>-BROWSER-PL-B`.
     *
     * @var array<string, array{0: class-string<Model>, 1: string, 2: string}>
     */
    protected const FILTER_RECORDS = [
        'boiler-room' => [BoilerRoomRecord::class, 'boiler_room_id', 'BR'],
        'cages-track' => [CagesTrackRecord::class, 'cages_track_number', 'CT'],
        'clarification' => [ClarificationRecord::class, 'clarification_id', 'CLR'],
        'cpo-dispatch' => [CpoDispatchRecord::class, 'cpo_dispatch_id', 'CD'],
        'depricarping' => [DepricarpingRecord::class, 'presser_id', 'DP'],
        'effluent-plant' => [EffluentPlantRecord::class, 'effluent_plant_id', 'EP'],
        'engine-room' => [EngineRoomRecord::class, 'engine_room_id', 'ER'],
        'grading' => [GradingRecord::class, 'grading_number', 'GR'],
        'kernel-dispatch' => [KernelDispatchRecord::class, 'kernel_dispatch_id', 'KD'],
        'kernel-plant' => [KernelPlantRecord::class, 'kernel_plant_id', 'KP'],
        'pressing' => [PressingRecord::class, 'presser_id', 'PR'],
        'process-quality-control' => [ProcessQualityControlRecord::class, 'process_qc_id', 'PQC'],
        'process-water' => [ProcessWaterRecord::class, 'process_water_id', 'PW'],
        'solid-waste-disposal' => [SolidWasteDisposalRecord::class, 'solid_waste_disposal_id', 'SWD'],
        'sterilizer' => [SterilizerRecord::class, 'sterilizer_id', 'STER'],
        'storage-tank' => [StorageTankRecord::class, 'storage_tank_id', 'ST'],
        'threshing' => [ThreshingRecord::class, 'thresher_id', 'TH'],
        'weighbridge' => [WeighbridgeRecord::class, 'wb_card_number', 'WB'],
    ];

    /**
     * Satu record per jenis stasiun di PL Mill A dan satu di PL Mill B.
     *
     * Dibiarkan DRAFT, bukan saved: enam model menolak `saved` tanpa baris
     * detail (lihat promoteToSaved()), dan Data Browser tidak menyaring status
     * sama sekali — record draft tampil sama seperti record saved. Setiap record
     * punya paling banyak SATU baris detail (hanya CPO/Kernel Dispatch, lihat di
     * bawah), jadi setiap record menghasilkan tepat SATU baris di ekspor CSV.
     *
     * Ditulis langsung lewat model, jadi tidak melewati kunci periode
     * (usecase-141, ditegakkan di *RecordService) — sama seperti editRecords().
     */
    protected function productionLineFilterFixtures(BusinessUnit $businessUnit, ProductionLine $mainLine): void
    {
        $author = User::where('username', 'eptest-supervisor01')->firstOrFail();

        $secondLine = $this->productionLine($businessUnit, self::FILTER_SECOND_LINE);

        foreach (StationType::cases() as $type) {
            if ($type !== StationType::Other) {
                $this->station($secondLine, $type, active: true);
            }
        }

        foreach (['A' => $mainLine, 'B' => $secondLine] as $suffix => $line) {
            $weighbridge = null;

            // Weighbridge LEBIH DULU: Grading butuh weighbridge_record_id-nya.
            foreach (['weighbridge', ...array_keys(self::FILTER_RECORDS)] as $stationType) {
                if ($stationType === 'weighbridge' && $weighbridge !== null) {
                    continue;
                }

                [$recordClass, $idColumn, $prefix] = self::FILTER_RECORDS[$stationType];

                $station = Station::where('production_line_id', $line->id)
                    ->where('type', $stationType)
                    ->firstOrFail();

                $attributes = [
                    'station_id' => $station->id,
                    'production_line_id' => $station->production_line_id,
                    'status' => RecordStatus::DraftOngoing,
                    'created_by' => $author->id,
                ] + match ($stationType) {
                    'weighbridge' => [
                        'weighbridge_type' => 'receive',
                        'record_datetime' => self::FILTER_RECORD_DATE.' 08:00:00',
                        'vehicle_number' => "B 9{$suffix} PL",
                        'driver_name' => 'Driver Filter Line',
                        'estate_supplier' => 'Estate Filter Line',
                        'division' => 'Divisi 1',
                        'gross_weight' => 15000,
                        'tare_weight' => 5000,
                        'net_weight' => 10000,
                    ],
                    'grading' => [
                        'date' => self::FILTER_RECORD_DATE,
                        'weighbridge_record_id' => $weighbridge->id,
                        'license_plate_no' => $weighbridge->vehicle_number,
                        'estate_supplier' => $weighbridge->estate_supplier,
                        'netto' => 10000,
                        'quantity' => 120,
                    ],
                    'cages-track' => [
                        'date' => self::FILTER_RECORD_DATE,
                        'tippler_start_time' => self::FILTER_RECORD_DATE.' 06:00:00',
                        'tippler_stop_time' => self::FILTER_RECORD_DATE.' 18:00:00',
                        'cages_out' => 12,
                        'cages_tipped' => 10,
                    ],
                    default => ['date' => self::FILTER_RECORD_DATE],
                };

                $record = $recordClass::updateOrCreate(
                    [$idColumn => "{$prefix}-BROWSER-PL-{$suffix}"],
                    $attributes,
                );

                if ($stationType === 'weighbridge') {
                    $weighbridge = $record;
                }

                // Satu baris log untuk dua jenis Dispatch. detail-cpo-dispatch dan
                // detail-kernel-dispatch.spec.ts membuka BARIS PERTAMA Data Browser
                // dan menuntut tabel log-nya; tabel itu hanya dirender bila ada
                // detail. Kedua spec itu berjalan SEBELUM form-*-dispatch (urutan
                // abjad), jadi pada database yang baru di-seed record ini bisa
                // menjadi satu-satunya — dan tanpa detail ia membuat keduanya merah.
                if ($stationType === 'cpo-dispatch') {
                    CpoDispatchDetail::updateOrCreate(
                        ['cpo_dispatch_record_id' => $record->id, 'event_date' => self::FILTER_RECORD_DATE],
                        ['waybill_number' => "SJ-PL-{$suffix}", 'net_weight_mt' => 30],
                    );
                } elseif ($stationType === 'kernel-dispatch') {
                    KernelDispatchDetail::updateOrCreate(
                        ['kernel_dispatch_record_id' => $record->id, 'event_date' => self::FILTER_RECORD_DATE],
                        ['waybill_number' => "SJ-PL-{$suffix}", 'net_weight_mt' => 20],
                    );
                }
            }
        }
    }

    /**
     * Fixture untuk e2e-web/tests/kelola-production-line.spec.ts (2026-10-03).
     *
     * Spec itu menyebut lima data berdasarkan nama yang TIDAK PERNAH ditanam
     * seeder mana pun — Business Unit "Mill PL Baru" / "Mill PL Tujuan Edit",
     * line "PL Sebelum Edit", "PL Hapus Bersih" (0 station), "PL Ada Station"
     * (>= 1 station) — dan kode "PL-DUP-01". Kelas yang sama dengan
     * machineryFixtures(): skenario berbasis data gagal sejak ditulis.
     *
     * Kode PL-DUP-01 punya riwayat yang menjelaskan mengapa ia DIPAKSA ke satu
     * baris: karena tidak ada yang menanamnya, skenario "Kode duplikat"
     * pertama kali justru BERHASIL membuat "Line Kode Duplikat" berkode itu —
     * lalu setiap run berikutnya gagal karena baris yang diasersi tidak ada
     * itu kini ada. Baris itu (bila ada) dinamai ulang menjadi fixture-nya,
     * bukan dihapus: ia sudah membawa 18 station yang mungkin sudah dirujuk
     * record.
     *
     * Yang dimutasi spec dipulihkan setiap seed: "PL Sebelum Edit" dinamai
     * ulang oleh skenario edit dan "PL Hapus Bersih" dihapus oleh skenario
     * hapus. Sisa skenario tambah/edit (kode `PL-BROWSER-%`) disapu lebih
     * dulu; station-nya ikut terhapus lewat FK cascade.
     */
    protected function kelolaProductionLineFixtures(): void
    {
        foreach (ProductionLine::where('code', 'LIKE', 'PL-BROWSER-%')->get() as $leftover) {
            try {
                $leftover->delete();
            } catch (QueryException) {
                // Station-nya sudah dirujuk record (FK RESTRICT) — dibiarkan,
                // jangan pernah menghapus data record demi perapian.
            }
        }

        $baru = $this->businessUnit('Mill PL Baru');
        $this->businessUnit('Mill PL Tujuan Edit');

        $this->productionLine($baru, 'PL Sebelum Edit');
        $this->productionLine($baru, 'PL Hapus Bersih');

        $adaStation = $this->productionLine($baru, 'PL Ada Station');
        $this->station($adaStation, StationType::Weighbridge, active: true);

        $duplicate = ProductionLine::where('code', 'PL-DUP-01')->first();

        if ($duplicate === null) {
            $this->productionLine($baru, 'PL Kode Duplikat')->update(['code' => 'PL-DUP-01']);
        } else {
            $duplicate->update(['name' => 'PL Kode Duplikat']);
        }
    }

    /**
     * Business Unit fixture, idempoten pada nama.
     *
     * MENGAPA TIDAK LAGI `firstOrCreate(..., factory()->make()->toArray())`.
     * Argumen kedua firstOrCreate dievaluasi SETIAP kali, juga ketika barisnya
     * sudah ada — dan BusinessUnitFactory::definition() memuat
     * `company_id => Company::factory()`, yang oleh Laravel di-`create()` bahkan
     * di dalam make(). Akibatnya setiap seed membocorkan satu Corporate + satu
     * Company bernama faker per pemanggilan. Terukur 2026-10-03: satu seed
     * menambah 3 corporate dan 3 company; tabel corporates sudah berisi
     * puluhan nama faker yang mendorong fixture spec kelola-corporate ke
     * halaman 2-3 daftarnya. Sekarang factory hanya dipanggil bila barisnya
     * belum ada, dan induknya adalah Company fixture yang tetap, bukan
     * buatan baru.
     */
    protected function businessUnit(string $name, ?Company $company = null): BusinessUnit
    {
        $existing = BusinessUnit::where('name', $name)->first();

        if ($existing !== null) {
            if ($company !== null && $existing->company_id !== $company->id) {
                $existing->update(['company_id' => $company->id]);
            }

            return $existing;
        }

        return BusinessUnit::factory()->create([
            'name' => $name,
            'company_id' => ($company ?? $this->company('PT Browser Fixture', $this->corporate('Corp Browser Fixture')))->id,
        ]);
    }

    /** Corporate fixture, idempoten pada nama (unik di database). */
    protected function corporate(string $name): Corporate
    {
        return Corporate::where('name', $name)->first()
            ?? Corporate::factory()->create(['name' => $name]);
    }

    /**
     * Company fixture, idempoten pada nama. Dicari berdasarkan NAMA SAJA, bukan
     * (corporate, nama): skenario edit memindahkan "PT Sebelum Edit" ke
     * corporate lain, dan pemulihannya harus memindahkannya kembali, bukan
     * membuat kembarannya.
     */
    protected function company(string $name, Corporate $corporate): Company
    {
        // company_code WAJIB di form (CompanyService: required+unique), tetapi
        // CompanyFactory tidak mengisinya — tanpa kode, skenario edit ditolak
        // "Kode company wajib diisi." dan modalnya tidak pernah tertutup.
        $code = 'COMP-FX-'.strtoupper(substr(md5($name), 0, 8));
        $existing = Company::where('name', $name)->first();

        if ($existing !== null) {
            $existing->update(array_filter([
                'corporate_id' => $existing->corporate_id !== $corporate->id ? $corporate->id : null,
                'company_code' => $existing->company_code === null ? $code : null,
            ]));

            return $existing;
        }

        return Company::factory()->create(['name' => $name, 'corporate_id' => $corporate->id, 'company_code' => $code]);
    }

    /**
     * Fixture untuk kelola-corporate / kelola-company / kelola-business-unit /
     * kelola-station.spec.ts (2026-10-03).
     *
     * Keempat spec itu menyebut data berdasarkan nama yang tidak pernah
     * ditanam siapa pun — kelas yang sama dengan machineryFixtures() dan
     * kelolaProductionLineFixtures(). Nama yang diasersi TIDAK ADA
     * ("Mill Kode Duplikat", "Weighbridge Kode Duplikat", ...) sengaja tidak
     * dipakai sebagai nama fixture, bahkan sebagai substring: spec mencari
     * baris dengan `hasText`, yang mencocokkan substring.
     *
     * Yang dimutasi spec dipulihkan setiap seed (baris "Sebelum Edit" yang
     * dinamai ulang dan dipindah induknya, baris "Hapus Bersih" yang dihapus),
     * dan sisa skenario tambah/edit disapu — hanya bila ia tidak punya anak,
     * persis aturan hapus layarnya sendiri, sehingga data yang sudah dirujuk
     * tidak pernah ikut terhapus.
     */
    protected function kelolaHierarchyFixtures(): void
    {
        // ── Sapu sisa run sebelumnya ───────────────────────────────────────
        foreach (Station::where('code', 'LIKE', 'STA-BROWSER-%')
            ->orWhere('name', 'LIKE', 'Weighbridge Sesudah Edit %')->get() as $station) {
            if (MachineryGroup::where('station_id', $station->id)->exists()
                || Machinery::where('station_id', $station->id)->exists()) {
                continue;
            }

            try {
                $station->delete();
            } catch (QueryException) {
                // Sudah dirujuk record (FK RESTRICT) — dibiarkan.
            }
        }

        BusinessUnit::where(fn ($q) => $q->where('code', 'LIKE', 'BU-BROWSER-%')
            ->orWhere('name', 'LIKE', 'Mill Sesudah Edit %'))
            ->whereDoesntHave('stations')
            ->get()
            ->each(function (BusinessUnit $bu) {
                if (! ProductionLine::where('business_unit_id', $bu->id)->exists()
                    && ! User::where('business_unit_id', $bu->id)->exists()) {
                    $bu->delete();
                }
            });

        Company::where(fn ($q) => $q->where('name', 'LIKE', 'PT Anak Baru %')
            ->orWhere('name', 'LIKE', 'PT Sesudah Edit %'))
            ->get()
            ->each(function (Company $company) {
                if (! BusinessUnit::where('company_id', $company->id)->exists()) {
                    $company->delete();
                }
            });

        Corporate::where(fn ($q) => $q->where('name', 'LIKE', 'PT Baru %')
            ->orWhere('name', 'LIKE', 'PT Sesudah Edit %'))
            ->get()
            ->each(function (Corporate $corporate) {
                if (! Company::where('corporate_id', $corporate->id)->exists()) {
                    $corporate->delete();
                }
            });

        // ── kelola-corporate ───────────────────────────────────────────────
        $this->corporate('PT Sebelum Edit');
        $this->corporate('PT Hapus Bersih');
        $this->corporate('PT Nama Duplikat');
        $this->company('PT Fixture Anak Corporate', $this->corporate('PT Ada Company'));

        // ── kelola-company ─────────────────────────────────────────────────
        $induk = $this->corporate('PT Induk Baru');
        $this->corporate('PT Tujuan Edit');
        $this->company('PT Sebelum Edit', $induk);
        $this->company('PT Hapus Bersih', $induk);
        $this->businessUnit('Mill Fixture Anak Company', $this->company('PT Ada Business Unit', $induk));
        $this->company('PT Nama Duplikat', $this->corporate('PT Sama Corporate'));

        // ── kelola-business-unit ───────────────────────────────────────────
        $fixtureCorporate = $this->corporate('Corp Browser Fixture');
        $companyBaru = $this->company('PT Company Baru', $fixtureCorporate);
        $this->company('PT Company Tujuan Edit', $fixtureCorporate);
        $this->businessUnit('Mill Sebelum Edit', $companyBaru);
        $this->businessUnit('Mill Hapus Bersih', $companyBaru);
        $adaStation = $this->businessUnit('Mill Ada Station', $companyBaru);
        $this->station($this->productionLine($adaStation, 'PL Mill Ada Station'), StationType::Weighbridge, active: true);

        // BU-DUP-01: seperti PL-DUP-01, skenario "kode duplikat" pernah
        // MEMBUAT "Mill Kode Duplikat" berkode ini karena tidak ada yang
        // memegangnya — lalu setiap run berikutnya gagal karena baris itu kini
        // ada. Dinamai ulang, bukan dihapus.
        $buDuplicate = BusinessUnit::where('code', 'BU-DUP-01')->first();

        if ($buDuplicate === null) {
            $this->businessUnit('Mill BU Duplikat', $companyBaru)->update(['code' => 'BU-DUP-01']);
        } elseif ($buDuplicate->name !== 'Mill BU Duplikat') {
            $buDuplicate->update(['name' => 'Mill BU Duplikat']);
        }

        // "Mill Kode Duplikat" — residu yang sama itu, sementara itu, sudah
        // menjadi fixture yang DIANDALKAN: kelola-periode-pelaporan,
        // detail-periode-pelaporan, form-weighbridge dan lima spec laporan-*
        // memakainya sebagai "mill tanpa satu pun stasiun aktif" (OTHER_MILL).
        // Kini ditanam dengan sengaja — kode lain, TANPA production line atau
        // stasiun — dan skenario "kode duplikat" di kelola-business-unit memakai
        // nama yang berbeda supaya keduanya tidak bertabrakan.
        $this->businessUnit('Mill Kode Duplikat', $companyBaru);

        // ── mills-setting ──────────────────────────────────────────────────
        // Tiga mill yang dipilih spec itu berdasarkan nama. "Mill Kosong" sengaja
        // tanpa production line maupun station (skenario "belum ada station"),
        // dan tidak pernah disimpan oleh spec mana pun, jadi tetap "belum
        // pernah diatur". Icon station fixture dikembalikan ke default setiap
        // seed — skenario icon mengubahnya.
        $this->businessUnit('Mill Setting Uji', $companyBaru);
        $this->businessUnit('Mill Kosong', $companyBaru);
        $iconStation = $this->namedStation(
            $this->productionLine($this->businessUnit('Mill Station Icon', $companyBaru), 'PL Station Icon'),
            'Weighbridge Icon Test',
        );
        $iconStation->update(['icon' => null]);

        // ── kelola-station ─────────────────────────────────────────────────
        $stationBaru = $this->businessUnit('Mill Station Baru', $companyBaru);
        $stationBaruLine = $this->productionLine($stationBaru, 'PL Station Baru');
        $this->productionLine($this->businessUnit('Mill Station Tujuan Edit', $companyBaru), 'PL Station Tujuan Edit');

        foreach (['Weighbridge Sebelum Edit', 'Weighbridge Hapus Bersih', 'Weighbridge Ada Machinery'] as $name) {
            $this->namedStation($stationBaruLine, $name)->update(['type' => StationType::Weighbridge]);
        }

        $adaMachinery = Station::where('production_line_id', $stationBaruLine->id)
            ->where('name', 'Weighbridge Ada Machinery')->firstOrFail();
        $this->machineryGroup($adaMachinery, 'MG-FIXTURE-STATION-ADA');

        $staDuplicate = Station::where('code', 'STA-DUP-01')->first();

        if ($staDuplicate === null) {
            $this->namedStation($stationBaruLine, 'Weighbridge Fixture Kode')->update(['code' => 'STA-DUP-01']);
        }
    }

    /**
     * Sweeps the `*-BROWSER-EDIT-DONE` rows the edit scenarios leave behind.
     *
     * WHY THIS IS NECESSARY, not tidiness. Every "klik Edit dari Detail"
     * scenario RENAMES its fixture to `<id>-DONE` — that rename IS the thing it
     * asserts. So the fixture consumes itself, and on the next seed
     * updateOrCreate() finds no `<id>` row and CREATES a fresh one, leaving two
     * rows whose ids differ only by a suffix. The specs locate their row with
     * `hasText: '<id>'`, which is a SUBSTRING match: it then resolves to two
     * elements and Playwright refuses it as a strict mode violation. Measured,
     * not predicted — it happened to Grading, Weighbridge and Boiler Room within
     * one afternoon.
     *
     * Same situation, and the same remedy, as the MachineryGroup residue swept in
     * machineryFixtures(): the specs mutate their fixture, so the seeder restores
     * it to exactly one row per run. The contract is "seed, then run" — running
     * the suite twice without re-seeding leaves the edit scenarios without their
     * row, which is why this seeder exists at all.
     *
     * ORDER MATTERS: `grading_records.weighbridge_record_id` is NOT NULL, so a
     * Grading row must go before the Weighbridge row it points at.
     */
    protected function sweepEditLeftovers(): void
    {
        GradingRecord::where('grading_number', 'GR-BROWSER-EDIT-DONE')->delete();
        // Grading SISA form-grading.spec.ts yang menunjuk WB-BROWSER-EDIT-DONE.
        // Skenario "Simpan berhasil" di spec itu memilih kartu WB pertama
        // (urut record_datetime terbaru) — dan setelah skenario edit Weighbridge
        // jalan, kartu itu adalah WB-BROWSER-EDIT-DONE. FK
        // grading_records.weighbridge_record_id RESTRICT, jadi tanpa baris ini
        // DELETE di bawah ditolak dan SELURUH seeder berhenti di sini (terukur
        // 2026-10-03: GR-BROWSER-1790934437740). Hanya baris berawalan
        // GR-BROWSER- yang disapu — data pabrik tidak pernah memakai awalan itu.
        // grading_details ikut terhapus lewat FK cascade.
        GradingRecord::where('grading_number', 'LIKE', 'GR-BROWSER-%')
            ->whereIn(
                'weighbridge_record_id',
                WeighbridgeRecord::where('wb_card_number', 'WB-BROWSER-EDIT-DONE')->select('id'),
            )
            ->delete();
        WeighbridgeRecord::where('wb_card_number', 'WB-BROWSER-EDIT-DONE')->delete();
        CagesTrackRecord::where('cages_track_number', 'CT-BROWSER-EDIT-DONE')->delete();

        foreach (self::EDIT_RECORDS as [$recordClass, , $idColumn, $businessId]) {
            $recordClass::where($idColumn, $businessId.'-DONE')->delete();
        }
    }

    /**
     * WB-BROWSER-EDIT. The one record table with no `date` column and no
     * detail table at all — Weighbridge is one row per weighing transaction,
     * so there is nothing to attach a detail row to.
     */
    protected function weighbridgeEditRecord(ProductionLine $line, User $author): WeighbridgeRecord
    {
        $station = Station::where('production_line_id', $line->id)
            ->where('type', StationType::Weighbridge)
            ->firstOrFail();

        return WeighbridgeRecord::updateOrCreate(
            ['wb_card_number' => 'WB-BROWSER-EDIT'],
            [
                'station_id' => $station->id,
                'production_line_id' => $station->production_line_id,
                'weighbridge_type' => 'receive',
                'record_datetime' => self::RECORD_DATE.' 08:00:00',
                'vehicle_number' => 'B 1234 WB',
                'driver_name' => 'Driver Browser Test',
                'estate_supplier' => 'Estate Browser Test',
                'division' => 'Divisi 1',
                'gross_weight' => 15000,
                'tare_weight' => 5000,
                'net_weight' => 10000,
                'status' => RecordStatus::Saved,
                'created_by' => $author->id,
            ],
        );
    }

    /**
     * GR-BROWSER-EDIT. Four figures are NOT NULL on `grading_records`
     * (license_plate_no, estate_supplier, netto, quantity) on top of the
     * usual columns, and its detail row needs a real grading parameter plus
     * quantity/uom/percentage — so it cannot ride the uniform loop above.
     */
    protected function gradingEditRecord(ProductionLine $line, User $author, WeighbridgeRecord $weighbridge): void
    {
        $station = Station::where('production_line_id', $line->id)
            ->where('type', StationType::Grading)
            ->firstOrFail();

        $record = GradingRecord::updateOrCreate(
            ['grading_number' => 'GR-BROWSER-EDIT'],
            [
                'station_id' => $station->id,
                'production_line_id' => $station->production_line_id,
                'date' => self::RECORD_DATE,
                'weighbridge_record_id' => $weighbridge->id,
                'license_plate_no' => $weighbridge->vehicle_number,
                'estate_supplier' => $weighbridge->estate_supplier,
                'netto' => 10000,
                'quantity' => 120,
                'status' => RecordStatus::DraftOngoing,
                'created_by' => $author->id,
            ],
        );

        // firstOrFail, not a created parameter: the 16 grading parameters are
        // master data from the ordinary seeders. Inventing one here would
        // hide their absence, which is a real failure rather than a fixture
        // gap this seeder should paper over.
        $parameter = GradingParameter::query()->orderBy('name')->firstOrFail();

        GradingDetail::updateOrCreate(
            [
                'grading_record_id' => $record->id,
                'grading_parameter_id' => $parameter->id,
            ],
            [
                'quantity' => 30,
                'uom' => 'kg',
                'percentage' => 25,
            ],
        );

        $this->promoteToSaved($record);
    }

    /**
     * CT-BROWSER-EDIT. Three extra NOT NULL header fields
     * (tippler_start_time, cages_out, cages_tipped), and a detail table
     * called `cages_tipped_times` rather than `cages_track_details` whose key
     * is `tipped_hour`, not `time_slot`. `checked_cage_numbers` is CSV TEXT,
     * matching CagesTrackRecordService's own spelling (it implodes the array
     * with commas) — a JSON array here would read back as one bogus cage.
     */
    protected function cagesTrackEditRecord(ProductionLine $line, User $author): void
    {
        $station = Station::where('production_line_id', $line->id)
            ->where('type', StationType::CagesTrack)
            ->firstOrFail();

        $record = CagesTrackRecord::updateOrCreate(
            ['cages_track_number' => 'CT-BROWSER-EDIT'],
            [
                'station_id' => $station->id,
                'production_line_id' => $station->production_line_id,
                'date' => self::RECORD_DATE,
                'tippler_start_time' => self::RECORD_DATE.' 06:00:00',
                // WAJIB menurut validasi form meski NULLABLE di database. Tanpa
                // ini record-nya tersimpan tetapi TIDAK BISA DISIMPAN ULANG:
                // "klik Edit dari Detail" ditolak dengan "Tippler Stop Time wajib
                // diisi." dan halaman tidak pernah keluar dari /edit. Dibaca dari
                // respons server lewat trace.zip, bukan ditebak dari tangkapan layar.
                'tippler_stop_time' => self::RECORD_DATE.' 18:00:00',
                'cages_out' => 12,
                'cages_tipped' => 10,
                'status' => RecordStatus::DraftOngoing,
                'created_by' => $author->id,
            ],
        );

        CagesTippedTime::updateOrCreate(
            [
                'cages_track_record_id' => $record->id,
                'tipped_hour' => 7,
            ],
            [
                'checked_cage_numbers' => '1,2',
                'total_cages' => 2,
                'cages_remain' => self::MAIN_LINE_CAGES - 2,
            ],
        );

        $this->promoteToSaved($record);
    }

    /**
     * Flips a record to `saved` AFTER its detail row exists.
     *
     * SIX of the record models refuse `saved` while they have no detail —
     * CagesTrack, Depricarping, Grading, KernelPlant, Pressing and Threshing
     * each throw a ValidationException from a `saving` hook. Creating the
     * record already saved and attaching the detail afterwards is therefore
     * impossible for them, so every record here is created DRAFT and promoted
     * once its detail is in place. The twelve models without the hook take the
     * same path rather than a second one: the specs read `saved` from all of
     * them, and one code path cannot drift from the other.
     */
    protected function promoteToSaved(Model $record): void
    {
        $record->update(['status' => RecordStatus::Saved]);
    }

    protected function accountsFor(string $prefix, BusinessUnit $businessUnit): void
    {
        $this->user("{$prefix}-admin01", UserRole::Admin, $businessUnit);
        $this->user("{$prefix}-supervisor01", UserRole::Supervisor, $businessUnit);

        if (in_array($prefix, self::MILL_MANAGEMENT_PREFIXES, true)) {
            $this->user("{$prefix}-millmgmt01", UserRole::MillManagement, $businessUnit);
            $this->user("{$prefix}-mm01", UserRole::MillManagement, $businessUnit);
        }
    }

    protected function user(string $username, UserRole $role, BusinessUnit $businessUnit, ?string $password = null): User
    {
        return User::updateOrCreate(
            ['username' => $username],
            [
                'name' => ucfirst(str_replace('-', ' ', $username)),
                'password_hash' => Hash::make($password ?? self::PASSWORD),
                'role' => $role,
                'business_unit_id' => $businessUnit->id,
                'is_active' => true,
            ],
        );
    }

    protected function productionLine(BusinessUnit $businessUnit, string $name): ProductionLine
    {
        return ProductionLine::firstOrCreate(
            ['business_unit_id' => $businessUnit->id, 'name' => $name],
            ProductionLine::factory()
                ->make(['business_unit_id' => $businessUnit->id, 'name' => $name])
                ->toArray(),
        );
    }

    /**
     * Built with explicit attributes rather than StationFactory: its
     * definition() calls faker->unique() for the name, and this seeder
     * creates ~200 stations in one process, which exhausts unique()'s retry
     * budget. A deterministic name is also easier to recognise in a failing
     * screenshot than "Weighbridge 47".
     */
    protected function station(ProductionLine $line, StationType $type, bool $active): Station
    {
        $label = ucwords(str_replace('-', ' ', $type->value));

        return Station::firstOrCreate(
            ['production_line_id' => $line->id, 'type' => $type],
            [
                'business_unit_id' => $line->business_unit_id,
                'name' => $label,
                'type' => $type,
                'is_active' => $active,
            ],
        );
    }
}
