<?php

namespace Database\Seeders;

use App\Enums\RecordStatus;
use App\Enums\StationType;
use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
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
     * @var array<string, array{0: class-string, 1: class-string, 2: string, 3: string}>
     */
    protected const EDIT_RECORDS = [
        'effluent-plant' => [
            \App\Models\EffluentPlantRecord::class,
            \App\Models\EffluentPlantDetail::class,
            'effluent_plant_id',
            'EP-BROWSER-EDIT',
        ],
    ];

    protected const RECORD_DATE = '2026-08-05';

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

        $this->editRecords($mainLine);

        $this->command?->info('Browser fixture: users, "'.self::MAIN_PRODUCTION_LINE.'" and per-station "PL Tanpa ..." lines are ready.');
    }

    /**
     * One pre-existing record per station, on the main line, with a single
     * detail row so the detail grid is not empty.
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
                    'date' => self::RECORD_DATE,
                    'status' => RecordStatus::Saved,
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
        }
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
