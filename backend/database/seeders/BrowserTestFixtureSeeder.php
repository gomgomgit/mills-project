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
use App\Models\DepricarpingDetail;
use App\Models\DepricarpingRecord;
use App\Models\EffluentPlantDetail;
use App\Models\EffluentPlantRecord;
use App\Models\EngineRoomDetail;
use App\Models\EngineRoomRecord;
use App\Models\GradingDetail;
use App\Models\GradingParameter;
use App\Models\GradingRecord;
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
use App\Models\Station;
use App\Models\StorageTankDetail;
use App\Models\StorageTankRecord;
use App\Models\ThreshingDetail;
use App\Models\ThreshingRecord;
use App\Models\User;
use App\Models\WeighbridgeRecord;
use Illuminate\Database\Eloquent\Model;
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
     * station type => [record model, detail model, id column, business id]
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
        'boiler-room' => [BoilerRoomRecord::class, BoilerRoomDetail::class, 'boiler_room_id', 'BR-BROWSER-EDIT'],
        'clarification' => [ClarificationRecord::class, ClarificationDetail::class, 'clarification_id', 'CLR-BROWSER-EDIT'],
        'depricarping' => [DepricarpingRecord::class, DepricarpingDetail::class, 'presser_id', 'DP-BROWSER-EDIT'],
        'effluent-plant' => [EffluentPlantRecord::class, EffluentPlantDetail::class, 'effluent_plant_id', 'EP-BROWSER-EDIT'],
        'engine-room' => [EngineRoomRecord::class, EngineRoomDetail::class, 'engine_room_id', 'ER-BROWSER-EDIT'],
        'kernel-plant' => [KernelPlantRecord::class, KernelPlantDetail::class, 'kernel_plant_id', 'KP-BROWSER-EDIT'],
        'pressing' => [PressingRecord::class, PressingDetail::class, 'presser_id', 'PR-BROWSER-EDIT'],
        'process-quality-control' => [ProcessQualityControlRecord::class, ProcessQualityControlDetail::class, 'process_qc_id', 'PQC-BROWSER-EDIT'],
        'process-water' => [ProcessWaterRecord::class, ProcessWaterDetail::class, 'process_water_id', 'PW-BROWSER-EDIT'],
        'storage-tank' => [StorageTankRecord::class, StorageTankDetail::class, 'storage_tank_id', 'ST-BROWSER-EDIT'],
        'threshing' => [ThreshingRecord::class, ThreshingDetail::class, 'thresher_id', 'TH-BROWSER-EDIT'],
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
        $businessUnit = BusinessUnit::firstOrCreate(
            ['name' => self::BUSINESS_UNIT_NAME],
            BusinessUnit::factory()->make(['name' => self::BUSINESS_UNIT_NAME])->toArray(),
        );

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

        foreach (self::CHANGE_PASSWORD_USERS as $username) {
            $this->user($username, UserRole::Supervisor, $businessUnit, self::CHANGE_PASSWORD_PASSWORD);
        }

        $this->cagesFixtures($businessUnit, $mainLine);
        $this->editRecords($mainLine);
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

        foreach (self::EDIT_RECORDS as $stationType => [$recordClass, $detailClass, $idColumn, $businessId]) {
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
                [],
            );

            $this->promoteToSaved($record);
        }

        // The Weighbridge record comes FIRST: the Grading record below needs
        // its id for the NOT NULL `weighbridge_record_id`, and Form Grading's
        // WB Card No dropdown is fed by exactly this row
        // (FormGrading::loadWeighbridgeOptions() lists every Weighbridge
        // record whose station belongs to the chosen mill). One row serves
        // both, which is why they are not seeded independently.
        $weighbridge = $this->weighbridgeEditRecord($line, $author);
        $this->gradingEditRecord($line, $author, $weighbridge);
        $this->cagesTrackEditRecord($line, $author);
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
