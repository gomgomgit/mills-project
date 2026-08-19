<?php

/**
 * MachineryGroupServiceTest — screen-033--kelola-machinery-group /
 * usecase-033--kelola-machinery-group.
 *
 * Unit tests for App\Services\MachineryGroupService::listMachineryGroups()
 * / ::stationOptions() / ::create() / ::update() / ::delete(). Calls the
 * service directly (no HTTP layer), mirroring tests/Unit/Services/
 * StationServiceTest.php's pragmatic deviation from
 * test_strategy.unit_test.mock_policy ("mock all I/O"): this service
 * persists/queries via Eloquent, no injectable repository abstraction
 * exists in this codebase — this suite binds Tests\TestCase +
 * RefreshDatabase (sqlite in-memory) and seeds fixture data via model
 * factories.
 *
 * CRITICAL — the key structural rule for this screen: `business_unit_id`
 * is NEVER read from create()/update()'s $data payload, even when a
 * caller spoofs one — it is always independently re-derived server-side
 * from the selected Station's own business_unit_id. See the dedicated
 * "spoofed business_unit_id" block below; this is exercised from the
 * Service layer here, and again from both the Api and Livewire Feature
 * suites.
 */

use App\Exceptions\MachineryGroupHasMachineryException;
use App\Models\BusinessUnit;
use App\Models\Machinery;
use App\Models\MachineryGroup;
use App\Models\Station;
use App\Services\MachineryGroupService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new MachineryGroupService();
});

// --- create(): validation -------------------------------------------------

