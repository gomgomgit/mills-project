<?php

/**
 * MachineryServiceTest — screen-031--kelola-machinery /
 * usecase-031--kelola-machinery.
 *
 * Unit tests for App\Services\MachineryService::listMachinery() /
 * ::machineryGroupOptions() / ::detail() / ::create() / ::update() /
 * ::delete(), covering the unit_test_cases derived from this screen's
 * business_logic. Calls the service directly (no HTTP layer), mirroring
 * tests/Unit/Services/MachineryGroupServiceTest.php's pragmatic deviation
 * from test_strategy.unit_test.mock_policy: this service persists/queries
 * via Eloquent, no injectable repository abstraction exists in this
 * codebase, so this suite binds Tests\TestCase + RefreshDatabase
 * (sqlite in-memory) and seeds fixture data via model factories.
 *
 * CRITICAL — the key structural rules this screen exists to enforce:
 *  - `station_id`/`production_line_id` are NEVER read from create()/
 *    update()'s $data payload, even when a caller spoofs them — both are
 *    always independently re-derived server-side from the selected
 *    MachineryGroup's own station_id/production_line_id. See the
 *    dedicated "spoofed" block below.
 *  - `insurances`/`tax_purchases` are replace-all child-row collections:
 *    create() always syncs both (from an empty default if absent),
 *    update() only replaces a collection when its key is PRESENT in the
 *    payload (even as an empty array) — see the dedicated child-row
 *    blocks below.
 *  - delete() has NO guard of any kind and must never throw, even when
 *    child rows exist.
 */

use App\Models\Machinery;
use App\Models\MachineryGroup;
use App\Models\MachineryInsurance;
use App\Models\MachineryTaxPurchase;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Services\MachineryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new MachineryService;
});

if (! function_exists('makeMachineryGroupFixture')) {
    function makeMachineryGroupFixture(): MachineryGroup
    {
        return MachineryGroup::factory()->create();
    }
}

// --- create(): validation ---------------------------------------------------

it('throws a ValidationException when creating without a machinery_group_id', function () {
    try {
        $this->service->create(['equipment_code' => 'EQ-001', 'name' => 'Mesin Tanpa Group']);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('machinery_group_id');
    }

    expect(Machinery::count())->toBe(0);
});

