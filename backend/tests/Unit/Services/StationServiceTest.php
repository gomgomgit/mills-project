<?php

/**
 * StationServiceTest — screen-030--kelola-station /
 * usecase-030--kelola-station.
 *
 * Unit tests for App\Services\StationService::listStations() /
 * ::businessUnitOptions() / ::create() / ::update() / ::delete(), covering
 * the unit_test_cases derived from this screen's business_logic. Calls the
 * service directly (no HTTP layer), mirroring tests/Unit/Services/
 * BusinessUnitServiceTest.php's pragmatic deviation from
 * test_strategy.unit_test.mock_policy ("mock all I/O"): this service
 * persists/queries via Eloquent (Station::query(), no injectable
 * repository abstraction exists in this codebase), so this suite binds
 * Tests\TestCase + RefreshDatabase (sqlite in-memory, per phpunit.xml) and
 * seeds fixture data via model factories.
 *
 * Divergence from BusinessUnitServiceTest.php: Station has no logo field
 * at all (no LOGO_DISK, no UploadedFile parameter) — this suite has no
 * logo-related tests. `code` is OPTIONAL+globally-unique (not required),
 * and `is_active` carries the cross-field rule documented below.
 *
 * CRITICAL — the key edge case for this screen: `is_active` may only be
 * `true` when `type` is one of weighbridge/grading/cages-track — it is
 * REJECTED (422, error under `is_active`) when `type=other`. See the
 * dedicated block of tests below; this is exercised from the Service
 * layer here, and again from both the Api and Livewire Feature suites.
 */

use App\Exceptions\StationHasMachineryException;
use App\Models\BusinessUnit;
use App\Models\Machinery;
use App\Models\MachineryGroup;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Services\StationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new StationService();
});

// --- create(): validation -------------------------------------------------