// create with a non-existent station_id -> 422 under station_id.
it('throws a ValidationException when creating with a non-existent station_id', function () {
    try {
        $this->service->create([
            'station_id' => '00000000-0000-0000-0000-000000000000',
            'group_code' => 'MG-001',
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('station_id');
    }

    expect(MachineryGroup::count())->toBe(0);
});

// create without a station_id at all -> 422 under station_id.
it('throws a ValidationException when creating without a station_id', function () {
    try {
        $this->service->create([
            'group_code' => 'MG-002',
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('station_id');
    }

    expect(MachineryGroup::count())->toBe(0);
});

// create with an empty group_code -> 422 under group_code.
it('throws a ValidationException when creating with an empty group_code', function () {
    $station = Station::factory()->create();

    try {
        $this->service->create([
            'station_id' => $station->id,
            'group_code' => '',
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('group_code');
    }

    expect(MachineryGroup::count())->toBe(0);
});

// create without group_code at all -> 422 under group_code.
it('throws a ValidationException when creating without a group_code', function () {
    $station = Station::factory()->create();

    try {
        $this->service->create([
            'station_id' => $station->id,
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('group_code');
    }

    expect(MachineryGroup::count())->toBe(0);
});

// create with a duplicate group_code (already exists globally) -> 422
// isolated to group_code.
it('throws a ValidationException when creating with a group_code that already exists', function () {
    $station = Station::factory()->create();
    MachineryGroup::factory()->forStation($station)->withGroupCode('MG-DUP')->create();

    try {
        $this->service->create([
            'station_id' => $station->id,
            'group_code' => 'MG-DUP',
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('group_code');
    }

    expect(MachineryGroup::where('group_code', 'MG-DUP')->count())->toBe(1);
});

// group_code uniqueness is GLOBAL, not scoped to station — a duplicate
// under a DIFFERENT station is still rejected.
it('rejects a duplicate group_code even when the new machinery group targets a different station', function () {
    $stationA = Station::factory()->create();
    $stationB = Station::factory()->create();
    MachineryGroup::factory()->forStation($stationA)->withGroupCode('MG-GLOBAL')->create();

    try {
        $this->service->create([
            'station_id' => $stationB->id,
            'group_code' => 'MG-GLOBAL',
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('group_code');
    }
});

// workshop_factor non-numeric -> 422 under workshop_factor.
it('throws a ValidationException when workshop_factor is not numeric', function () {
    $station = Station::factory()->create();

    try {
        $this->service->create([
            'station_id' => $station->id,
            'group_code' => 'MG-BADWF',
            'workshop_factor' => 'bukan-angka',
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('workshop_factor');
    }
});

// cost_per_equipment non-numeric -> 422 under cost_per_equipment.
it('throws a ValidationException when cost_per_equipment is not numeric', function () {
    $station = Station::factory()->create();

    try {
        $this->service->create([
            'station_id' => $station->id,
            'group_code' => 'MG-BADCOST',
            'cost_per_equipment' => 'bukan-angka',
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('cost_per_equipment');
    }
});

// --- create(): business_unit_id structural rule ----------------------------

// CRITICAL — business_unit_id is ALWAYS server-derived from the Station,
// never trusted from the caller, even when the caller sends a spoofed
// value pointing at a totally different Business Unit.
it('ignores a spoofed business_unit_id and always derives it from the selected Station', function () {
    $realBusinessUnit = BusinessUnit::factory()->create();
    $spoofedBusinessUnit = BusinessUnit::factory()->create();
    $station = Station::factory()->forBusinessUnit($realBusinessUnit)->create();

    $result = $this->service->create([
        'station_id' => $station->id,
        'business_unit_id' => $spoofedBusinessUnit->id,
        'group_code' => 'MG-SPOOF',
    ]);

    expect($result['business_unit_id'])->toBe($realBusinessUnit->id);
    expect($result['business_unit_id'])->not->toBe($spoofedBusinessUnit->id);

    $fresh = MachineryGroup::where('group_code', 'MG-SPOOF')->firstOrFail();
    expect($fresh->business_unit_id)->toBe($realBusinessUnit->id);
});

// --- create(): happy paths --------------------------------------------------

// Happy path — create: all fields, returns the expected row shape.
it('creates a machinery group with all fields and returns the expected row shape', function () {
    $businessUnit = BusinessUnit::factory()->create(['name' => 'Mill Unit Utama']);
    $station = Station::factory()->forBusinessUnit($businessUnit)->create(['name' => 'Weighbridge Utama']);

    $result = $this->service->create([
        'station_id' => $station->id,
        'group_code' => 'MG-100',
        'description' => 'Kelompok mesin utama',
        'unit' => 'unit',
        'workshop_factor' => 1.5,
        'cost_per_equipment' => 2500000,
    ]);

    expect($result)->toHaveKeys([
        'id', 'business_unit_id', 'business_unit_name', 'station_id', 'station_name',
        'group_code', 'description', 'unit', 'workshop_factor', 'cost_per_equipment',
        'machinery_count', 'created_at',
    ]);
    expect($result['group_code'])->toBe('MG-100');
    expect($result['station_id'])->toBe($station->id);
    expect($result['station_name'])->toBe('Weighbridge Utama');
    expect($result['business_unit_id'])->toBe($businessUnit->id);
    expect($result['business_unit_name'])->toBe('Mill Unit Utama');
    expect($result['description'])->toBe('Kelompok mesin utama');
    expect($result['unit'])->toBe('unit');
    expect($result['workshop_factor'])->toBe(1.5);
    expect($result['cost_per_equipment'])->toBe(2500000.0);
    expect($result['machinery_count'])->toBe(0);
    expect(MachineryGroup::where('group_code', 'MG-100')->exists())->toBeTrue();
});

// Happy path — create: minimal fields, optional fields omitted -> saved as
// null (not empty string).
it('creates a machinery group with minimal fields, omitted optional fields saved as null', function () {
    $station = Station::factory()->create();

    $result = $this->service->create([
        'station_id' => $station->id,
        'group_code' => 'MG-MIN',
    ]);

    expect($result['description'])->toBeNull();
    expect($result['unit'])->toBeNull();
    expect($result['workshop_factor'])->toBeNull();
    expect($result['cost_per_equipment'])->toBeNull();

    $fresh = MachineryGroup::where('group_code', 'MG-MIN')->firstOrFail();
    expect($fresh->description)->toBeNull();
    expect($fresh->workshop_factor)->toBeNull();
});

// --- update() -----------------------------------------------------------

// update a non-existent id -> 404.
it('throws a ModelNotFoundException when updating a non-existent machinery group', function () {
    $station = Station::factory()->create();

    expect(fn () => $this->service->update('00000000-0000-0000-0000-000000000000', [
        'station_id' => $station->id,
        'group_code' => 'MG-ANY',
    ]))->toThrow(ModelNotFoundException::class);
});

// update with a group_code taken by another machinery group -> 422
// isolated to group_code.
it('throws a ValidationException when updating to a group_code taken by another machinery group', function () {
    $station = Station::factory()->create();
    MachineryGroup::factory()->forStation($station)->withGroupCode('MG-OTHER')->create();
    $target = MachineryGroup::factory()->forStation($station)->withGroupCode('MG-TARGET')->create();

    try {
        $this->service->update($target->id, [
            'station_id' => $station->id,
            'group_code' => 'MG-OTHER',
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('group_code');
    }

    expect($target->fresh()->group_code)->toBe('MG-TARGET');
});

// Keep-own-code-unchanged on update must NOT false-positive the unique
// rule (Rule::unique(...)->ignore() excluding self).
it('updates a machinery group keeping its own group_code without a false-positive uniqueness error', function () {
    $station = Station::factory()->create();
    $machineryGroup = MachineryGroup::factory()->forStation($station)->withGroupCode('MG-SELF')->create(['description' => 'Lama']);

    $result = $this->service->update($machineryGroup->id, [
        'station_id' => $station->id,
        'group_code' => 'MG-SELF',
        'description' => 'Baru',
    ]);

    expect($result['group_code'])->toBe('MG-SELF');
    expect($result['description'])->toBe('Baru');
    expect($machineryGroup->fresh()->description)->toBe('Baru');
});

// CRITICAL — updating a machinery group to point at a different Station
// re-derives business_unit_id from the NEW station, not the old one, and
// a spoofed business_unit_id in the update payload is still ignored.
it('re-derives business_unit_id from the new station when the station is changed on update, ignoring a spoofed value', function () {
    $businessUnitA = BusinessUnit::factory()->create();
    $businessUnitB = BusinessUnit::factory()->create();
    $spoofedBusinessUnit = BusinessUnit::factory()->create();
    $stationA = Station::factory()->forBusinessUnit($businessUnitA)->create();
    $stationB = Station::factory()->forBusinessUnit($businessUnitB)->create();
    $machineryGroup = MachineryGroup::factory()->forStation($stationA)->create(['business_unit_id' => $businessUnitA->id]);

    $result = $this->service->update($machineryGroup->id, [
        'station_id' => $stationB->id,
        'business_unit_id' => $spoofedBusinessUnit->id,
        'group_code' => $machineryGroup->group_code,
    ]);

    expect($result['station_id'])->toBe($stationB->id);
    expect($result['business_unit_id'])->toBe($businessUnitB->id);
    expect($machineryGroup->fresh()->business_unit_id)->toBe($businessUnitB->id);
});

// Happy path — update: success, returns the expected row shape with the
// updated fields.
it('updates a machinery group and returns the expected row shape', function () {
    $stationA = Station::factory()->create();
    $stationB = Station::factory()->create(['name' => 'Weighbridge Tujuan']);
    $machineryGroup = MachineryGroup::factory()->forStation($stationA)->withGroupCode('MG-200')->create();

    $result = $this->service->update($machineryGroup->id, [
        'station_id' => $stationB->id,
        'group_code' => 'MG-200',
        'unit' => 'set',
        'workshop_factor' => 2.25,
    ]);

    expect($result)->toHaveKeys(['id', 'business_unit_id', 'station_id', 'station_name', 'group_code', 'unit', 'workshop_factor']);
    expect($result['id'])->toBe($machineryGroup->id);
    expect($result['station_id'])->toBe($stationB->id);
    expect($result['station_name'])->toBe('Weighbridge Tujuan');
    expect($result['unit'])->toBe('set');
    expect($result['workshop_factor'])->toBe(2.25);
    expect($machineryGroup->fresh()->station_id)->toBe($stationB->id);
});

// --- delete() -------------------------------------------------------------

// delete a non-existent id -> 404.
it('throws a ModelNotFoundException when deleting a non-existent machinery group', function () {
    expect(fn () => $this->service->delete('00000000-0000-0000-0000-000000000000'))
        ->toThrow(ModelNotFoundException::class);
});

// Delete-guard: a related Machinery row blocks the delete -> 409, row
// still exists after.
it('throws a MachineryGroupHasMachineryException when deleting a machinery group that has related Machinery', function () {
    $machineryGroup = MachineryGroup::factory()->create();
    Machinery::factory()->forMachineryGroup($machineryGroup)->create();

    expect(fn () => $this->service->delete($machineryGroup->id))
        ->toThrow(MachineryGroupHasMachineryException::class);

    expect(MachineryGroup::find($machineryGroup->id))->not->toBeNull();
});

// Happy path — delete: success, the machinery group is actually removed
// when it has no related Machinery rows.
it('deletes a machinery group that has no related Machinery rows', function () {
    $machineryGroup = MachineryGroup::factory()->create();

    $this->service->delete($machineryGroup->id);

    expect(MachineryGroup::find($machineryGroup->id))->toBeNull();
});

// --- listMachineryGroups() / stationOptions() --------------------------------

// listMachineryGroups(): paginated list returns rows with station_name +
// business_unit_id + machinery_count per row.
it('lists machinery groups paginated with station_name, business_unit_id, and machinery_count per row', function () {
    $businessUnit = BusinessUnit::factory()->create(['name' => 'Mill Unit Alpha']);
    $station = Station::factory()->forBusinessUnit($businessUnit)->create(['name' => 'Weighbridge Alpha']);
    $machineryGroup = MachineryGroup::factory()->forStation($station)->create(['business_unit_id' => $businessUnit->id]);
    Machinery::factory()->forMachineryGroup($machineryGroup)->count(3)->create();
    MachineryGroup::factory()->forStation($station)->create(['business_unit_id' => $businessUnit->id]);

    $result = $this->service->listMachineryGroups(1, 20);

    expect($result['meta'])->toBe([
        'page' => 1,
        'per_page' => 20,
        'total' => 2,
        'total_pages' => 1,
    ]);
    expect($result['data'])->toHaveCount(2);

    $row = collect($result['data'])->firstWhere('id', $machineryGroup->id);
    expect($row['station_name'])->toBe('Weighbridge Alpha');
    expect($row['business_unit_id'])->toBe($businessUnit->id);
    expect($row['machinery_count'])->toBe(3);
});

// listMachineryGroups(): pagination — page 2 of a per_page=1 result set
// returns the second row only.
it('paginates the machinery group list by page and per_page', function () {
    MachineryGroup::factory()->create();
    MachineryGroup::factory()->create();

    $result = $this->service->listMachineryGroups(2, 1);

    expect($result['meta'])->toBe([
        'page' => 2,
        'per_page' => 1,
        'total' => 2,
        'total_pages' => 2,
    ]);
    expect($result['data'])->toHaveCount(1);
});

// listMachineryGroups(): list filtered by station_id query param returns
// only that station's machinery groups.
it('filters the list by station_id when provided', function () {
    $stationA = Station::factory()->create();
    $stationB = Station::factory()->create();
    MachineryGroup::factory()->forStation($stationA)->withGroupCode('MG-A1')->create();
    MachineryGroup::factory()->forStation($stationA)->withGroupCode('MG-A2')->create();
    MachineryGroup::factory()->forStation($stationB)->withGroupCode('MG-B1')->create();

    $result = $this->service->listMachineryGroups(1, 20, $stationA->id);

    expect($result['meta']['total'])->toBe(2);
    expect(collect($result['data'])->pluck('group_code')->sort()->values()->all())->toBe(['MG-A1', 'MG-A2']);
});

// stationOptions(): returns a populated list, ordered by name, with
// business_unit_id per row, when stations exist.
it('returns a populated station options list ordered by name with business_unit_id per row', function () {
    $businessUnit = BusinessUnit::factory()->create();
    Station::factory()->forBusinessUnit($businessUnit)->create(['name' => 'Weighbridge Zulu']);
    Station::factory()->forBusinessUnit($businessUnit)->create(['name' => 'Weighbridge Alpha']);

    $result = $this->service->stationOptions();

    expect(collect($result)->pluck('name')->all())->toBe(['Weighbridge Alpha', 'Weighbridge Zulu']);
    expect($result[0])->toHaveKeys(['id', 'name', 'business_unit_id']);
    expect($result[0]['business_unit_id'])->toBe($businessUnit->id);
});

// stationOptions(): "No Station exists yet" edge case -> empty list.
it('returns an empty station options list when no stations exist', function () {
    $result = $this->service->stationOptions();

    expect($result)->toBe([]);
});