it('throws a ValidationException when creating with a machinery_group_id that does not exist', function () {
    try {
        $this->service->create([
            'machinery_group_id' => (string) Str::uuid(),
            'equipment_code' => 'EQ-002',
            'name' => 'Mesin Group Tak Ada',
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('machinery_group_id');
    }
});

it('throws a ValidationException when creating with an empty name', function () {
    $group = makeMachineryGroupFixture();

    try {
        $this->service->create([
            'machinery_group_id' => $group->id,
            'equipment_code' => 'EQ-003',
            'name' => '',
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('name');
    }

    expect(Machinery::count())->toBe(0);
});

it('throws a ValidationException when creating without an equipment_code', function () {
    $group = makeMachineryGroupFixture();

    try {
        $this->service->create([
            'machinery_group_id' => $group->id,
            'name' => 'Mesin Tanpa Kode',
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('equipment_code');
    }

    expect(Machinery::count())->toBe(0);
});

it('throws a ValidationException when creating with an equipment_code that already exists', function () {
    $group = makeMachineryGroupFixture();
    Machinery::factory()->forFullMachineryGroup($group)->withEquipmentCode('EQ-DUP')->create();

    try {
        $this->service->create([
            'machinery_group_id' => $group->id,
            'equipment_code' => 'EQ-DUP',
            'name' => 'Mesin Duplikat',
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('equipment_code');
    }

    expect(Machinery::count())->toBe(1);
});

it('throws a ValidationException when year_made is not an integer', function () {
    $group = makeMachineryGroupFixture();

    try {
        $this->service->create([
            'machinery_group_id' => $group->id,
            'equipment_code' => 'EQ-004',
            'name' => 'Mesin Tahun Invalid',
            'year_made' => 'bukan-angka',
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('year_made');
    }
});

// --- create(): station_id/production_line_id structural rule ------------------

it('ignores spoofed station_id/production_line_id and always derives them from the selected MachineryGroup', function () {
    $group = makeMachineryGroupFixture();
    $spoofedStation = Station::factory()->create();
    $spoofedProductionLine = ProductionLine::factory()->create();

    $result = $this->service->create([
        'machinery_group_id' => $group->id,
        'station_id' => $spoofedStation->id,
        'production_line_id' => $spoofedProductionLine->id,
        'equipment_code' => 'EQ-SPOOF',
        'name' => 'Mesin Spoof',
    ]);

    expect($result['station_id'])->toBe($group->station_id);
    expect($result['station_id'])->not->toBe($spoofedStation->id);
    expect($result['production_line_id'])->toBe($group->production_line_id);
    expect($result['production_line_id'])->not->toBe($spoofedProductionLine->id);

    $fresh = Machinery::where('equipment_code', 'EQ-SPOOF')->firstOrFail();
    expect($fresh->station_id)->toBe($group->station_id);
    expect($fresh->production_line_id)->toBe($group->production_line_id);
});

// --- create(): happy path ----------------------------------------------------

it('creates a machinery with all technical fields and returns the expected row shape', function () {
    $group = makeMachineryGroupFixture();

    $result = $this->service->create([
        'machinery_group_id' => $group->id,
        'equipment_code' => 'EQ-100',
        'name' => 'Boiler Utama',
        'description' => 'Boiler untuk stasiun rebusan',
        'registration_no' => 'REG-1',
        'make' => 'Merk A',
        'model' => 'Model X',
        'equipment_type' => 'Boiler',
        'part_no' => 'PN-1',
        'serial_no' => 'SN-1',
        'gearbox' => 'GB-1',
        'motor' => 'MT-1',
        'mounting' => 'Fixed',
        'rpm' => '1500.5',
        'chain' => 'CH-1',
        'capacity' => '10 ton/jam',
        'brand' => 'BrandX',
        'year_made' => '2020',
        'fixed_asset' => 'FA-1',
        'control_activity' => 'Manual',
        'owner_ite' => 'IT-1',
    ]);

    expect($result['equipment_code'])->toBe('EQ-100');
    expect($result['name'])->toBe('Boiler Utama');
    expect($result['rpm'])->toBe(1500.5);
    expect($result['year_made'])->toBe(2020);
    expect($result['machinery_group_code'])->toBe($group->group_code);
    expect($result['insurances'])->toBe([]);
    expect($result['tax_purchases'])->toBe([]);

    expect(Machinery::count())->toBe(1);
});

// --- create(): child rows ----------------------------------------------------

it('persists exactly N insurance rows and M tax_purchase rows on create', function () {
    $group = makeMachineryGroupFixture();

    $result = $this->service->create([
        'machinery_group_id' => $group->id,
        'equipment_code' => 'EQ-CHILD-1',
        'name' => 'Mesin Dengan Anak Baris',
        'insurances' => [
            ['ownership' => 'Perusahaan', 'insurance_policy_no' => 'POL-1', 'premium' => '100.5'],
            ['ownership' => 'Perusahaan', 'insurance_policy_no' => 'POL-2', 'premium' => '200'],
            ['ownership' => 'Perusahaan', 'insurance_policy_no' => 'POL-3', 'premium' => '300'],
        ],
        'tax_purchases' => [
            ['policy_type' => 'Cash', 'purchase_cost' => '1000'],
        ],
    ]);

    expect($result['insurances'])->toHaveCount(3);
    expect($result['tax_purchases'])->toHaveCount(1);

    $machinery = Machinery::where('equipment_code', 'EQ-CHILD-1')->firstOrFail();
    expect(MachineryInsurance::where('machinery_id', $machinery->id)->count())->toBe(3);
    expect(MachineryTaxPurchase::where('machinery_id', $machinery->id)->count())->toBe(1);
});

it('creates a machinery successfully with zero insurance/tax_purchase rows when both are omitted', function () {
    $group = makeMachineryGroupFixture();

    $result = $this->service->create([
        'machinery_group_id' => $group->id,
        'equipment_code' => 'EQ-CHILD-0',
        'name' => 'Mesin Tanpa Anak Baris',
    ]);

    expect($result['insurances'])->toBe([]);
    expect($result['tax_purchases'])->toBe([]);
});

// --- update(): validation + structural rule ----------------------------------

it('updates a machinery keeping its own equipment_code unchanged without errors', function () {
    $group = makeMachineryGroupFixture();
    $machinery = Machinery::factory()->forFullMachineryGroup($group)->withEquipmentCode('EQ-SELF')->create(['name' => 'Lama']);

    $result = $this->service->update($machinery->id, [
        'machinery_group_id' => $group->id,
        'equipment_code' => 'EQ-SELF',
        'name' => 'Baru',
    ]);

    expect($result['name'])->toBe('Baru');
    expect($result['equipment_code'])->toBe('EQ-SELF');
});

it('throws a ValidationException when updating to an equipment_code already used by another machinery', function () {
    $group = makeMachineryGroupFixture();
    Machinery::factory()->forFullMachineryGroup($group)->withEquipmentCode('EQ-TAKEN')->create();
    $machinery = Machinery::factory()->forFullMachineryGroup($group)->withEquipmentCode('EQ-FREE')->create();

    try {
        $this->service->update($machinery->id, [
            'machinery_group_id' => $group->id,
            'equipment_code' => 'EQ-TAKEN',
            'name' => $machinery->name,
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('equipment_code');
    }
});

it('throws a ModelNotFoundException when updating a machinery id that does not exist', function () {
    $group = makeMachineryGroupFixture();

    expect(fn () => $this->service->update((string) Str::uuid(), [
        'machinery_group_id' => $group->id,
        'equipment_code' => 'EQ-404',
        'name' => 'Tidak Ada',
    ]))->toThrow(ModelNotFoundException::class);
});

it('re-derives station_id/production_line_id when the machinery_group_id changes on update, ignoring spoofed values', function () {
    $groupA = makeMachineryGroupFixture();
    $groupB = makeMachineryGroupFixture();
    $machinery = Machinery::factory()->forFullMachineryGroup($groupA)->create();
    $spoofedProductionLine = ProductionLine::factory()->create();

    $result = $this->service->update($machinery->id, [
        'machinery_group_id' => $groupB->id,
        'production_line_id' => $spoofedProductionLine->id,
        'equipment_code' => $machinery->equipment_code,
        'name' => $machinery->name,
    ]);

    expect($result['machinery_group_id'])->toBe($groupB->id);
    expect($result['station_id'])->toBe($groupB->station_id);
    expect($result['production_line_id'])->toBe($groupB->production_line_id);
    expect($result['production_line_id'])->not->toBe($spoofedProductionLine->id);
});

// --- update(): child-row replace-all semantics --------------------------------

it('replaces all existing child rows on update when insurances/tax_purchases keys are present', function () {
    $group = makeMachineryGroupFixture();
    $machinery = Machinery::factory()->forFullMachineryGroup($group)->create();
    MachineryInsurance::factory()->forMachinery($machinery)->count(2)->create();
    MachineryTaxPurchase::factory()->forMachinery($machinery)->count(2)->create();

    $result = $this->service->update($machinery->id, [
        'machinery_group_id' => $group->id,
        'equipment_code' => $machinery->equipment_code,
        'name' => $machinery->name,
        'insurances' => [
            ['ownership' => 'Baru', 'insurance_policy_no' => 'POL-NEW'],
        ],
        'tax_purchases' => [],
    ]);

    expect($result['insurances'])->toHaveCount(1);
    expect($result['insurances'][0]['insurance_policy_no'])->toBe('POL-NEW');
    expect($result['tax_purchases'])->toHaveCount(0);

    expect(MachineryInsurance::where('machinery_id', $machinery->id)->count())->toBe(1);
    expect(MachineryTaxPurchase::where('machinery_id', $machinery->id)->count())->toBe(0);
});

it('leaves existing child rows untouched on update when insurances/tax_purchases keys are absent', function () {
    $group = makeMachineryGroupFixture();
    $machinery = Machinery::factory()->forFullMachineryGroup($group)->create();
    MachineryInsurance::factory()->forMachinery($machinery)->count(2)->create();
    MachineryTaxPurchase::factory()->forMachinery($machinery)->count(3)->create();

    $result = $this->service->update($machinery->id, [
        'machinery_group_id' => $group->id,
        'equipment_code' => $machinery->equipment_code,
        'name' => 'Nama Diperbarui Saja',
    ]);

    expect($result['insurances'])->toHaveCount(2);
    expect($result['tax_purchases'])->toHaveCount(3);
    expect(MachineryInsurance::where('machinery_id', $machinery->id)->count())->toBe(2);
    expect(MachineryTaxPurchase::where('machinery_id', $machinery->id)->count())->toBe(3);
});

// --- delete(): no guard of any kind -------------------------------------------

it('deletes a machinery and its child rows successfully, even when child rows exist, without throwing', function () {
    $group = makeMachineryGroupFixture();
    $machinery = Machinery::factory()->forFullMachineryGroup($group)->create();
    MachineryInsurance::factory()->forMachinery($machinery)->count(2)->create();
    MachineryTaxPurchase::factory()->forMachinery($machinery)->count(2)->create();

    $this->service->delete($machinery->id);

    expect(Machinery::find($machinery->id))->toBeNull();
    expect(MachineryInsurance::where('machinery_id', $machinery->id)->count())->toBe(0);
    expect(MachineryTaxPurchase::where('machinery_id', $machinery->id)->count())->toBe(0);
});

it('throws a ModelNotFoundException when deleting a machinery id that does not exist', function () {
    expect(fn () => $this->service->delete((string) Str::uuid()))
        ->toThrow(ModelNotFoundException::class);
});

// --- picture upload ------------------------------------------------------------

it('uploads a valid picture successfully and stores the file', function () {
    Storage::fake(MachineryService::PICTURE_DISK);
    $group = makeMachineryGroupFixture();
    $picture = fakeRealImage('picture.jpg', 500);

    $result = $this->service->create([
        'machinery_group_id' => $group->id,
        'equipment_code' => 'EQ-PIC',
        'name' => 'Mesin Berfoto',
    ], $picture);

    expect($result['picture'])->not->toBeNull();
    Storage::disk(MachineryService::PICTURE_DISK)->assertExists($result['picture']);
});

it('throws a ValidationException when the picture exceeds the max size', function () {
    Storage::fake(MachineryService::PICTURE_DISK);
    $group = makeMachineryGroupFixture();
    $picture = fakeRealImage('picture.jpg', 3000);

    try {
        $this->service->create([
            'machinery_group_id' => $group->id,
            'equipment_code' => 'EQ-PIC-BIG',
            'name' => 'Mesin Foto Besar',
        ], $picture);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('picture');
    }
});

it('throws a ValidationException when the picture has a disallowed mime type', function () {
    Storage::fake(MachineryService::PICTURE_DISK);
    $group = makeMachineryGroupFixture();
    $picture = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    try {
        $this->service->create([
            'machinery_group_id' => $group->id,
            'equipment_code' => 'EQ-PIC-PDF',
            'name' => 'Mesin Foto PDF',
        ], $picture);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('picture');
    }
});

// --- listMachinery() / machineryGroupOptions() / detail() ---------------------

it('lists machinery with machinery_group_code eager-loaded and no child arrays', function () {
    $group = makeMachineryGroupFixture();
    Machinery::factory()->forFullMachineryGroup($group)->create(['name' => 'Mesin 1']);

    $result = $this->service->listMachinery(1, 20);

    expect($result['data'])->toHaveCount(1);
    expect($result['data'][0]['machinery_group_code'])->toBe($group->group_code);
    expect($result['data'][0])->not->toHaveKey('insurances');
});

it('filters listMachinery by machinery_group_id', function () {
    $groupA = makeMachineryGroupFixture();
    $groupB = makeMachineryGroupFixture();
    Machinery::factory()->forFullMachineryGroup($groupA)->create();
    Machinery::factory()->forFullMachineryGroup($groupB)->create();

    $result = $this->service->listMachinery(1, 20, $groupA->id);

    expect($result['data'])->toHaveCount(1);
    expect($result['data'][0]['machinery_group_id'])->toBe($groupA->id);
});

it('returns machineryGroupOptions with id/group_code/station_id/production_line_id', function () {
    $group = makeMachineryGroupFixture();

    $options = $this->service->machineryGroupOptions();

    expect($options)->toHaveCount(1);
    expect($options[0])->toMatchArray([
        'id' => $group->id,
        'group_code' => $group->group_code,
        'station_id' => $group->station_id,
        'production_line_id' => $group->production_line_id,
    ]);
    // Label tampilan (audit 2026-10-04 #9c): kode grup + station/line.
    expect($options[0]['label'])->toStartWith($group->group_code)
        ->toContain((string) $group->station->name);
});

it('returns detail() with insurances/tax_purchases arrays populated', function () {
    $group = makeMachineryGroupFixture();
    $machinery = Machinery::factory()->forFullMachineryGroup($group)->create();
    MachineryInsurance::factory()->forMachinery($machinery)->create(['insurance_policy_no' => 'POL-DETAIL']);
    MachineryTaxPurchase::factory()->forMachinery($machinery)->create(['policy_type' => 'Leasing']);

    $result = $this->service->detail($machinery->id);

    expect($result['insurances'])->toHaveCount(1);
    expect($result['insurances'][0]['insurance_policy_no'])->toBe('POL-DETAIL');
    expect($result['tax_purchases'])->toHaveCount(1);
    expect($result['tax_purchases'][0]['policy_type'])->toBe('Leasing');
});

it('throws a ModelNotFoundException when fetching detail() for a machinery id that does not exist', function () {
    expect(fn () => $this->service->detail((string) Str::uuid()))
        ->toThrow(ModelNotFoundException::class);
});

/*
 * ────────────────────────────────────────────────────────────────────────
 * Penggabungan layar Kelola Mesin (2026-09-30) — listMachinery() kini
 * melayani TIGA peran lewat argumen yang saling eksklusif: isi satu grup
 * yang dibuka, wadah "Tanpa grup", dan daftar rata mode Rata.
 * ────────────────────────────────────────────────────────────────────────
 */

it('returns only ungrouped machinery when ungrouped is true', function () {
    $group = MachineryGroup::factory()->create();
    Machinery::factory()->count(3)->forFullMachineryGroup($group)->create();
    Machinery::factory()->count(2)->create(['machinery_group_id' => null]);

    $result = $this->service->listMachinery(1, 20, null, true);

    expect($result['data'])->toHaveCount(2);
    foreach ($result['data'] as $row) {
        expect($row['machinery_group_id'])->toBeNull();
        expect($row['machinery_group_code'])->toBeNull();
    }
});

it('rejects machinery_group_id and ungrouped sent together', function () {
    $group = MachineryGroup::factory()->create();

    expect(fn () => $this->service->listMachinery(1, 20, $group->id, true))
        ->toThrow(ValidationException::class);
});

it('keeps the ungrouped bucket separate from a group filter', function () {
    $group = MachineryGroup::factory()->create();
    Machinery::factory()->count(4)->forFullMachineryGroup($group)->create();
    Machinery::factory()->create(['machinery_group_id' => null]);

    expect($this->service->listMachinery(1, 20, $group->id)['data'])->toHaveCount(4);
    expect($this->service->listMachinery(1, 20, null, true)['data'])->toHaveCount(1);
    // No filter at all = mode Rata: every machine, grouped or not.
    expect($this->service->listMachinery(1, 20)['data'])->toHaveCount(5);
});

it('searches machinery on equipment_code and on name', function () {
    $group = MachineryGroup::factory()->create();
    Machinery::factory()->forFullMachineryGroup($group)
        ->create(['equipment_code' => 'EQ-SEARCH-1', 'name' => 'Screw Press Utama']);
    Machinery::factory()->forFullMachineryGroup($group)
        ->create(['equipment_code' => 'EQ-OTHER-2', 'name' => 'Boiler Feed']);

    $byName = $this->service->listMachinery(1, 20, null, false, 'press');
    expect($byName['data'])->toHaveCount(1);
    expect($byName['data'][0]['equipment_code'])->toBe('EQ-SEARCH-1');

    $byCode = $this->service->listMachinery(1, 20, null, false, 'EQ-OTHER-2');
    expect($byCode['data'])->toHaveCount(1);
    expect($byCode['data'][0]['name'])->toBe('Boiler Feed');
});

/*
 * Jebakan lintas driver, dibuktikan langsung sebelum kode ditulis:
 * SQLite MENOLAK ILIKE (syntax error), sementara LIKE polos case-INSENSITIVE
 * di SQLite tapi case-SENSITIVE di PostgreSQL. Artinya LIKE polos akan lolos
 * seluruh suite ini lalu gagal di produksi. Test ini memakai kapitalisasi
 * yang BERBEDA dari data supaya perbedaan itu tertangkap, bukan tersamar.
 */
it('matches the search term regardless of letter case', function () {
    $group = MachineryGroup::factory()->create();
    Machinery::factory()->forFullMachineryGroup($group)
        ->create(['equipment_code' => 'EQ-CASE-1', 'name' => 'Screw Press Utama']);

    foreach (['press', 'PRESS', 'PrEsS'] as $term) {
        expect($this->service->listMachinery(1, 20, null, false, $term)['data'])
            ->toHaveCount(1, "kata kunci '{$term}' harus cocok");
    }
    expect($this->service->listMachinery(1, 20, null, false, 'eq-case-1')['data'])
        ->toHaveCount(1, 'kode peralatan juga harus cocok tanpa peduli kapitalisasi');
});

it('ANDs the search with the group filter instead of ORing it', function () {
    $groupA = MachineryGroup::factory()->create();
    $groupB = MachineryGroup::factory()->create();
    Machinery::factory()->forFullMachineryGroup($groupA)->create(['name' => 'Press Satu']);
    Machinery::factory()->forFullMachineryGroup($groupB)->create(['name' => 'Press Dua']);

    $result = $this->service->listMachinery(1, 20, $groupA->id, false, 'press');

    // OR would leak 'Press Dua' in from group B.
    expect($result['data'])->toHaveCount(1);
    expect($result['data'][0]['name'])->toBe('Press Satu');
});

it('returns an empty list rather than an error when the search matches nothing', function () {
    Machinery::factory()->forFullMachineryGroup()->create(['name' => 'Screw Press']);

    $result = $this->service->listMachinery(1, 20, null, false, 'tidak-ada-yang-cocok');

    expect($result['data'])->toBe([]);
    expect($result['meta']['total'])->toBe(0);
});

/*
 * Test perilaku di atas TIDAK bisa menangkap regresi ini, dan itu perlu
 * dinyatakan terang-terangan: di SQLite, LIKE polos sudah case-insensitive,
 * jadi mengembalikan kode ke bentuk yang rusak di PostgreSQL tetap membuat
 * seluruh suite hijau. Satu-satunya cara mengunci tanpa menjalankan test di
 * PostgreSQL adalah memeriksa BENTUK SQL-nya.
 *
 * Dibuktikan langsung di kedua driver sebelum kode ditulis:
 *   SQLite      ILIKE -> syntax error | LIKE -> cocok | lower()+LIKE -> cocok
 *   PostgreSQL  ILIKE -> cocok        | LIKE -> 0 BARIS | lower()+LIKE -> cocok
 */
it('lowercases both sides of the search comparison in the generated SQL', function () {
    Machinery::factory()->forFullMachineryGroup()->create();

    DB::enableQueryLog();
    $this->service->listMachinery(1, 20, null, false, 'PrEsS');
    $log = DB::getQueryLog();
    DB::disableQueryLog();

    // Dicocokkan pada versi huruf kecil: SQL mentah mempertahankan
    // kapitalisasi yang ditulis di service, jadi pencocokan literal rapuh.
    $select = collect($log)->pluck('query')
        ->map(fn ($q) => strtolower($q))
        ->first(fn ($q) => str_contains($q, ' like '));
    expect($select)->not->toBeNull('query pencarian tidak ditemukan di query log');

    // Kolom dibungkus lower() — inilah yang membuatnya sama di kedua driver.
    expect($select)->toContain('lower(equipment_code) like');
    expect($select)->toContain('lower(name) like');

    // ILIKE tidak boleh muncul: SQLite menolaknya mentah-mentah.
    expect($select)->not->toContain('ilike');

    // Sisi nilai juga harus sudah huruf kecil sebelum dikirim.
    $binding = collect($log)->pluck('bindings')->flatten()->first(
        fn ($b) => is_string($b) && str_contains($b, 'press')
    );
    expect($binding)->toBe('%press%', 'kata kunci harus di-lowercase sebelum jadi binding');
});