// create with a non-existent business_unit_id -> 422 under business_unit_id.
it('throws a ValidationException when creating with a non-existent business_unit_id', function () {
    try {
        $this->service->create([
            'business_unit_id' => '00000000-0000-0000-0000-000000000000',
            'name' => 'Weighbridge Utama',
            'type' => 'weighbridge',
            'is_active' => true,
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('business_unit_id');
    }

    expect(Station::count())->toBe(0);
});

// create without a business_unit_id at all -> 422 under business_unit_id.
it('throws a ValidationException when creating without a business_unit_id', function () {
    try {
        $this->service->create([
            'name' => 'Weighbridge Tanpa BU',
            'type' => 'weighbridge',
            'is_active' => true,
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('business_unit_id');
    }

    expect(Station::count())->toBe(0);
});

// 2026-08-20 (entity-catalog v9): create with a non-existent
// production_line_id -> 422 under production_line_id.
it('throws a ValidationException when creating with a non-existent production_line_id', function () {
    $businessUnit = BusinessUnit::factory()->create();

    try {
        $this->service->create([
            'business_unit_id' => $businessUnit->id,
            'production_line_id' => '00000000-0000-0000-0000-000000000000',
            'name' => 'Weighbridge Utama',
            'type' => 'weighbridge',
            'is_active' => true,
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('production_line_id');
    }

    expect(Station::count())->toBe(0);
});

// create without a production_line_id at all -> 422 under production_line_id.
it('throws a ValidationException when creating without a production_line_id', function () {
    $businessUnit = BusinessUnit::factory()->create();

    try {
        $this->service->create([
            'business_unit_id' => $businessUnit->id,
            'name' => 'Weighbridge Tanpa PL',
            'type' => 'weighbridge',
            'is_active' => true,
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('production_line_id');
    }

    expect(Station::count())->toBe(0);
});

// entity-catalog constraint: production_line_id must belong to the same
// business_unit_id -> 422 under production_line_id.
it('throws a ValidationException when production_line_id does not belong to business_unit_id', function () {
    $businessUnit = BusinessUnit::factory()->create();
    $otherProductionLine = ProductionLine::factory()->create();

    try {
        $this->service->create([
            'business_unit_id' => $businessUnit->id,
            'production_line_id' => $otherProductionLine->id,
            'name' => 'Weighbridge Salah PL',
            'type' => 'weighbridge',
            'is_active' => true,
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('production_line_id');
    }

    expect(Station::count())->toBe(0);
});

// create with an empty name -> 422 under name.
it('throws a ValidationException when creating with an empty name', function () {
    $businessUnit = BusinessUnit::factory()->create();

    try {
        $this->service->create([
            'business_unit_id' => $businessUnit->id,
            'name' => '',
            'type' => 'weighbridge',
            'is_active' => true,
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('name');
    }

    expect(Station::count())->toBe(0);
});

// create without a type at all -> 422 under type.
it('throws a ValidationException when creating without a type', function () {
    $businessUnit = BusinessUnit::factory()->create();

    try {
        $this->service->create([
            'business_unit_id' => $businessUnit->id,
            'name' => 'Station Tanpa Type',
            'is_active' => true,
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('type');
    }

    expect(Station::count())->toBe(0);
});

// create with an invalid (not in enum) type -> 422 under type.
it('throws a ValidationException when creating with an invalid type', function () {
    $businessUnit = BusinessUnit::factory()->create();

    try {
        $this->service->create([
            'business_unit_id' => $businessUnit->id,
            'name' => 'Station Type Salah',
            'type' => 'not-a-real-type',
            'is_active' => true,
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('type');
    }

    expect(Station::count())->toBe(0);
});

// create without is_active at all -> 422 under is_active (required, even
// though the DB column has a default(true) — presence is normalized to
// null before validation, see StationService::validate()'s docblock).
it('throws a ValidationException when creating without is_active', function () {
    $businessUnit = BusinessUnit::factory()->create();

    try {
        $this->service->create([
            'business_unit_id' => $businessUnit->id,
            'name' => 'Station Tanpa Status',
            'type' => 'weighbridge',
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('is_active');
    }

    expect(Station::count())->toBe(0);
});

// create with a non-boolean is_active -> 422 under is_active.
it('throws a ValidationException when creating with a non-boolean is_active', function () {
    $businessUnit = BusinessUnit::factory()->create();

    try {
        $this->service->create([
            'business_unit_id' => $businessUnit->id,
            'name' => 'Station Status Salah',
            'type' => 'weighbridge',
            'is_active' => 'bukan-boolean',
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('is_active');
    }

    expect(Station::count())->toBe(0);
});

// create with a duplicate code (already exists globally) -> 422 isolated
// to code.
it('throws a ValidationException when creating with a code that already exists', function () {
    $businessUnit = BusinessUnit::factory()->create();
    Station::factory()->forBusinessUnit($businessUnit)->withCode('STA-DUP')->create();

    try {
        $this->service->create([
            'business_unit_id' => $businessUnit->id,
            'name' => 'Station Kode Duplikat',
            'type' => 'weighbridge',
            'is_active' => true,
            'code' => 'STA-DUP',
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('code');
        expect($e->errors())->not->toHaveKey('name');
    }

    expect(Station::where('code', 'STA-DUP')->count())->toBe(1);
});

// CRITICAL — the cross-field rule: is_active=true with type=other is
// rejected, error attached to `is_active`, message mentions "Other".
it('throws a ValidationException when creating with is_active=true and type=other', function () {
    $businessUnit = BusinessUnit::factory()->create();

    try {
        $this->service->create([
            'business_unit_id' => $businessUnit->id,
            'name' => 'Station Other Aktif',
            'type' => 'other',
            'is_active' => true,
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('is_active');
        expect($e->errors()['is_active'][0])->toContain('Other');
    }

    expect(Station::count())->toBe(0);
});

// Allows is_active=true for every non-"other" type.
it('allows is_active=true for weighbridge, grading, and cages-track types', function (string $type) {
    $businessUnit = BusinessUnit::factory()->create();
    $productionLine = ProductionLine::factory()->forBusinessUnit($businessUnit)->create();

    $result = $this->service->create([
        'business_unit_id' => $businessUnit->id,
        'production_line_id' => $productionLine->id,
        'name' => "Station Aktif $type",
        'type' => $type,
        'is_active' => true,
    ]);

    expect($result['type'])->toBe($type);
    expect($result['is_active'])->toBeTrue();
})->with([
    'weighbridge' => ['weighbridge'],
    'grading' => ['grading'],
    'cages-track' => ['cages-track'],
]);

// Allows is_active=false for type=other (the only valid combination for
// that type).
it('allows is_active=false for type=other', function () {
    $businessUnit = BusinessUnit::factory()->create();
    $productionLine = ProductionLine::factory()->forBusinessUnit($businessUnit)->create();

    $result = $this->service->create([
        'business_unit_id' => $businessUnit->id,
        'production_line_id' => $productionLine->id,
        'name' => 'Station Other Nonaktif',
        'type' => 'other',
        'is_active' => false,
    ]);

    expect($result['type'])->toBe('other');
    expect($result['is_active'])->toBeFalse();
});

// --- create(): happy paths --------------------------------------------------

// Happy path — create: all fields, returns the expected row shape.
it('creates a station with all fields and returns the expected row shape', function () {
    $businessUnit = BusinessUnit::factory()->create(['name' => 'Mill Unit Utama']);
    $productionLine = ProductionLine::factory()->forBusinessUnit($businessUnit)->create();

    $result = $this->service->create([
        'business_unit_id' => $businessUnit->id,
        'production_line_id' => $productionLine->id,
        'name' => 'Weighbridge 1',
        'type' => 'weighbridge',
        'is_active' => true,
        'code' => 'STA-100',
        'description' => 'Weighbridge utama pabrik',
    ]);

    expect($result)->toHaveKeys([
        'id', 'business_unit_id', 'business_unit_name', 'production_line_id', 'name', 'type',
        'is_active', 'code', 'description', 'machinery_group_count', 'created_at',
    ]);
    expect($result['name'])->toBe('Weighbridge 1');
    expect($result['business_unit_id'])->toBe($businessUnit->id);
    expect($result['production_line_id'])->toBe($productionLine->id);
    expect($result['business_unit_name'])->toBe('Mill Unit Utama');
    expect($result['type'])->toBe('weighbridge');
    expect($result['is_active'])->toBeTrue();
    expect($result['code'])->toBe('STA-100');
    expect($result['description'])->toBe('Weighbridge utama pabrik');
    expect($result['machinery_group_count'])->toBe(0);
    expect(Station::where('name', 'Weighbridge 1')->exists())->toBeTrue();
});

// Happy path — create: minimal fields, code/description omitted -> saved
// as null (not empty string).
it('creates a station with minimal fields, omitted code and description saved as null', function () {
    $businessUnit = BusinessUnit::factory()->create();
    $productionLine = ProductionLine::factory()->forBusinessUnit($businessUnit)->create();

    $result = $this->service->create([
        'business_unit_id' => $businessUnit->id,
        'production_line_id' => $productionLine->id,
        'name' => 'Weighbridge Minimal',
        'type' => 'weighbridge',
        'is_active' => true,
    ]);

    expect($result['code'])->toBeNull();
    expect($result['description'])->toBeNull();

    $fresh = Station::where('name', 'Weighbridge Minimal')->firstOrFail();
    expect($fresh->code)->toBeNull();
    expect($fresh->description)->toBeNull();
});

// --- update() -----------------------------------------------------------

// update a non-existent id -> 404.
it('throws a ModelNotFoundException when updating a non-existent station', function () {
    $businessUnit = BusinessUnit::factory()->create();
    $productionLine = ProductionLine::factory()->forBusinessUnit($businessUnit)->create();

    expect(fn () => $this->service->update('00000000-0000-0000-0000-000000000000', [
        'business_unit_id' => $businessUnit->id,
        'production_line_id' => $productionLine->id,
        'name' => 'Any Name',
        'type' => 'weighbridge',
        'is_active' => true,
    ]))->toThrow(ModelNotFoundException::class);
});

// update with a code taken by another station -> 422 isolated to code.
it('throws a ValidationException when updating to a code taken by another station', function () {
    $businessUnit = BusinessUnit::factory()->create();
    Station::factory()->forBusinessUnit($businessUnit)->withCode('STA-OTHER')->create();
    $target = Station::factory()->forBusinessUnit($businessUnit)->withCode('STA-TARGET')->create();

    try {
        $this->service->update($target->id, [
            'business_unit_id' => $businessUnit->id,
            'production_line_id' => $target->production_line_id,
            'name' => $target->name,
            'type' => 'weighbridge',
            'is_active' => true,
            'code' => 'STA-OTHER',
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('code');
    }

    expect($target->fresh()->code)->toBe('STA-TARGET');
});

// Keep-own-code-unchanged on update must NOT false-positive the unique
// rule (Rule::unique(...)->ignore() excluding self).
it('updates a station keeping its own code without a false-positive uniqueness error', function () {
    $businessUnit = BusinessUnit::factory()->create();
    $station = Station::factory()->forBusinessUnit($businessUnit)->withCode('STA-SELF')->create(['name' => 'Weighbridge Lama']);

    $result = $this->service->update($station->id, [
        'business_unit_id' => $businessUnit->id,
        'production_line_id' => $station->production_line_id,
        'name' => 'Weighbridge Baru',
        'type' => 'weighbridge',
        'is_active' => true,
        'code' => 'STA-SELF',
    ]);

    expect($result['code'])->toBe('STA-SELF');
    expect($result['name'])->toBe('Weighbridge Baru');
    expect($station->fresh()->name)->toBe('Weighbridge Baru');
});

// CRITICAL — the cross-field rule also applies on update().
it('throws a ValidationException when updating to is_active=true and type=other', function () {
    $station = Station::factory()->create(['type' => \App\Enums\StationType::Weighbridge, 'is_active' => true]);

    try {
        $this->service->update($station->id, [
            'business_unit_id' => $station->business_unit_id,
            'production_line_id' => $station->production_line_id,
            'name' => $station->name,
            'type' => 'other',
            'is_active' => true,
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('is_active');
    }

    expect($station->fresh()->type->value)->toBe('weighbridge');
});

// Happy path — update: success, returns the expected row shape with the
// updated fields.
it('updates a station and returns the expected row shape', function () {
    $businessUnitA = BusinessUnit::factory()->create();
    $businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Unit Tujuan']);
    $productionLineB = ProductionLine::factory()->forBusinessUnit($businessUnitB)->create();
    $station = Station::factory()->forBusinessUnit($businessUnitA)->withCode('STA-200')->create(['name' => 'Weighbridge Lama']);

    $result = $this->service->update($station->id, [
        'business_unit_id' => $businessUnitB->id,
        'production_line_id' => $productionLineB->id,
        'name' => 'Weighbridge Baru',
        'type' => 'grading',
        'is_active' => true,
        'code' => 'STA-200',
    ]);

    expect($result)->toHaveKeys(['id', 'business_unit_id', 'business_unit_name', 'name', 'type', 'is_active', 'code', 'created_at']);
    expect($result['id'])->toBe($station->id);
    expect($result['name'])->toBe('Weighbridge Baru');
    expect($result['type'])->toBe('grading');
    expect($result['business_unit_id'])->toBe($businessUnitB->id);
    expect($result['business_unit_name'])->toBe('Mill Unit Tujuan');
    expect($station->fresh()->name)->toBe('Weighbridge Baru');
    expect($station->fresh()->business_unit_id)->toBe($businessUnitB->id);
});

// --- delete() -------------------------------------------------------------

// delete a non-existent id -> 404.
it('throws a ModelNotFoundException when deleting a non-existent station', function () {
    expect(fn () => $this->service->delete('00000000-0000-0000-0000-000000000000'))
        ->toThrow(ModelNotFoundException::class);
});

// Delete-guard: a related MachineryGroup row blocks the delete -> 409, row
// still exists after.
it('throws a StationHasMachineryException when deleting a station that has a related MachineryGroup', function () {
    $station = Station::factory()->create();
    MachineryGroup::factory()->forStation($station)->create();

    expect(fn () => $this->service->delete($station->id))
        ->toThrow(StationHasMachineryException::class);

    expect(Station::find($station->id))->not->toBeNull();
});

// Delete-guard: a related Machinery row (independent of MachineryGroup)
// blocks the delete -> 409, row still exists after.
it('throws a StationHasMachineryException when deleting a station that has a related Machinery', function () {
    $station = Station::factory()->create();
    Machinery::factory()->create(['station_id' => $station->id]);

    expect(fn () => $this->service->delete($station->id))
        ->toThrow(StationHasMachineryException::class);

    expect(Station::find($station->id))->not->toBeNull();
});

// Happy path — delete: success, the station is actually removed when it
// has no related MachineryGroup/Machinery rows.
it('deletes a station that has no related MachineryGroup or Machinery rows', function () {
    $station = Station::factory()->create();

    $this->service->delete($station->id);

    expect(Station::find($station->id))->toBeNull();
});

// --- listStations() / businessUnitOptions() --------------------------------

// listStations(): paginated list returns rows with business_unit_name +
// machinery_group_count per row.
it('lists stations paginated with business_unit_name and machinery_group_count per row', function () {
    $businessUnit = BusinessUnit::factory()->create(['name' => 'Mill Unit Alpha']);
    $station = Station::factory()->forBusinessUnit($businessUnit)->create(['name' => 'Weighbridge Alpha']);
    MachineryGroup::factory()->forStation($station)->count(3)->create();
    Station::factory()->forBusinessUnit($businessUnit)->create(['name' => 'Weighbridge Beta']);

    $result = $this->service->listStations(1, 20);

    expect($result['meta'])->toBe([
        'page' => 1,
        'per_page' => 20,
        'total' => 2,
        'total_pages' => 1,
    ]);
    expect($result['data'])->toHaveCount(2);

    $row = collect($result['data'])->firstWhere('name', 'Weighbridge Alpha');
    expect($row['business_unit_name'])->toBe('Mill Unit Alpha');
    expect($row['machinery_group_count'])->toBe(3);
});

// listStations(): pagination — page 2 of a per_page=1 result set returns
// the second row only.
it('paginates the station list by page and per_page', function () {
    Station::factory()->create(['name' => 'Weighbridge A']);
    Station::factory()->create(['name' => 'Weighbridge B']);

    $result = $this->service->listStations(2, 1);

    expect($result['meta'])->toBe([
        'page' => 2,
        'per_page' => 1,
        'total' => 2,
        'total_pages' => 2,
    ]);
    expect($result['data'])->toHaveCount(1);
});

// listStations(): list filtered by business_unit_id query param returns
// only that business unit's stations.
it('filters the list by business_unit_id when provided', function () {
    $businessUnitA = BusinessUnit::factory()->create();
    $businessUnitB = BusinessUnit::factory()->create();
    Station::factory()->forBusinessUnit($businessUnitA)->create(['name' => 'Weighbridge A1']);
    Station::factory()->forBusinessUnit($businessUnitA)->create(['name' => 'Weighbridge A2']);
    Station::factory()->forBusinessUnit($businessUnitB)->create(['name' => 'Weighbridge B1']);

    $result = $this->service->listStations(1, 20, $businessUnitA->id);

    expect($result['meta']['total'])->toBe(2);
    expect(collect($result['data'])->pluck('name')->sort()->values()->all())->toBe(['Weighbridge A1', 'Weighbridge A2']);
});

// businessUnitOptions(): returns a populated list, ordered by name, when
// business units exist.
it('returns a populated business unit options list ordered by name', function () {
    BusinessUnit::factory()->create(['name' => 'Mill Zulu']);
    BusinessUnit::factory()->create(['name' => 'Mill Alpha']);

    $result = $this->service->businessUnitOptions();

    expect(collect($result)->pluck('name')->all())->toBe(['Mill Alpha', 'Mill Zulu']);
    expect($result[0])->toHaveKeys(['id', 'name']);
});

// businessUnitOptions(): "No Business Unit exists yet" edge case -> empty
// list.
it('returns an empty business unit options list when no business units exist', function () {
    $result = $this->service->businessUnitOptions();

    expect($result)->toBe([]);
});

// productionLineOptions(): 2026-08-20 (entity-catalog v9) — returns only
// the given business unit's own production lines, ordered by name.
it('returns a populated production line options list scoped to the given business unit, ordered by name', function () {
    $businessUnitA = BusinessUnit::factory()->create();
    $businessUnitB = BusinessUnit::factory()->create();
    ProductionLine::factory()->forBusinessUnit($businessUnitA)->create(['name' => 'Line Zulu']);
    ProductionLine::factory()->forBusinessUnit($businessUnitA)->create(['name' => 'Line Alpha']);
    ProductionLine::factory()->forBusinessUnit($businessUnitB)->create(['name' => 'Line Other BU']);

    $result = $this->service->productionLineOptions($businessUnitA->id);

    expect(collect($result)->pluck('name')->all())->toBe(['Line Alpha', 'Line Zulu']);
    expect($result[0])->toHaveKeys(['id', 'name']);
});

// productionLineOptions(): null/blank business_unit_id -> empty list
// (nothing selected yet on the cascading dropdown).
it('returns an empty production line options list when business_unit_id is null', function () {
    ProductionLine::factory()->create();

    expect($this->service->productionLineOptions(null))->toBe([]);
    expect($this->service->productionLineOptions(''))->toBe([]);
});
